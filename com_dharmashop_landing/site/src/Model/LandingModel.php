<?php

/**
 * Dharma Shop — посадочные страницы (C85).
 *
 * @package    com_dharmashop_landing
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShopLanding\Site\Model;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterService;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterState;
use Dharma\Component\DharmaShop\Site\Model\CategoryModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Посадочная страница как «виртуальная категория»: область запроса — категория-источник страницы (`catid`), условия —
 * сохранённое состояние фильтра плюс выбор посетителя. Выборка, итоги, сортировка, группы, индексы и кэш — общие с категорией
 * (`CategoryModel`), второго запроса к каталогу нет.
 */
class LandingModel extends CategoryModel
{
    private ?int $landingId = null;
    private ?object $page = null;
    private ?object $virtual = null;
    private ?FilterState $landingState = null;
    private ?FilterState $visitorState = null;

    /** Задать страницу кодом вместо номера из адреса. */
    public function forLanding(int $id): static
    {
        $this->landingId = max(0, $id);
        $this->page = $this->virtual = $this->landingState = $this->visitorState = null;

        return $this;
    }

    public function setFilterModule(Registry $params): void
    {
        parent::setFilterModule($params);
        $this->landingState = $this->visitorState = null;
    }

    /** Строка страницы: опубликована и доступна посетителю, иначе 404. */
    public function getPage(): object
    {
        if ($this->page !== null) {
            return $this->page;
        }

        $id = $this->landingId ?? Factory::getApplication()->getInput()->getInt('id');
        $db = $this->getDatabase();
        $page = $id > 0 ? $db->setQuery(
            $db->createQuery()->select('*')->from($db->quoteName('#__dharmashop_landing_pages'))->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject() : null;

        if (!$page || (int) $page->state !== 1 || (int) $page->broken === 1 || !in_array((int) $page->access, $this->getCurrentUser()->getAuthorisedViewLevels(), true)) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_LANDING_NOT_FOUND'), 404);
        }

        // Категория-источник должна быть доступна посетителю: иначе страница вывела бы товары закрытой ветки.
        if ((int) $page->catid > 1) {
            $category = $db->setQuery(
                $db->createQuery()->select('published, access')->from($db->quoteName('#__categories'))
                    ->where($db->quoteName('id') . ' = :c')->where($db->quoteName('extension') . ' = ' . $db->quote('com_dharmashop'))
                    ->bind(':c', $page->catid, ParameterType::INTEGER)
            )->loadObject();

            if (!$category || (int) $category->published !== 1 || !in_array((int) $category->access, $this->getCurrentUser()->getAuthorisedViewLevels(), true)) {
                throw new \RuntimeException(Text::_('COM_DHARMASHOP_LANDING_NOT_FOUND'), 404);
            }
        }

        return $this->page = $page;
    }

    /** Объект, похожий на категорию: `id` — область запроса (категория-источник), тексты и SEO — самой страницы. */
    public function getParent(): object
    {
        if ($this->virtual !== null) {
            return $this->virtual;
        }

        $page = $this->getPage();
        $own = new Registry((string) $page->params);
        // Вывод страницы: сортировка, вид и режим (товары / группы и товары) — как у категории, через общие настройки отображения;
        // пустое значение — наследуется от настроек магазина, пункт меню переопределяет страницу.
        $params = new Registry(array_filter([
            'introtext' => (string) $page->introtext, 'image' => (string) $page->image, 'image_alt' => (string) $page->image_alt,
            'meta_title' => (string) $page->meta_title,
            'list_ordering' => (string) $page->sort, 'category_default_view' => (string) $page->product_view,
            'category_listing_mode' => (string) $own->get('listing_mode', ''),
        ], static fn (string $value): bool => $value !== ''));
        $metadata = new Registry(['robots' => (string) $page->robots]);

        return $this->virtual = (object) [
            'id' => max(0, (int) $page->catid) > 1 ? (int) $page->catid : 0,
            'landing_id' => (int) $page->id,
            'parent_id' => 0,
            'title' => (string) $page->title,
            'alias' => (string) $page->alias,
            'description' => (string) $page->fulltext,
            'metadesc' => (string) $page->meta_description,
            'published' => 1,
            'access' => (int) $page->access,
            'params' => $params,
            'metadata' => $metadata,
        ];
    }

