<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\DTO;

/**
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class FileData
{
    /**
     * @param bool $isValid
     * @param int|null $id
     * @param string|null $name
     * Get "beautified" name from "Title" Concrete File attribute.<br>
     * Selected extensions (see ExtensionRemovableFromNameEnum class) will be stripped from the end.<br>
     * Counter suffix in "Title" formatted like: " - 001", " - 002" and so on, will be stripped from the end.<br>
     * Example:<br>
     * "Some random title - 001.webp" will be converted to "Some random title"
     * @param string|null $fileName
     * @param string|null $extension
     * @param string|null $url
     * @param string|null $relativePath
     * @param string|null $downloadUrl
     * Get full download link, which for example can be used for:<br>
     * - tracking file downloads,<br>
     * - hiding real path,<br>
     * - taking advantage of Concrete permissions.
     * @param int|null $width
     * @param int|null $height
     * @param int|null $duration
     */
    public function __construct(
        public readonly bool    $isValid,
        public readonly ?int    $id,
        public readonly ?string $name,
        public readonly ?string $fileName,
        public readonly ?string $extension,
        public readonly ?string $url,
        public readonly ?string $relativePath,
        public readonly ?string $downloadUrl,
        public readonly ?int    $width,
        public readonly ?int    $height,
        public readonly ?int    $duration,
    )
    {
    }
}
