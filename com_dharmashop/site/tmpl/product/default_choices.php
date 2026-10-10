<?php
\defined('_JEXEC') or die;
use Dharma\Component\DharmaShop\Site\Support\ProductChoices;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Router\Route;
$axes = $this->item->choices ?? [];
if (!$axes || !array_filter($axes, static fn(object $axis): bool => ProductChoices::display($axis) !== 'off')) { return; }
$layout = new FileLayout('components.dharmashop.variant-controls.default', null, ['component' => 'none', 'client' => 0]);
$mode = $this->params->get('product_choice_mode', 'navigate') === 'ajax' ? 'ajax' : 'navigate';
?>
<form class="dharmaProductChoices mb-3" action="<?php echo $this->escape(Route::_('index.php', false)); ?>" method="get" data-product-choices data-choice-mode="<?php echo $mode; ?>">
    <input type="hidden" name="option" value="com_dharmashop"><input type="hidden" name="task" value="product.choose"><input type="hidden" name="id" value="<?php echo (int) $this->item->id; ?>">
    <?php foreach ($axes as $axis) : $control = ProductChoices::display($axis); ?>
        <?php if ($control === 'off') : ?>
            <input type="hidden" name="choice[<?php echo (int) $axis->id; ?>]" value="<?php echo (int) $axis->selected; ?>">
        <?php else : ?>
            <?php echo $layout->render([
                'field' => (object) ['id' => (int) $axis->id, 'title' => $axis->title, 'type' => $axis->type, 'values' => $axis->options],
                'selected' => (int) $axis->selected, 'id' => 'dharma-product-' . (int) $this->item->id . '-choice-' . (int) $axis->id,
                'name' => 'choice[' . (int) $axis->id . ']', 'attribute' => 'data-product-choice', 'control' => $control,
                'showTitle' => (int) $axis->params->get('product_choice_title', 1) === 1,
                'format' => (string) $axis->params->get('product_choice_format', 'image_title'),
            ]); ?>
        <?php endif; ?>
    <?php endforeach; ?>
    <button class="btn btn-outline-primary" type="submit" data-product-choice-submit><?php echo Text::_('COM_DHARMASHOP_PRODUCT_CHOICE_OPEN'); ?></button>
    <p class="small text-danger mt-2 mb-0" data-product-choice-message role="status" aria-live="polite"></p>
</form>