    public function getAncestors(): array
    {
        return [];
    }

    /** На посадочной странице плиток подкатегорий нет. */
    public function getItems(): array
    {
        return [];
    }

    /**
     * Условия страницы + выбор посетителя. Сохранённые условия задают поля страницы; посетитель дополняет их (поля, уже
     * зафиксированные страницей, из формы не показываются, поэтому конфликта не бывает).
     */
    public function getFilterState(): FilterState
    {
        if ($this->landingState !== null) {
            return $this->landingState;
        }

        $page = $this->getPage();
        $base = (string) $page->source === 'manual' ? [] : (array) (json_decode((string) $page->conditions, true) ?: []);
        $service = new FilterService($this->getDatabase());

        // Бренд, бейдж, поле или значение из условий удалены или сняты: без них страница показала бы всю категорию — вместо этого 404.
        if ($base && \Dharma\Component\DharmaShopLanding\Administrator\Maintenance\LandingChecker::conditionsChanged($service, (int) $page->catid, $base)) {
            throw new \RuntimeException(Text::_('COM_DHARMASHOP_LANDING_NOT_FOUND'), 404);
        }

        return $this->landingState = $service->state(self::merge($base, $this->visitorInput()), $this->getFilterDefinition());
    }

    /** Только сохранённые условия страницы: эти поля в форме фильтра не показываются (посетитель их не меняет). */
    public function getFixedFilterState(): FilterState
    {
        $page = $this->getPage();
        $base = (string) $page->source === 'manual' ? [] : (array) (json_decode((string) $page->conditions, true) ?: []);

        return (new FilterService($this->getDatabase()))->state($base, $this->getFilterDefinition());
    }

    /** Только выбор посетителя (без сохранённых условий страницы): адреса форм и страниц, `noindex`, канонический адрес. */
    public function getVisitorFilterState(): FilterState
    {
        return $this->visitorState ??= (new FilterService($this->getDatabase()))->state($this->visitorInput(), $this->getFilterDefinition());
    }

    private function visitorInput(): array
    {
        $input = Factory::getApplication()->getInput()->get('filter', [], 'array');

        return is_array($input) ? $input : [];
    }

    /** Сложение двух массивов условий в формате адреса `filter[…]` (алиасы). */
    public static function merge(array $base, array $extra): array
    {
        unset($extra['search']);
        $out = $base;

        foreach (['brand', 'badge', 'cat'] as $key) {
            if (isset($extra[$key]) || isset($base[$key])) {
                $out[$key] = array_values(array_unique(array_merge((array) ($base[$key] ?? []), (array) ($extra[$key] ?? []))));
            }
        }

        foreach (['f'] as $key) {
            if (isset($extra[$key]) && is_array($extra[$key])) {
                $out[$key] = array_replace((array) ($base[$key] ?? []), $extra[$key]);
            }
        }

        foreach (['price', 'stock', 'sale'] as $key) {
            if (isset($extra[$key])) {
                $out[$key] = $extra[$key];
            }
        }

        return $out;
    }

    /** Ручной список не входит в индексы витрины. */
    protected function listingRestricted(): bool
    {
        return (string) $this->getPage()->source !== 'filter';
    }

    protected function productsQuery(?FilterState $filterOverride = null, bool $forListing = false, array $knownGroups = []): QueryInterface
    {
        $query = parent::productsQuery($filterOverride, $forListing, $knownGroups);
        $page = $this->getPage();

        if ((string) $page->source === 'manual') {
            $query->where('p.id IN (SELECT lp.product_id FROM #__dharmashop_landing_products lp WHERE lp.landing_id = ' . (int) $page->id . ')');
        }

        return $query;
    }
}
