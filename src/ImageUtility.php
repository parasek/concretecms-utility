<?php

declare(strict_types=1);

namespace ConcreteCmsUtility;

use ConcreteCmsUtility\DTO\GalleryImageData;
use ConcreteCmsUtility\DTO\ImageData;
use ConcreteCmsUtility\DTO\SliderImageData;
use ConcreteCmsUtility\DTO\SvgData;
use ConcreteCmsUtility\DTO\ThumbnailData;
use ConcreteCmsUtility\DTO\VideoData;
use ConcreteCmsUtility\Enums\ExtensionEnum;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use Concrete\Core\File\Image\BasicThumbnailer;
use Concrete\Core\File\Set\Set as FileSet;
use Concrete\Core\Page\Page;
use ConcreteCmsUtility\Enums\VideoExtensionEnum;
use getID3;

/**
 * Opinionated image-related helpers for Concrete 9 and PHP 8.1+.
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class ImageUtility extends FileUtility
{
    private BasicThumbnailer $ih;

    public function __construct(BasicThumbnailer $ih)
    {
        $this->ih = $ih;
    }

    /**
     * Create Image using BasicThumbnailer service ('helper/image') and get common info.
     *
     * You can provide File ID, File Object or File Version Object.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to replace default alt attribute (modified Concrete Title attribute)."
     * @return ImageData
     */
    public function getImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
    ): ImageData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isValidImage = $this->isValidImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isValid = ($isValidImage or $isSvg);

        $fileData = $this->getFile(file: $file);
        $svgData = $this->getSvg(file: $file);

        $url = null;
        $placeholder = $this->getPlaceholderString(width: $width, height: $height);

        if ($isValidImage) {
            $thumbnail = $this->generateThumbnail(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
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
            isImage: $isValidImage,
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
     * Get list of Images by selected File Set.
     *
     * You can provide File Set ID, File Set Name or File Set Object.
     *
     * @param int|string|FileSet|null $fileSet "File Set ID, File Set Name or File Set Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all Images."
     * @return ImageData[]
     */
    public function getImagesByFileSet(
        int|string|FileSet|null $fileSet,
        int                     $width,
        int                     $height,
        bool                    $crop,
        ?string                 $alt = null
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
     * @return ImageData[]
     */
    public function getImagesByMainImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null
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
            );
        }

        return $output;
    }

    /**
     * Create Image using BasicThumbnailer service ('helper/image') and get common info.
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
    ): GalleryImageData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isValidImage = $this->isValidImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isValid = ($isValidImage or $isSvg);

        $fileData = $this->getFile(file: $file);
        $svgData = $this->getSvg(file: $file);

        $url = null;
        $fullscreenUrl = null;
        $placeholder = $this->getPlaceholderString(width: $width, height: $height);

        if ($isValidImage) {
            $thumbnail = $this->generateThumbnail(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
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
            isImage: $isValidImage,
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
            );
        }

        return $output;
    }

    /**
     * @param int|FileEntity|FileVersionEntity|null $file
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt
     * @param array $additionalImageWidths
     * @param string|null $sizes
     * @return SliderImageData
     * @see getSliderImagesByFileset()
     */
    public function getSliderImage(
        int|FileEntity|FileVersionEntity|null $file,
        int                                   $width,
        int                                   $height,
        bool                                  $crop,
        ?string                               $alt = null,
        array                                 $additionalImageWidths = [],
        ?string                               $sizes = null,
    ): SliderImageData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isValidImage = $this->isValidImage(file: $file);
        $isSvg = $this->isSvg(file: $file);
        $isVideo = $this->isVideo(file: $file);
        $isValid = ($isValidImage or $isSvg or $isVideo);

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

        if ($isValidImage) {
            $thumbnail = $this->generateThumbnail(
                file: $file,
                width: $width,
                height: $height,
                crop: $crop,
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

        if ($isValid) {
            $dimensions = [];
            foreach ($additionalImageWidths as $additionalImageWidth) {
                $dimensions[] = [
                    'width' => $additionalImageWidth,
                    'height' => (int)round(($height / $width) * $additionalImageWidth)
                ];
            }

            $i = 0;
            foreach ($dimensions as $dimension) {
                $i++;
                $srcsetUrl = $this->ih->getThumbnail($file, $dimension['width'], $dimension['height'], $crop)->src;
                $intrinsicWidthInPixels = $dimension['width'];
                $srcset .= $srcsetUrl . ' ' . $intrinsicWidthInPixels . 'w';
                if ($i != count($dimensions)) {
                    $srcset .= ',' . PHP_EOL;
                }
            }
            $sizes = '(max-width: 767px)  100vw,
                      (max-width: 991px)  740px,
                      (max-width: 1199px) 960px,
                      (max-width: 1649px) 1140px,
                     ' . $width . 'px';

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
            isImage: $isValidImage,
            isVideo: $isVideo,
            isSvg: $isSvg,
            id: $file?->getFileID(),
            url: $url,
            srcset: $srcset,
            sizes: $sizes,
            placeholder: $placeholder,
            width: $width,
            height: $height,
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
     * @return SliderImageData[]
     */
    public function getSliderImagesByFileset(
        int|string|FileSet|null $fileSet,
        int                     $width,
        int                     $height,
        bool                    $crop,
        ?string                 $alt = null,
        array                   $additionalImageWidths = [],
        ?string                 $sizes = null,
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
            );
        }

        return $output;
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
     * @param FileEntity|FileVersionEntity $file "File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return ThumbnailData
     */
    private function generateThumbnail(
        FileEntity|FileVersionEntity $file,
        int                          $width,
        int                          $height,
        bool                         $crop
    ): ThumbnailData
    {
        /* @var FileEntity|FileVersionEntity $file */

        $thumbnailUrl = $file->getURL();
        $thumbnailWidth = $file->getAttribute('width');
        $thumbnailHeight = $file->getAttribute('height');
        if ($thumbnailWidth > $width or $thumbnailHeight > $height) {
            $thumbnail = $this->ih->getThumbnail($file, $width, $height, $crop);
            $thumbnailUrl = $thumbnail->src;
            $thumbnailWidth = $thumbnail->width;
            $thumbnailHeight = $thumbnail->height;
        }

        return new ThumbnailData(
            url: (string)$thumbnailUrl,
            width: (int)$thumbnailWidth,
            height: (int)$thumbnailHeight,
        );
    }

    /**
     * @param FileEntity|FileVersionEntity|null $file "File Object or File Version Object"
     * @return bool
     */
    private function isValidImage(FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */

        if (!($file instanceof FileEntity) and !($file instanceof FileVersionEntity)) {
            return false;
        }

        if (!$file->canEdit()) return false;

        return true;
    }

    /**
     * @param FileEntity|FileVersionEntity|null $file "File Object or File Version Object"
     * @return bool
     */
    private function isSvg(FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */

        if (!($file instanceof FileEntity) and !($file instanceof FileVersionEntity)) {
            return false;
        }

        if (strtolower($file->getExtension()) !== ExtensionEnum::SVG_EXTENSION->value) {
            return false;
        }

        return true;
    }

    /**
     * @param FileEntity|FileVersionEntity|null $file "File Object or File Version Object"
     * @return bool
     */
    public function isVideo(FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */

        if (!($file instanceof FileEntity) and !($file instanceof FileVersionEntity)) {
            return false;
        }

        $extension = strtolower($file->getExtension());
        $videoExtensions = array_column(VideoExtensionEnum::cases(), 'value');

        if (!in_array($extension, $videoExtensions)) {
            return false;
        }

        return true;
    }

    /**
     * @param FileEntity|FileVersionEntity|null $file "File Object or File Version Object"
     * @return SvgData
     */
    private function getSvg(FileEntity|FileVersionEntity|null $file): SvgData
    {
        $width = null;
        $height = null;
        $ratio = null;
        $inlineCode = null;

        if ($this->isSvg($file)) {

            $path = realpath($_SERVER['DOCUMENT_ROOT']) . $file->getRelativePath();
            $svg = simplexml_load_file($path);

            // Browser defaults
            $width = 300;
            $height = 150;
            $ratio = (float)number_format(($height / $width) * 100, 5, '.', '');

            // Get dimensions from width and height attributes
            $svgWidth = (float)$svg['width'];
            $svgHeight = (float)$svg['height'];
            if (!empty($svgWidth) and !empty($svgHeight)) {
                $ratio = (float)number_format(($svgHeight / $svgWidth) * 100, 5, '.', '');
                $width = (int)round($svgWidth);
                $height = (int)round($svgHeight);
            }

            // Get dimensions from view box
            $viewBox = (string)$svg['viewBox'];
            if (!empty($viewBox)) {
                $viewBoxDimensions = explode(' ', $viewBox);
                if (!empty($viewBoxDimensions[2]) and !empty($viewBoxDimensions[3])) {
                    $viewBoxWidth = (float)$viewBoxDimensions[2];
                    $viewBoxHeight = (float)$viewBoxDimensions[3];
                    $ratio = (float)number_format(($viewBoxHeight / $viewBoxWidth) * 100, 5, '.', '');
                    $width = (int)round($viewBoxWidth);
                    $height = (int)round($viewBoxHeight);
                }
            }

            // Create inline code
            $svgInline = $svg;
            unset($svgInline['height'], $svgInline['width']);
            $inlineCode .= preg_replace('/<\?xml.+?\?>/', '', $svgInline->asXml());
        }

        return new SvgData(
            width: $width,
            height: $height,
            ratio: $ratio,
            inlineCode: $inlineCode,
        );
    }

    /**
     * @param FileEntity|FileVersionEntity|null $file "File Object or File Version Object"
     * @return VideoData
     */
    private function getVideo(FileEntity|FileVersionEntity|null $file): VideoData
    {
        $width = null;
        $height = null;
        $duration = null;
        $size = null;
        $fullSize = null;
        $ratio = null;

        if ($this->isVideo($file)) {

            $path = realpath($_SERVER['DOCUMENT_ROOT']) . $file->getRelativePath();

            if (class_exists(getID3::class)) {
                $getID3 = new getID3();
                $fileInfo = $getID3->analyze($path);

                $width = $fileInfo['video']['resolution_x'];
                $height = $fileInfo['video']['resolution_y'];
                $duration = (float)$fileInfo['playtime_seconds'];
                $size = (string)$file->getSize();
                $fullSize = (int)$file->getFullSize();
                $ratio = (float)number_format(($height / $width) * 100, 5, '.', '');
            }
        }

        return new VideoData(
            width: $width,
            height: $height,
            ratio: $ratio,
            duration: $duration,
            size: $size,
            fullSize: $fullSize,
        );
    }
}
