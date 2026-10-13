<?php
/**
 * Проверки посадочных страниц (C85) на ИЗОЛИРОВАННОЙ копии таблиц магазина (сам магазин не изменяется).
 * Страница с сохранёнными условиями должна давать тот же состав, порядок и итог, что категория с такими же условиями в адресе,
 * в обоих режимах выдачи («товары» и «группы и товары»); ручной список — ровно перечисленные товары в порядке `ordering`;
 * условия посетителя дополняют сохранённые; закрытая страница и закрытая категория-источник дают 404.
 * Условия взяты из тестового сайта (бренд `apple`, поле `fdemo-color`, значение `chernyj`): при другой конфигурации поправьте.
 * Использование: php dev/bench/landing-checks.php
 */
declare(strict_types=1);

require __DIR__ . '/../boot.php';

use Dharma\Component\DharmaShop\Administrator\Catalog\GroupIndex;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterCache;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\{DatabaseFactory, DatabaseInterface};

$db = Factory::getContainer()->get(DatabaseInterface::class);
$config = Factory::getConfig();
$app = Factory::getContainer()->get(Joomla\CMS\Application\SiteApplication::class);
Factory::$application = $app;
$app->loadIdentity(new Joomla\CMS\User\User());
$app->loadLanguage(Factory::getContainer()->get(Joomla\CMS\Language\LanguageFactoryInterface::class)->createLanguage('ru-RU'));
$app->getLanguage()->load('com_dharmashop', JPATH_SITE);
$prefix = 'dlqa_' . bin2hex(random_bytes(3)) . '_';
$bench = (new DatabaseFactory())->getDriver('mysqli', ['host' => $config->get('host'), 'user' => $config->get('user'), 'password' => $config->get('password'), 'database' => $config->get('db'), 'prefix' => $prefix, 'select' => true]);
$main = $db->getPrefix();
$created = [];
$pass = 0;
$fail = 0;
$check = function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n";
    $ok ? ++$pass : ++$fail;
};

