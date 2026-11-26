<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Image;

use Concrete\Core\Application\Application;
use Concrete\Core\Entity\File\File;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\File\Image\BitmapFormat;
use Concrete\Core\File\Image\Thumbnail\ThumbnailFormatService;
use Concrete\Core\File\Image\Thumbnail\ThumbnailPlaceholderService;
use Concrete\Core\File\StorageLocation\Configuration\LocalConfiguration;
use ConcreteCmsUtility\Command\GenerateThumbnailCommand;
use ConcreteCmsUtility\DTO\ThumbnailData;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ImagineInterface;
use InvalidArgumentException;
use RuntimeException;

/** Generates cached variants independently of application thumbnailer overrides. */
class ThumbnailGenerator
{
    public function __construct(private readonly Application $application)
    {
    }

    public function generate(
        File|Version $file,
        int $width,
        int $height,
        bool $crop,
        ?string $customName = null,
        ?string $format = null,
        ?int $quality = null,
        ?int $pngCompressionLevel = null,
    ): ThumbnailData {
        $this->validateDimensions($width, $height, $crop);
        $this->validateCompression($quality, $pngCompressionLevel);

        $version = $file instanceof Version ? $file : $file->getApprovedVersion();
        if (!$version instanceof Version) {
            throw new RuntimeException('The image has no approved file version.');
        }

        $format = $this->resolveFormat($format, $version);
        $bitmapFormat = $this->application->make(BitmapFormat::class);
        if ($format === 'png') {
            $quality = null;
            $pngCompressionLevel ??= $bitmapFormat->getDefaultPngCompressionLevel();
        } else {
            $quality ??= $format === 'webp'
                ? $bitmapFormat->getDefaultWebpQuality()
                : $bitmapFormat->getDefaultJpegQuality();
            $pngCompressionLevel = null;
        }

        $command = new GenerateThumbnailCommand(
            fileIdentifier: (int) $version->getFileID(),
            fileVersionIdentifier: (int) $version->getFileVersionID(),
            sourceFingerprint: $this->getSourceFingerprint($version),
            width: $width,
            height: $height,
            crop: $crop,
            customName: $this->resolveName($customName, $version),
            format: $format,
            quality: $quality,
            pngCompressionLevel: $pngCompressionLevel,
        );

        $storage = $version->getFile()->getFileStorageLocationObject();
        $path = $this->getCachePath($command);
        if ($storage->getFileSystemObject()->has($path)) {
            return $this->getCachedThumbnail($version, $path);
        }

        if ($this->application->make('config')->get('concrete.misc.basic_thumbnailer_generation_strategy') === 'async') {
            $dimensions = $this->getThumbnailDimensions(
                new Box((int) $version->getAttribute('width'), (int) $version->getAttribute('height')),
                $width,
                $height,
                $crop,
            );
            $this->application->executeCommand($command);
            $placeholder = $this->application->make(ThumbnailPlaceholderService::class)->getThumbnailImage(
                $dimensions->getWidth(),
                $dimensions->getHeight(),
            );

            return new ThumbnailData(
                url: 'data:image/svg+xml;base64,' . base64_encode((string) $placeholder),
                width: $dimensions->getWidth(),
                height: $dimensions->getHeight(),
            );
        }

        return $this->writeThumbnail($version, $command);
    }

    /** Ignore obsolete jobs rather than writing a replacement source into the old cache key. */
    public function generateQueued(Version $version, GenerateThumbnailCommand $command): void
    {
        if ((int) $version->getFileID() !== $command->fileIdentifier
            || (int) $version->getFileVersionID() !== $command->fileVersionIdentifier
            || $this->getSourceFingerprint($version) !== $command->sourceFingerprint) {
            return;
        }

        $this->writeThumbnail($version, $command);
    }

    private function writeThumbnail(Version $version, GenerateThumbnailCommand $command): ThumbnailData
    {
        $storage = $version->getFile()->getFileStorageLocationObject();
        $filesystem = $storage->getFileSystemObject();
        $path = $this->getCachePath($command);
        if ($filesystem->has($path)) {
            return $this->getCachedThumbnail($version, $path);
        }

        $contents = $version->getFileResource()->read();
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException('Unable to read the source image.');
        }
        $image = $this->application->make(ImagineInterface::class)->load($contents);
        $target = $this->getTargetBox($image->getSize(), $command->width, $command->height);
        $thumbnail = $image->thumbnail(
            $target,
            $command->crop ? ImageInterface::THUMBNAIL_OUTBOUND : ImageInterface::THUMBNAIL_INSET,
        );
        $encodingOptions = $command->format === 'png'
            ? ['png_compression_level' => $command->pngCompressionLevel]
            : [$command->format . '_quality' => $command->quality];

        // Re-encode even if Imagine returns a copy with unchanged dimensions.
        $encoded = $thumbnail->get($command->format, $encodingOptions);
        if ($filesystem->put($path, $encoded) === false) {
            throw new RuntimeException('Unable to write the thumbnail.');
        }

