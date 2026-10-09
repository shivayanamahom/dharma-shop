<?php
/**
 * Фильтр товаров (C84): GET-форма на страницу списка; работает без JavaScript. Разделы выводятся из настроек модуля, значения управления —
 * макеты `layouts/mod_dharmashop_filter/control/*` (переопределяются в шаблоне сайта: templates/<шаблон>/html/layouts/mod_dharmashop_filter/control/).
 * Весь шаблон можно заменить переопределением модуля: templates/<шаблон>/html/mod_dharmashop_filter/default.php.
 * Только классы Bootstrap, без inline-скриптов и стилей; сворачивание групп — штатный Bootstrap collapse (accordion), скрипт подключается только при включённых сворачиваемых группах.
 *
 * @var  array<string, mixed>  $filter  orientation, action, hidden, sections, chips, resetUrl, active, total, totalCapped, approximate, showCounts, collapsible
 * @var  \Joomla\Registry\Registry  $params
 * @var  object  $module
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;

if (empty($filter)) {
    return;
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$id = (int) $module->id;
$horizontal = $filter['orientation'] === 'horizontal';
$sfx = $escape(trim((string) $params->get('moduleclass_sfx', '')));
$number = static fn (int $n): string => number_format($n, 0, ',', "\u{00A0}");
$totalText = $number((int) $filter['total']) . ($filter['totalCapped'] ? '+' : '');
$applyText = trim((string) $params->get('apply_text', '')) !== '' ? (string) $params->get('apply_text') : Text::_('MOD_DHARMASHOP_FILTER_APPLY_DEFAULT');
$applyText = str_replace('{count}', $totalText, $applyText);
// Поведение для скрипта (подключается Dispatcher-ом только при включённых AJAX/«сразу»).
$instant = (string) $params->get('apply_mode', 'button') === 'instant';
$dataAttributes = (int) $params->get('ajax', 0) === 1 || $instant
    ? ' data-ajax="' . ((int) $params->get('ajax', 0) === 1 ? 1 : 0) . '" data-apply="' . ($instant ? 'instant' : 'button') . '" data-delay="' . max(200, min(3000, (int) $params->get('instant_delay', 700))) . '"'
    : '';
// Макеты значений лежат в modules/mod_dharmashop_filter/layouts; переопределение — templates/<шаблон>/html/layouts/mod_dharmashop_filter/control/<имя>.php.
$layout = static function (string $name, array $data): string {
    $file = new FileLayout('mod_dharmashop_filter.control.' . $name);
    $file->addIncludePath(JPATH_ROOT . '/modules/mod_dharmashop_filter/layouts');

    return $file->render($data);
};

// Выдвижная панель (Bootstrap offcanvas): на узком экране (или всегда) форма открывается кнопкой; на широком — обычный блок.
$mode = (string) $params->get('offcanvas_mode', 'never');
$offcanvas = in_array($mode, ['mobile', 'always'], true);
$breakpoint = in_array((string) $params->get('offcanvas_breakpoint', 'lg'), ['sm', 'md', 'lg', 'xl'], true) ? (string) $params->get('offcanvas_breakpoint', 'lg') : 'lg';
$panelId = 'dharmashop-filter-panel-' . $id;
$panelClass = ($mode === 'always' ? 'offcanvas' : 'offcanvas-' . $breakpoint) . ' offcanvas-' . ((string) $params->get('offcanvas_position', 'start') === 'end' ? 'end' : 'start');
$buttonClass = $mode === 'always' ? '' : ' d-' . $breakpoint . '-none';
$buttonText = trim((string) $params->get('offcanvas_button_text', '')) !== '' ? (string) $params->get('offcanvas_button_text') : Text::_('MOD_DHARMASHOP_FILTER_OFFCANVAS_BUTTON_DEFAULT');
$showText = trim((string) $params->get('offcanvas_show_text', '')) !== '' ? (string) $params->get('offcanvas_show_text') : Text::_('MOD_DHARMASHOP_FILTER_OFFCANVAS_SHOW_DEFAULT');
$showText = str_replace('{count}', $totalText, $showText);
$live = $dataAttributes !== '';
?>
<?php if ($offcanvas) : ?>
    <button type="button" class="btn btn-outline-primary w-100<?php echo $buttonClass; ?> dharmaFilterToggle" data-bs-toggle="offcanvas" data-bs-target="#<?php echo $panelId; ?>" aria-controls="<?php echo $panelId; ?>">
        <?php echo $escape($buttonText); ?><?php echo $filter['active'] ? ' <span class="badge text-bg-primary">' . (int) $filter['active'] . '</span>' : ''; ?>
    </button>
    <div class="<?php echo $panelClass; ?>" tabindex="-1" id="<?php echo $panelId; ?>" aria-labelledby="<?php echo $panelId; ?>-title">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="<?php echo $panelId; ?>-title"><?php echo $escape($buttonText); ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#<?php echo $panelId; ?>" aria-label="<?php echo $escape(Text::_('JCLOSE')); ?>"></button>
        </div>
        <div class="offcanvas-body">
<?php endif; ?>
<form action="<?php echo $escape($filter['action']); ?>" method="get" class="dharmaFilter dharmaFilter-<?php echo $horizontal ? 'horizontal' : 'vertical'; ?> <?php echo $sfx; ?>" id="dharmashop-filter-<?php echo $id; ?>" data-dharmashop-filter<?php echo $dataAttributes; ?>>
    <?php foreach ($filter['hidden'] as $name => $value) : ?>
        <input type="hidden" name="<?php echo $escape($name); ?>" value="<?php echo $escape($value); ?>">
    <?php endforeach; ?>
    <?php if ($filter['chips']) : ?>
        <div class="d-flex flex-wrap gap-2 mb-3 dharmaFilterChips" aria-label="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_ACTIVE')); ?>">
            <?php foreach ($filter['chips'] as $chip) : ?>
                <a class="badge text-bg-light border text-decoration-none fw-normal" href="<?php echo $escape($chip['url']); ?>" title="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_REMOVE')); ?>"><?php echo $escape($chip['label']); ?> <span aria-hidden="true">&times;</span></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ((int) $params->get('show_result_count', 1) === 1) : ?>
        <div class="small text-body-secondary mb-2 dharmaFilterTotal" role="status" aria-live="polite"><?php echo Text::sprintf('MOD_DHARMASHOP_FILTER_FOUND', $totalText); ?><?php echo $filter['approximate'] ? ' <span title="' . $escape(Text::_('MOD_DHARMASHOP_FILTER_APPROXIMATE')) . '">≈</span>' : ''; ?></div>
    <?php endif; ?>
    <div class="<?php echo $horizontal ? 'row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3' : 'd-flex flex-column'; ?>">
        <?php foreach ($filter['sections'] as $section) :
            $control = (string) $section['control'];
            $body = match ($section['kind']) {
                'range', 'price' => $layout('range', ['section' => $section, 'module' => $id]),
                'stock' => $layout('switch', ['section' => $section, 'module' => $id]),
                default => $layout($control === 'swatches' ? 'buttons' : $control, ['section' => $section, 'module' => $id]),
            };
            ?>
            <div class="<?php echo $horizontal ? 'col' : 'border-bottom py-2'; ?> dharmaFilterSection dharmaFilterSection-<?php echo $escape($section['kind']); ?>">
                <?php if ($filter['collapsible'] && $section['kind'] !== 'stock') : ?>
                    <?php $groupId = 'dharmashop-filter-' . $id . '-g-' . md5((string) $section['name']); ?>
                    <?php // Штатный Bootstrap accordion без родителя: группы сворачиваются независимо (скрипт bootstrap.collapse подключает Dispatcher). ?>
                    <div class="accordion accordion-flush">
                        <div class="accordion-item border-0 bg-transparent">
                            <h3 class="accordion-header fs-6 m-0">
                                <button class="accordion-button px-0 py-1 fw-semibold bg-transparent shadow-none text-body<?php echo $section['expanded'] ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $groupId; ?>" aria-expanded="<?php echo $section['expanded'] ? 'true' : 'false'; ?>" aria-controls="<?php echo $groupId; ?>"><?php echo $escape($section['title']); ?></button>
                            </h3>
                            <div id="<?php echo $groupId; ?>" class="accordion-collapse collapse<?php echo $section['expanded'] ? ' show' : ''; ?>">
                                <div class="pt-2 pb-1"><?php echo $body; ?></div>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <?php if ($section['kind'] !== 'stock') : ?><div class="fw-semibold py-1"><?php echo $escape($section['title']); ?></div><?php endif; ?>
                    <div class="pt-1 pb-1"><?php echo $body; ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ((int) $params->get('show_apply_button', 1) === 1 || ((int) $params->get('show_reset_button', 1) === 1 && $filter['active'])) : ?>
        <div class="d-flex flex-wrap gap-2 mt-3 dharmaFilterActions">
            <?php if ((int) $params->get('show_apply_button', 1) === 1) : ?>
                <button type="submit" class="btn btn-primary bg-primary text-white flex-grow-1"><?php echo $escape($applyText); ?></button>
            <?php endif; ?>
            <?php if ((int) $params->get('show_reset_button', 1) === 1 && $filter['active']) : ?>
                <a class="btn btn-outline-secondary" href="<?php echo $escape($filter['resetUrl']); ?>"><?php echo Text::_('MOD_DHARMASHOP_FILTER_RESET'); ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ($offcanvas && $live) : ?>
        <div class="mt-3 dharmaFilterShow<?php echo $mode === 'always' ? '' : ' d-' . $breakpoint . '-none'; ?>">
            <button type="button" class="btn btn-primary bg-primary text-white w-100" data-bs-dismiss="offcanvas" data-bs-target="#<?php echo $panelId; ?>"><?php echo $escape($showText); ?></button>
        </div>
    <?php endif; ?>
</form>
<?php if ($offcanvas) : ?>
        </div>
    </div>
<?php endif; ?>
