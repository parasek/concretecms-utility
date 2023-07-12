<?php

declare(strict_types=1);

namespace ConcreteCmsUtility;

use ConcreteCmsUtility\Traits\FileTrait;
use ConcreteCmsUtility\Traits\VideoTrait;

/**
 * Opinionated video-related helpers for Concrete 9 and PHP 8.1+.
 *
 * @license https://opensource.org/licenses/MIT The MIT License
 * @link https://github.com/parasek/concretecms-utility
 */
class VideoUtility extends FileUtility
{
    use FileTrait, VideoTrait;
}
