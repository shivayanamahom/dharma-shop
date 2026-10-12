<?php
/**
 * Диапазон «от — до» (цена, число, диапазон поля): два числовых поля; пределы — из значений каталога; быстрые интервалы цены (price_presets) — ссылки
 * на ту же страницу с границами. Данные: section (name, bounds, value, step, presets [label, url]), module.
 */
\defined('_JEXEC') or die;

$section = $displayData['section'];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$render = static function(string $name) use ($displayData): string {
    $layout=new \Joomla\CMS\Layout\FileLayout('mod_dharmashop_filter.control.'.$name);
    $layout->addIncludePath(JPATH_ROOT.'/modules/mod_dharmashop_filter/layouts');
    return $layout->render($displayData);
};
echo $render('range-slider');
echo $render('range-inputs');
?>
<?php if (!empty($section['presets'])) : ?>
    <div class="d-flex flex-wrap gap-1 mt-2">
        <?php foreach ($section['presets'] as $preset) : ?>
            <a<?php echo (int) \Joomla\CMS\Component\ComponentHelper::getParams('com_dharmashop')->get('filter_nofollow',1) ? ' rel="nofollow"' : ''; ?> class="badge text-bg-light border text-decoration-none fw-normal" href="<?php echo $escape($preset['url']); ?>"><?php echo $escape($preset['label']); ?></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
