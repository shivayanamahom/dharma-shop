<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Dharma\Component\DharmaShop\Site\Support\GroupCardContext;
use Dharma\Component\DharmaShop\Site\Support\GroupCardRenderer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

\defined('_JEXEC') or die;

/** Read-only request for one selected variant. Signed scope, fresh ACL checks, no writes to cart/catalog. */
final class GroupcardController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function select(): void
    {
        $result = ['success' => false, 'message' => Text::_('COM_DHARMASHOP_GROUP_CARD_ERROR')];
        try {
            $token = $this->input->getString('context');
            $data = GroupCardContext::decode($token);
            $scope = $data['scope'];
            $this->input->set('id', (int) $scope['id']);
            $this->input->set('filter', (array) $scope['filter']);
            $this->input->set('q', (string) $scope['search']);
            $this->input->set('filter_module', (int) ($scope['filter_module'] ?? 0));
            $this->input->set('Itemid', (int) ($scope['itemid'] ?? 0));
            $factory = $this->app->bootComponent('com_dharmashop')->getMVCFactory();
            $model = $factory->createModel(ucfirst($scope['view']), 'Site', ['ignore_request' => true]);
            $model->forCategory((int) $scope['id']);
            $params = clone ComponentHelper::getParams('com_dharmashop');
            foreach ($data['params'] as $key => $value) { if ($value !== null) { $params->set($key, $value); } }
            $images = (string) $params->get('product_card_images', '1');
            $limit = in_array($images, ['0', '1', '2', '3', '4', '5'], true) ? (int) $images : 1;
            $item = $model->selectGroupCardProduct($this->input->getInt('source'), (array) $this->input->get('choice', [], 'array'), $limit);
            $result = ['success' => true, 'product' => (int) $item->id,
                'html' => (new GroupCardRenderer())->render($item, $params, $data['mode'], $data['columns'], null, $token, '', (string) ($data['favoriteActiveClass'] ?? 'text-danger'))];
        } catch (\Throwable $error) {
            if ((int) $error->getCode() === 404) { $result['message'] = Text::_('COM_DHARMASHOP_GROUP_CARD_UNAVAILABLE'); $result['reason'] = 'unavailable'; }
            else { \Joomla\CMS\Log\Log::add('Group card selection: ' . $error->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_dharmashop'); }
        }
        $this->app->allowCache(false);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true); $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('X-Robots-Tag', 'noindex', true);
        $this->app->sendHeaders();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->app->close();
    }
}
