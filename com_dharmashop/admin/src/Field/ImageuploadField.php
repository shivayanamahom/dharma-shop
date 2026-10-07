<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Поле «изображение» с загрузкой перетаскиванием файла (или выбором по клику). Значение — путь к файлу относительно сайта
 * (как у штатного поля Media, без служебного хвоста). Атрибут folder задаёт подпапку в images/dharmashop.
 *
 *     <field name="image" type="imageupload" folder="categories" label="..." />
 */
class ImageuploadField extends FormField
{
    protected $type = 'Imageupload';

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.uploader')->useStyle('com_dharmashop.uploader');

        $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT);
        $value = trim((string) strstr(((string) $this->value) . '#', '#', true));
        $folder = (string) ($this->element['folder'] ?: 'common');
        $attr = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $maxMb = $uploader->maxBytes() > 0 ? round($uploader->maxBytes() / 1048576, 1) : 0;

        return '<div class="dharmashop-uploader" data-url="' . $attr('index.php?option=com_dharmashop&task=media.upload&format=json')
            . '" data-token="' . $attr(Session::getFormToken()) . '" data-folder="' . $attr($folder)
            . '" data-root="' . $attr(Uri::root()) . '" data-max-bytes="' . (int) $uploader->maxBytes()
            . '" data-accept="' . $attr(implode(',', $uploader->allowedExtensions()))
            . '" data-error-type="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_TYPE_LIST', implode(', ', $uploader->allowedExtensions())))
            . '" data-error-size="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_SIZE_LIMIT', $maxMb))
            . '" data-error-server="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_SERVER')) . '">'
            . '<input type="hidden" class="dharmashop-uploader__input" name="' . $attr($this->name) . '" id="' . $attr($this->id) . '" value="' . $attr($value) . '">'
            . '<input type="file" class="dharmashop-uploader__file visually-hidden" tabindex="-1" aria-hidden="true" accept="image/*">'
            . '<div class="dharmashop-uploader__drop" tabindex="0" role="button" aria-label="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_HINT')) . '">'
            . '<img class="dharmashop-uploader__preview" alt="" hidden>'
            . '<span class="dharmashop-uploader__hint"><span class="icon-upload" aria-hidden="true"></span> ' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_HINT')) . '</span>'
            . '</div>'
            . '<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" style="width:0%"></div></div>'
            . '<div class="d-flex align-items-center gap-2 mt-2">'
            . '<small class="dharmashop-uploader__path text-body-secondary flex-grow-1"></small>'
            . '<button type="button" class="btn btn-sm btn-danger dharmashop-uploader__remove" hidden>' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '</button>'
            . '</div></div>';
    }
}
