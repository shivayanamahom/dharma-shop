<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\MediaField;

\defined('_JEXEC') or die;

/** Native media picker with a compact preview for option rows. */
final class CompactmediaField extends MediaField
{
    protected $type = 'Compactmedia';

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useStyle('com_dharmashop.uploader');

        // Keep Joomla's assets, attributes and web component behaviour intact.
        return str_replace(
            'class="field-media-wrapper"',
            'class="field-media-wrapper dharmashop-compact-media d-flex align-items-center gap-2"',
            parent::getInput()
        );
    }
}
