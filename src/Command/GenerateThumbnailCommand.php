<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Command;

use Concrete\Core\Foundation\Command\AsyncCommandInterface;
use Concrete\Core\Foundation\Command\Command;

/** A queued thumbnail retains the source version and resolved encoding settings. */
class GenerateThumbnailCommand extends Command implements AsyncCommandInterface
{
    public function __construct(
        public readonly int $fileIdentifier,
        public readonly int $fileVersionIdentifier,
        public readonly string $sourceFingerprint,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $crop,
        public readonly string $customName,
        public readonly string $format,
        public readonly ?int $quality,
        public readonly ?int $pngCompressionLevel,
    ) {
    }

    public static function getHandler(): string
    {
        return GenerateThumbnailCommandHandler::class;
    }
}
