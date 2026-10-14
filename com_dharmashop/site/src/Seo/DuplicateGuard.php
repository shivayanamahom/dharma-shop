<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Seo;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Защита от дублей адресов (C96): страница каталога с лишними параметрами (`?foo=1`, `?fbclid=…` без метки в списке,
 * пустые `?sort=`) — тот же документ под другим адресом. При включённой настройке такой запрос постоянно
 * перенаправляется на тот же адрес без лишнего. Метки аналитики и рекламы (`utm_*`, `yclid`…) из списка «допустимых»
 * не убираются: редирект стёр бы их до того, как счётчик прочитает адрес. Canonical страниц от защиты не зависит —
 * представления строят его по маршруту всегда.
 *
 * Параметры страницы (сортировка, вид, фильтр, поиск, навигация) известны классу и допустимы всегда; остальное допустимо
 * только по списку из настроек (`duplicates_protection_allowed`).
 */
final class DuplicateGuard
{
    /** Допустимые метки по умолчанию (значение настройки до её сохранения). */
    public const TOLERATED_DEFAULT = "utm_*\n_ym_*\n_openstat\nyclid\nysclid\ngclid\nfbclid\nerid\ntmpl";

    /** Параметры маршрута: их собственная обработка у Joomla, поэтому защита их не касается. */
    private const ROUTE = ['Itemid'];

    /** Собственные параметры страниц по виду: точное совпадение, регистр важен (как у `getCmd()`). */
    private const OWN = [
        'listing' => ['start', 'limitstart', 'sort', 'product_view', 'filter', 'filter_module', 'q'],
        'list' => ['start', 'limitstart'],
        'product' => [],
    ];

    /**
     * Перенаправляет запрос на адрес без лишних параметров, если защита включена. Работает только для GET/HEAD страниц HTML
     * при ЧПУ; адреса вида `?option=…&view=…` обрабатываются прежним перенаправлением на ЧПУ.
     *
     * @param string $kind `listing` — списки товаров (категория, бренд, бейдж, поиск, посадочная); `list` — списки категорий, брендов,
     *                     бейджей; `product` — страница товара.
     */
    public static function enforce(string $kind): void
    {
        $settings = ComponentHelper::getParams('com_dharmashop');

        if ((int) $settings->get('duplicates_protection', 0) !== 1 || !isset(self::OWN[$kind])) {
            return;
        }

        $app = Factory::getApplication();
        $input = $app->getInput();
        $format = $input->getCmd('format', '');

        if (!in_array($input->getMethod(), ['GET', 'HEAD'], true) || ($format !== '' && $format !== 'html') || !(bool) $app->get('sef')) {
            return;
        }

        $uri = Uri::getInstance();
        $query = (string) $uri->getQuery();

        if ($query === '') {
            return;
        }

        $clean = self::clean($query, array_merge(self::ROUTE, self::OWN[$kind]), self::patterns((string) $settings->get('duplicates_protection_allowed', self::TOLERATED_DEFAULT)));

        // Служебный адрес Joomla (`option`, `view`) — это не дубль ЧПУ, а ещё не приведённый к ЧПУ запрос: им занимается представление.
        if ($clean === null || self::hasRouteVars($query)) {
            return;
        }

        $app->redirect($uri->toString(['path']) . ($clean !== '' ? '?' . $clean : ''), 301);
    }

    /**
     * Убирает из строки запроса лишнее. Порядок и кодирование оставшихся частей сохраняются как в запросе (массивы
     * `filter[f][1][]=…` не пересобираются). Возвращает новую строку или `null`, если менять нечего.
     *
     * @param string[] $own      собственные параметры страницы (точное совпадение)
     * @param string[] $patterns допустимые шаблоны из настроек (см. {@see matches()})
     */
    public static function clean(string $query, array $own, array $patterns): ?string
    {
        $kept = [];
        $changed = false;

        foreach (explode('&', $query) as $piece) {
            if ($piece === '') {
                continue;
            }

            $parts = explode('=', $piece, 2);
            $name = urldecode($parts[0]);
            $bracket = strpos($name, '[');
            $top = $bracket === false ? $name : substr($name, 0, $bracket);
            $known = $top !== '' && (in_array($top, $own, true) || self::matches($top, $patterns));
            $value = trim(urldecode($parts[1] ?? ''));
            // Пустое значение скалярного параметра (`?sort=`) ничего не задаёт; массивы проверяют сами представления.
            $empty = $bracket === false && $value === '';
            // Первая страница — адрес без `start`: убираем здесь, чтобы не было второго перенаправления.
            $first = $bracket === false && ($top === 'start' || $top === 'limitstart') && (int) $value === 0;

            if (!$known || $empty || $first) {
                $changed = true;

                continue;
            }

            $kept[] = $piece;
        }

        return $changed ? implode('&', $kept) : null;
    }

    /** Строка настройки → список шаблонов (по одному в строке, без пустых строк и шаблонов из одних `*`). */
    public static function patterns(string $text): array
    {
        $patterns = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = strtolower(trim($line));

            if ($line !== '' && trim($line, '*') !== '') {
                $patterns[] = $line;
            }
        }

        return array_values(array_unique($patterns));
    }

    /**
     * Имя совпадает с одним из шаблонов без учёта регистра: `имя` — точно, `имя*` — начинается, `*имя` — заканчивается,
     * `*имя*` — содержит.
     *
     * @param string[] $patterns
     */
    public static function matches(string $name, array $patterns): bool
    {
        $name = strtolower($name);

        foreach ($patterns as $pattern) {
            $needle = trim($pattern, '*');

            if ($needle === '') {
                continue;
            }

            $starts = str_starts_with($pattern, '*');
            $ends = str_ends_with($pattern, '*');

            if (($starts && $ends && str_contains($name, $needle))
                || ($ends && !$starts && str_starts_with($name, $needle))
                || ($starts && !$ends && str_ends_with($name, $needle))
                || (!$starts && !$ends && $name === $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function hasRouteVars(string $query): bool
    {
        foreach (explode('&', $query) as $piece) {
            $name = strtolower(urldecode(explode('=', $piece, 2)[0]));

            if ($name === 'option' || $name === 'view') {
                return true;
            }
        }

        return false;
    }
}
