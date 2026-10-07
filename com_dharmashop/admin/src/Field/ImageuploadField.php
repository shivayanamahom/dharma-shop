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
use Joomla\CMS\Form\Field\MediaField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Поле «изображение» с загрузкой перетаскиванием файла (или выбором по клику). Значение — путь к файлу относительно сайта
 * (как у штатного поля Media, без служебного хвоста). Атрибут folder задаёт тип (подпапку) внутри папки загрузок из настроек (по умолчанию images/dharmashop).
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

        $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));
        $value = trim((string) strstr(((string) $this->value) . '#', '#', true));
        $folder = (string) ($this->element['folder'] ?: 'common');
        $attr = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $maxMb = $uploader->maxBytes() > 0 ? round($uploader->maxBytes() / 1048576, 1) : 0;

        // Штатное поле Media: прячем внутри и открываем кнопкой «Выбрать с сервера».
        // Штатное поле Media: прячем внутри и открываем кнопкой «Выбрать с сервера». getInput() у него защищённый — открываем через подкласс.
        $native = new class ($this->form) extends MediaField {
            public function inputHtml(): string
            {
                return $this->getInput();
            }
        };
        $native->setup(new \SimpleXMLElement('<field name="dharmashop_native_' . $attr($this->fieldname) . '" type="media" types="images" preview="false" />'), $value);
        $limits = Text::sprintf('COM_DHARMASHOP_UPLOAD_LIMITS', strtoupper(implode(', ', $uploader->allowedExtensions())), $maxMb ?: '—');

        return '<div class="dharmashop-uploader" data-url="' . $attr('index.php?option=com_dharmashop&task=media.upload&format=json')
            . '" data-token="' . $attr(Session::getFormToken()) . '" data-folder="' . $attr($folder)
            . '" data-root="' . $attr(Uri::root()) . '" data-max-bytes="' . (int) $uploader->maxBytes()
            . '" data-accept="' . $attr(implode(',', $uploader->allowedExtensions()))
            . '" data-error-type="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_TYPE_LIST', implode(', ', $uploader->allowedExtensions())))
            . '" data-error-size="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_SIZE_LIMIT', $maxMb))
            . '" data-error-paste-unsupported="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_UNSUPPORTED'))
            . '" data-error-paste-empty="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_EMPTY'))
            . '" data-error-paste-denied="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_DENIED'))
            . '" data-error-server="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_SERVER')) . '">'
            . '<input type="hidden" class="dharmashop-uploader__input" name="' . $attr($this->name) . '" id="' . $attr($this->id) . '" value="' . $attr($value) . '">'
            . '<input type="file" class="dharmashop-uploader__file visually-hidden" tabindex="-1" aria-hidden="true" accept="image/*">'
            . '<div class="dharmashop-uploader__drop" tabindex="0" role="button" aria-label="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_DROP_TITLE')) . '">'
            . '<img class="dharmashop-uploader__preview" alt="" hidden>'
            . '<div class="dharmashop-uploader__hint">'
            . '<svg class="dharmashop-uploader__icon" viewBox="0 0 64 64" width="64" height="64" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M21 47h-3.5a11.5 11.5 0 0 1-1.7-22.9 16 16 0 0 1 31.4 2.2A10.4 10.4 0 0 1 46 47h-3"/>'
            . '<path d="M32 54V31"/><path d="M23.5 39.5 32 31l8.5 8.5"/></svg>'
            . '<span class="dharmashop-uploader__title">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_DROP_TITLE')) . '</span>'
            . '<span class="dharmashop-uploader__actions">'
            . '<button type="button" class="btn btn-link dharmashop-uploader__choose">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_CHOOSE')) . '</button>'
            . '<span aria-hidden="true">·</span>'
            . '<button type="button" class="btn btn-link dharmashop-uploader__paste">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_PASTE')) . '</button>'
            . '</span>'
            . '<small class="dharmashop-uploader__limits">' . $attr($limits) . '</small>'
            . '</div></div>'
            . '<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" style="width:0%"></div></div>'
            . '<div class="input-group mt-2">'
            . '<input type="text" class="form-control dharmashop-uploader__path" readonly placeholder="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_NO_FILE')) . '" aria-label="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_PATH')) . '">'
            . '<button type="button" class="btn btn-secondary dharmashop-uploader__server"><span class="icon-folder-open" aria-hidden="true"></span> ' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_FROM_SERVER')) . '</button>'
            . '<button type="button" class="btn btn-danger dharmashop-uploader__remove" title="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '" disabled><span class="icon-times" aria-hidden="true"></span><span class="visually-hidden">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '</span></button>'
            . '</div>'
            . '<div class="dharmashop-uploader__native" hidden>' . $native->inputHtml() . '</div>'
            . '</div>';
    }
}