try {
    foreach ($db->setQuery("SHOW TABLES LIKE '" . $main . "dharmashop\\_%'")->loadColumn() as $table) {
        $new = $prefix . substr($table, strlen($main));
        $created[] = $new;
        $bench->setQuery("CREATE TABLE `$new` LIKE `$table`")->execute();
        $bench->setQuery("INSERT INTO `$new` SELECT * FROM `$table`")->execute();
    }

    $new = $prefix . 'categories';
    $created[] = $new;
    $bench->setQuery("CREATE TABLE `$new` LIKE `{$main}categories`")->execute();
    $bench->setQuery("INSERT INTO `$new` SELECT * FROM `{$main}categories`")->execute();
    $bench->setQuery('TRUNCATE TABLE #__dharmashop_landing_pages')->execute();
    $bench->setQuery('TRUNCATE TABLE #__dharmashop_landing_products')->execute();
    (new GroupIndex($bench))->rebuild(null);

    $catid = (int) $bench->setQuery("SELECT id FROM #__categories WHERE extension='com_dharmashop' AND alias='smartfony' LIMIT 1")->loadResult();
    $insert = function (array $row) use ($bench): int {
        $object = (object) ($row + ['state' => 1, 'access' => 1, 'created' => gmdate('Y-m-d H:i:s'), 'conditions_hash' => sha1((string) ($row['alias'] ?? ''))]);
        $bench->insertObject('#__dharmashop_landing_pages', $object);

        return (int) $bench->insertid();
    };
    $brand = $insert(['title' => 'QA brand', 'alias' => 'qa-brand', 'catid' => $catid, 'source' => 'filter', 'conditions' => json_encode(['brand' => ['apple']])]);
    $color = $insert(['title' => 'QA color', 'alias' => 'qa-color', 'catid' => 0, 'source' => 'filter', 'conditions' => json_encode(['f' => ['fdemo-color' => ['chernyj']]])]);
    $plain = $insert(['title' => 'QA plain', 'alias' => 'qa-plain', 'catid' => $catid, 'source' => 'filter', 'conditions' => '']);
    $manual = $insert(['title' => 'QA manual', 'alias' => 'qa-manual', 'catid' => 0, 'source' => 'manual', 'conditions' => '']);
    $draft = $insert(['title' => 'QA draft', 'alias' => 'qa-draft', 'catid' => $catid, 'source' => 'filter', 'state' => 0, 'conditions' => '']);
    $restricted = $insert(['title' => 'QA restricted', 'alias' => 'qa-restricted', 'catid' => $catid, 'source' => 'filter', 'access' => 3, 'conditions' => '']);
    $order = [296, 254, 273, 240];

    foreach ($order as $position => $productId) {
        $row = (object) ['landing_id' => $manual, 'product_id' => $productId, 'ordering' => $position];
        $bench->insertObject('#__dharmashop_landing_products', $row);
    }

    $core = $app->bootComponent('com_dharmashop');
    $landing = $app->bootComponent('com_dharmashop_landing');
    $settings = ComponentHelper::getParams('com_dharmashop');
    $ids = static fn (array $items): array => array_map(static fn (object $item): int => (int) $item->id, $items);
    $landingModel = function (int $id, string $mode) use ($landing, $bench, $settings): object {
        $settings->set('category_listing_mode', $mode);
        $model = $landing->getMVCFactory()->createModel('Landing', 'Site', ['ignore_request' => true]);
        $model->setDatabase($bench);
        $model->forLanding($id);
        $model->setListingMode($mode);

        return $model;
    };
    $categoryModel = function (int $scope, string $mode) use ($core, $bench, $settings): object {
        $settings->set('category_listing_mode', $mode);
        $model = $core->getMVCFactory()->createModel('Category', 'Site', ['ignore_request' => true]);
        $model->setDatabase($bench);
        $model->forCategory($scope);
        $model->setListingMode($mode);

        return $model;
    };

    // Состав и порядок: страница = категория с теми же условиями в адресе.
    $cases = [[$brand, $catid, ['brand' => ['apple']]], [$color, 0, ['f' => ['fdemo-color' => ['chernyj']]]], [$plain, $catid, []]];

    foreach (['products', 'groups_products'] as $mode) {
        $diff = 0;

        foreach ($cases as [$pageId, $scope, $filter]) {
            foreach (['order', 'new', 'title', 'price_asc', 'price_desc'] as $sort) {
                foreach ([0, 24] as $start) {
                    FilterCache::forget();
                    $app->getInput()->set('filter', []);
                    $a = $landingModel($pageId, $mode);
                    $left = [$a->getTotal(), $ids($a->getProducts($sort, 24, $start))];
                    $app->getInput()->set('filter', $filter);
                    $b = $categoryModel($scope, $mode);
                    $right = [$b->getTotal(), $ids($b->getProducts($sort, 24, $start))];

                    if ($left !== $right) {
                        ++$diff;
                    }
                }
            }
        }

        $app->getInput()->set('filter', []);
        $check("состав, порядок и итог страницы = категория с теми же условиями (режим $mode; 3 страницы, 5 сортировок, 2 смещения)", $diff === 0);
    }

    // Ручной список.
    foreach (['products', 'groups_products'] as $mode) {
        FilterCache::forget();
        $model = $landingModel($manual, $mode);
        $got = $ids($model->getProducts('order', 24, 0));
        sort($got);
        $expected = $order;
        sort($expected);
        $check("ручной список выводит только перечисленные товары (режим $mode)", $model->getTotal() > 0 && $got === array_values(array_intersect($got, $expected)));
    }

    // Условия посетителя дополняют сохранённые.
    FilterCache::forget();
    $app->getInput()->set('filter', ['f' => ['fdemo-memory' => ['128-gb']]]);
    $extra = $landingModel($color, 'products');
    $both = $extra->getTotal();
    $app->getInput()->set('filter', []);
    $only = $landingModel($color, 'products')->getTotal();
    $app->getInput()->set('filter', ['f' => ['fdemo-color' => ['chernyj'], 'fdemo-memory' => ['128-gb']]]);
    $reference = $categoryModel(0, 'products')->getTotal();
    $app->getInput()->set('filter', []);
    $check('выбор посетителя сужает страницу так же, как условия в адресе категории', $both <= $only && $both === $reference);
    $visitor = $landingModel($color, 'products');
    $app->getInput()->set('filter', ['f' => ['fdemo-memory' => ['128-gb']]]);
    $check('адреса и noindex строятся по выбору посетителя без сохранённых условий', $visitor->getVisitorFilterState()->activeCount() === 1 && $visitor->getFilterState()->activeCount() === 2);
    $app->getInput()->set('filter', []);

    // Фиксированные условия страницы (для формы фильтра на странице): только сохранённые, без выбора посетителя.
    $app->getInput()->set('filter', ['f' => ['fdemo-memory' => ['128-gb']]]);
    $fixedModel = $landingModel($brand, 'products');
    $check('условия страницы отдельно от выбора посетителя: фиксированных 1 (бренд), выбранных посетителем 1, вместе 2',
        $fixedModel->getFixedFilterState()->activeCount() === 1 && $fixedModel->getFixedFilterState()->brands !== [] && $fixedModel->getVisitorFilterState()->activeCount() === 1 && $fixedModel->getFilterState()->activeCount() === 2);
    $app->getInput()->set('filter', []);
    $check('у страницы без условий фиксированных условий нет', $landingModel($plain, 'products')->getFixedFilterState()->isEmpty());

    // Редирект со страницы фильтра: поиск страницы по категории и каноническому набору условий.
    $lookup = '\\Dharma\\Component\\DharmaShopLanding\\Site\\Service\\LandingLookup';
    $levels = [1];
    $hashOf = static fn (int $scope, array $conditions): string => sha1($scope . '|' . json_encode($conditions, JSON_UNESCAPED_UNICODE));
    $bench->setQuery("UPDATE #__dharmashop_landing_pages SET conditions_hash = '" . $hashOf($catid, ['brand' => ['apple']]) . "' WHERE id = $brand")->execute();
    $bench->setQuery("UPDATE #__dharmashop_landing_pages SET conditions_hash = '" . $hashOf(0, ['f' => ['fdemo-color' => ['chernyj']]]) . "' WHERE id = $color")->execute();
    $check('страница находится по категории и условиям', $lookup::urlFor($bench, $catid, ['brand' => ['apple']], $levels) !== null
        && $lookup::urlFor($bench, 1, ['f' => ['fdemo-color' => ['chernyj']]], $levels) !== null);
    $check('другие условия или другая категория страницу не находят', $lookup::urlFor($bench, $catid, ['brand' => ['samsung']], $levels) === null
        && $lookup::urlFor($bench, 0, ['brand' => ['apple']], $levels) === null && $lookup::urlFor($bench, $catid, [], $levels) === null);
    $check('закрытая страница и закрытый уровень доступа не находятся', $lookup::urlFor($bench, $catid, ['brand' => ['apple']], [2]) === null);
    $bench->setQuery("UPDATE #__dharmashop_landing_pages SET state = 0 WHERE id = $brand")->execute();
    $check('снятая с публикации страница не находится', $lookup::urlFor($bench, $catid, ['brand' => ['apple']], $levels) === null);
    $bench->setQuery("UPDATE #__dharmashop_landing_pages SET state = 1 WHERE id = $brand")->execute();

    // Виртуальная категория.
    $parent = $landingModel($brand, 'products')->getParent();
    $check('виртуальная категория: область запроса — категория-источник, тексты и SEO — страницы', (int) $parent->id === $catid && (int) $parent->landing_id === $brand && $parent->title === 'QA brand');

    // Вывод страницы: сортировка, вид и режим страницы идут в общие настройки отображения; пустые значения не передаются.
    $shown = $insert(['title' => 'QA display', 'alias' => 'qa-display', 'catid' => $catid, 'source' => 'filter', 'conditions' => '', 'sort' => 'price_desc', 'product_view' => 'list', 'params' => json_encode(['listing_mode' => 'products'])]);
    $displayParams = $landingModel($shown, 'groups_products')->getParent()->params;
    $plainParams = $landingModel($plain, 'groups_products')->getParent()->params;
    $check('страница передаёт свои сортировку, вид и режим вывода в настройки отображения', $displayParams->get('list_ordering') === 'price_desc'
        && $displayParams->get('category_default_view') === 'list' && $displayParams->get('category_listing_mode') === 'products');
    $check('пустые настройки вывода страницы не переопределяют настройки магазина', $plainParams->get('list_ordering') === null && $plainParams->get('category_listing_mode') === null);

    // Доступ.
    foreach ([['снятая с публикации', $draft], ['с закрытым уровнем доступа', $restricted], ['несуществующая', 99999]] as [$label, $pageId]) {
        try {
            $landingModel($pageId, 'products')->getParent();
            $check("страница $label даёт 404", false);
        } catch (RuntimeException $error) {
            $check("страница $label даёт 404", (int) $error->getCode() === 404);
        }
    }

    $bench->setQuery("UPDATE #__categories SET published = 0 WHERE id = $catid")->execute();

    try {
        $landingModel($plain, 'products')->getParent();
        $check('страница с закрытой категорией-источником даёт 404', false);
    } catch (RuntimeException $error) {
        $check('страница с закрытой категорией-источником даёт 404', (int) $error->getCode() === 404);
    }

    echo "$pass проверок пройдено, $fail не пройдено\n";
} finally {
    foreach (array_reverse($created) as $table) {
        $bench->setQuery("DROP TABLE IF EXISTS `$table`")->execute();
    }

    echo "Own isolated tables removed.\n";
}

exit($fail ? 1 : 0);
