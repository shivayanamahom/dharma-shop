<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Shipping;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/** Типы доставки регистрируются включёнными плагинами группы dharmashopshipping; произвольные файлы не ищутся. */
final class TypeRegistry
{
    /** @var array<string, TypeInterface> */
    private array $types = [];

    private bool $sealed = false;

    private static ?self $instance = null;

    public static function current(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $registry   = new self();
        $dispatcher = Factory::getApplication()->getDispatcher();
        PluginHelper::importPlugin('dharmashopshipping', null, true, $dispatcher);
        $dispatcher->dispatch('onDharmaShopShippingTypesCollect', new Event('onDharmaShopShippingTypesCollect', ['registry' => $registry]));
        $registry->sealed = true;

        return self::$instance = $registry;
    }

    public function register(TypeInterface $type): void
    {
        $id = $type->id();

        if ($this->sealed || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $id) || isset($this->types[$id]) || !is_file($type->settingsPath())) {
            throw new \RuntimeException('Invalid or duplicate Dharma Shop shipping type: ' . $id);
        }

        $this->types[$id] = $type;
    }

    /** @return array<string, TypeInterface> */
    public function all(): array
    {
        return $this->types;
    }

    public function find(string $id): ?TypeInterface
    {
        return $this->types[$id] ?? null;
    }
}
