<?php

namespace Application\Utility;

use Concrete\Core\Application\Application;
use Concrete\Core\Support\Facade\Application as ApplicationFacade;
use Concrete\Core\Entity\File\Version;
use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\File\File;
use Concrete\Core\File\Set\Set as FileSet;
use Concrete\Core\File\FileList;


/**
 * Opinionated file-related helpers for concrete5.8 and php7.3+.
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/c5-snippets
 */
class FileUtility
{
    protected $app;

    public function __construct()
    {
        $this->app = ApplicationFacade::getFacadeApplication();
    }

    /**
     * Get file info as an array.
     *
     * [
     *     'id'            => '',
     *     'name'          => '',
     *     'file_name'     => '',
     *     'url'           => '',
     *     'relative_path' => '',
     *     'download_url'  => '',
     * ];
     *
     * @param $file "File Object / File ID"
     * @return array
     */
    public function getFile($file): array
    {
        /* @var Version $file */

        $output = [
            'id'            => '',
            'name'          => '',
            'file_name'     => '',
            'url'           => '',
            'relative_path' => '',
            'download_url'  => '',
        ];

        $file = $this->convertToObject($file);

        if ($file !== null) {

            $output = array_merge($output, [
                'id'            => $file->getFileID(),
                'name'          => $this->getModifiedName($file),
                'file_name'     => $file->getFileName(),
                'url'           => $file->getURL(),
                'relative_path' => $file->getRelativePath(),
                'download_url'  => (string)$file->getForceDownloadURL(),
            ]);

        }

        return $output;
    }

    /**
     * Get list of files by file set.
     *
     * @param $fileSet "File set Object / File set ID / File set name"
     * @return array
     */
    public function getFilesByFileSet($fileSet): array
    {
        $output = [];

        $files = $this->getFilesFromFileset($fileSet);

        foreach ($files as $file) {
            $output[] = $this->getFile($file);
        }

        return $output;
    }

    /**
     * Get a list of files using selected main file.
     * Files will be fetched from the first found file set.
     * Selected file will be the first element of list, other files will be sorted by position in file set.
     *
     * @param $file "File Object / File ID"
     * @return array
     */
    public function getFilesByMainFile($file): array
    {
        $output = [];

        $fileSetFiles = $this->getFilesFromFirstFileSet($file);

        foreach ($fileSetFiles as $fileSetFile) {
            $output[] = $this->getFile($fileSetFile);
        }

        return $output;
    }


    /**
     * Get modified name based on concrete5 title attribute
     *
     * @param $file "File Object / File ID"
     * @return string
     */
    public function getModifiedName($file): string
    {
        /* @var Version $file */

        $file = $this->convertToObject($file);

        $title = '';

        if ($file !== null) {

            $title = $file->getTitle();

            $supportedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
            $extension = strtolower(pathinfo($file->getTitle(), PATHINFO_EXTENSION));
            if (!empty($extension) and in_array($extension, $supportedExtensions)) {
                $title = pathinfo($file->getTitle(), PATHINFO_FILENAME); // Remove extension
                $title = preg_replace('/ - [0-9]*$/', '', $title); // Remove counter (at the end of filename), " - 001" etc.
            }

        }

        return $title;
    }

    protected function getMainFileset($file): ?FileSet
    {
        /* @var FileEntity $file */

        $file = $this->convertToObject($file);
        if ($file === null) return null;

        return $file->getFileSets()[0];
    }

    protected function getFilesFromFileset($fileSet): ?array
    {
        $output = [];

        if (is_int($fileSet)) {
            $fileSet = $fileSet = FileSet::getByID($fileSet);
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

    protected function getFilesFromFirstFileSet($file): array
    {
        $output = [];

        $file = $this->convertToObject($file);
        if ($file === null) return $output;

        $output[0] = $file; // Put main file at the beginning of array

        $mainFileSet = $this->getMainFileset($file);

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

    protected function convertToObject($file): ?FileEntity
    {
        if (is_string($file)) {
            $file = (int)$file;
        }

        if (is_int($file)) {
            $file = File::getByID($file);
        }

        if (!($file instanceof FileEntity)) return null;

        return $file;
    }
}
