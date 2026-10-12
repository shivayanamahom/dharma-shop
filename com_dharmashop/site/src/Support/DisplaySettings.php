<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Общая логика наследования настроек отображения: явно заданные значения переопределяют базовые. */
final class DisplaySettings
{
    /**
     * Применяет значения источника к целевым параметрам, пропуская пустые значения и нулевые маркеры «наследовать».
     *
     * @param list<string> $keys
     */
    public static function merge(Registry $target, Registry $source, array $keys): void
    {
        foreach ($keys as $key) {
            $value = $source->get($key);

            if ($value !== null && $value !== '') {
                $target->set($key, $value);
            }
        }
    }
}