        return new ThumbnailData(
            url: (string) $storage->getConfigurationObject()->getPublicURLToFile($path),
            width: $thumbnail->getSize()->getWidth(),
            height: $thumbnail->getSize()->getHeight(),
        );
    }

    private function getCachedThumbnail(Version $version, string $path): ThumbnailData
    {
        $storage = $version->getFile()->getFileStorageLocationObject();
        $configuration = $storage->getConfigurationObject();
        if ($configuration instanceof LocalConfiguration) {
            $dimensions = getimagesize($configuration->getRootPath() . $path);
        } else {
            // Use the storage adapter, never an HTTP request to the public thumbnail URL.
            $contents = $storage->getFileSystemObject()->read($path);
            $dimensions = is_string($contents) ? getimagesizefromstring($contents) : false;
        }
        if ($dimensions === false) {
            throw new RuntimeException('Unable to read the cached thumbnail dimensions.');
        }

        return new ThumbnailData(
            url: (string) $configuration->getPublicURLToFile($path),
            width: $dimensions[0],
            height: $dimensions[1],
        );
    }

    private function getSourceFingerprint(Version $version): string
    {
        $resource = $version->getFileResource();

        return hash('sha256', json_encode([
            $resource->getPath(),
            $resource->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    private function getCachePath(GenerateThumbnailCommand $command): string
    {
        $fingerprint = hash('sha256', json_encode([
            1, // Increment when the transformation algorithm changes.
            $command->fileIdentifier,
            $command->fileVersionIdentifier,
            $command->sourceFingerprint,
            $command->width,
            $command->height,
            $command->crop,
            $command->format,
            $command->quality,
            $command->pngCompressionLevel,
        ], JSON_THROW_ON_ERROR));
        $extension = $command->format === 'jpeg' ? 'jpg' : $command->format;

        return '/cache/thumbnails/concretecms-utility/' . $command->customName . '-' . $fingerprint . '.' . $extension;
    }

    private function resolveFormat(?string $format, Version $version): string
    {
        $format ??= $this->application->make('config')->get('concrete.misc.default_thumbnail_format');
        $format = strtolower(trim((string) $format));
        if ($format === 'auto') {
            $format = $this->application->make(ThumbnailFormatService::class)->getAutomaticFormatForFile($version);
        }
        if ($format === 'jpg') {
            $format = 'jpeg';
        }
        if (!in_array($format, ['jpeg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException('Unsupported thumbnail format. Use jpeg, png, webp or auto.');
        }

        return $format;
    }

    private function resolveName(?string $customName, Version $version): string
    {
        if ($customName !== null && (trim($customName) === '' || strpbrk($customName, ".\\/\0") !== false)) {
            throw new InvalidArgumentException('The thumbnail name must not contain a path or extension.');
        }
        $name = $customName ?? pathinfo((string) $version->getTitle(), PATHINFO_FILENAME);
        $name = $this->application->make('helper/text')->sanitizeFileSystem($name);
        $name = trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $name), '-_');
        if ($name === '') {
            if ($customName !== null) {
                throw new InvalidArgumentException('The thumbnail name must contain letters or digits.');
            }
            $name = 'image';
        }

        return substr($name, 0, 100);
    }

    private function validateDimensions(int $width, int $height, bool $crop): void
    {
        if ($width < 0 || $height < 0 || ($width === 0 && $height === 0)
            || ($crop && ($width === 0 || $height === 0))) {
            throw new InvalidArgumentException('Provide positive thumbnail dimensions. One dimension may be zero without cropping.');
        }
    }

    private function validateCompression(?int $quality, ?int $pngCompressionLevel): void
    {
        if ($quality !== null && ($quality < 0 || $quality > 100)) {
            throw new InvalidArgumentException('Thumbnail quality must be between 0 and 100.');
        }
        if ($pngCompressionLevel !== null && ($pngCompressionLevel < 0 || $pngCompressionLevel > 9)) {
            throw new InvalidArgumentException('PNG compression must be between 0 and 9.');
        }
    }

    private function getTargetBox(BoxInterface $source, int $width, int $height): BoxInterface
    {
        if ($height === 0) {
            return $source->widen($width);
        }
        if ($width === 0) {
            return $source->heighten($height);
        }

        return new Box($width, $height);
    }

    /** Match Imagine's no-upscale geometry without decoding a source in the web request. */
    private function getThumbnailDimensions(BoxInterface $source, int $width, int $height, bool $crop): BoxInterface
    {
        $target = $this->getTargetBox($source, $width, $height);
        if ($target->contains($source)) {
            return $source;
        }
        if ($crop) {
            return new Box(min($source->getWidth(), $target->getWidth()), min($source->getHeight(), $target->getHeight()));
        }

        return $source->scale(min($target->getWidth() / $source->getWidth(), $target->getHeight() / $source->getHeight()));
    }
}
