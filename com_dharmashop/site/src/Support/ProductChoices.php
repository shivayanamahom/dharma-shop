<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
/** Native GET selector with bounded options and shared visible-choice resolution. */
final class ProductChoices
{
    public function __construct(private readonly DatabaseInterface $db) {}
    public static function display(object $axis): string
    {
        if (!(int) $axis->params->get('variation', 0)) { return 'off'; }
        $display = (string) $axis->params->get($axis->type === 'color' ? 'color_product_choice_display' : 'product_choice_display', $axis->type === 'color' ? 'color' : 'select');
        return in_array($display, ['off', 'select', 'radio', 'color'], true) ? $display : 'select';
    }
    public function forProduct(object $item): array
    {
        $id=(int)($item->group_id ?? 0);if(!$id) {return [];}
        $service=new GroupService($this->db);$group=$service->get($id);
        if(!$group || (int)$group->state!==1) {return [];}
        $levels=array_map('intval',Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        $access=implode(',', $levels ?: [0]);
        $groupCategory=(int)($group->catid ?? 0);
        $validCategory=$this->db->setQuery('SELECT c.id FROM #__categories c WHERE c.id='.$groupCategory.' AND ((c.id=1 AND c.extension='.$this->db->quote('system').' AND c.parent_id=0) OR (c.extension='.$this->db->quote('com_dharmashop').' AND c.published=1 AND c.access IN ('.$access.') AND NOT EXISTS (SELECT 1 FROM #__categories b WHERE b.extension=c.extension AND b.lft<=c.lft AND b.rgt>=c.rgt AND (b.published<>1 OR b.access NOT IN ('.$access.')))))')->loadResult();
        if (!$validCategory) { return []; }
        $axes=$this->db->setQuery('SELECT f.id,f.title,f.type,f.params,f.access,f.state,f.group_id,fg.state AS fieldgroup_state,fg.target AS fieldgroup_target,f.target FROM #__dharmashop_group_axes a INNER JOIN #__dharmashop_fields f ON f.id=a.field_id LEFT JOIN #__dharmashop_fieldgroups fg ON fg.id=f.group_id WHERE a.group_id='.$id.' ORDER BY a.ordering,f.id')->loadObjectList();
        if(!$axes || count($axes)>6) {return [];}
        foreach($axes as $axis) {if((int)$axis->state !== 1 || !in_array((int)$axis->access,$levels,true) || ((int)$axis->group_id > 0 && ((int)$axis->fieldgroup_state !== 1 || $axis->fieldgroup_target !== $axis->target))) {return [];}}
        $selected=$service->combination((int)$item->id,$id);
        $rows=$this->db->setQuery('SELECT DISTINCT fv.field_id,o.id,o.title,o.color,o.image,o.ordering FROM #__dharmashop_products p INNER JOIN #__categories c ON c.id=p.catid INNER JOIN #__dharmashop_field_values fv ON fv.product_id=p.id AND fv.variant_id IS NULL INNER JOIN #__dharmashop_group_axes ga ON ga.group_id=p.group_id AND ga.field_id=fv.field_id INNER JOIN #__dharmashop_field_options o ON o.id=fv.option_id AND o.field_id=fv.field_id WHERE p.group_id='.$id.' AND p.state=1 AND c.published=1 AND c.extension='.$this->db->quote('com_dharmashop').' AND NOT EXISTS (SELECT 1 FROM #__categories b WHERE b.extension='.$this->db->quote('com_dharmashop').' AND b.lft<=c.lft AND b.rgt>=c.rgt AND (b.published<>1 OR b.access NOT IN ('.implode(',',$levels ?: [0]).'))) ORDER BY o.ordering,o.id')->loadObjectList();
        $options=[];foreach($rows as $row) {$options[(int)$row->field_id][]=$row;}
        foreach($axes as $axis) {$axis->options=$options[(int)$axis->id] ?? []; $axis->selected=$selected[(int)$axis->id] ?? 0; $axis->params=new Registry($axis->params);}
        return $axes;
    }
    /** Keep loaded values for layouts/tokens, suppress only repeated standard presentation. */
    public static function suppressRepeatedFields(object $item): void
    {
        $shown = [];
        foreach ($item->choices ?? [] as $axis) {
            if (self::display($axis) !== 'off' && !empty($axis->options)) { $shown[(int) $axis->id] = true; }
        }
        foreach ($item->fields ?? [] as $field) {
            if (isset($shown[(int) $field->id])) { $field->show = false; $field->show_intro = false; }
        }
    }

    public function resolve(object $source, array $requested): int
    {
        $visible=$preferred=[];
        $axes=$source->choices ?? [];
        if (!$axes || count($requested)>6) { return 0; }
        $known=array_map(static fn(object $axis): int => (int) $axis->id, $axes);
        foreach ($requested as $field=>$value) { if (!in_array((int) $field,$known,true) || !is_scalar($value)) { return 0; } }
        foreach ($axes as $axis) {
            $selected=(int)$axis->selected;
            $value=(int)($requested[(int)$axis->id] ?? $selected);
            if ((self::display($axis)==='off' && $value!==$selected)
                || !in_array($value,array_map(static fn(object $option): int => (int)$option->id,$axis->options),true)) { return 0; }
            if (self::display($axis)==='off') { $preferred[(int)$axis->id]=$selected; }
            else { $visible[(int)$axis->id]=$value; }
        }
        $levels=implode(',',array_map('intval',Factory::getApplication()->getIdentity()->getAuthorisedViewLevels()) ?: [0]);
        $candidates=$this->db->createQuery()->from('#__dharmashop_products AS p')
            ->join('INNER', '#__categories AS c ON c.id=p.catid')
            ->where('p.state=1')->where('c.extension='.$this->db->quote('com_dharmashop'))
            ->where('c.published=1')->where('c.access IN ('.$levels.')')
            ->where('NOT EXISTS (SELECT 1 FROM #__categories b WHERE b.extension=c.extension AND b.lft<=c.lft AND b.rgt>=c.rgt AND (b.published<>1 OR b.access NOT IN ('.$levels.')))');
        return (new VariantSelection($this->db))->choose($candidates, (int)$source->group_id, $visible, $preferred);
    }

}
