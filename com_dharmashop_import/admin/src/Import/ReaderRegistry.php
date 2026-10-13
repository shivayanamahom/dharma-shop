<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Event;
\defined('_JEXEC') or die;

/** Format readers are collected from dharmashopimport plugins. CSV remains built in. */
final class ReaderRegistry
{
    public static function get(string $format): object
    {
        if ($format === 'csv') { return new CsvReader([]); }
        $readers = [];
        PluginHelper::importPlugin('dharmashopimport');
        $event = new Event('onDharmaShopImportReadersCollect', ['readers' => $readers]);
        Factory::getApplication()->getDispatcher()->dispatch($event->getName(), $event);
        $readers = $event->getArgument('readers', []);
        if (!isset($readers[$format]) || !is_object($readers[$format])) { throw new \RuntimeException('COM_DHARMASHOP_IMPORT_READER_MISSING'); }
        return $readers[$format];
    }
}
