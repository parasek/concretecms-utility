<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Traits;

use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use ConcreteCmsUtility\DTO\VideoAdditionalData;
use ConcreteCmsUtility\DTO\VideoData;
use ConcreteCmsUtility\Enums\VideoExtensionEnum;
use getID3;

trait VideoTrait
{
    /**
     * Check if given File is a Video File.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return bool
     */
    public function isVideo(int|FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

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
     * Get Video data.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return VideoData
     */
    public function getVideo(int|FileEntity|FileVersionEntity|null $file): VideoData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        $isVideo = $this->isVideo(file: $file);
        $isValid = $isVideo;

        // Video data from Concrete
        $id = null;
        $url = null;
        if ($isValid) {
            $id = $file->getFileID();
            $url = $file->getURL();
        }

        // Video additional data

        $width = null;
        $height = null;
        $duration = null;
        $size = null;
        $fullSize = null;
        $ratio = null;
        $type = null;

        if ($isValid) {

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
                $type = $fileInfo['mime_type'];
            }
        }

        return new VideoData(
            isValid: $isValid,
            id: $id,
            url: $url,
            width: $width,
            height: $height,
            ratio: $ratio,
            duration: $duration,
            type: $type,
            file: $this->getFile($file),
            video: new VideoAdditionalData(
                width: $width,
                height: $height,
                ratio: $ratio,
                duration: $duration,
                size: $size,
                fullSize: $fullSize,
            ),
        );
    }

}
