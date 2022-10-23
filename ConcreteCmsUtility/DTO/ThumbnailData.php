<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\DTO;

/**
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class ThumbnailData
{
    public function __construct(
        public readonly ?string $url,
        public readonly ?int    $width,
        public readonly ?int    $height,
    )
    {
    }
}
