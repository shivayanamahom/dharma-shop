<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Service;

use Dharma\Component\DharmaShop\Administrator\Cart\CartService;
use Dharma\Component\DharmaShop\Administrator\Customer\CustomerService;
use Dharma\Component\DharmaShop\Administrator\Order\OrderService;
use Dharma\Component\DharmaShop\Administrator\Order\OrderStatusService;
use Dharma\Component\DharmaShop\Administrator\Notification\NotificationService;
use Dharma\Component\DharmaShop\Administrator\Catalog\GalleryRepository;
use Dharma\Component\DharmaShop\Administrator\Catalog\Indexer;
use Dharma\Component\DharmaShop\Administrator\Catalog\ProductRebuilder;
use Dharma\Component\DharmaShop\Administrator\Catalog\VariantRepository;
use Dharma\Component\DharmaShop\Administrator\Image\ImageWebpMigration;
use Dharma\Component\DharmaShop\Administrator\Maintenance\MaintenanceService;
use Dharma\Component\DharmaShop\Administrator\Order\OrderRules;
use Dharma\Component\DharmaShop\Administrator\Pricing\MoneyFormatter;
use Dharma\Component\DharmaShop\Administrator\Pricing\PriceResolver;
use Dharma\Component\DharmaShop\Administrator\Pricing\SaleScheduler;
use Dharma\Component\DharmaShop\Administrator\Stock\StockService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/**
 * Службы ядра, общие для админки, сайта, консоли и плагинов:
 *
 *     $shop = Factory::getApplication()->bootComponent('com_dharmashop')->getServices();
 */
final class ShopServices
{
    private ?Indexer $indexer = null;

    private ?ProductRebuilder $rebuilder = null;

    private ?VariantRepository $variants = null;

    private ?PriceResolver $prices = null;

    private ?StockService $stock = null;

    private ?MoneyFormatter $money = null;

    private ?GalleryRepository $gallery = null;

    private ?SaleScheduler $sales = null;

    private ?MaintenanceService $maintenance = null;

    private ?ImageWebpMigration $imageWebpMigration = null;

    private ?OrderRules $orderRules = null;

    private ?CartService $cart = null;
    private ?CustomerService $customers = null;
    private ?OrderService $orders = null;
    private ?NotificationService $notifications = null;
    private ?OrderStatusService $orderStatuses = null;

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    public function indexer(): Indexer
    {
        return $this->indexer ??= new Indexer($this->db);
    }

    public function rebuilder(): ProductRebuilder
    {
        return $this->rebuilder ??= new ProductRebuilder($this->db);
    }

    public function prices(): PriceResolver
    {
        return $this->prices ??= new PriceResolver();
    }

    public function variants(): VariantRepository
    {
        return $this->variants ??= new VariantRepository($this->db, $this->prices());
    }

    public function stock(): StockService
    {
        return $this->stock ??= new StockService($this->db);
    }

    public function gallery(): GalleryRepository
    {
        return $this->gallery ??= new GalleryRepository($this->db);
    }

    public function money(): MoneyFormatter
    {
        return $this->money ??= new MoneyFormatter(ComponentHelper::getParams('com_dharmashop'));
    }

    public function sales(): SaleScheduler
    {
        return $this->sales ??= new SaleScheduler($this->db, $this->rebuilder(), $this->indexer());
    }

    public function maintenance(): MaintenanceService
    {
        return $this->maintenance ??= new MaintenanceService($this->db, $this->indexer(), $this->rebuilder(), $this->sales(), $this->orderRules(), $this->imageWebpMigration());
    }

    public function imageWebpMigration(): ImageWebpMigration
    {
        return $this->imageWebpMigration ??= new ImageWebpMigration($this->db, JPATH_ROOT);
    }

    public function cart(): CartService
    {
        return $this->cart ??= new CartService($this->db, $this->orderRules());
    }

    public function orderRules(): OrderRules
    {
        return $this->orderRules ??= new OrderRules($this->db, ComponentHelper::getParams('com_dharmashop'));
    }

    public function customers(): CustomerService
    {
        return $this->customers ??= new CustomerService($this->db);
    }

    public function orders(): OrderService
    {
        return $this->orders ??= new OrderService($this->db, $this->cart(), $this->customers(), $this->notifications());
    }

    public function notifications(): NotificationService
    {
        return $this->notifications ??= new NotificationService($this->db);
    }

    public function orderStatuses(): OrderStatusService
    {
        return $this->orderStatuses ??= new OrderStatusService($this->db, $this->notifications());
    }
}
