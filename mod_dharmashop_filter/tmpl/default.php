<?php
/**
 * Фильтр товаров (C84): GET-форма на страницу списка; работает без JavaScript. Разделы выводятся из настроек модуля, значения управления —
 * макеты `layouts/mod_dharmashop_filter/control/*` (переопределяются в шаблоне сайта: templates/<шаблон>/html/layouts/mod_dharmashop_filter/control/).
 * Весь шаблон можно заменить переопределением модуля: templates/<шаблон>/html/mod_dharmashop_filter/default.php.
 * Только классы Bootstrap, без inline-скриптов и стилей; сворачивание групп — элемент <details>, без JavaScript.
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
// Макеты значений лежат в modules/mod_dharmashop_filter/layouts; переопределение — templates/<шаблон>/html/layouts/mod_dharmashop_filter/control/<имя>.php.
$layout = static function (string $name, array $data): string {
    $file = new FileLayout('mod_dharmashop_filter.control.' . $name);
    $file->addIncludePath(JPATH_ROOT . '/modules/mod_dharmashop_filter/layouts');

    return $file->render($data);
};
?>
<form action="<?php echo $escape($filter['action']); ?>" method="get" class="dharmaFilter dharmaFilter-<?php echo $horizontal ? 'horizontal' : 'vertical'; ?> <?php echo $sfx; ?>" id="dharmashop-filter-<?php echo $id; ?>" data-dharmashop-filter>
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
                    <details<?php echo $section['expanded'] ? ' open' : ''; ?>>
                        <summary class="fw-semibold py-1"><?php echo $escape($section['title']); ?></summary>
                        <div class="pt-2 pb-1"><?php echo $body; ?></div>
                    </details>
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
</form>
