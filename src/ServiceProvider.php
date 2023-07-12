<?php

declare(strict_types=1);

namespace ConcreteCmsUtility;

use Concrete\Core\Foundation\Service\Provider;

class ServiceProvider extends Provider
{
    public function register(): void
    {
        $register = [
            'utils/file' => FileUtility::class,
            'utils/image' => ImageUtility::class,
            'utils/video' => VideoUtility::class,
        ];

        foreach ($register as $key => $value) {
            $this->app->bind($key, $value);
        }
    }
}
