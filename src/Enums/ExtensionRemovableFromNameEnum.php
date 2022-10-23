<?php

declare(strict_types=1);

namespace ConcreteCmsUtility\Enums;

enum ExtensionRemovableFromNameEnum: string
{
    case WEBP_EXTENSION = 'webp';
    case JPG_EXTENSION = 'jpg';
    case JPEG_EXTENSION = 'jpeg';
    case GIF_EXTENSION = 'gif';
    case TIFF_EXTENSION = 'tiff';
    case PNG_EXTENSION = 'png';
    case SVG_EXTENSION = 'svg';
    case PDF_EXTENSION = 'pdf';
    case DOC_EXTENSION = 'doc';
    case DOCX_EXTENSION = 'docx';
    case MP3_EXTENSION = 'mp3';
    case MP4_EXTENSION = 'mp4';
}
