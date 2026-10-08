<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Administrator\Order\CheckoutFields;
use Dharma\Component\DharmaShop\Administrator\Shipping\ShippingInputException;
use Dharma\Component\DharmaShop\Site\Support\CartSession;
use Dharma\Component\DharmaShop\Site\Support\OrderAccess;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
\defined('_JEXEC') or die;

class CheckoutController extends BaseController
{
    protected $option = 'com_dharmashop';
    public function submit(): void
    {
        $url = Route::_('index.php?option=com_dharmashop&view=checkout',false);
        $settings = ComponentHelper::getParams($this->option);
        if ($this->input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_CART_ERROR_TOKEN'),'warning'); return;
        }
        if ((int) $settings->get('catalog_mode',0) === 1) {
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_CART_ERROR_CATALOG'),'warning'); return;
        }
        $identity = $this->app->getIdentity();
        $userId = $identity && !$identity->guest ? (int) $identity->id : 0;
        if ($userId === 0 && (int) $settings->get('checkout_guest',1) !== 1) {
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_CHECKOUT_LOGIN_REQUIRED'),'warning'); return;
        }
        $key = $this->input->post->getString('checkout_key');
        $pending = (array) $this->app->getUserState('com_dharmashop.checkout.pending',[]);
        if (!$key || !isset($pending['key']) || !hash_equals((string) $pending['key'],$key) || (int) ($pending['user_id'] ?? -1) !== $userId) {
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_ORDER_INVALID'),'warning'); return;
        }
        $shop = $this->app->bootComponent($this->option)->getServices();
        $session = new CartSession($shop->cart(),$this->app);
        $cart = $session->current();
        if (!$cart || (int) $cart->id !== (int) ($pending['cart_id'] ?? 0)) {
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_CART_EMPTY'),'warning'); return;
        }
        // Доставка: id способа и данные по способу; проверку и цену выполняет сервер при создании заказа.
        $shipping = ['method'=>$this->input->post->getInt('shipping_method'),'input'=>$this->input->post->get('shipping',[],'array')];
        $this->app->setUserState('com_dharmashop.checkout.shipping',['method'=>$shipping['method'],'input'=>$shipping['input'],'errors'=>[]]);
        $validated = CheckoutFields::validate($settings,$this->input->post->get('recipient',[],'array'),$this->input->post->get('consents',[],'array'));
        if ($validated['errors']) {
            $this->app->setUserState('com_dharmashop.checkout.values',$validated['values']);
            $this->app->setUserState('com_dharmashop.checkout.errors',$validated['errors']);
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_ORDER_FIELDS_ERROR'),'warning'); return;
        }
        try {
            $result = $shop->orders()->create($cart,$userId,$key,$validated,$settings,$shipping);
        } catch (ShippingInputException $error) {
            $this->app->setUserState('com_dharmashop.checkout.values',$validated['values']);
            $this->app->setUserState('com_dharmashop.checkout.shipping',['method'=>$shipping['method'],'input'=>$shipping['input'],'errors'=>$error->errors()]);
            $this->setRedirect($url,$error->getMessage(),'warning'); return;
        } catch (\DomainException $error) {
            $this->app->setUserState('com_dharmashop.checkout.values',$validated['values']);
            $this->setRedirect($url,$error->getMessage(),'warning'); return;
        } catch (\Throwable $error) {
            // В ответ не отправляем SQL/реквизиты подключения или контакты посетителя.
            Log::add('Checkout create failed: ' . get_class($error),Log::ERROR,'com_dharmashop');
            $this->app->setUserState('com_dharmashop.checkout.values',$validated['values']);
            $this->setRedirect($url,Text::_('COM_DHARMASHOP_ORDER_SAVE_FAILED'),'error'); return;
        }
        $this->app->setUserState('com_dharmashop.checkout.values',null);
        $this->app->setUserState('com_dharmashop.checkout.errors',null);
        $this->app->setUserState('com_dharmashop.checkout.shipping',null);
        $this->app->setUserState('com_dharmashop.checkout.receipt',$result + ['user_id'=>$userId,'time'=>time()]);
        OrderAccess::remember($this->app, (int) $result['id'], $userId);
        $pending['used'] = true;
        $this->app->setUserState('com_dharmashop.checkout.pending',$pending);
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=checkoutcomplete',false));
    }
}
