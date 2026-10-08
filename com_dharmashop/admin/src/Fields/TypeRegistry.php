<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Fields;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
\defined('_JEXEC') or die;
/** Explicit registration from enabled providers; no arbitrary PHP file discovery. */
final class TypeRegistry
{
    private array $types = [];
    private bool $sealed = false;
    private static ?self $instance = null;
    public static function current(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        $registry = new self();
        $dispatcher = Factory::getApplication()->getDispatcher();
        PluginHelper::importPlugin('dharmashopfield', null, true, $dispatcher);
        $dispatcher->dispatch('onDharmaShopFieldTypesCollect', new Event('onDharmaShopFieldTypesCollect', ['registry' => $registry]));
        $registry->sealed = true;
        return self::$instance = $registry;
    }
    public function register(TypeInterface $type): void
    {
        $id = $type->id();
        if ($this->sealed || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $id) || isset($this->types[$id])) {
            throw new \RuntimeException('Invalid or duplicate Dharma Shop field type: ' . $id);
        }
        if (!in_array($type->store(), ['option', 'number', 'string', 'text', 'range', 'media'], true) || !is_file($type->settingsPath())) {
            throw new \RuntimeException('Invalid Dharma Shop field type definition: ' . $id);
        }
        $this->types[$id] = $type;
    }
    public function all(): array { return $this->types; }
    public function get(string $id): TypeInterface
    {
        return $this->types[$id] ?? throw new \InvalidArgumentException('Unavailable Dharma Shop field type: ' . $id);
    }
}
