<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShop\Structureddata\Extension;

use Dharma\Component\DharmaShop\Site\Seo\CollectionMetadata;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Dharma\Component\DharmaShop\Site\Seo\ProductMetadata;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** JSON-LD из контекста компонента. Не запрашивает каталог и не создаёт хлебные крошки. */
final class Structureddata extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopMetadataReady' => 'addStructuredData'];
    }

    public function addStructuredData(EventInterface $event): void
    {
        $app = $this->getApplication();
        $document = $event->getArgument('document');
        $page = $event->getArgument('page');
        $settings = $event->getArgument('settings');
        $content = $event->getArgument('content');
        if (!$app->isClient('site') || $app->getInput()->getCmd('option') !== 'com_dharmashop'
            || !$document instanceof HtmlDocument || !$page instanceof PageMetadata
            || !$settings instanceof Registry || !($content instanceof CollectionMetadata || $content instanceof ProductMetadata)
            || (int) $settings->get('seo_structureddata', 1) !== 1) {
            return;
        }

        if ($content instanceof ProductMetadata) {
            $data = $this->product($page, $content);
        } else {
            $elements = [];
            foreach ($content->items as $entry) {
                $elements[] = [
                    '@type' => 'ListItem',
                    'position' => count($elements) + 1,
                    'item' => [
                        '@type' => $content->itemType,
                        '@id' => $entry['url'] . ($content->itemType === 'Product' ? '#product' : '#webpage'),
                        'url' => $entry['url'],
                        'name' => $this->plainText($entry['name']),
                    ],
                ];
            }
            $collection = [
                '@type' => 'CollectionPage',
                '@id' => $page->url . '#webpage',
                'url' => $page->url,
                'name' => $this->plainText($content->name),
                'inLanguage' => str_replace('_', '-', $page->locale),
                'mainEntity' => ['@id' => $page->url . '#itemlist'],
            ];
            if ($page->description !== '') {
                $collection['description'] = $page->description;
            }
            $data = ['@context' => 'https://schema.org', '@graph' => [
                $collection,
                [
                    '@type' => 'ItemList',
                    '@id' => $page->url . '#itemlist',
                    'numberOfItems' => count($elements),
                    'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
                    'itemListElement' => $elements,
                ],
            ]];
        }
        // HEX-флаги защищают границу script при названиях с HTML; Unicode остаётся читаемым.
        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        // Постоянное имя актива: повторная обработка заменяет содержимое, не добавляет второй script.
        $document->getWebAssetManager()->addInline('script', $json,
            ['name' => 'inline.dharmashop.structureddata'], ['type' => 'application/ld+json']);
    }

    private function product(PageMetadata $page, ProductMetadata $content): array
    {
        $product = ['@type' => 'Product', '@id' => $page->url . '#product', 'url' => $page->url,
            'name' => $this->plainText($content->name)];
        foreach (['sku' => $content->sku, 'description' => $content->description, 'image' => $content->image] as $key => $value) {
            if ($value !== '') {
                $product[$key] = $this->plainText($value);
            }
        }
        if ($content->brand !== '') {
            $product['brand'] = ['@type' => 'Brand', 'name' => $this->plainText($content->brand)];
        }
        if ($content->price !== null && is_numeric($content->price) && (float) $content->price >= 0
            && preg_match('/^[A-Z]{3}$/D', $content->currency)) {
            $product['offers'] = ['@type' => 'Offer', 'url' => $page->url,
                'price' => $content->price, 'priceCurrency' => $content->currency,
                'availability' => 'https://schema.org/' . ($content->inStock ? 'InStock' : 'OutOfStock')];
        }
        return ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $page->url . '#webpage',
            'url' => $page->url, 'name' => $page->title, 'description' => $page->description,
            'inLanguage' => str_replace('_', '-', $page->locale), 'mainEntity' => $product];
    }

    private function plainText(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
