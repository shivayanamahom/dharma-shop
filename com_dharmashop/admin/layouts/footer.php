<?php
/**
 * Dharma Shop — подвал админки: логотип и версия компонента. Выводится диспетчером под каждой страницей.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\ExtensionHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

// Версия берётся из установленного пакета (кэш манифеста), без обращений к файлам.
$version = '';

try {
    $record = ExtensionHelper::getExtensionRecord('com_dharmashop', 'component');
    $version = $record ? (string) (new Registry((string) $record->manifest_cache))->get('version', '') : '';
} catch (\Throwable) {
    // подвал без версии лучше, чем ошибка страницы
}
?>
<footer class="mt-4 py-3 border-top text-center small text-muted">
	<img src="<?php echo htmlspecialchars(Uri::root(true) . '/media/com_dharmashop/images/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="Dharma Shop" height="22" class="bg-white rounded px-2 py-1 me-2 align-middle">
	<span class="align-middle">Dharma Shop<?php echo $version !== '' ? ' ' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') : ''; ?></span>
</footer>
