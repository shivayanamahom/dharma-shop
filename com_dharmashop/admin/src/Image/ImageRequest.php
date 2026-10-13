<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
\defined('_JEXEC') or die;
final class ImageRequest
{
    public string $url;
    /** Размеры итогового изображения в пикселях (0 — неизвестны); заполняет плагин нарезки. */
    public int $width = 0;
    public int $height = 0;
    public function __construct(public readonly string $source, public readonly string $context)
    {
        $this->url = $source;
    }
}
