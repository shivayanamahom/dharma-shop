<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Promotion;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;

\defined('_JEXEC') or die;

/** Реестр типов, расширяемый только включёнными плагинами. */
final class TypeRegistry
{
    /** @var array<string, PromotionTypeInterface> */
    private array $types = [];
    private bool $sealed = false;
    private static ?self $instance = null;

    public static function current(): self
    {
        if (self::$instance !== null) return self::$instance;
        $registry = new self();
        $dispatcher = Factory::getApplication()->getDispatcher();
        PluginHelper::importPlugin('dharmashoppromotion', null, true, $dispatcher);
        $dispatcher->dispatch('onDharmaShopPromotionTypesCollect', new Event('onDharmaShopPromotionTypesCollect', ['registry' => $registry]));
        $registry->sealed = true;
        return self::$instance = $registry;
    }

    public function register(PromotionTypeInterface $type): void
    {
        $id = $type->id();
        if ($this->sealed || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $id) || isset($this->types[$id]) || !is_file($type->settingsPath())) {
            throw new \RuntimeException('Invalid or duplicate Dharma Shop promotion type: ' . $id);
        }
        $this->types[$id] = $type;
    }

    /** @return array<string, PromotionTypeInterface> */
    public function all(): array { return $this->types; }
    public function find(string $id): ?PromotionTypeInterface { return $this->types[$id] ?? null; }
}
