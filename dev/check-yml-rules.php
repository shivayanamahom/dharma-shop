<?php
/** Focused YML offer validation checks; no Joomla installation or catalog writes. */
declare(strict_types=1);

define('_JEXEC', true);
$root = dirname(__DIR__);
require $root.'/com_dharmashop_export/admin/src/Export/FormatInterface.php';
require $root.'/com_dharmashop_export/admin/src/Export/Cells.php';
require $root.'/plg_dharmashopexport_yml/src/Feed.php';

use Dharma\Plugin\DharmaShopExport\YML\Feed;

$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$checks;
    echo "PASS {$message}\n";
};
$issues = [];
$context = [
    'settings' => [],
    'categories' => [['id' => 1]],
    'schema' => [],
    'issue' => static function (string $reason, int $id) use (&$issues): void {
        $issues[$reason][] = $id;
    },
];
$base = [
    'public' => 1,
    'category_id' => 1,
    'title' => 'Test product',
    'price' => 90,
    'base_price' => 100,
    'brand' => 'Brand',
    'sku' => 'SKU',
    'url' => 'https://shop.test/product',
    'gallery' => ['https://shop.test/image.jpg'],
    'introtext' => 'Product description',
    'in_stock' => 1,
];
$rows = [];
foreach ([
    ['id' => 1, 'introtext' => ''],
    ['id' => 2, 'price' => 99.58],
    ['id' => 3, 'base_price' => 100.25],
    ['id' => 4],
    ['id' => 5, 'price' => 20],
] as $changes) {
    $rows[] = array_replace($base, $changes);
}
$feed = new Feed();
$stream = fopen('php://temp', 'w+b');
$written = $feed->rows($stream, $rows, $context);
rewind($stream);
$xml = stream_get_contents($stream);
fclose($stream);

$assert($written === 4 && !str_contains($xml, 'id="1"'), 'Missing required description skips the offer');
$assert(in_array(2, $issues['COM_DHARMASHOP_EXPORT_YML_OLDPRICE_INVALID'] ?? [], true), 'Discount below five percent is reported');
$assert(in_array(3, $issues['COM_DHARMASHOP_EXPORT_YML_OLDPRICE_INVALID'] ?? [], true), 'Fractional old price is reported');
$assert(in_array(5, $issues['COM_DHARMASHOP_EXPORT_YML_OLDPRICE_INVALID'] ?? [], true), 'Discount above 75 percent is reported');
$assert(substr_count($xml, '<oldprice>100</oldprice>') === 1, 'Only an eligible old price is emitted as an integer');

$stream = fopen('php://temp', 'w+b');
try {
    $strict = $context;
    $strict['settings'] = ['yml_strict' => 1];
    $feed->rows($stream, [$rows[0]], $strict);
    $assert(false, 'Strict mode rejects a missing required description');
} catch (RuntimeException) {
    $assert(true, 'Strict mode rejects a missing required description');
} finally {
    fclose($stream);
}

echo "{$checks} YML checks passed.\n";
