<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Seo;

\defined('_JEXEC') or die;

/** Контракт страницы для разметки: только готовые строки, без моделей и запросов. */
final readonly class PageMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $robots,
        public string $h1,
        public string $breadcrumb,
        public string $image,
        public string $url,
        public string $siteName,
        public string $locale,
        public string $type = 'website',
        public string $imageAlt = '',
    ) {
    }
}
