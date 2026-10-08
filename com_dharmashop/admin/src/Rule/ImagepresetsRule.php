<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Rule;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
class ImagepresetsRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if ($value instanceof Registry) $value = $value->toArray();
        elseif ($value instanceof \stdClass) $value = get_object_vars($value);
        if ($value === null || $value === '') return true;
        if (is_string($value)) $value = json_decode($value, true);
        if (!is_array($value) || count($value) > 80) return false;
        $seen = [];
        foreach ($value as $row) {
            $row = $row instanceof Registry ? $row->toArray() : (array) $row; $context = (string) ($row['context'] ?? '');
            if (!isset(ImageContexts::SIZES[$context]) || isset($seen[$context])) return false;
            $seen[$context] = true;
            foreach (['width','height'] as $key) if (filter_var($row[$key] ?? null, FILTER_VALIDATE_INT) === false || (int)$row[$key]<0 || (int)$row[$key]>2400) return false;
            if (!in_array($row['mode'] ?? '', ['contain','cover','stretch'], true) || !in_array($row['format'] ?? '', ['auto','webp','jpeg','png','original'], true)) return false;
        }
        return true;
    }
}
