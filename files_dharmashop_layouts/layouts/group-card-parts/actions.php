<?php
\defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="dharmaGroupPrice fw-bold"><?php echo $escape($displayData['priceRange']); ?></div>
<a class="btn btn-outline-primary mt-2 dharmaGroupChoose" href="<?php echo $escape($displayData['url']); ?>"><?php echo $escape(Text::_('COM_DHARMASHOP_GROUP_CARD_CHOOSE')); ?></a>
