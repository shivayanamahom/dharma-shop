<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
$axes=$this->item->choices ?? [];
if(!$axes) {return;}
?>
<form class="dharmaProductChoices mb-3" action="<?php echo Route::_('index.php'); ?>" method="get">
<input type="hidden" name="option" value="com_dharmashop"><input type="hidden" name="task" value="product.choose"><input type="hidden" name="id" value="<?php echo (int)$this->item->id; ?>">
<?php foreach($axes as $axis): ?>
<fieldset class="mb-3"><legend class="fs-6"><?php echo $this->escape($axis->title); ?></legend>
<?php if($axis->type==='color'): ?><div class="d-flex flex-wrap gap-2">
<?php foreach($axis->options as $option): $control='choice-'.(int)$axis->id.'-'.(int)$option->id; ?>
<input class="btn-check" type="radio" name="choice[<?php echo (int)$axis->id; ?>]" id="<?php echo $control; ?>" value="<?php echo (int)$option->id; ?>" <?php echo (int)$axis->selected===(int)$option->id?'checked':''; ?> required>
<label class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" for="<?php echo $control; ?>"><?php echo LayoutHelper::render('components.dharmashop.fields.swatch',['color'=>$option->color,'image'=>$option->image,'context'=>'group.choice','label'=>$option->title,'round'=>true]); ?><span><?php echo $this->escape($option->title); ?></span></label>
<?php endforeach; ?></div>
<?php else: ?><select class="form-select" name="choice[<?php echo (int)$axis->id; ?>]" aria-label="<?php echo $this->escape($axis->title); ?>" required>
<?php foreach($axis->options as $option): ?><option value="<?php echo (int)$option->id; ?>" <?php echo (int)$axis->selected===(int)$option->id?'selected':''; ?>><?php echo $this->escape($option->title); ?></option><?php endforeach; ?>
</select><?php endif; ?></fieldset>
<?php endforeach; ?>
<button class="btn btn-primary" type="submit"><?php echo Text::_('COM_DHARMASHOP_GROUP_CHOOSE'); ?></button>
</form>
