<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Placeholder;

\defined('_JEXEC') or die;

/** Однократные подстановки: неизвестные коды сохраняются, значение не становится новым шаблоном. */
final class Renderer
{
    public function render(string $template, array $values): string
    {
        $replace = [];
        foreach ($values as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }

        return strtr($template, $replace);
    }
}
