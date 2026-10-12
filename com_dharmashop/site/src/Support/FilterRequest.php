<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Support;

use Dharma\Component\DharmaShop\Administrator\Filter\FilterDefinition;
use Dharma\Component\DharmaShop\Administrator\Filter\FilterField;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/** Только назначенный, опубликованный и доступный модуль может задавать политику фильтра. */
final class FilterRequest
{
    public static function params(): Registry
    {
        $id = Factory::getApplication()->getInput()->getInt('filter_module');
        if ($id < 1) { return new Registry(); }
        $module = ModuleHelper::getModuleById((string) $id);
        return ($module->module ?? '') === 'mod_dharmashop_filter' && (int) ($module->id ?? 0) === $id
            ? new Registry($module->params) : new Registry();
    }

    /** Логика строки модуля участвует в SQL и каскаде, а не только в подписи элемента. */
    public static function definition(FilterDefinition $definition, Registry $params): FilterDefinition
    {
        $fields = $definition->fields;
        foreach ((array) $params->get('fields_config', []) as $row) {
            $row = (array) $row;
            $field = $definition->fieldByAlias((string) ($row['field'] ?? ''));
            $logic = (string) ($row['logic'] ?? 'inherit');
            if (!$field || !in_array($logic, ['or', 'and'], true)) { continue; }
            $fields[$field->id] = new FilterField($field->id, $field->alias, $field->title, $field->type, $field->kind,
                $field->level, $field->unit, $field->options, ['logic' => $logic] + $field->settings);
        }
        return new FilterDefinition($definition->catid, $fields, $definition->subcategories, $definition->brands, $definition->badges);
    }
}
