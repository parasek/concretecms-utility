<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\DTO;

/**
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class VideoData
{
    public function __construct(
        public readonly bool                 $isValid,
        public readonly ?int                 $id,
        public readonly ?string              $url,
        public readonly ?int                 $width,
        public readonly ?int                 $height,
        public readonly ?float               $ratio,
        public readonly ?float               $duration,
        public readonly ?string              $type,
        public readonly ?FileData            $file,
        public readonly ?VideoAdditionalData $video,
    )
    {
    }
}
