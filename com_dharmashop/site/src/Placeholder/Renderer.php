<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Placeholder;

\defined('_JEXEC') or die;

/**
 * Подстановки `{область.имя}` (реестр — docs/SHORTCODES.md). Значение — строка или функция (вычисляется только если код есть в шаблоне).
 * Правила: подстановка однократная (значение не становится новым шаблоном); неизвестный код остаётся как есть;
 * в SEO-шаблоне (`render`) фрагмент между знаками препинания, где значение пустое, вырезается вместе с соседним знаком —
 * «Столы — цены от {min} до {max} | Сайт» без цен даёт «Столы | Сайт», а не «цены от до»;
 * в тексте с HTML (`renderHtml`) значение экранируется, пустое заменяется пустой строкой.
 */
final class Renderer
{
    private const TOKEN = '\{[a-z][a-z0-9_.]*\}';

    private const DELIMITERS = [',', ';', '.', '|', '—', '–', ':', '(', ')', '!', '?'];

    /** @param array<string, string|int|float|callable|null> $values */
    public function render(string $template, array $values): string
    {
        return $this->process($template, $this->resolver($values), false, true);
    }

    /** @param array<string, string|int|float|callable|null> $values */
    public function renderHtml(string $html, array $values): string
    {
        if (!str_contains($html, '{')) {
            return $html;
        }

        // Код подстановки, скопированный из справочника вместе с форматированием (<code>{...}</code>), выводится обычным текстом.
        $html = (string) preg_replace('~<(code|kbd|samp|tt)(?:\s[^>]*)?>\s*(' . self::TOKEN . ')\s*</\1>~iu', '$2', $html);
        $resolve = $this->resolver($values);
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        foreach ($parts as $index => $part) {
            // Теги не трогаем; в текстовых узлах действуют те же правила, значения экранируются, пробелы по краям узла сохраняются.
            if ($part !== '' && $part[0] !== '<' && str_contains($part, '{')) {
                $parts[$index] = $this->process($part, $resolve, true, false);
            }
        }

        return implode('', $parts);
    }

    /**
     * @param callable(string): ?string $resolve
     * @param bool                      $escape     экранировать значения (HTML)
     * @param bool                      $normalize  схлопывать пробелы и обрезать края (одна строка SEO)
     */
    private function process(string $text, callable $resolve, bool $escape, bool $normalize): string
    {
        $items = [];
        $segment = ['type' => 'text', 'text' => '', 'empty' => false];

        foreach (preg_split('/(' . self::TOKEN . '|[,;.|—–:()!?])/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            if (in_array($piece, self::DELIMITERS, true)) {
                $items[] = $segment;
                $items[] = ['type' => 'delimiter', 'text' => $piece];
                $segment = ['type' => 'text', 'text' => '', 'empty' => false];
                continue;
            }

            if (preg_match('/^' . self::TOKEN . '$/', $piece)) {
                $value = $resolve(substr($piece, 1, -1));

                if ($value === null) {
                    $segment['text'] .= $piece;
                } elseif ($value === '') {
                    $segment['empty'] = true;
                } else {
                    $segment['text'] .= $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
                }

                continue;
            }

            $segment['text'] .= $piece;
        }

        $items[] = $segment;
        $remove = [];

        foreach ($items as $index => $item) {
            if ($item['type'] !== 'text' || !$item['empty']) {
                continue;
            }

            $remove[$index] = true;
            $previous = $items[$index - 1] ?? null;
            $next = $items[$index + 1] ?? null;

            if ($previous && $next && $previous['text'] === '(' && $next['text'] === ')') {
                // Пустое содержимое скобок: убираем скобки целиком.
                $remove[$index - 1] = $remove[$index + 1] = true;
            } elseif ($previous && $previous['type'] === 'delimiter' && empty($remove[$index - 1])) {
                $remove[$index - 1] = true;
            } elseif ($next && $next['type'] === 'delimiter') {
                $remove[$index + 1] = true;
            }
        }

        $result = '';

        foreach ($items as $index => $item) {
            if (empty($remove[$index])) {
                $result .= $item['text'];
            }
        }

        if (!$normalize) {
            return $result;
        }

        $result = (string) preg_replace('/\s+/u', ' ', $result);

        if ($remove) {
            // После вырезания фрагмента вокруг «|» и «—» должны остаться пробелы.
            $result = (string) preg_replace(['/(\S)([|—])/u', '/([|—])(\S)/u'], ['$1 $2', '$1 $2'], $result);
        }

        return trim($result);
    }

    /** @return callable(string): ?string null — код неизвестен */
    private function resolver(array $values): callable
    {
        $cache = [];

        return static function (string $name) use ($values, &$cache): ?string {
            if (!array_key_exists($name, $values)) {
                return null;
            }

            if (!array_key_exists($name, $cache)) {
                $value = $values[$name];
                $cache[$name] = trim((string) (is_callable($value) ? $value() : $value));
            }

            return $cache[$name];
        };
    }
}
