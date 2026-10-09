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
use Joomla\CMS\Form\Field\MediaField;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/**
 * Галерея изображений (несколько файлов): перетаскивание, выбор, вставка из буфера, «Выбрать с сервера»;
 * порядок меняется перетаскиванием, первое изображение — главное. Значение — список {path, alt, title}.
 * Серверная проверка — ImageUploader::filterGallery (атрибут filter поля) и MediaController::upload.
 *
 *     <field name="gallery" type="gallery" folder="products" filter="...ImageUploader::filterGallery" label="..." />
 */
class GalleryField extends FormField
{
    protected $type = 'Gallery';

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.gallery')->useStyle('com_dharmashop.gallery');

        $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));
        $attr = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $folder = (string) ($this->element['folder'] ?: 'common');
        $fieldId = max(0, (int) ($this->element['field_id'] ?? 0));
        // Joomla may omit custom XML attributes while building nested custom fields;
        // the generated name still carries the stable f_<id> key.
        if ($fieldId < 1 && preg_match('/\[f_(\d+)\]/', (string) $this->name, $matches)) {
            $fieldId = (int) $matches[1];
        }
        $isFieldGallery = $fieldId > 0;
        $maxCount = max(1, min(100, (int) ($this->element['max_count'] ?? 100)));
        $maxMb = $uploader->maxBytes() > 0 ? round($uploader->maxBytes() / 1048576, 1) : 0;
        $items = ImageUploader::filterGallery($this->value);
        $root = Uri::root();

        $native = new class ($this->form) extends MediaField {
            public function inputHtml(): string
            {
                return $this->getInput();
            }
        };
        $native->setup(new \SimpleXMLElement('<field name="dharmashop_native_' . hash('xxh64', $this->id) . '" type="media" types="images" preview="false" />'), '');

        $card = function (string $index, string $path, string $description) use ($attr, $root, $isFieldGallery): string {
            $name = $this->name;

            return '<li class="dharmashop-gallery__item" draggable="true">'
                . '<input type="hidden" data-field="path" name="' . $attr($name) . '[' . $index . '][path]" value="' . $attr($path) . '">'
                . '<div class="dharmashop-gallery__frame"><img class="dharmashop-gallery__thumb" src="' . ($path !== '' ? $attr($root . ltrim($path, '/')) : '') . '" alt="" loading="lazy" draggable="false">'
                . ($isFieldGallery ? '' : '<span class="dharmashop-gallery__main">' . $attr(Text::_('COM_DHARMASHOP_GALLERY_MAIN')) . '</span>')
                . '<span class="dharmashop-gallery__handle" title="' . $attr(Text::_('COM_DHARMASHOP_GALLERY_DRAG')) . '" aria-hidden="true"><span class="icon-ellipsis-v"></span><span class="icon-ellipsis-v"></span></span>'
                . '<button type="button" class="dharmashop-gallery__remove btn btn-sm btn-danger" title="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '"><span class="icon-times" aria-hidden="true"></span><span class="visually-hidden">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '</span></button></div>'
                . '<input type="text" class="form-control form-control-sm" data-field="alt" name="' . $attr($name) . '[' . $index . '][alt]" value="' . $attr($description) . '" maxlength="255" placeholder="' . $attr(Text::_('COM_DHARMASHOP_GALLERY_DESCRIPTION')) . '" aria-label="' . $attr(Text::_('COM_DHARMASHOP_GALLERY_DESCRIPTION')) . '">'
                . '</li>';
        };

        $list = '';

        foreach ($items as $i => $item) {
            $list .= $card((string) $i, $item['path'], $item['alt'] !== '' ? $item['alt'] : $item['title']);
        }

        $limits = Text::sprintf('COM_DHARMASHOP_UPLOAD_LIMITS', strtoupper(implode(', ', $uploader->allowedExtensions())), $maxMb ?: '—');
        $uploadUrl = $isFieldGallery
            ? 'index.php?option=com_dharmashop&task=media.uploadFieldGallery&field_id=' . $fieldId . '&format=json'
            : 'index.php?option=com_dharmashop&task=media.upload&format=json';

        return '<div id="' . $attr($this->id) . '" class="dharmashop-gallery"' . ($isFieldGallery ? ' data-kind="field-gallery" data-field-id="' . $fieldId . '" data-entity-id="' . (int) $this->form->getValue('id', null, 0) . '"' : '')
            . ' data-url="' . $attr($uploadUrl)
            . '" data-token="' . $attr(Session::getFormToken()) . '" data-folder="' . $attr($folder) . '" data-name="' . $attr($this->name) . '" data-max-count="' . $maxCount
            . '" data-root="' . $attr($root) . '" data-max-bytes="' . (int) $uploader->maxBytes()
            . '" data-accept="' . $attr(implode(',', $uploader->allowedExtensions()))
            . '" data-error-type="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_TYPE_LIST', implode(', ', $uploader->allowedExtensions())))
            . '" data-error-count="' . $attr(Text::_($isFieldGallery ? 'PLG_DHARMASHOPFIELD_BASIC_GALLERY_COUNT_INVALID' : 'COM_DHARMASHOP_UPLOAD_ERROR_SERVER'))
            . '" data-error-size="' . $attr(Text::sprintf('COM_DHARMASHOP_UPLOAD_ERROR_SIZE_LIMIT', $maxMb))
            . '" data-error-paste-unsupported="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_UNSUPPORTED'))
            . '" data-error-paste-empty="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_EMPTY'))
            . '" data-error-paste-denied="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_PASTE_DENIED'))
            . '" data-error-server="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_SERVER')) . '">'
            // Служебное поле: гарантирует, что ключ галереи придёт в запросе, даже когда все изображения убраны (пустой путь отбрасывается фильтром).
            . '<input type="hidden" name="' . $attr($this->name) . '[_][path]" value="">'
            . '<input type="file" class="dharmashop-gallery__file visually-hidden" tabindex="-1" aria-hidden="true" accept="image/*" multiple>'
            . '<div class="dharmashop-gallery__drop" tabindex="0" role="button" aria-label="' . $attr(Text::_('COM_DHARMASHOP_GALLERY_DROP_TITLE')) . '">'
            . '<svg class="dharmashop-gallery__icon" viewBox="0 0 64 64" width="48" height="48" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M21 47h-3.5a11.5 11.5 0 0 1-1.7-22.9 16 16 0 0 1 31.4 2.2A10.4 10.4 0 0 1 46 47h-3"/><path d="M32 54V31"/><path d="M23.5 39.5 32 31l8.5 8.5"/></svg>'
            . '<span class="dharmashop-gallery__title">' . $attr(Text::_('COM_DHARMASHOP_GALLERY_DROP_TITLE')) . '</span>'
            . '<span class="dharmashop-gallery__actions">'
            . '<button type="button" class="btn btn-link dharmashop-gallery__choose">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_CHOOSE')) . '</button>'
            . '<span aria-hidden="true">·</span>'
            . '<button type="button" class="btn btn-link dharmashop-gallery__paste">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_PASTE')) . '</button>'
            . '<span aria-hidden="true">·</span>'
            . '<button type="button" class="btn btn-link dharmashop-gallery__server">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_FROM_SERVER')) . '</button>'
            . '</span>'
            . '<small class="dharmashop-gallery__limits">' . $attr($limits) . '</small>'
            . '</div>'
            . '<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" style="width:0%"></div></div>'
            . '<ul class="dharmashop-gallery__list">' . $list . '</ul>'
            . '<template class="dharmashop-gallery__tpl">' . $card('__i__', '', '') . '</template>'
            . '<div class="dharmashop-gallery__native" hidden>' . $native->inputHtml() . '</div>'
            . '</div>';
    }
}
