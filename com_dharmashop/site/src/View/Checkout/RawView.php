<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\View\Checkout;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/**
 * Фрагменты оформления «в один шаг» (C100, `layout=onepage&format=raw`): `part=cart` — состав корзины, `part=form` — форма заказа
 * и боковая панель (или сообщение о пустой корзине). Данные те же, что у страницы: доставка, оплата и
 * итоги считаются сервером по текущему составу корзины. Скрипт checkout-onepage.js подменяет ими блоки страницы.
 */
class RawView extends HtmlView
{
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $this->onepage = true;
        $this->setLayout('onepage');
        parent::display($app->getInput()->getCmd('part') === 'cart' ? 'cart' : 'fragment');
    }
}
