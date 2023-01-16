# Custom utility classes for Concrete CMS

Few handy, opinionated helpers for dealing with Files/Images when generating Images/Galleries/Sliders/File Lists etc.

Requires Concrete 9 and PHP 8.1.

Most methods will return DTO objects or array of DTOs. Your Editor should help you with autocompletion.
If not, add respective code:

```php
// Hints for services
/** @var \ConcreteCmsUtility\FileUtility $fileUtility */
/** @var \ConcreteCmsUtility\ImageUtility $imageUtility */

// Hints for returned objects
/** @var \ConcreteCmsUtility\DTO\FileData $file */
/** @var \ConcreteCmsUtility\DTO\ImageData $image */
/** @var \ConcreteCmsUtility\DTO\GalleryImageData $image */
/** @var \ConcreteCmsUtility\DTO\SliderImageData $image */

// Hints for arrays (when using foreach loop etc.).
/** @var \ConcreteCmsUtility\DTO\ImageData[] $images */
```

You can access returned values like objects:

```php
$file->url;

$image->url;
$image->width;
$image->height;

$image->file->url;

// etc.
```



Available methods:
```
// You will find more information about methods
// in phpDoc block comments.

// $fileUtility
getFile();
getFilesByFileSet();
getFilesByMainFile();
getMainFileset();
getModifiedName();
convertToFileObject();

// $imageUtility
getImage();
getImagesByFileSet();
getImagesByMainImage();
getGalleryImage();
getGalleryImagesByFileSet();
getGalleryImagesByMainImage();
getSliderImage
getSliderImagesByFileset();
getPlaceholderString();
```

## Installation

```php
composer require parasek/concretecms_utility
```

## Installation (without composer)

Download the latest release and copy content of `src` folder into `application/src/ConcreteCmsUtility`.

Add code below in `application/bootstrap/app.php`:

```php
$classLoader = new \Symfony\Component\ClassLoader\Psr4ClassLoader();
$classLoader->addPrefix('ConcreteCmsUtility', DIR_APPLICATION . '/' . DIRNAME_CLASSES . '/' . 'ConcreteCmsUtility');
$classLoader->register();
```

## Service provider
You can register service provider in `application/config/app.php` in `providers` section

```php
'providers' => [
    'concrete_cms_utility' => ConcreteCmsUtility\ServiceProvider::class,
],
```
Then you will be able to use shorthands like:
```php
app('utils/image')->getPlaceholderString(width: 100, height: 100);
$app->make('utils/file')->getFile(file: 1)->url;
```

## Example usage

How to load services:

```php
use ConcreteCmsUtility\FileUtility;
use ConcreteCmsUtility\ImageUtility;

// Using Service container in controllers
$fileUtility = $this->app->make(FileUtility::class);
$imageUtility = $this->app->make(ImageUtility::class);

// Using Service container in view files
$app = \Concrete\Core\Support\Facade\Application::getFacadeApplication();
$fileUtility = $app->make(FileUtility::class);
$imageUtility = $app->make(ImageUtility::class);

// Using app helper (everywhere)
$fileUtility = app(FileUtility::class);
$imageUtility = app(ImageUtility::class);

// Using dependency injection
class CustomClass
{
    protected FileUtility $fileUtility;
    protected ImageUtility $imageUtility;

    public function __construct(FileUtility $fileUtility, ImageUtility $imageUtility)
    {
        $this->fileUtility = $fileUtility;
        $this->imageUtility = $imageUtility;
    }
}
```

Example:
```php
<?php
/** @var \ConcreteCmsUtility\ImageUtility $imageUtility */
/** @var \ConcreteCmsUtility\DTO\GalleryImageData $image */

$imageUtility = app(\ConcreteCmsUtility\ImageUtility::class);

$image = $imageUtility->getGalleryImage(
    file: 29,
    width: 300,
    height: 200,
    crop: true,
    alt: 'Custom alt',
    title: 'Custom Title for lightbox etc.',
    fullscreenWidth: 1600,
    fullscreenHeight: 1200,
    fullscreenCrop: false,
);
//dump($image);
?>
<?php if ($image->isValid): ?>
    <a href="<?php echo h($image->fullscreenUrl); ?>"
       title="<?php echo h($image->title); ?>"
    >
        <img src="<?php echo h($image->url); ?>"
             alt="<?php echo h($image->alt); ?>"
             width="<?php echo h($image->width); ?>"
             height="<?php echo h($image->height); ?>"
        >
    </a>
    <a href="<?php echo h($image->file->downloadUrl); ?>"
       title="<?php echo h($image->title); ?>"
    >
        <?php echo t('Download'); ?>
    </a>
<?php endif; ?>
```
