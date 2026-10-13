<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Seo;

use Dharma\Component\DharmaShop\Administrator\Seo\SeoSettings;
use Dharma\Component\DharmaShop\Site\Placeholder\Renderer;
use Dharma\Component\DharmaShop\Site\Placeholder\GlobalValues;
use Dharma\Component\DharmaShop\Administrator\Image\ImageUrl;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Один источник SEO для всех макетов и подключаемых плагинов. */
final class PageSeo
{
    public function __construct(private readonly Registry $settings)
    {
    }

    public function resolve(string $area, object $item, array $values, Registry $menu, string $url, ?string $robots = null, int $page = 1, bool $prepareImage = true): PageMetadata
    {
        $app = Factory::getApplication();
        $defaults = new SeoSettings($this->settings);
        $renderer = new Renderer();
        $params = $item->params;
        $values += GlobalValues::build($this->settings);
        // Неразрывные пробелы из форматирования цен в заголовках и описаниях заменяются обычными.
        $text = static fn (string $value): string => trim(str_replace("\u{00A0}", ' ', strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
        $part = function (string $key, string $own, string $menuValue = '') use ($defaults, $renderer, $values, $area, $text): string {
            $templates = [trim($own), trim($menuValue), $defaults->template($area, $key), SeoSettings::builtin($area, $key)];

            // Первый шаблон, который после подстановки не пуст (запись → пункт меню → настройки → встроенный).
            foreach (array_filter($templates, static fn (string $template): bool => $template !== '') as $template) {
                $result = $text($renderer->render($template, $values));

                if ($result !== '') {
                    return $result;
                }
            }

            return '';
        };
        $image = '';
        $imageWidth = $imageHeight = 0;
        foreach ($prepareImage ? [$params->get('meta_image', ''), $item->cover ?? '', $params->get('image', ''), $defaults->defaultImage()] : [] as $candidate) {
            if ($image = ImageUrl::resolve((string) $candidate, 'seo.social')) {
                [$imageWidth, $imageHeight] = ImageUrl::size((string) $candidate, 'seo.social');
                break;
            }
        }
        // Явное значение (например, страница-дубль с параметрами) важнее настроек записи, меню и магазина.
        $robots = $robots ?? (string) ($item->metadata->get('robots') ?: $menu->get('robots') ?: $defaults->robots());

        // Страницы постраничной навигации (со второй) получают уникальные заголовок, описание и h1 (« — страница N»).
        $paged = static fn (string $value): string => $page > 1 && $value !== '' ? $value . ' — ' . Text::sprintf('COM_DHARMASHOP_PAGE_N', $page) : $value;

        return new PageMetadata(
            $paged($part('title', (string) $params->get('meta_title', ''), (string) $menu->get('page_title', ''))),
            $paged($part('description', (string) ($item->metadesc ?? ''), (string) $menu->get('menu-meta_description', ''))),
            $robots,
            $paged($part('h1', (string) $params->get('h1', ''), (string) $menu->get('page_heading', ''))),
            $part('breadcrumb', (string) $params->get('breadcrumb_title', '')),
            $image,
            $url,
            $text((string) $app->get('sitename')),
            str_replace('-', '_', $app->getLanguage()->getTag()),
            imageAlt: $text((string) ($item->title ?? '')), imageWidth: $imageWidth, imageHeight: $imageHeight,
        );
    }

    public function apply(PageMetadata $page, HtmlDocument $document, CollectionMetadata|ProductMetadata|null $content = null): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $document->setTitle($page->title);
        $document->setDescription($page->description);
        $document->setMetaData('robots', $page->robots);
        $links = $document->getHeadData()['links'];
        foreach ($links as $key => $link) {
            if (($link['relation'] ?? '') === 'canonical') {
                unset($links[$key]);
            }
        }
        $document->setHeadData(['links' => $links]);
        $document->addHeadLink(htmlspecialchars($page->url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'canonical');
        PluginHelper::importPlugin('dharmashop');
        $app->getDispatcher()->dispatch('onDharmaShopMetadataReady', new Event('onDharmaShopMetadataReady', [
            'page' => $page, 'document' => $document, 'settings' => clone $this->settings, 'content' => $content,
        ]));
    }
}
