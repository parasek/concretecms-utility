<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Traits;

use Concrete\Core\Entity\File\File as FileEntity;
use Concrete\Core\Entity\File\Version as FileVersionEntity;
use ConcreteCmsUtility\DTO\SvgAdditionalData;
use ConcreteCmsUtility\Enums\ExtensionEnum;

trait SvgTrait
{
    /**
     * Check if given File is an SVG File.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return bool
     */
    public function isSvg(int|FileEntity|FileVersionEntity|null $file): bool
    {
        /* @var FileEntity|FileVersionEntity $file */
        $file = $this->convertToFileObject(file: $file);

        if (!($file instanceof FileEntity) and !($file instanceof FileVersionEntity)) {
            return false;
        }

        if (strtolower($file->getExtension()) !== ExtensionEnum::SVG_EXTENSION->value) {
            return false;
        }

        return true;
    }

    /**
     * Get SVG data.
     *
     * @param int|FileEntity|FileVersionEntity|null $file "File ID, File Object or File Version Object"
     * @return SvgAdditionalData
     */
    public function getSvg(int|FileEntity|FileVersionEntity|null $file): SvgAdditionalData
    {
        $width = null;
        $height = null;
        $ratio = null;
        $inlineCode = null;

        if ($this->isSvg($file)) {

            $path = realpath($_SERVER['DOCUMENT_ROOT']) . $file->getRelativePath();
            $svg = simplexml_load_file($path);

            // Browser defaults
            $width = 300;
            $height = 150;
            $ratio = (float)number_format(($height / $width) * 100, 5, '.', '');

            // Get dimensions from width and height attributes
            $svgWidth = (float)$svg['width'];
            $svgHeight = (float)$svg['height'];
            if (!empty($svgWidth) and !empty($svgHeight)) {
                $ratio = (float)number_format(($svgHeight / $svgWidth) * 100, 5, '.', '');
                $width = (int)round($svgWidth);
                $height = (int)round($svgHeight);
            }

            // Get dimensions from view box
            $viewBox = (string)$svg['viewBox'];
            if (!empty($viewBox)) {
                $viewBoxDimensions = explode(' ', $viewBox);
                if (!empty($viewBoxDimensions[2]) and !empty($viewBoxDimensions[3])) {
                    $viewBoxWidth = (float)$viewBoxDimensions[2];
                    $viewBoxHeight = (float)$viewBoxDimensions[3];
                    $ratio = (float)number_format(($viewBoxHeight / $viewBoxWidth) * 100, 5, '.', '');
                    $width = (int)round($viewBoxWidth);
                    $height = (int)round($viewBoxHeight);
                }
            }

            // Create inline code
            $svgInline = $svg;
            unset($svgInline['height'], $svgInline['width']);
            $inlineCode .= preg_replace('/<\?xml.+?\?>/', '', $svgInline->asXml());
        }

        return new SvgAdditionalData(
            width: $width,
            height: $height,
            ratio: $ratio,
            inlineCode: $inlineCode,
        );
    }
}
