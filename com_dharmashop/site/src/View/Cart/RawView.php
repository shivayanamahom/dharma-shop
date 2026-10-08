<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Cart;

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
        parent::display($tpl);
    }
}
