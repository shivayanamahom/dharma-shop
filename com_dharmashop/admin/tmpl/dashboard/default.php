<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Dashboard\HtmlView $this */
?>
<div class="alert alert-info">
    <span class="icon-info-circle" aria-hidden="true"></span>
    <?php echo Text::_('COM_DHARMASHOP_DASHBOARD_STAGE0'); ?>
</div>

<table class="table">
    <caption class="visually-hidden"><?php echo Text::_('COM_DHARMASHOP_DASHBOARD_TABLES'); ?></caption>
    <thead>
        <tr>
            <th scope="col"><?php echo Text::_('COM_DHARMASHOP_DASHBOARD_TABLE'); ?></th>
            <th scope="col" class="text-end"><?php echo Text::_('COM_DHARMASHOP_DASHBOARD_ROWS'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($this->counts as $name => $rows) : ?>
            <tr>
                <th scope="row"><?php echo $this->escape($name); ?></th>
                <td class="text-end"><?php echo (int) $rows; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
