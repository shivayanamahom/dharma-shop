<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\View\Landing;

use Dharma\Component\DharmaShop\Site\View\Category\HtmlView as CategoryHtmlView;
use Dharma\Component\DharmaShop\Site\Model\CategoriesModel;
use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/** Страница = категория для вывода списка (вид ядра), адреса форм и ссылок ведут на саму страницу. */
class HtmlView extends CategoryHtmlView
{
    use LandingRoutes;

    public function display($tpl = null): void
    {
        parent::display($tpl);
        $this->addLandingCrumb();
    }

    /** У страницы нет предков и подкатегорий: последняя «крошка» — она сама, если пункт меню ведёт не прямо на неё. */
    protected function pathwayItems(array $ancestors): array
    {
        return [];
    }

    protected function seoArea(): string
    {
        return 'landing';
    }

    /** Подстановки страницы (`{landing.*}`) плюс прежние `{category.*}` — тексты страниц, написанные с ними, продолжают работать. */
    protected function seoValues(array $ancestors, CategoriesModel $model): array
    {
        $page = $this->getModel()->getPage();
        $page_model = $this->getModel();
        $category = '';

        if ((int) $page->catid > 1) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $category = (string) $db->setQuery($db->createQuery()->select('title')->from($db->quoteName('#__categories'))->where('id = ' . (int) $page->catid))->loadResult();
        }

        return [
            'landing.title' => (string) $page->title,
            'landing.category' => $category,
            'landing.count' => static fn (): string => (string) $page_model->getTotal(),
            'landing.introtext' => static function () use ($page): string {
                $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $page->introtext, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

                return mb_strlen($plain) > 200 ? rtrim(mb_substr($plain, 0, 199)) . '…' : $plain;
            },
        ] + parent::seoValues($ancestors, $model);
    }

    private function addLandingCrumb(): void
    {
        $app = Factory::getApplication();
        $active = $app->getMenu()->getActive();
        $page = $this->getModel()->getPage();
        $own = $active && $active->component === 'com_dharmashop_landing' && ($active->query['view'] ?? '') === 'landing' && (int) ($active->query['id'] ?? 0) === (int) $page->id;

        if (!$own) {
            $app->getPathway()->addItem((string) ($this->item->params->get('meta_title') ?: $page->title), '');
        }
    }
}
