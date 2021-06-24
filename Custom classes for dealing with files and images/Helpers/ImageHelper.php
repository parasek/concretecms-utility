<?php

namespace Application\Helpers;

use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\Page\Page;

/**
 * Opinionated image-related helpers for concrete5.8 and php7.4+ (mostly generating images).
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/c5-snippets
 */
class ImageHelper extends FileHelper
{
    private $ih;

    public function __construct()
    {
        parent::__construct();
        $this->ih = $this->app->make('helper/image');
    }

    /**
     * Get image info as an array.
     * If no image or invalid image is selected, placeholder data will be returned.
     *
     * [
     *     'id'          => '',
     *     'thumbnail'   => '',
     *     'url'         => '',
     *     'placeholder' => '',
     *     'width'       => '',
     *     'height'      => '',
     *     'alt'         => '',
     * ];
     *
     * @param $file "File Object / File ID"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to replace default alt attribute (modified concrete5 Title attribute)."
     * @return array
     */
    public function getImage($file, int $width, int $height, bool $crop, ?string $alt = null): array
    {
        /* @var Version $file */

        $file = $this->convertToObject($file);

        $placeholder = $this->getPlaceholderString($width, $height);

        $output = [
            'id'          => false,
            'thumbnail'   => false,
            'url'         => $placeholder,
            'placeholder' => '',
            'width'       => $width,
            'height'      => $height,
            'alt'         => ($alt === null) ? '' : $alt,
        ];

        if ($this->isValidImage($file)) {

            $thumbnail = $this->generateThumbnail($file, $width, $height, $crop);

            $output = array_merge($output, [
                'id'          => $file->getFileID(),
                'thumbnail'   => true,
                'url'         => $thumbnail['url'],
                'placeholder' => $placeholder,
                'width'       => $thumbnail['width'],
                'height'      => $thumbnail['height'],
                'alt'         => ($alt === null) ? $this->getModifiedName($file) : $alt,
            ]);

        }

        return $output;
    }

    /**
     * Get list of images by file set.
     *
     * @param $fileSet "File set Object / File set ID / File set name"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all images."
     * @return array
     */
    public function getImagesByFileSet($fileSet, int $width, int $height, bool $crop, ?string $alt = null): array
    {
        $output = [];

        $files = $this->getFilesFromFileset($fileSet);

        foreach ($files as $file) {
            $output[] = $this->getImage($file, $width, $height, $crop, $alt);
        }

        return $output;
    }

    /**
     * Get a list of images using selected main image.
     * Images will be fetched from the first found file set.
     * Selected image will be the first element of list, other images will be sorted by position in file set.
     *
     * @param $file "File Object / File ID"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all images."
     * @return array
     */
    public function getImagesByMainImage($file, int $width, int $height, bool $crop, ?string $alt = null): array
    {
        $output = [];

        $fileSetFiles = $this->getFilesFromFirstFileSet($file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getImage($fileSetFile, $width, $height, $crop, $alt);
        }

        return $output;
    }

    /**
     * Get image info as array (additional image is generated for fullscreen lightbox).
     * If no image or invalid image is selected, placeholder data will be returned.
     *
     * [
     *     'id'                      => '',
     *     'thumbnail'               => '',
     *     'url'                     => '',
     *     'placeholder'             => '',
     *     'width'                   => '',
     *     'height'                  => '',
     *     'alt'                     => '',
     *     'title'                   => '',
     *     'fullscreen_image_url'    => '',
     *     'fullscreen_image_width'  => '',
     *     'fullscreen_image_height' => '',
     * ];
     *
     * @param $file "File Object / File ID"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to replace default alt attribute (modified concrete5 Title attribute)."
     * @param string|null $title "Provide string to replace default lightbox title (which is the same as $alt)."
     * @return array
     */
    public function getGalleryImage($file, int $width, int $height, bool $crop, ?string $alt = null, ?string $title = null): array
    {
        $file = $this->convertToObject($file);

        $placeholder = $this->getPlaceholderString($width, $height);

        $thumbnailAlt = ($alt === null) ? '' : $alt;

        $output = [
            'id'                      => false,
            'thumbnail'               => false,
            'url'                     => $placeholder,
            'placeholder'             => '',
            'width'                   => $width,
            'height'                  => $height,
            'alt'                     => $thumbnailAlt,
            'title'                   => ($title === null) ? $thumbnailAlt : $title,
            'fullscreen_image_url'    => '',
            'fullscreen_image_width'  => '',
            'fullscreen_image_height' => '',
        ];

        if ($this->isValidImage($file)) {

            $thumbnail = $this->generateThumbnail($file, $width, $height, $crop);
            $fullscreenImage = $this->generateThumbnail($file, 1920, 1080, false);

            $thumbnailAlt = ($alt === null) ? $this->getModifiedName($file) : $alt;

            $output = array_merge($output, [
                'id'                      => $file->getFileID(),
                'thumbnail'               => true,
                'url'                     => $thumbnail['url'],
                'placeholder'             => $placeholder,
                'width'                   => $thumbnail['width'],
                'height'                  => $thumbnail['height'],
                'alt'                     => $thumbnailAlt,
                'title'                   => ($title === null) ? $thumbnailAlt : $title,
                'fullscreen_image_url'    => $fullscreenImage['url'],
                'fullscreen_image_width'  => $fullscreenImage['width'],
                'fullscreen_image_height' => $fullscreenImage['height'],
            ]);

        }

        return $output;
    }

