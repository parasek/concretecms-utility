<?php

declare(strict_types=1);

namespace ConcreteCmsUtility;

use ConcreteCmsUtility\DTO\GalleryImageData;
use ConcreteCmsUtility\DTO\ImageData;
use ConcreteCmsUtility\DTO\SliderImageData;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use Concrete\Core\File\Set\Set as FileSet;
use Concrete\Core\Page\Page;
use ConcreteCmsUtility\Image\ThumbnailGenerator;
use ConcreteCmsUtility\Traits\ImageTrait;
use ConcreteCmsUtility\Traits\SvgTrait;
use ConcreteCmsUtility\Traits\VideoTrait;
use InvalidArgumentException;

/**
 * Opinionated image-related helpers for Concrete 9 and PHP 8.1+.
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class ImageUtility extends FileUtility
{
    use ImageTrait, SvgTrait, VideoTrait;

    public function __construct(private readonly ThumbnailGenerator $thumbnailGenerator)
    {
    }

    /**
     * Get list of Images by selected File Set.
     *
     * You can provide File Set ID, File Set Name or File Set Object.
     *
     * @param int|string|FileSet|null $fileSet "File Set ID, File Set Name or File Set Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Images."
     * @param string|null $customName Filename base for generated variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return ImageData[]
     */
    public function getImagesByFileSet(
        int|string|FileSet|null $fileSet,
        int                     $width,
        int                     $height,
        bool                    $crop,
        ?string                 $alt = null,
        ?string                 $customName = null,
        ?string                 $format = null,
        ?int                    $quality = null,
        ?int                    $pngCompressionLevel = null,
    ): array
    {
        $output = [];

        $files = $this->listFilesFromFileSet(fileSet: $fileSet);

        foreach ($files as $file) {
            $output[] = $this->getImage(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
                alt: $alt,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );
        }

        return $output;
    }

    /**
     * Get a list of Images using selected main Image.
     *
     * Images will be fetched from the first found File Set.
     *
     * Selected Image will be the first element of list, other Images will be sorted by position in File Set
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Images."
     * @param string|null $customName Filename base for generated variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return ImageData[]
     */
    public function getImagesByMainImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        ?string                               $customName = null,
        ?string                               $format = null,
        ?int                                  $quality = null,
        ?int                                  $pngCompressionLevel = null,
    ): array
    {
        $output = [];

        $fileSetFiles = $this->listFilesFromFirstFileSet(file: $file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getImage(
                file: $fileSetFile,
                width: $width,
                height: $height,
                crop: $crop,
                alt: $alt,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );
        }

        return $output;
    }

    /**
     * Generate cached image variants and get common info.
     *
     * Additional Image (for fullscreen lightbox) will be created.
     *
     * You can provide File ID, File Object or File Version Object.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to replace default alt attribute (which is modified Title attribute of Concrete File)."
     * @param string|null $title "Provide string to replace default lightbox title (which is by default the same as $alt)."
     * @param int $fullscreenWidth
     * @param int $fullscreenHeight
     * @param bool $fullscreenCrop
     * @param string|null $customName Filename base for both image variants.
     * @param string|null $format Output format for both variants, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return GalleryImageData
     */
    public function getGalleryImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        ?string                               $title = null,
        int                                   $fullscreenWidth = 1920,
        int                                   $fullscreenHeight = 1080,
        bool                                  $fullscreenCrop = false,
        ?string                               $customName = null,
        ?string                               $format = null,
        ?int                                  $quality = null,
        ?int                                  $pngCompressionLevel = null,
    ): GalleryImageData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isImage = $this->isImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isValid = ($isImage or $isSvg);

        $fileData = $this->getFile(file: $file);
        $svgData = $this->getSvg(file: $file);

        $url = null;
        $fullscreenUrl = null;
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
                height: $thumbnail->height,
            );
            $width = $thumbnail->width;
            $height = $thumbnail->height;

            $fullscreenThumbnail = $this->generateThumbnail(
                file: $file,
                width: $fullscreenWidth,
                height: $fullscreenHeight,
                crop: $fullscreenCrop,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );

            $fullscreenUrl = $fullscreenThumbnail->url;
            $fullscreenWidth = $fullscreenThumbnail->width;
            $fullscreenHeight = $fullscreenThumbnail->height;
        }

        if ($isSvg) {
            $url = $file->getURL();
            $fullscreenUrl = $file->getURL();

            $width = $svgData->width;
            $height = $svgData->height;

            $fullscreenWidth = $svgData->width;
            $fullscreenHeight = $svgData->height;
        }

        if ($isValid) {
            $alt = ($alt === null) ? $this->getModifiedName(file: $file) : $alt;
        } else {
            $alt = ($alt === null) ? 'Placeholder' : $alt;
        }

        return new GalleryImageData(
            isValid: $isValid,
            isImage: $isImage,
            isSvg: $isSvg,
            id: $file?->getFileID(),
            url: $url,
            placeholder: $placeholder,
            width: $width,
            height: $height,
            alt: $alt,
            title: $title ?: $alt,
            fullscreenUrl: $fullscreenUrl,
            fullscreenWidth: $fullscreenWidth,
            fullscreenHeight: $fullscreenHeight,
            file: $fileData,
            svg: $svgData,
        );
    }

    /**
     * Get list of Gallery Images by selected File Set.
     *
     * You can provide File Set ID, File Set Name or File Set Object.
     *
     * @param int|string|FileSet|null $fileSet "File Set ID, File Set Name or File Set Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Gallery Images."
     * @param string|null $title "Provide string to set single lightbox title for all Gallery Images."
     * @param int $fullscreenWidth
     * @param int $fullscreenHeight
     * @param bool $fullscreenCrop
     * @param string|null $customName Filename base for generated variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return GalleryImageData[]
     */
    public function getGalleryImagesByFileSet(
        int|string|FileSet|null $fileSet,
        int                     $width,
        int                     $height,
        bool                    $crop,
        ?string                 $alt = null,
        ?string                 $title = null,
        int                     $fullscreenWidth = 1920,
        int                     $fullscreenHeight = 1080,
        bool                    $fullscreenCrop = false,
        ?string                 $customName = null,
        ?string                 $format = null,
        ?int                    $quality = null,
        ?int                    $pngCompressionLevel = null,
    ): array
    {
        $output = [];

        $files = $this->listFilesFromFileSet(fileSet: $fileSet);

        foreach ($files as $file) {
            $output[] = $this->getGalleryImage(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
                alt: $alt,
                title: $title,
                fullscreenWidth: $fullscreenWidth,
                fullscreenHeight: $fullscreenHeight,
                fullscreenCrop: $fullscreenCrop,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );
        }

        return $output;
    }

    /**
     * Get list of Gallery Images using selected main File.
     *
     * Gallery Images will be fetched from the first found File Set.
     *
     * Selected Gallery Image will be the first element of list, other Gallery Images will be sorted by position in File Set.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Gallery Images."
     * @param string|null $title "Provide string to set single lightbox title for all Gallery Images."
     * @param int $fullscreenWidth
     * @param int $fullscreenHeight
     * @param bool $fullscreenCrop
     * @param string|null $customName Filename base for generated variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return GalleryImageData[]
     */
    public function getGalleryImagesByMainImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        ?string                               $title = null,
        int                                   $fullscreenWidth = 1920,
        int                                   $fullscreenHeight = 1080,
        bool                                  $fullscreenCrop = false,
        ?string                               $customName = null,
        ?string                               $format = null,
        ?int                                  $quality = null,
        ?int                                  $pngCompressionLevel = null,
    ): array
    {
        $output = [];

        $fileSetFiles = $this->listFilesFromFirstFileSet(file: $file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getGalleryImage(
                file: $fileSetFile,
                width: $width,
                height: $height,
                crop: $crop,
                alt: $alt,
                title: $title,
                fullscreenWidth: $fullscreenWidth,
                fullscreenHeight: $fullscreenHeight,
                fullscreenCrop: $fullscreenCrop,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );
        }

        return $output;
    }

    /**
     * Get slider Image.
     *
     * @param int|FileEntity|FileVersionEntity|null $file
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt
     * @param array $additionalImageWidths
     * @param string|null $sizes
     * @param string|null $customName Filename base for the image and srcset variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return SliderImageData
     * @see getSliderImagesByFileSet()
     */
    public function getSliderImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        array                                 $additionalImageWidths = [],
        ?string                               $sizes = null,
        ?string                               $customName = null,
        ?string                               $format = null,
        ?int                                  $quality = null,
        ?int                                  $pngCompressionLevel = null,
    ): SliderImageData
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('Image dimensions must be positive integers.');
        }

        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isImage = $this->isImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isVideo = $this->isVideo(file: $file);
        $isValid = ($isImage or $isSvg or $isVideo);

        $fileData = $this->getFile(file: $file);
        $svgData = $this->getSvg(file: $file);
        $videoData = $this->getVideo(file: $file);

        $url = null;
        $srcset = null;
        $placeholder = $this->getPlaceholderString(width: $width, height: $height);
        $title = null;
        $subtitle = null;
        $link = null;
        $buttonText = null;
        $textAlignment = null;
        $newWindow = false;

        $ratio = ($height / $width) * 100;

        if ($isImage) {
            foreach ($additionalImageWidths as $additionalImageWidth) {
                if (!is_int($additionalImageWidth) || $additionalImageWidth < 1) {
                    throw new InvalidArgumentException('Additional image widths must be positive integers.');
                }
            }

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
                height: $thumbnail->height,
            );
            $width = $thumbnail->width;
            $height = $thumbnail->height;
        }

        if ($isSvg) {
            $url = $file->getURL();
            $width = $svgData->width;
            $height = $svgData->height;
        }

        if ($isVideo) {
            $url = $file->getURL();
            $width = $videoData->width;
            $height = $videoData->height;
        }

        if ($isValid) {
            $alt = ($alt === null) ? $this->getModifiedName(file: $file) : $alt;
        } else {
            $alt = ($alt === null) ? 'Placeholder' : $alt;
        }

        if ($isImage) {
            $imageSources = [$width => $url . ' ' . $width . 'w'];
            foreach ($additionalImageWidths as $additionalImageWidth) {
                if (isset($imageSources[$additionalImageWidth])) {
                    continue;
                }

                $thumbnail = $this->generateThumbnail(
                    file: $file,
                    width: $additionalImageWidth,
                    height: max(1, (int) round(($height / $width) * $additionalImageWidth)),
                    crop: $crop,
                    customName: $customName,
                    format: $format,
                    quality: $quality,
                    pngCompressionLevel: $pngCompressionLevel,
                );
                // Describe actual dimensions and keep one candidate for each width.
                $imageSources[$thumbnail->width] ??= $thumbnail->url . ' ' . $thumbnail->width . 'w';
            }

            ksort($imageSources, SORT_NUMERIC);
            $srcset = implode(', ', $imageSources);
            $sizes ??= '(max-width: 767px)  100vw,
                      (max-width: 991px)  740px,
                      (max-width: 1199px) 960px,
                      (max-width: 1649px) 1140px,
                     ' . $width . 'px';
        }

        if ($isValid) {
            if ($linkAttribute = $file->getAttribute('slide_link')) {
                $linkPage = Page::getByID($linkAttribute);
                if (is_object($linkPage) and !$linkPage->isError() and !$linkPage->isInTrash()) {
                    $link = $linkPage->getCollectionLink();
                    if ($linkSuffixAttribute = $file->getAttribute('slide_link_suffix')) {
                        $link .= $linkSuffixAttribute;
                    }
                }
            }
            if ($file->getAttribute('slide_link_external')) {
                $link = $file->getAttribute('slide_link_external');
            }

            $title = $file->getAttribute('slide_title') ?? null;
            $subtitle = $file->getAttribute('slide_subtitle') ?? null;
            $buttonText = $file->getAttribute('slide_button_text') ?? null;
            $textAlignment = (string)$file->getAttribute('slide_text_alignment');
            $newWindow = (bool)$file->getAttribute('slide_new_window');
        }

        return new SliderImageData(
            isValid: $isValid,
            isImage: $isImage,
            isVideo: $isVideo,
            isSvg: $isSvg,
            id: $file?->getFileID(),
            url: $url,
            srcset: $srcset,
            sizes: $sizes,
            placeholder: $placeholder,
            width: $width,
            height: $height,
            ratio: $ratio,
            alt: $alt,
            title: $title,
            subtitle: $subtitle,
            link: $link,
            buttonText: $buttonText,
            textAlignment: $textAlignment,
            newWindow: $newWindow,
            file: $fileData,
            svg: $svgData,
            video: $videoData,
        );
    }

    /**
     * Get list of Slider Images by selected File Set.
     *
     * Beforehand, you should add Concrete CMS File attributes with specific handles:
     * - slide_title (Text Area)
     * - slide_subtitle (Text Area)
     * - slide_link (Page Selector)
     * - slide_link_suffix (Text)
     * - slide_link_external (Text)
     * - slide_button_text (Text)
     * - slide_text_alignment (Select)
     * - slide_new_window (Checkbox)
     *
     * Argument $additionalImageWidths:
     * - Additional Images (for different breakpoints) will be generated based on those dimensions.
     * - Provide widths in pixels as an array, for example: [360, 480, 740, 960, 1140, 1372].
     * - Heights of Images will be calculated automatically, taking into account proportions of base Image.
     * - These Images will be displayed in "srcset" attribute
     * - "sizes" attribute have predefined value, but it can be overridden if necessary
     *
     * Argument $sizes:
     * - If you skip it/set to null, custom default "sizes" attribute will be printed.
     *
     * @param int|string|FileSet|null $fileSet "File Set ID, File Set Name or File Set Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Slider Images."
     * @param array $additionalImageWidths
     * @param string|null $sizes
     * @param string|null $customName Filename base for generated variants.
     * @param string|null $format Output format, or null to use the dashboard setting.
     * @param int|null $quality JPEG or WebP quality from 0 to 100.
     * @param int|null $pngCompressionLevel PNG compression from 0 to 9.
     * @return SliderImageData[]
     */
    public function getSliderImagesByFileSet(
        int|string|FileSet|null $fileSet,
        int                     $width,
        int                     $height,
        bool                    $crop,
        ?string                 $alt = null,
        array                   $additionalImageWidths = [],
        ?string                 $sizes = null,
        ?string                 $customName = null,
        ?string                 $format = null,
        ?int                    $quality = null,
        ?int                    $pngCompressionLevel = null,
    ): array
    {
        /* @var FileEntity|FileVersionEntity $file */

        $output = [];

        $files = $this->listFilesFromFileSet(fileSet: $fileSet);

        foreach ($files as $file) {
            $output[] = $this->getSliderImage(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
                alt: $alt,
                additionalImageWidths: $additionalImageWidths,
                sizes: $sizes,
                customName: $customName,
                format: $format,
                quality: $quality,
                pngCompressionLevel: $pngCompressionLevel,
            );
        }

        return $output;
    }
}
