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
        if (!$app->isClient('site') || !in_array($app->getInput()->getCmd('option'), ['com_dharmashop', 'com_dharmashop_landing'], true)
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
        $currency = $content->currency;
        $product = ['@type' => 'Product', '@id' => $page->url . '#product', 'url' => $page->url,
            'name' => $this->plainText($content->name)];
        foreach (['sku' => $content->sku, 'description' => $content->description] as $key => $value) {
            if ($value !== '') {
                $product[$key] = $this->plainText($value);
            }
        }
        // Все фото товара (основное первым); без галереи — обложка.
        $images = array_values(array_unique(array_filter($content->images, static fn (string $url): bool => $url !== '')));
        if (!$images && $content->image !== '') {
            $images = [$content->image];
        }
        if ($images) {
            $product['image'] = count($images) === 1 ? $images[0] : $images;
        }
        if ($content->brand !== '') {
            $product['brand'] = ['@type' => 'Brand', 'name' => $this->plainText($content->brand)];
        }
        if ($content->category !== '') {
            $product['category'] = $this->plainText($content->category);
        }
        // Характеристики товара: название и значение (с единицей измерения), как на странице.
        $properties = [];
        foreach ($content->properties as [$name, $value]) {
            $name = $this->plainText($name);
            $value = $this->plainText($value);
            if ($name !== '' && $value !== '') {
                $properties[] = ['@type' => 'PropertyValue', 'name' => $name, 'value' => $value];
            }
        }
        if ($properties) {
            $product['additionalProperty'] = $properties;
        }
        if ($content->price !== null && is_numeric($content->price) && (float) $content->price >= 0 && preg_match('/^[A-Z]{3}$/D', $currency)) {
            $offer = ['@type' => 'Offer', 'url' => $page->url, 'price' => $content->price, 'priceCurrency' => $currency,
                'availability' => 'https://schema.org/' . ($content->inStock ? 'InStock' : 'OutOfStock'),
                'itemCondition' => 'https://schema.org/NewCondition'];
            if ($page->siteName !== '') {
                $offer['seller'] = ['@type' => 'Organization', 'name' => $page->siteName];
            }
            // Скидка: зачёркнутая цена (ListPrice) и дата окончания акции.
            if ($content->listPrice !== null && is_numeric($content->listPrice) && (float) $content->listPrice > (float) $content->price) {
                $offer['priceSpecification'] = ['@type' => 'UnitPriceSpecification', 'priceType' => 'https://schema.org/ListPrice',
                    'price' => $content->listPrice, 'priceCurrency' => $currency];
            }
            if ($content->priceValidUntil !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $content->priceValidUntil)) {
                $offer['priceValidUntil'] = $content->priceValidUntil;
            }
            $product['offers'] = $offer;
        }
        $entity = $product;
        // Товар из группы выбора: ProductGroup со всеми доступными вариантами (текущий — полным описанием), чтобы поисковик объединил варианты.
        if ($content->group !== null && !empty($content->group['variants'])) {
            $variants = [];
            foreach ((array) $content->group['variants'] as $variant) {
                if ($variant['url'] === $page->url) {
                    // Текущий товар — полное описание плюс значения осей выбора (они могут быть скрыты в характеристиках страницы).
                    $known = array_column((array) ($product['additionalProperty'] ?? []), 'name');
                    foreach ((array) $variant['options'] as $title => $value) {
                        if (!in_array($this->plainText((string) $title), $known, true)) {
                            $product['additionalProperty'][] = ['@type' => 'PropertyValue', 'name' => $this->plainText((string) $title), 'value' => $this->plainText((string) $value)];
                        }
                    }
                    $variants[] = $product;
                    continue;
                }
                $node = ['@type' => 'Product', '@id' => $variant['url'] . '#product', 'url' => $variant['url'], 'name' => $this->plainText((string) $variant['name'])];
                if ($variant['sku'] !== '') {
                    $node['sku'] = $variant['sku'];
                }
                if ($variant['image'] !== '') {
                    $node['image'] = $variant['image'];
                }
                $variantProperties = [];
                foreach ((array) $variant['options'] as $title => $value) {
                    $variantProperties[] = ['@type' => 'PropertyValue', 'name' => $this->plainText((string) $title), 'value' => $this->plainText((string) $value)];
                }
                if ($variantProperties) {
                    $node['additionalProperty'] = $variantProperties;
                }
                if ($variant['price'] !== null && is_numeric($variant['price']) && preg_match('/^[A-Z]{3}$/D', $currency)) {
                    $node['offers'] = ['@type' => 'Offer', 'url' => $variant['url'], 'price' => $variant['price'], 'priceCurrency' => $currency,
                        'availability' => 'https://schema.org/' . ($variant['in_stock'] ? 'InStock' : 'OutOfStock'), 'itemCondition' => 'https://schema.org/NewCondition'];
                }
                $variants[] = $node;
            }
            $entity = ['@type' => 'ProductGroup', '@id' => $page->url . '#productgroup', 'url' => $page->url,
                'name' => $this->plainText((string) $content->group['name']), 'productGroupID' => (string) $content->group['id']];
            if (!empty($content->group['varies_by'])) {
                $entity['variesBy'] = array_values((array) $content->group['varies_by']);
            }
            if ($content->brand !== '') {
                $entity['brand'] = $product['brand'];
            }
            $entity['hasVariant'] = $variants;
        }
        return ['@context' => 'https://schema.org', '@type' => 'WebPage', '@id' => $page->url . '#webpage',
            'url' => $page->url, 'name' => $page->title, 'description' => $page->description,
            'inLanguage' => str_replace('_', '-', $page->locale), 'mainEntity' => $entity];
    }

    private function plainText(string $value): string
    {
        return trim(strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}
