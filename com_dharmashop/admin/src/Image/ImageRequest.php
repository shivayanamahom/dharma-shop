<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
\defined('_JEXEC') or die;
final class ImageRequest
{
    public string $url;
    public function __construct(public readonly string $source, public readonly string $context)
    {
        $this->url = $source;
    }
}
