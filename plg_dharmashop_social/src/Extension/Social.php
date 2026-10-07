<?php
declare(strict_types=1);

namespace Dharma\Plugin\DharmaShop\Social\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/** Социальные теги из готового контекста компонента; своих настроек и запросов каталога нет. */
final class Social extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onDharmaShopMetadataReady' => 'addMetadata'];
    }

    public function addMetadata(EventInterface $event): void
    {
        $app = $this->getApplication();
        $document = $event->getArgument('document');
        if (!$app->isClient('site') || $app->getInput()->getCmd('option') !== 'com_dharmashop' || !$document instanceof HtmlDocument) {
            return;
        }
        $page = $event->getArgument('page');
        $settings = $event->getArgument('settings');
        if ((int) $settings->get('seo_opengraph', 1) === 1) {
            $tags = ['og:title' => $page->title, 'og:description' => $page->description, 'og:type' => $page->type,
                'og:url' => $page->url, 'og:site_name' => $page->siteName, 'og:locale' => $page->locale,
                'og:image' => $page->image];
            foreach ($tags as $name => $value) {
                if ($value !== '') {
                    $document->setMetaData($name, $value, 'property');
                }
            }
        }
        if ((int) $settings->get('seo_twitter', 1) === 1) {
            $document->setMetaData('twitter:card', $page->image !== '' ? 'summary_large_image' : 'summary');
            foreach (['twitter:title' => $page->title, 'twitter:description' => $page->description, 'twitter:image' => $page->image] as $name => $value) {
                if ($value !== '') {
                    $document->setMetaData($name, $value);
                }
            }
        }
    }
}
