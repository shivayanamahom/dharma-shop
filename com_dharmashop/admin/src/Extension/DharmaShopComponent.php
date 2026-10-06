<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Extension;

use Dharma\Component\DharmaShop\Administrator\Service\ShopServices;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper as LibraryContentHelper;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Psr\Container\ContainerInterface;

\defined('_JEXEC') or die;

/**
 * Точка входа компонента. Через неё получают службы ядра:
 *
 *     Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
 */
final class DharmaShopComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface
{
    use CategoryServiceTrait;
    use RouterServiceTrait;

    private ?ShopServices $services = null;

    public function boot(ContainerInterface $container): void
    {
        // Журнал компонента: administrator/logs/com_dharmashop.log.php (записи с категорией «com_dharmashop»).
        Log::addLogger(['text_file' => 'com_dharmashop.log.php'], Log::ALL, ['com_dharmashop']);
    }

    public function getServices(): ShopServices
    {
        return $this->services ??= new ShopServices(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Дополнительные поля формы категории по типу: у бренда — сайт, у бейджа — цвет.
     * Типы — это секции категорий Joomla: com_dharmashop (категории), com_dharmashop.brand, com_dharmashop.badge.
     */
    public function prepareForm(Form $form, $data): void
    {
        $extension = (string) (is_array($data) ? ($data['extension'] ?? '') : ($data->extension ?? ''));

        if ($extension === '') {
            $extension = (string) Factory::getApplication()->getInput()->get('extension', '', 'string');
        }

        $file = match ($extension) {
            'com_dharmashop.brand' => 'category_brand',
            'com_dharmashop.badge' => 'category_badge',
            default => null,
        };

        if ($file !== null) {
            $form->loadFile(JPATH_ADMINISTRATOR . '/components/com_dharmashop/forms/' . $file . '.xml', false);
        }
    }

    /** Число товаров в категориях, брендах и бейджах для менеджера категорий (по витринному индексу). */
    public function countItems(array $items, string $section): void
    {
        LibraryContentHelper::countRelations($items, (object) [
            'related_tbl' => 'dharmashop_product_categories',
            'state_col' => 'state',
            'group_col' => 'catid',
            'relation_type' => 'category_or_group',
            'uses_workflows' => false,
        ]);
    }

    protected function getTableNameForSection(?string $section = null): string
    {
        return '#__dharmashop_products';
    }

    protected function getStateColumnForSection(?string $section = null): string
    {
        return 'state';
    }
}
