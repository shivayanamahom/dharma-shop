<?php

/**
 * Dharma Shop — страница обновлений.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Dharma\Component\DharmaShop\Administrator\Extension\ExtensionUpdates;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Dharma\Component\DharmaShop\Administrator\View\Updates\HtmlView $this */
$e = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$action = static fn (string $task, array $extra = []): string => 'index.php?option=com_dharmashop&ext_action=' . $task . '&return=updates' . ($extra ? '&' . http_build_query($extra) : '');
$pending = count(array_filter($this->rows, static fn (array $r): bool => $r['available'] !== '' && $r['updateId'] > 0));
$groupName = static fn (array $r): string => match ($r['type']) {
    'package' => Text::_('COM_DHARMASHOP_EXT_GROUP_PACKAGE'),
    'file' => Text::_('COM_DHARMASHOP_EXT_GROUP_FILE'),
    'component' => Text::_('COM_DHARMASHOP_EXT_GROUP_COMPONENT'),
    'module' => Text::_('COM_DHARMASHOP_EXT_GROUP_MODULE'),
    default => Text::sprintf('COM_DHARMASHOP_EXT_GROUP_PLUGIN', $r['folder']),
};
?>
<form action="<?php echo $e('index.php?option=com_dharmashop&view=updates'); ?>" method="post" name="adminForm" id="adminForm">
<?php if (!$this->rows) : ?>
    <div class="alert alert-info"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_NO_SITES')); ?></div>
<?php else : ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <button type="submit" class="btn btn-primary" formaction="<?php echo $e($action('check')); ?>" formnovalidate><span class="icon-sync" aria-hidden="true"></span> <?php echo $e(Text::_('COM_DHARMASHOP_EXT_CHECK_NOW')); ?></button>
        <?php if ($pending) : ?>
            <button type="submit" class="btn btn-success" formaction="<?php echo $e($action('updateall')); ?>" formnovalidate><?php echo $e(Text::sprintf('COM_DHARMASHOP_EXT_UPDATE_ALL', $pending)); ?></button>
        <?php endif; ?>
        <span class="text-body-secondary small ms-2"><?php echo $e(Text::sprintf('COM_DHARMASHOP_UPDATES_CHANNEL', Text::_('COM_DHARMASHOP_EXT_CHANNEL_' . strtoupper(ExtensionUpdates::CHANNELS[$this->stability] ?? 'stable')))); ?></span>
    </div>
    <div class="table-responsive">
        <table class="table align-middle table-sm">
            <caption class="visually-hidden"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_CAPTION')); ?></caption>
            <thead><tr>
                <th scope="col"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_COL_NAME')); ?></th>
                <th scope="col"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_COL_GROUP')); ?></th>
                <th scope="col"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_COL_INSTALLED')); ?></th>
                <th scope="col"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_COL_AVAILABLE')); ?></th>
                <th scope="col"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_COL_STATUS')); ?></th>
                <th scope="col"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($this->rows as $r) : $has = $r['available'] !== '' && $r['updateId'] > 0; ?>
                <tr>
                    <th scope="row"><?php echo $e($r['title']); ?><?php echo $r['enabled'] ? '' : ' <span class="small text-body-secondary fw-normal">(' . $e(Text::_('COM_DHARMASHOP_EXT_DISABLED')) . ')</span>'; ?>
                        <div class="small text-body-secondary fw-normal"><?php echo $e($r['element']); ?></div></th>
                    <td class="small text-body-secondary"><?php echo $e($groupName($r)); ?></td>
                    <td><?php echo $e($r['installed']); ?></td>
                    <td><?php echo $e($r['available'] !== '' ? $r['available'] : '—'); ?></td>
                    <td>
                        <?php if (!$r['siteEnabled']) : ?><span class="badge text-bg-secondary"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_SOURCE_OFF')); ?></span>
                        <?php elseif ($has) : ?><span class="badge text-bg-warning"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_UPDATE_AVAILABLE')); ?></span>
                        <?php else : ?><span class="badge text-bg-success"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_UP_TO_DATE')); ?></span><?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($has) : ?>
                            <button type="submit" class="btn btn-sm btn-primary" formaction="<?php echo $e($action('update', ['update_id' => $r['updateId']])); ?>" formnovalidate><?php echo $e(Text::sprintf('COM_DHARMASHOP_EXT_UPDATE_TO', $r['available'])); ?></button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?php echo $e($action('rollback', ['extension_id' => $r['id']])); ?>" formnovalidate
                            onclick="return confirm('<?php echo $e(Text::_('COM_DHARMASHOP_EXT_ROLLBACK_CONFIRM')); ?>');"><?php echo $e(Text::_('COM_DHARMASHOP_EXT_ROLLBACK')); ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small text-body-secondary"><?php echo $e(Text::_('COM_DHARMASHOP_UPDATES_HINT')); ?></p>
<?php endif; ?>
    <input type="hidden" name="boxchecked" value="0">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
