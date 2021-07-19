# Custom classes for dealing with files and images

Few handy helpers to deal with files/images when generating galleries or listing files.

```
getFile();
getFilesByFileSet();
getFilesByMainFile();
getModifiedName();

getImage();
getImagesByFileSet();
getImagesByMainImage();
getGalleryImage();
getGalleryByFileSet();
getGalleryByMainImage();
getSliderImages();
getPlaceholderString();
```

## Installation

Paste Utility folder into application/src.

If you aren't auto-loading classes from src folder, add code below to autoload.php:

```php
// application/bootstrap/autoload.php

$classLoader = new \Symfony\Component\ClassLoader\Psr4ClassLoader();
$classLoader->addPrefix('Application', DIR_APPLICATION . '/' . DIRNAME_CLASSES);
$classLoader->register();
```

## Example usage in controller

```php
use Application\Utility\FileUtility;
use Application\Utility\ImageUtility;
        
$fileUtility = new FileUtility();
$files = $fileUtility->getFilesByFileSet('File Set name');

$imageUtility = new ImageUtility();
$galleryImage = $imageUtility->getGalleryImage($this->c->getAttribute('thumbnail'), 100, 200, true);
$sliderImages = $imageUtility->getSliderImages(
    'Example File Set name',
    1372,
    664,
    true,
    [360, 480, 740, 960, 1140, 1372]
);
```

or

```php
$fileUtility = $this->app->make('Application\Utility\FileUtility');
$file = $fileUtility->getFile($this->c->getAttribute('attribute_handle'));
$files = $fileUtility->getFilesByFileSet('Example File Set name');

$imageUtility = $this->app->make('Application\Utility\ImageUtility');
$images = $imageUtility->getImagesByMainImage($this->c->getAttribute('main_image'), 100, 200, true);
```
