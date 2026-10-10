<?php
\defined('_JEXEC') or die;
$escape = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
data-dharmashop-group="<?php echo (int) $displayData['group']->id; ?>"<?php if (!empty($displayData['ajax'])) : ?> data-dharmashop-group-card data-group-source="<?php echo (int) $displayData['item']->id; ?>" data-group-context="<?php echo $escape($displayData['context']); ?>" data-group-url="<?php echo $escape($displayData['ajaxUrl']); ?>"<?php endif; ?>