    /**
     * Get list of images by file set.
     *
     * @param $fileSet "File set Object / File set ID / File set name"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all images."
     * @param string|null $title "Provide string to set single lightbox title for all images."
     * @return array
     */
    public function getGalleryByFileSet($fileSet, int $width, int $height, bool $crop, ?string $alt = null, ?string $title = null): array
    {
        $output = [];

        $files = $this->getFilesFromFileset($fileSet);

        foreach ($files as $file) {
            $output[] = $this->getGalleryImage($file, $width, $height, $crop, $alt, $title);
        }

        return $output;
    }

    /**
     * Get a list of images using selected main image.
     * Images will be fetched from the first found file set.
     * Selected image will be the first element of list, other images will be sorted by position in file set.
     *
     * @param $file "File Object / File ID"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param string|null $alt "Provide string to set single alt attribute for all images."
     * @param string|null $title "Provide string to set single lightbox title for all images."
     * @return array
     */
    public function getGalleryByMainImage($file, int $width, int $height, bool $crop, ?string $alt = null, ?string $title = null): array
    {
        $output = [];

        $fileSetFiles = $this->getFilesFromFirstFileSet($file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getGalleryImage($fileSetFile, $width, $height, $crop, $alt, $title);
        }

        return $output;
    }

    /**
     * Get list of slider images from file set.
     * Concrete5 file attributes handles: title, subtitle, link, link_external, link_anchor, button_text, right_alignment, new_window
     *
     * @param $fileSet "File set Object / File set ID / File set name"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @param array $additionalImageWidths "Provide widths in pixels as an array, for example: [360, 480, 740, 960, 1140, 1372].
     * Heights will be calculated automatically, taking into account proportions of base image.
     * Additional images (for different breakpoints) will be generated based on those dimensions."
     * @return array
     */
    public function getSliderImages($fileSet, int $width, int $height, bool $crop, array $additionalImageWidths = []): array
    {
        /* @var Version $file */

        $output = [];

        $files = $this->getFilesFromFileset($fileSet);
        if (empty($files)) return $output;

        $dimensions = [];
        foreach ($additionalImageWidths as $additionalImageWidth) {
            $dimensions[] = [
                'width'  => $additionalImageWidth,
                'height' => (int)round(($height / $width) * $additionalImageWidth)
            ];
        }

        foreach ($files as $file) {

            $srcset = '';
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

            $link = null;
            if ($linkAttribute = $file->getAttribute('link')) {
                $linkPage = Page::getByID($linkAttribute);
                if (is_object($linkPage) and !$linkPage->isError() and !$linkPage->isInTrash()) {
                    $link = $linkPage->getCollectionLink();
                }
            }
            if ($file->getAttribute('link_external')) {
                $link = $file->getAttribute('link_external');
            }

            $output[] = [
                'url'             => $this->ih->getThumbnail($file, $width, $height, $crop)->src,
                'width'           => $width,
                'height'          => $height,
                'srcset'          => $srcset,
                'sizes'           => '(max-width: 767px)  100vw,
                                      (max-width: 991px)  740px,
                                      (max-width: 1199px) 960px,
                                      (max-width: 1649px) 1140px,
                                     ' . $width . 'px',
                'title'           => $file->getAttribute('title') ?? null,
                'subtitle'        => $file->getAttribute('subtitle') ?? null,
                'link'            => $link,
                'link_anchor'     => $file->getAttribute('link_anchor') ?? null,
                'button_text'     => $file->getAttribute('button_text') ?? null,
                'right_alignment' => $file->getAttribute('right_alignment') ?? null,
                'new_window'      => $file->getAttribute('new_window') ?? null
            ];
        }

        return $output;
    }

    /**
     * Get an inline svg string that can be used for image "src" attribute when using lazy-loading.
     *
     * @param int $width
     * @param int $height
     * @return string
     */
    public function getPlaceholderString(int $width, int $height): string
    {
        return 'data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20' . $width . '%20' . $height . '%22%20%2F%3E';
    }

    private function generateThumbnail(FileEntity $file, int $width, int $height, bool $crop): array
    {
        /* @var Version $file */

        $thumbnailUrl = $file->getURL();
        $thumbnailWidth = $file->getAttribute('width');
        $thumbnailHeight = $file->getAttribute('height');
        if ($thumbnailWidth > $width or $thumbnailHeight > $height) {
            $thumbnail = $this->ih->getThumbnail($file, $width, $height, $crop);
            $thumbnailUrl = $thumbnail->src;
            $thumbnailWidth = $thumbnail->width;
            $thumbnailHeight = $thumbnail->height;
        }

        return [
            'url'    => $thumbnailUrl,
            'width'  => $thumbnailWidth,
            'height' => $thumbnailHeight,
        ];
    }

    private function isValidImage($file): bool
    {
        /* @var Version $file */

        if (!($file instanceof FileEntity)) return false;
        if (!$file->canEdit()) return false;

        return true;
    }
}
