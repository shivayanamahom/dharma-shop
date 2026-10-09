<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Field;

use Dharma\Plugin\DharmaShopField\Basic\Type\VideoType;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\MediaField;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

\defined('_JEXEC') or die;

/** Combined uploader and repeatable provider links; isolated per field and entity. */
class VideosuploadField extends FormField
{
    protected $type = 'Videosupload';

    protected function getInput(): string
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_dharmashop');
        $wa->useScript('com_dharmashop.gallery')->useStyle('com_dharmashop.gallery');
        $rawSettings = [];
        foreach ($this->element->attributes() as $name=>$value) { $rawSettings[(string) $name] = (string) $value; }
        $settings = VideoType::currentSettings($rawSettings);
        $fieldId = (int) $this->element['field_id'];
        $attr = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $native = new class ($this->form) extends MediaField {
            public function inputHtml(): string { return $this->getInput(); }
        };
        $native->setup(new \SimpleXMLElement('<field name="dharmashop_video_native_' . hash('xxh64', $this->id) . '" type="media" types="videos" directory="dharmashop/videos" preview="false" />'), '');
        $mimeByProvider = ['youtube'=>'application/vnd.dharmashop.video.youtube','rutube'=>'application/vnd.dharmashop.video.rutube','vk'=>'application/vnd.dharmashop.video.vk'];
        $card = function (string $index, array $item) use ($attr, $mimeByProvider): string {
            $base = $attr($this->name) . '[' . $index . ']';
            $mime = (string) ($item['mime'] ?? '');
            $source = array_search($mime, $mimeByProvider, true) ?: 'local';
            $remote = $source !== 'local';
            $path = (string) ($item['path'] ?? '');
            $url = $remote ? $path : '';
            $poster = (string) ($item['alt'] ?? '');
            $filename = (string) ($item['filename'] ?? basename($path));
            $details = $remote ? Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_' . strtoupper($source))
                : (isset($item['bytes']) ? number_format((float) $item['bytes'] / 1048576, 1, ',', ' ') . ' MiB' : '');
            return '<li class="dharmashop-gallery__item position-relative dharmashop-video__item">'
                . '<input type="hidden" data-field="path" name="' . $base . '[path]" value="' . $attr($path) . '">'
                . '<input type="hidden" data-field="poster" name="' . $base . '[poster]" value="' . $attr($poster) . '">'
                . '<input type="hidden" data-field="mime" name="' . $base . '[mime]" value="' . $attr($mime) . '">'
                . '<input type="hidden" data-field="url" name="' . $base . '[url]" value="' . $attr($url) . '">'
                . '<input type="hidden" data-field="source" value="' . $attr($source) . '">'
                . '<img class="dharmashop-video__poster img-fluid rounded mb-2"' . ($poster !== '' ? ' src="' . $attr(Uri::root() . $poster) . '"' : ' hidden') . ' alt="" width="240" loading="lazy">'
                . '<div class="mb-2 text-body-secondary dharmashop-video__icon"' . ($poster !== '' ? ' hidden' : '') . '><span class="icon-video" aria-hidden="true"></span></div>'
                . '<div class="pe-4"><span class="dharmashop-gallery__filename text-break">' . $attr($remote ? $details : $filename) . '</span></div>'
                . '<label class="small">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_TITLE'))
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
        $extensions = explode(',', $settings['video_extensions']);
        return '<div id="' . $attr($this->id) . '" class="dharmashop-gallery dharmashop-video" data-kind="video" data-name="' . $attr($this->name) . '" data-field-id="' . $fieldId
            . '" data-entity-id="' . (int) $this->form->getValue('id', null, 0)
            . '" data-url="index.php?option=com_dharmashop&amp;task=media.uploadVideo&amp;format=json" data-select-url="index.php?option=com_dharmashop&amp;task=media.selectVideo&amp;format=json" data-resolve-url="index.php?option=com_dharmashop&amp;task=media.resolveVideo&amp;format=json"'
            . ' data-root="' . $attr(Uri::root()) . '" data-token="' . $attr(Session::getFormToken()) . '" data-max-count="' . (int) $settings['video_max_count']
            . '" data-max-bytes="0" data-accept="' . $attr($settings['video_extensions']) . '" data-fetch-cover="' . (int) $settings['video_fetch_cover']
            . '" data-error-count="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_COUNT_INVALID'))
            . '" data-error-url="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL_INVALID'))
            . '" data-error-type="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_TYPE_INVALID'))
            . '" data-error-size="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_FILES_SIZE_INVALID'))
            . '" data-error-server="' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_ERROR_SERVER')) . '">'
            . '<input type="hidden" name="' . $attr($this->name) . '[_][path]" value="">'
            . '<input type="file" class="dharmashop-gallery__file visually-hidden" tabindex="-1" aria-hidden="true" multiple accept=".' . $attr(implode(',.', $extensions)) . '">'
            . '<div class="dharmashop-gallery__drop" tabindex="0" role="button" aria-label="' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_DROP')) . '">'
            . '<span class="icon-upload dharmashop-gallery__icon" aria-hidden="true"></span><span class="dharmashop-gallery__title">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_DROP')) . '</span>'
            . '<span class="dharmashop-gallery__actions"><button type="button" class="btn btn-link dharmashop-gallery__choose">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_CHOOSE')) . '</button><span aria-hidden="true">·</span><button type="button" class="btn btn-link dharmashop-gallery__server">' . $attr(Text::_('COM_DHARMASHOP_UPLOAD_FROM_SERVER')) . '</button></span>'
            . '<small class="dharmashop-gallery__limits">' . $attr(Text::sprintf('PLG_DHARMASHOPFIELD_BASIC_VIDEO_LIMITS', strtoupper($settings['video_extensions']), $settings['video_max_count'])) . '</small></div>'
            . '<div class="d-flex flex-wrap align-items-end gap-2 mt-3 dharmashop-video__addform">'
            . '<label class="flex-grow-1">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_PROVIDER')) . '<select class="form-select dharmashop-video__provider"><option value="youtube">YouTube</option><option value="rutube">RUTUBE</option><option value="vk">VK Видео</option></select></label>'
            . '<label class="flex-grow-1">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_URL')) . '<input type="url" class="form-control dharmashop-video__url" placeholder="https://…"></label>'
            . '<button type="button" class="btn btn-outline-primary dharmashop-video__add">' . $attr(Text::_('PLG_DHARMASHOPFIELD_BASIC_VIDEO_ADD_LINK')) . '</button></div>'
            . '<div class="progress mt-2" hidden><div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div></div>'
            . '<ul class="dharmashop-gallery__list list-unstyled">' . $items . '</ul><template class="dharmashop-gallery__tpl">' . $card('__i__', []) . '</template>'
            . '<div class="dharmashop-gallery__native" hidden>' . $native->inputHtml() . '</div></div>';
    }
}
