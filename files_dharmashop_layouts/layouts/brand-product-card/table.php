<?php
/** Карточка товара на странице бренда: используется, когда выключен переключатель «Карточки товаров как в категориях». Копия стандартной карточки, правится отдельно. */
/** Строка товара в таблице. Содержимое будет добавлено отдельным шагом. */
\defined('_JEXEC') or die;

use Joomla\CMS\Layout\FileLayout;

// The complete card design is still pending; the field rendering contract is ready.
$fields = (new FileLayout('components.dharmashop.fields.default', null, ['component' => 'none', 'client' => 0]))->render(['fields' => $displayData['fields'] ?? $displayData['item']->fields ?? [], 'context' => 'card']);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = '<a class="link-body-emphasis text-decoration-none" href="' . $escape($displayData['productUrl'] ?? '#') . '">' . $escape($displayData['item']->title) . '</a>';
if ($fields !== '') { echo '<tr class="dharmaCard"><td>' . $title . '</td><td>' . $fields . '</td></tr>'; }
