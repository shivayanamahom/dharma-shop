<?php
/**
 * Проверки сборщика данных электронной коммерции (C98): товар, разница корзины, имя контейнера.
 * Поднимает консольный Joomla тестового сайта (dev/boot.php) только для классов; в базу ничего не пишет.
 */
declare(strict_types=1);

require __DIR__ . '/boot.php';
require_once dirname(__DIR__) . '/com_dharmashop/site/src/Analytics/Ecommerce.php';

use Dharma\Component\DharmaShop\Site\Analytics\Ecommerce as E;
use Joomla\Registry\Registry;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
    echo "PASS {$message}\n";
};

// Настройки
$assert(!E::enabled(new Registry()) && !E::enabled(new Registry(['analytics_ecommerce' => 0])) && E::enabled(new Registry(['analytics_ecommerce' => '1'])), 'выключено по умолчанию, включается значением 1');
$assert(E::currency(new Registry([])) === 'RUB' && E::currency(new Registry(['currency_code' => ' eur '])) === 'EUR' && E::currency(new Registry(['currency_code' => ''])) === 'RUB', 'валюта: пробелы, регистр, пустое значение');
$assert(E::container(new Registry([])) === 'dataLayer' && E::container(new Registry(['analytics_container' => 'myLayer'])) === 'myLayer', 'имя контейнера: по умолчанию и своё');
$assert(E::container(new Registry(['analytics_container' => 'a;alert(1)'])) === 'dataLayer' && E::container(new Registry(['analytics_container' => '1x'])) === 'dataLayer', 'имя контейнера: небезопасное заменяется на dataLayer');

// Товар
$p = E::product(7, ' <b>Чайник</b> &amp; крышка ', 1234.5, 2.0, 'Посуда', 'Бренд', '');
$assert($p === ['id' => '7', 'name' => 'Чайник & крышка', 'price' => 1234.5, 'quantity' => 2, 'category' => 'Посуда', 'brand' => 'Бренд'], 'товар: id строкой, название без разметки, количество целым, пустое не передаётся');
$assert(!isset(E::product(7, 'x', null, 0.0)['price']) && !isset(E::product(7, 'x', null, 0.0)['quantity']), 'товар: цена «по запросу» и нулевое количество не передаются');
$assert(E::product(7, 'x', 0.0)['price'] === 0.0, 'товар: нулевая цена («бесплатно») передаётся');
$assert(E::product(7, 'x', 1.0, 1.5)['quantity'] === 1.5, 'товар: дробное количество сохраняется');

// Разница корзины
$line = static fn (int $product, float $qty): array => ['qty' => $qty, 'product' => E::product($product, 'Товар ' . $product, 100.0, 0.0)];
$settings = new Registry(['currency_code' => 'RUB']);
$assert(E::cartChanges([], [], $settings) === null, 'корзина: пусто → пусто, событий нет');
$assert(E::cartChanges([1 => $line(5, 2.0)], [1 => $line(5, 2.0)], $settings) === null, 'корзина: без изменений событий нет');

$add = E::cartChanges([], [10 => $line(5, 1.0)], $settings);
$assert($add !== null && count($add['events']) === 1 && $add['events'][0]['action'] === 'add' && $add['events'][0]['products'][0]['quantity'] === 1 && $add['events'][0]['products'][0]['id'] === '5', 'корзина: новая позиция → add на всё количество');
$assert($add['currency'] === 'RUB' && preg_match('/^[a-f0-9]{12}$/', $add['uid']) === 1, 'корзина: валюта и одноразовый uid в ответе');

$more = E::cartChanges([10 => $line(5, 1.0)], [10 => $line(5, 3.0)], $settings);
$assert($more['events'][0]['action'] === 'add' && $more['events'][0]['products'][0]['quantity'] === 2, 'корзина: количество 1 → 3 — add на разницу (2)');

$less = E::cartChanges([10 => $line(5, 3.0)], [10 => $line(5, 1.5)], $settings);
$assert($less['events'][0]['action'] === 'remove' && $less['events'][0]['products'][0]['quantity'] === 1.5, 'корзина: количество 3 → 1,5 — remove на разницу (1,5)');

$gone = E::cartChanges([10 => $line(5, 2.0), 11 => $line(6, 1.0)], [11 => $line(6, 1.0)], $settings);
$assert(count($gone['events']) === 1 && $gone['events'][0]['action'] === 'remove' && $gone['events'][0]['products'][0]['id'] === '5' && $gone['events'][0]['products'][0]['quantity'] === 2, 'корзина: позиция удалена — remove на всё её количество');

$mix = E::cartChanges([10 => $line(5, 2.0), 11 => $line(6, 1.0)], [10 => $line(5, 1.0), 12 => $line(7, 4.0)], $settings);
$byAction = array_column($mix['events'], 'products', 'action');
$assert(array_keys($byAction) === ['add', 'remove'] && $byAction['add'][0]['id'] === '7' && $byAction['add'][0]['quantity'] === 4
    && count($byAction['remove']) === 2, 'корзина: одно действие — add и remove вместе (очистка/слияние)');

$clear = E::cartChanges([10 => $line(5, 1.0), 11 => $line(6, 2.0)], [], $settings);
$assert($clear['events'][0]['action'] === 'remove' && count($clear['events'][0]['products']) === 2, 'корзина: очистка — remove всех позиций');
$assert(E::cartChanges([1 => $line(5, 2.0)], [1 => $line(5, 2.0004)], $settings) === null, 'корзина: разница меньше 0,001 не событие');

// База: чтение
$assert(E::cartSnapshot(null) === [] && E::categoryTitles([]) === [] && E::categoryTitles([0, -1]) === [], 'снимок и категории: пустой ввод без запроса');
$titles = E::categoryTitles([PHP_INT_MAX]);
$assert($titles === [], 'категории: несуществующий товар — пусто, запрос выполняется');

echo "OK: {$checks}\n";
