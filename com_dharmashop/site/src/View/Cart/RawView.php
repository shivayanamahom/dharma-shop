<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Cart;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Фрагмент мини-корзины (`format=raw`) для боковой панели модуля: содержимое панели (шаблон модуля `default_items.php`).
 * Те же данные, что у страницы корзины; режим каталога и кеш обрабатывает родительский вид. Что показывать и какие классы у кнопок,
 * берётся из настроек модуля корзины (номер модуля — параметр `mid`; на данные корзины это не влияет).
 */
class RawView extends HtmlView
{
    public function display($tpl = null): void
    {
        $input = Factory::getApplication()->getInput();

        // layout=page — содержимое страницы корзины (обновляется скриптом cart-page.js), иначе — мини-корзина модуля.
        if ($input->getCmd('layout') === 'page') {
            $this->setLayout('default');
            parent::display('content');

            return;
        }

        $this->setLayout('mini');
        $params = $this->moduleParams($input->getInt('mid', 0));
        $this->mini = [
            'image' => (int) $params->get('show_image', 1) === 1,
            'category' => (int) $params->get('show_category', 1) === 1,
            'sku' => (int) $params->get('show_sku', 0) === 1,
            'unit' => (int) $params->get('show_unit_price', 1) === 1,
            'discount' => (int) $params->get('show_discount', 1) === 1,
            'checkout' => (int) $params->get('show_checkout', 1) === 1,
            'cart_class' => $this->classes((string) $params->get('cart_button_class', ''), 'btn btn-outline-primary'),
            'checkout_class' => $this->classes((string) $params->get('checkout_button_class', ''), 'btn btn-primary'),
        ];

        parent::display($tpl);
    }

    /**
     * Мини-корзина: разметку берём из шаблона модуля (`default_items.php`, с учётом переопределения в шаблоне сайта); файл подключается
     * здесь, поэтому в нём `$this` — этот вид. Остальные макеты — как обычно.
     */
    public function loadTemplate($tpl = null)
    {
        if ($this->getLayout() !== 'mini') {
            return parent::loadTemplate($tpl);
        }

        $path = ModuleHelper::getLayoutPath('mod_dharmashop_cart', 'default_items');

        if (!is_file($path)) {
            return '';
        }

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    /** Параметры опубликованного модуля корзины сайта; без номера или при его ошибке — значения по умолчанию. */
    private function moduleParams(int $id): Registry
    {
        if ($id <= 0) {
            return new Registry();
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $module = 'mod_dharmashop_cart';
        $query = $db->createQuery()->select($db->quoteName('params'))->from($db->quoteName('#__modules'))
            ->where($db->quoteName('id') . ' = :id')->where($db->quoteName('module') . ' = :module')
            ->where($db->quoteName('client_id') . ' = 0')->where($db->quoteName('published') . ' = 1')
            ->bind(':id', $id, ParameterType::INTEGER)->bind(':module', $module);

        return new Registry((string) $db->setQuery($query)->loadResult());
    }

    /** Классы кнопки из настроек: только буквы, цифры, «-» и «_», иначе значение по умолчанию. */
    private function classes(string $value, string $default): string
    {
        $value = trim((string) preg_replace('/[^A-Za-z0-9_\- ]+/', '', $value));

        return $value !== '' ? (string) preg_replace('/\s+/', ' ', $value) : $default;
    }
}
