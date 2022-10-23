<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\DTO;

/**
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class SvgData
{
    public function __construct(
        public readonly ?int    $width,
        public readonly ?int    $height,
        public readonly ?float  $ratio,
        public readonly ?string $inlineCode,
    )
    {
    }
}
