<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$data = $displayData;
$locked = $data['readonly'] || $data['disabled'];
$modal = ['popupType' => 'iframe', 'src' => Route::_($data['urls']['select'], false), 'textHeader' => Text::_('COM_DHARMASHOP_GROUP_PICKER_SELECT')];
?>
<div class="js-modal-content-select-field" <?php echo $data['dataAttribute']; ?>>
    <div class="input-group">
        <input type="text" class="form-control js-input-title" id="<?php echo $escape($data['id']); ?>" value="<?php echo $escape($data['valueTitle']); ?>" placeholder="<?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_NONE')); ?>" readonly>
        <?php if (!$locked) : ?>
            <button type="button" class="btn btn-outline-primary" data-button-action="select" data-modal-config="<?php echo $escape(json_encode($modal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>" title="<?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_PICKER_SELECT')); ?>">
                <span class="icon-layer-group" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_GROUP_PICKER_SELECT'); ?></span>
            </button>
            <?php if ($data['canCreate']) : ?>
                <joomla-toolbar-button task="product.createGroup" form="product-form" form-validation data-show-when-value=""<?php echo $data['value'] ? ' hidden' : ''; ?>>
                    <button type="button" class="btn btn-outline-success rounded-start-0" title="<?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_PICKER_CREATE')); ?>">
                        <span class="icon-plus" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_GROUP_PICKER_CREATE'); ?></span>
                    </button>
                </joomla-toolbar-button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <input type="hidden" class="js-input-value modal-value" id="<?php echo $escape($data['id']); ?>_id" name="<?php echo $escape($data['name']); ?>" value="<?php echo $escape($data['value']); ?>" data-required="0">
    <?php if (!$locked) : ?>
        <button type="button" class="btn btn-link btn-sm px-0" data-button-action="clear" data-show-when-value="1"<?php echo $data['value'] ? '' : ' hidden'; ?>>
            <span class="icon-times" aria-hidden="true"></span> <?php echo Text::_('COM_DHARMASHOP_GROUP_PICKER_REMOVE'); ?>
        </button>
    <?php endif; ?>
</div>
