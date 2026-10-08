<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Cart;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Фрагмент мини-корзины (`format=raw`) для боковой панели модуля: только содержимое макета `mini` (tmpl/cart/mini.php).
 * Те же данные, что у страницы корзины; режим каталога и кеш обрабатывает родительский вид.
 */
class RawView extends HtmlView
{
    public function display($tpl = null): void
    {
        $this->setLayout('mini');
        $input = Factory::getApplication()->getInput();

        // Что показывать в строках, задаёт модуль в адресе фрагмента (только отображение, на данные не влияет).
        foreach (['image' => 1, 'category' => 1, 'sku' => 0, 'unit' => 1] as $key => $default) {
            $this->mini[$key] = $input->getInt('mc_' . $key, $default) === 1;
        }

        parent::display($tpl);
    }
}
