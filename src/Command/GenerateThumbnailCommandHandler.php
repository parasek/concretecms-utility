<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Command;

use Concrete\Core\Entity\File\File;
use Concrete\Core\Entity\File\Version;
use ConcreteCmsUtility\Image\ThumbnailGenerator;
use Doctrine\ORM\EntityManagerInterface;

class GenerateThumbnailCommandHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ThumbnailGenerator $thumbnailGenerator,
    ) {
    }

    public function __invoke(GenerateThumbnailCommand $command): void
    {
        $file = $this->entityManager->find(File::class, $command->fileIdentifier);
        if (!$file instanceof File) {
            return;
        }

        $version = $file->getVersion($command->fileVersionIdentifier);
        if ($version instanceof Version) {
            $this->thumbnailGenerator->generateQueued($version, $command);
        }
    }
}
