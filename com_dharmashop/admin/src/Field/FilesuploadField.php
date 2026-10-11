<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;
use Dharma\Component\DharmaShop\Administrator\Fields\FileStorage;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\MediaField;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
\defined('_JEXEC') or die;

/** Independent collection per control; shares the gallery transport and drag/drop code. */
class FilesuploadField extends FormField
{
    protected $type = 'Filesupload';
    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.gallery')->useStyle('com_dharmashop.gallery');
        $settings = [];
        foreach (FileStorage::settings([]) as $key=>$default) { $settings[$key] = isset($this->element[$key]) ? (string) $this->element[$key] : $default; }
        $settings = FileStorage::settings($settings);
        $fieldId = (int) $this->element['field_id'];
        $storage = new FileStorage(JPATH_ROOT, $fieldId, $settings);
        $attr = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $native = new class ($this->form) extends MediaField { public function inputHtml(): string { return $this->getInput(); } };
        $native->setup(new \SimpleXMLElement('<field name="dharma_files_native_' . hash('xxh64', $this->id) . '" type="media" types="documents,images" preview="false" />'), '');
        $card = function (string $index, array $item) use ($attr): string {
            $base = $attr($this->name) . '[' . $index . ']';
            $filename = (string) ($item['filename'] ?? basename((string) ($item['path'] ?? '')));
            return '<li class="dharmashop-gallery__item position-relative">'
                . '<input type="hidden" data-field="path" name="' . $base . '[path]" value="' . $attr($item['path'] ?? '') . '">'
                . '<div class="pe-4"><span class="icon-file me-1" aria-hidden="true"></span><span class="dharmashop-gallery__filename text-break">' . $attr($filename) . '</span></div>'
                . '<small class="dharmashop-gallery__details text-muted">' . $attr(isset($item['bytes']) ? number_format((float) $item['bytes'] / 1024, 1, ',', ' ') . ' KiB' : '') . '</small>'
                . '<label class="small">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_CAPTION'))
                . '<input type="text" class="form-control form-control-sm" data-field="title" name="' . $base . '[title]" value="' . $attr($item['title'] ?? '') . '" maxlength="255"></label>'
                . '<div class="d-flex gap-1">'
                . '<button type="button" class="btn btn-sm btn-outline-secondary dharmashop-gallery__up" aria-label="' . $attr(Text::_('JLIB_HTML_MOVE_UP')) . '"><span class="icon-arrow-up" aria-hidden="true"></span></button>'
                . '<button type="button" class="btn btn-sm btn-outline-secondary dharmashop-gallery__down" aria-label="' . $attr(Text::_('JLIB_HTML_MOVE_DOWN')) . '"><span class="icon-arrow-down" aria-hidden="true"></span></button></div>'
                . '<button type="button" class="dharmashop-gallery__remove btn btn-sm btn-danger" aria-label="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_REMOVE')) . '"><span class="icon-times" aria-hidden="true"></span></button></li>';
        };
        $items = '';
        foreach ((array) $this->value as $index=>$item) {
            $item = (array) $item;
            if (!empty($item['path'])) { $items .= $card((string) $index, $item); }
        }
        $maxMb = round($storage->maxBytes(true) / 1048576, 2);
        return '<div id="' . $attr($this->id) . '" class="dharmashop-gallery" data-kind="files" data-name="' . $attr($this->name) . '" data-field-id="' . $fieldId
            . '" data-entity-id="' . (int) $this->form->getValue('id', null, 0)
            . '" data-url="index.php?option=com_dharmashop&amp;task=media.uploadFile&amp;format=json&amp;owner=' . ($this->element['owner'] === 'group' ? 'group' : '') . '" data-select-url="index.php?option=com_dharmashop&amp;task=media.selectFile&amp;format=json"'
            . ' data-root="' . $attr(Uri::root()) . '" data-token="' . $attr(Session::getFormToken()) . '" data-max-count="' . (int) $settings['files_max_count']
            . '" data-max-bytes="' . $storage->maxBytes(true) . '" data-accept="' . $attr($settings['files_extensions'])
            . '" data-error-count="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_COUNT_INVALID'))
            . '" data-error-type="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID'))
            . '" data-error-size="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_SIZE_INVALID'))
            . '" data-error-server="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_SERVER')) . '">'
            . '<input type="hidden" name="' . $attr($this->name) . '[_][path]" value="">'
            . '<input type="file" class="dharmashop-gallery__file visually-hidden" tabindex="-1" aria-hidden="true" multiple accept=".' . $attr(str_replace(',', ',.', $settings['files_extensions'])) . '">'
            . '<div class="dharmashop-gallery__drop" tabindex="0" role="button" aria-label="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_DROP')) . '">'
            . '<span class="icon-upload dharmashop-gallery__icon" aria-hidden="true"></span><span class="dharmashop-gallery__title">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_DROP')) . '</span>'
            . '<span class="dharmashop-gallery__actions"><button type="button" class="btn btn-link dharmashop-gallery__choose">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_CHOOSE')) . '</button>'
            . '<span aria-hidden="true">·</span><button type="button" class="btn btn-link dharmashop-gallery__server">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_FROM_SERVER')) . '</button></span>'
            . '<small class="dharmashop-gallery__limits">' . $attr(Text::sprintf('PLG_DHARMASHOPFIELD_BASIC_FILES_LIMITS', strtoupper($settings['files_extensions']), $maxMb, $settings['files_max_count'])) . '</small></div>'
            . '<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>'
            . '<ul class="dharmashop-gallery__list">' . $items . '</ul><template class="dharmashop-gallery__tpl">' . $card('__i__', []) . '</template>'
            . '<div class="dharmashop-gallery__native" hidden>' . $native->inputHtml() . '</div></div>';
    }
}
