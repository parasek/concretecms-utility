<?php

declare(strict_types=1);

namespace ConcreteCmsUtility;

use ConcreteCmsUtility\Enums\ExtensionRemovableFromNameEnum;
use ConcreteCmsUtility\DTO\FileData;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\File\File;
use Concrete\Core\File\Set\Set as FileSet;
use Concrete\Core\File\FileList;


/**
 * Opinionated file-related helper for Concrete 9 and PHP 8.1+.
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class FileUtility
{
    /**
     * Get common info from File Object.
     *
     * You can provide File ID, File Object or File Version Object.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return FileData
     */
    public function getFile(int|FileEntity|FileVersionEntity|null $file): FileData
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        return new FileData(
            isValid: isset($file),
            id: $file?->getFileID(),
            name: isset($file) ? $this->getModifiedName(file: $file) : null,
            fileName: $file?->getFileName(),
            extension: $file?->getExtension(),
            url: $file?->getURL(),
            relativePath: $file?->getRelativePath(),
            downloadUrl: is_object($file) ? (string)$file->getForceDownloadURL() : null,
            width: is_object($file) ? (int)$file->getAttribute('width') : null,
            height: is_object($file) ? (int)$file->getAttribute('height') : null,
            duration: is_object($file) ? (int)$file->getAttribute('duration') : null,
        );
    }

    /**
     * Get list of Files by selected File Set.
     *
     * You can provide File Set ID, File Set Name or File Set Object.
     *
     * @param int|string|FileSet|null $fileSet "File Set ID, File Set Name or File Set Object"
     * @return FileData[]
     */
    public function getFilesByFileSet(int|string|FileSet|null $fileSet): array
    {
        $output = [];

        $files = $this->listFilesFromFileSet(fileSet: $fileSet);

        foreach ($files as $file) {
            $output[] = $this->getFile(file: $file);
        }

        return $output;
    }

    /**
     * Get a list of Files using selected main File.
     *
     * Files will be fetched from the first found File Set.
     *
     * Selected File will be the first element of list, other Files will be sorted by position in File Set.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return FileData[]
     */
    public function getFilesByMainFile(int|FileEntity|FileVersionEntity|null $file): array
    {
        $output = [];

        $fileSetFiles = $this->listFilesFromFirstFileSet(file: $file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getFile(file: $fileSetFile);
        }

        return $output;
    }

    /**
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return FileSet|null
     */
    public function getMainFileset(int|FileEntity|FileVersionEntity|null $file): ?FileSet
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        if ($file === null) return null;

        return $file->getFileSets()[0] ?? null;
    }

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

    /**
     * @param int|string|FileSet|null $fileSet
     * @return array
     */
    public function listFilesFromFileSet(int|string|FileSet|null $fileSet): array
    {
        $output = [];

        if (is_numeric($fileSet)) {
            $fileSet = FileSet::getByID($fileSet);
        }

        if (is_string($fileSet)) {
            $fileSet = FileSet::getByName($fileSet);
        }

        if (!is_object($fileSet)) return $output;

        $list = new FileList();
        $list->filterBySet($fileSet);
        $list->sortByFileSetDisplayOrder();
        $output = $list->getResults();

        if (!count($output)) return $output;

        return $output;
    }

    /**
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return array
     */
    public function listFilesFromFirstFileSet(int|FileEntity|FileVersionEntity|null $file): array
    {
        $output = [];

        $file = $this->convertToFileObject(file: $file);
        if ($file === null) return $output;

        $output[0] = $file; // Put main file at the beginning of array

        $mainFileSet = $this->getMainFileset(file: $file);

        if (is_object($mainFileSet)) {

            $fileSetFiles = $mainFileSet->getFiles();

            foreach ($fileSetFiles as $fileSetFile) {

                if (!empty($fileSetFile) and $fileSetFile !== $file) {
                    $output[] = $fileSetFile;
                }

            }

        }

        return $output;
    }
}
