<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Traits;

use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use ConcreteCmsUtility\DTO\ImageData;
use ConcreteCmsUtility\DTO\ThumbnailData;

trait ImageTrait
{
    /**
     * Check if given File is an Image and if thumbnail can be generated (excluding svg).
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return bool
     */
    public function isImage(int|FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        if (!($file instanceof FileEntity) and !($file instanceof FileVersionEntity)) {
            return false;
        }

        if (!$file->canEdit()) return false;

        return true;
    }

    /**
     * Generate a cached image variant and get common info.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to replace default alt attribute (modified Concrete Title attribute)."
     * @param string|null $customName Filename base without a path or extension.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality, or null to use the dashboard setting.
     * @param int|null $pngCompressionLevel PNG compression, or null to use the dashboard setting.
     * @return ImageData
     */
    public function getImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        ?string                               $customName = null,
        ?string                               $format = null,
        ?int                                  $quality = null,
        ?int                                  $pngCompressionLevel = null,
    ): ImageData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isImage = $this->isImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isValid = ($isImage or $isSvg);

        $fileData = $this->getFile(file: $file);
        $svgData = $this->getSvg(file: $file);

        $url = null;
        $placeholder = $this->getPlaceholderString(width: $width, height: $height);

        if ($isImage) {
            $thumbnail = $this->generateThumbnail(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );

            $url = $thumbnail->url;
            $placeholder = $this->getPlaceholderString(
                width: $thumbnail->width,
                height: $thumbnail->height
            );
            $width = $thumbnail->width;
            $height = $thumbnail->height;
        }

        if ($isSvg) {
            $url = $file->getURL();
            $width = $svgData->width;
            $height = $svgData->height;
        }

        if ($isValid) {
            $alt = ($alt === null) ? $this->getModifiedName(file: $file) : $alt;
        } else {
            $alt = ($alt === null) ? 'Placeholder' : $alt;
        }

        return new ImageData(
            isValid: $isValid,
            isImage: $isImage,
            isSvg: $isSvg,
            id: $file?->getFileID(),
            url: $url,
            placeholder: $placeholder,
            width: $width,
            height: $height,
            alt: $alt,
            file: $fileData,
            svg: $svgData,
        );
    }

    /**
     * Get an inline svg string that can be used for image "src" attribute when lazy-loading.
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getPlaceholderString(int $width, int $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20' . $width . '%20' . $height . '%22%20%2F%3E';
    }

    /**
     * Generate or reuse a cached thumbnail, including for equally sized or smaller source images.
     *
     * @param FileEntity|FileVersionEntity $file "File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $customName Filename base without a path or extension.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality, or null to use the dashboard setting.
     * @param int|null $pngCompressionLevel PNG compression, or null to use the dashboard setting.
     * @return ThumbnailData
     */
    public function generateThumbnail(
        FileEntity|FileVersionEntity $file,
        int                          $width,
        int                          $height,
        bool                         $crop,
        ?string                      $customName = null,
        ?string                      $format = null,
        ?int                         $quality = null,
        ?int                         $pngCompressionLevel = null,
    ): ThumbnailData
    {
        return $this->thumbnailGenerator->generate(
            file: $file,
            width: $width,
            height: $height,
            crop: $crop,
            customName: $customName,
            format: $format,
            quality: $quality,
            pngCompressionLevel: $pngCompressionLevel,
        );
    }
}
