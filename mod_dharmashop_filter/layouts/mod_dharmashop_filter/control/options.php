<?php
/** Shared accessible choices. No JS: every value is visible. search/more enhance this markup. */
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
$section=$displayData['section']; $module=(int)$displayData['module'];
$escape=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$control=$displayData['control'] ?? $section['control'];
$radio=$control==='radio'; $tiles=in_array($control,['buttons','swatches','images'],true);
$prefix='dharmashop-filter-'.$module.'-'.md5($section['name']);
?>
<div data-filter-options data-limit="<?php echo (int)($section['limit']??0); ?>">
    <?php if (count($section['options'])>=10 && ($section['search']??true)) : ?>
        <label class="visually-hidden" for="<?php echo $prefix; ?>-search"><?php echo Text::_('MOD_DHARMASHOP_FILTER_SEARCH_VALUES'); ?></label>
        <input id="<?php echo $prefix; ?>-search" type="search" class="form-control form-control-sm mb-2 d-none" placeholder="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_SEARCH_VALUES')); ?>" data-filter-search autocomplete="off">
    <?php endif; ?>
    <div class="<?php echo $tiles?'d-flex flex-wrap gap-2':'d-flex flex-column gap-1'; ?>">
        <?php if ($radio) : ?>
            <div class="form-check"><input class="form-check-input" type="radio" name="<?php echo $escape($section['name']); ?>" value="" id="<?php echo $prefix; ?>-any"<?php echo $section['selected']?'':' checked'; ?>><label class="form-check-label" for="<?php echo $prefix; ?>-any"><?php echo Text::_('MOD_DHARMASHOP_FILTER_ANY'); ?></label></div>
        <?php endif; ?>
        <?php foreach($section['options'] as $i=>$option):$id=$prefix.'-'.$i; ?>
            <div class="<?php echo $tiles?'':'form-check'; ?>" data-filter-option data-label="<?php echo $escape($option['label']); ?>">
                <input class="<?php echo $tiles?'btn-check':'form-check-input'; ?>" type="<?php echo $radio?'radio':'checkbox'; ?>" name="<?php echo $escape($section['name']); ?>" value="<?php echo $escape($option['value']); ?>" id="<?php echo $id; ?>"<?php echo $option['checked']?' checked':''; ?><?php echo $option['disabled']?' disabled':''; ?>>
                <label class="<?php echo $tiles?'btn btn-outline-primary btn-sm':'form-check-label'; ?> d-inline-flex align-items-center gap-2<?php echo $option['disabled']?' text-body-secondary':''; ?>" for="<?php echo $id; ?>">
                    <?php if ($control==='swatches' || $control==='images' || ($radio && !empty($section['swatches']))) { echo LayoutHelper::render('components.dharmashop.fields.swatch',['color'=>$control==='images'?'':($option['color']??''),'image'=>$option['image']??'','context'=>!empty($section['swatches'])?'field.color.filter':'field.option.filter']); } ?>
                    <span><?php echo $escape($option['label']); ?></span>
                    <?php if($option['count']!==null): ?><span class="small opacity-75">(<?php echo (int)$option['count']; ?>)</span><?php endif; ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-link btn-sm px-0 mt-1 d-none" data-filter-more data-more="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_SHOW_MORE')); ?>" data-less="<?php echo $escape(Text::_('MOD_DHARMASHOP_FILTER_SHOW_LESS')); ?>" aria-expanded="false"><?php echo Text::_('MOD_DHARMASHOP_FILTER_SHOW_MORE'); ?></button>
    <p class="small text-body-secondary mt-2 d-none" data-filter-no-values role="status"><?php echo Text::_('MOD_DHARMASHOP_FILTER_NO_VALUES'); ?></p>
</div>
