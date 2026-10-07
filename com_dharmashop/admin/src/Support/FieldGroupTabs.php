<?php

/**
 * Dharma Shop — размещение групп дополнительных полей в форме.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

use Joomla\Database\DatabaseInterface;

\defined('_JEXEC') or die;

/** Только метаданные вкладок; определения, применимость и значения готовит подсистема полей. */
final class FieldGroupTabs
{
    /** @return array<int, object> Порядок групп общий для блоков и отдельных вкладок. */
    public static function load(DatabaseInterface $db, string $target): array
    {
        if (!in_array($target, ['product', 'category'], true)) {
            throw new \InvalidArgumentException('Unknown field target.');
        }

        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'title', 'tab_mode']))
            ->from($db->quoteName('#__dharmashop_fieldgroups'))
            ->where($db->quoteName('target') . ' = :target')
            ->where($db->quoteName('state') . ' = 1')
            ->bind(':target', $target)
            ->order($db->quoteName('ordering') . ' ASC')
            ->order($db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList();
    }

    /** Внутреннее имя fieldset: будущие типы добавляют сюда штатные FormField. */
    public static function fieldset(int $groupId): string
    {
        return 'dharmashop_fields_group_' . $groupId;
    }
}
