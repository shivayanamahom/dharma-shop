<?php
/**
 * Проверки модели прав доступа (без базы и без входа в админку):
 *  1) каждое действие, которое проверяет код (`authorise('…')`), объявлено в `access.xml`, и наоборот — объявленные `dharmashop.*` где-то проверяются;
 *  2) контроллеры полей, групп полей, групп товаров защищены трейтом `RequiresActionTrait` с нужным действием; представления тоже проверяют право;
 *  3) миграция прав при обновлении (`AclMigration`) переносит группы прежних общих прав в новые отдельные и не меняет заданное;
 *  4) пункты бокового меню указывают действия, объявленные в `access.xml`.
 * Использование: php dev/acl-checks.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    $ok ? ++$pass : ++$fail;
};

// --- объявленные действия
$xml = simplexml_load_file($root . '/com_dharmashop/admin/access.xml');
$declared = [];

foreach ($xml->section as $section) {
    foreach ($section->action as $action) {
        $declared[(string) $action['name']] = true;
    }
}

// --- используемые действия
$used = [];
$dirs = [$root . '/com_dharmashop/admin/src', $root . '/com_dharmashop/site/src', $root . '/com_dharmashop/admin/tmpl', $root . '/com_dharmashop/site/tmpl',
    $root . '/plg_system_dharmashopmenu/src', $root . '/plg_task_dharmashop/src'];
$files = [];

foreach ($dirs as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}

$unknown = [];

foreach ($files as $path) {
    $source = (string) file_get_contents($path);

    if (preg_match_all("/authorise\(\s*'((?:core|dharmashop)\.[a-z.]+)'/", $source, $matches)) {
        foreach ($matches[1] as $action) {
            $used[$action] = true;

            if (!isset($declared[$action])) {
                $unknown[$action . ' (' . basename($path) . ')'] = true;
            }
        }
    }
}

// действия, заданные строкой в таблицах (меню, трейт)
foreach ([$root . '/plg_system_dharmashopmenu/src/Extension/DharmaShopMenu.php', $root . '/com_dharmashop/admin/src/Controller'] as $target) {
    $list = is_dir($target) ? glob($target . '/*.php') : [$target];

    foreach ($list as $path) {
        if (preg_match_all("/'((?:dharmashop)\.[a-z]+\.[a-z]+|core\.(?:manage|admin|edit|create|delete))'/", (string) file_get_contents($path), $matches)) {
            foreach ($matches[1] as $action) {
                $used[$action] = true;

                if (!isset($declared[$action])) {
                    $unknown[$action . ' (' . basename($path) . ')'] = true;
                }
            }
        }
    }
}

$check('все проверяемые в коде действия объявлены в access.xml' . ($unknown ? ' — нет: ' . implode(', ', array_keys($unknown)) : ''), !$unknown);
$dead = array_filter(array_keys($declared), static fn (string $name): bool => str_starts_with($name, 'dharmashop.') && !isset($used[$name]));
$check('каждое объявленное действие dharmashop.* где-то проверяется' . ($dead ? ' — не используется: ' . implode(', ', $dead) : ''), !$dead);

// --- защита контроллеров и видов
$controllers = [
    'FieldController' => 'dharmashop.fields.manage', 'FieldsController' => 'dharmashop.fields.manage',
    'FieldgroupController' => 'dharmashop.fields.manage', 'FieldgroupsController' => 'dharmashop.fields.manage',
    'ProductgroupController' => 'dharmashop.groups.manage', 'ProductgroupsController' => 'dharmashop.groups.manage',
];
$bad = [];

foreach ($controllers as $name => $action) {
    $source = (string) file_get_contents($root . '/com_dharmashop/admin/src/Controller/' . $name . '.php');

    if (!str_contains($source, 'use RequiresActionTrait;') || !str_contains($source, "return '" . $action . "';")) {
        $bad[] = $name;
    }
}

$check('контроллеры полей, групп полей и групп товаров требуют своё действие (RequiresActionTrait)' . ($bad ? ' — нет: ' . implode(', ', $bad) : ''), !$bad);
$views = ['Fields' => 'dharmashop.fields.manage', 'Field' => 'dharmashop.fields.manage', 'Fieldgroups' => 'dharmashop.fields.manage', 'Fieldgroup' => 'dharmashop.fields.manage',
    'Productgroups' => 'dharmashop.groups.manage', 'Productgroup' => 'dharmashop.groups.manage', 'Statuses' => 'dharmashop.statuses.manage', 'Status' => 'dharmashop.statuses.manage'];
$bad = [];

foreach ($views as $name => $action) {
    if (!str_contains((string) file_get_contents($root . '/com_dharmashop/admin/src/View/' . $name . '/HtmlView.php'), $action)) {
        $bad[] = $name;
    }
}

$check('представления полей, групп и статусов проверяют своё действие' . ($bad ? ' — нет: ' . implode(', ', $bad) : ''), !$bad);

// --- миграция прав
define('_JEXEC', 1);
require_once $root . '/com_dharmashop/admin/src/Installer/AclMigration.php';
use Dharma\Component\DharmaShop\Administrator\Installer\AclMigration;

$before = '{"core.manage":{"6":1},"core.edit":{"6":1,"7":1},"dharmashop.orders.manage":{"6":1,"8":1},"dharmashop.orders.status":{"7":1}}';
$after = json_decode(AclMigration::migrate($before), true);
$check('миграция: поля и группы получают группы права «Править»', ($after['dharmashop.fields.manage'] ?? null) === ['6' => 1, '7' => 1] && ($after['dharmashop.groups.manage'] ?? null) === ['6' => 1, '7' => 1]);
$check('миграция: статусы заказов получают группы прежнего права статусов', ($after['dharmashop.statuses.manage'] ?? null) === ['7' => 1]);
$check('миграция: смена статуса заказа = прежние группы + «Управлять заказами»', ($after['dharmashop.orders.status'] ?? null) === ['7' => 1, '6' => 1, '8' => 1]);
$check('миграция: прежние права не меняются', $after['core.edit'] === ['6' => 1, '7' => 1] && $after['core.manage'] === ['6' => 1] && $after['dharmashop.orders.manage'] === ['6' => 1, '8' => 1]);
$again = AclMigration::migrate(json_encode($after));
$check('миграция: повторный запуск ничего не меняет', $again === json_encode($after));
$custom = '{"core.edit":{"6":1},"dharmashop.statuses.manage":{"7":1},"dharmashop.orders.status":{"9":1}}';
$check('миграция: уже мигрированные и заданные вручную права не перезаписываются', AclMigration::migrate($custom) === $custom);
$check('миграция: пустые и неверные правила не ломаются', AclMigration::migrate('') === '' && AclMigration::migrate('{}') === '{}' && AclMigration::migrate('not json') === 'not json');
$noSource = json_decode(AclMigration::migrate('{"core.manage":{"6":1}}'), true);
$check('миграция: без исходных прав действия записываются пустыми (признак «мигрировано»)', array_key_exists('dharmashop.statuses.manage', $noSource) && $noSource['dharmashop.statuses.manage'] === []);

// --- поведение защиты контроллера (заглушки вместо Joomla)
require_once $root . '/com_dharmashop/admin/src/Controller/RequiresActionTrait.php';
require_once __DIR__ . '/acl-checks-stubs.php';
$run = static function (array $allowed): string {
    $controller = new \DharmaAclStub\Guarded();
    $controller->app = new \DharmaAclStub\App(new \DharmaAclStub\Identity($allowed));

    try {
        return (string) $controller->execute('save');
    } catch (\RuntimeException $error) {
        return 'denied:' . $error->getCode();
    }
};
$check('контроллер: без действия задача отклоняется кодом 403', $run(['core.edit@com_dharmashop']) === 'denied:403');
$check('контроллер: общие права «Править», «Создать» и «Управлять» не заменяют отдельное действие', $run(['core.edit@com_dharmashop', 'core.create@com_dharmashop', 'core.manage@com_dharmashop']) === 'denied:403');
$check('контроллер: с действием задача выполняется', $run(['dharmashop.fields.manage@com_dharmashop']) === 'ran:save');
$check('контроллер: действие на другом активе не засчитывается', $run(['dharmashop.fields.manage@com_other']) === 'denied:403');

// --- меню
$menu = (string) file_get_contents($root . '/plg_system_dharmashopmenu/src/Extension/DharmaShopMenu.php');
preg_match_all("/'(dharmashop\.[a-z]+\.[a-z]+|core\.[a-z.]+)'\],/", $menu, $actions);
$missing = array_filter(array_unique($actions[1]), static fn (string $action): bool => !isset($declared[$action]));
$check('пункты меню указывают объявленные действия' . ($missing ? ' — нет: ' . implode(', ', $missing) : ''), !$missing && count($actions[1]) >= 18);

echo "$pass проверок пройдено, $fail не пройдено\n";
exit($fail ? 1 : 0);
