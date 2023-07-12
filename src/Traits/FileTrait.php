<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Traits;

use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use Concrete\Core\File\File;
use ConcreteCmsUtility\Enums\ExtensionRemovableFromNameEnum;

trait FileTrait
{
    /**
     * Get "beautified" name from "Title" Concrete File attribute.
     *
     * Selected extensions (see ExtensionRemovableFromNameEnum class) will be stripped from the end.
     *
     * Counter suffix in "Title" formatted like: " - 001", " - 002" and so on, will be stripped from the end.
     *
     * Example:
     * "Some random title - 001.webp" will be converted to "Some random title"
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return string|null
     */
    public function getModifiedName(int|FileEntity|FileVersionEntity|null $file): string|null
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        if ($file === null) return null;

        $removableExtensions = array_column(ExtensionRemovableFromNameEnum::cases(), 'value');

        $title = $file->getTitle();
        $extension = strtolower(pathinfo($title, PATHINFO_EXTENSION));

        $modifiedName = $title;
        if (!empty($extension) and in_array($extension, $removableExtensions)) {
            $modifiedName = pathinfo($title, PATHINFO_FILENAME); // Remove extension
            $modifiedName = preg_replace('/ - [0-9]*$/', '', $modifiedName); // Remove counter at the end of file name, " - 001", " - 002" and so on.
        }

        return $modifiedName;
    }

    /**
     * Convert File ID to Concrete File Object.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return FileEntity|null
     */
    public function convertToFileObject(int|FileEntity|FileVersionEntity|null $file): FileEntity|null
    {
        if (is_numeric($file)) {
            $file = File::getByID($file);
        }

        if ($file instanceof FileVersionEntity) {
            $file = $file->getFile();
        }

        if (!($file instanceof FileEntity)) {
            $file = null;
        }

        return $file;
    }
}
