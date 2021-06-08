# Custom class for generating images

Few handy helpers to deal with files/images when generating galleries or listing files.

```
getFile();
getFilesByFileSet();
getFilesByMainFile();

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

Paste Helpers folder into application/src.

## Example usage in controller

```php
use Application\Helpers\FileHelper;
use Application\Helpers\ImageHelper;
        
$fileHelper = new FileHelper();
$files = $fileHelper->getFilesByFileSet('File Set name');

$imageHelper = new ImageHelper();
$galleryImage = $imageHelper->getGalleryImage($this->c->getAttribute('thumbnail'), 100, 200, true);
$sliderImages = $imageHelper->getSliderImages(
    'Example File Set name',
    null,
    1372,
    664,
    true,
    [360, 480, 740, 960, 1140, 1372]
);
```

or

```php
$fileHelper = $this->app->make('Application\Helpers\FileHelper');
$file = $fileHelper->getFile($this->c->getAttribute('attribute_handle'));
$files = $fileHelper->getFilesByFileSet('Example File Set name');

$imageHelper = $this->app->make('Application\Helpers\ImageHelper');
$images = $imageHelper->getImagesByMainImage($this->c->getAttribute('main_image'), 100, 200, true);
```
