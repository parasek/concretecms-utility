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
     * Get Image using BasicThumbnailer service ('helper/image') and get common info.
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
     * Wrapper for generating thumbnail.
     * If image dimensions are smaller than provided dimensions, original url/dimensions will be used.
     *
     * @param FileEntity|FileVersionEntity $file "File Object or File Version Object"
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return ThumbnailData
     */
    public function generateThumbnail(
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
}
