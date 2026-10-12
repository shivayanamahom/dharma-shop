<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Site\Event\LayoutRenderEvent;
use Dharma\Component\DharmaShop\Site\Seo\PageMetadata;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общие точки расширения макетов: никаких запросов каталога или пустых HTML-обёрток. */
final class LayoutHooks
{
    private const EVENTS = [
        'category' => [
            'beforeTitle' => 'onDharmaShopCategoryBeforeTitle',
            'afterTitle' => 'onDharmaShopCategoryAfterTitle',
            'beforeContent' => 'onDharmaShopCategoryBeforeContent',
            'afterContent' => 'onDharmaShopCategoryAfterContent',
        ],
        'product' => [
            'beforeTitle' => 'onDharmaShopProductBeforeTitle',
            'afterTitle' => 'onDharmaShopProductAfterTitle',
            'beforeContent' => 'onDharmaShopProductBeforeContent',
            'afterContent' => 'onDharmaShopProductAfterContent',
            'beforeGallery' => 'onDharmaShopProductBeforeGallery',
            'afterGallery' => 'onDharmaShopProductAfterGallery',
            'beforeIntro' => 'onDharmaShopProductBeforeIntro',
            'afterIntro' => 'onDharmaShopProductAfterIntro',
            'beforeIntroFields' => 'onDharmaShopProductBeforeIntroFields',
            'afterIntroFields' => 'onDharmaShopProductAfterIntroFields',
            'beforeBuy' => 'onDharmaShopProductBeforeBuy',
            'afterBuy' => 'onDharmaShopProductAfterBuy',
            'beforePrice' => 'onDharmaShopProductBeforePrice',
            'afterPrice' => 'onDharmaShopProductAfterPrice',
            'beforeActions' => 'onDharmaShopProductBeforeActions',
            'afterActions' => 'onDharmaShopProductAfterActions',
            'beforeTabs' => 'onDharmaShopProductBeforeTabs',
            'afterTabs' => 'onDharmaShopProductAfterTabs',
            'beforeDescription' => 'onDharmaShopProductBeforeDescription',
            'afterDescription' => 'onDharmaShopProductAfterDescription',
            'tabs' => 'onDharmaShopProductTabs',
            'galleryActions' => 'onDharmaShopProductGalleryActions',
        ],
        'productCard' => [
            'mediaActions' => 'onDharmaShopProductCardMediaActions',
            'actions' => 'onDharmaShopProductCardActions',
        ],
    ];

    public function __construct(
        private readonly string $entity,
        private readonly string $view,
        private readonly object $item,
        private readonly Registry $params,
        private readonly array $items = [],
        private readonly ?PageMetadata $page = null,
    ) {
        if (!isset(self::EVENTS[$entity])) {
            throw new \InvalidArgumentException('Unknown Dharma Shop layout entity: ' . $entity);
        }
    }

    public function render(string $position, array $data = []): string
    {
        $name = self::EVENTS[$this->entity][$position] ?? null;
        if ($name === null) {
            throw new \InvalidArgumentException('Unknown Dharma Shop layout position: ' . $position);
        }
        $app = Factory::getApplication();
        if (!$app->isClient('site') || !$app->getDocument() instanceof HtmlDocument) {
            return '';
        }
        PluginHelper::importPlugin('dharmashop');
        $event = new LayoutRenderEvent($name, [
            'entity' => $this->entity,
            'view' => $this->view,
            'position' => $position,
            'item' => $this->item,
            'items' => $this->items,
            'params' => clone $this->params,
            'page' => $this->page,
            'data' => $data,
        ]);
        $app->getDispatcher()->dispatch($name, $event);

        return implode('', $event->getArgument('result', []));
    }
}
