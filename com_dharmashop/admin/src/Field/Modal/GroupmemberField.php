<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field\Modal;

use Dharma\Component\DharmaShop\Administrator\Catalog\ProductPicker;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Separate group-member picker, with the shared native product dialog. */
class GroupmemberField extends ProductField
{
    protected $type = 'Modal_Groupmember';
    protected string $previewLayout = 'field.group-member';
    protected string $imageContext = 'admin.picker.member';

    protected function previewData(ProductPicker $picker, object $item): array
    {
        return parent::previewData($picker, $item) + ['choices' => $picker->choices((int) $item->id)];
    }

    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        if (!parent::setup($element, $value, $group)) { return false; }
        $url = new Uri($this->urls['select']);
        $url->setVar('picker_members', 1);
        $url->setVar('picker_group', max(0, Factory::getApplication()->getInput()->getInt('id')));
        $this->urls['select'] = (string) $url;
        return true;
    }
}
