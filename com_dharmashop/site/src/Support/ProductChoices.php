<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Support;
use Dharma\Component\DharmaShop\Administrator\Catalog\GroupService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
\defined('_JEXEC') or die;
/** Native GET selector: one bounded query for options, exact hash lookup on navigation. */
final class ProductChoices
{
    public function __construct(private readonly DatabaseInterface $db) {}
    public function forProduct(object $item): array
    {
        $id=(int)($item->group_id ?? 0);if(!$id) {return [];}
        $service=new GroupService($this->db);$group=$service->get($id);
        if(!$group || (int)$group->state!==1) {return [];}
        $axes=$service->axes($id);$levels=array_map('intval',Factory::getApplication()->getIdentity()->getAuthorisedViewLevels());
        if(!$axes || count($axes)>6) {return [];}
        foreach($axes as $axis) {if((int)$axis->state !== 1 || !in_array((int)$axis->access,$levels,true)) {return [];}}
        $selected=$service->combination((int)$item->id,$id);
        $rows=$this->db->setQuery('SELECT DISTINCT fv.field_id,o.id,o.title,o.color,o.image,o.ordering FROM #__dharmashop_products p INNER JOIN #__categories c ON c.id=p.catid INNER JOIN #__dharmashop_field_values fv ON fv.product_id=p.id AND fv.variant_id IS NULL INNER JOIN #__dharmashop_group_axes ga ON ga.group_id=p.group_id AND ga.field_id=fv.field_id INNER JOIN #__dharmashop_field_options o ON o.id=fv.option_id AND o.field_id=fv.field_id WHERE p.group_id='.$id.' AND p.state=1 AND c.published=1 AND c.extension='.$this->db->quote('com_dharmashop').' AND NOT EXISTS (SELECT 1 FROM #__categories b WHERE b.extension='.$this->db->quote('com_dharmashop').' AND b.lft<=c.lft AND b.rgt>=c.rgt AND (b.published<>1 OR b.access NOT IN ('.implode(',',$levels ?: [0]).'))) ORDER BY o.ordering,o.id')->loadObjectList();
        $options=[];foreach($rows as $row) {$options[(int)$row->field_id][]=$row;}
        foreach($axes as $axis) {$axis->options=$options[(int)$axis->id] ?? []; $axis->selected=$selected[(int)$axis->id];}
        return $axes;
    }
}
