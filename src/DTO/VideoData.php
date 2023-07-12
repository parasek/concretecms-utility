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
        public readonly ?FileData            $file,
        public readonly ?VideoAdditionalData $video,
    )
    {
    }
}
