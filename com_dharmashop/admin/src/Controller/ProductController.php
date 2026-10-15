<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Dharma\Component\DharmaShop\Administrator\Support\BatchProgressResponse;

\defined('_JEXEC') or die;

/**
 * Правка одного товара: добавление, сохранение, отмена — штатная логика FormController.
 */
class ProductController extends FormController
{
    use QuickCreateTrait;

    protected $option = 'com_dharmashop';

    protected $text_prefix = 'COM_DHARMASHOP';

    protected $view_list = 'products';

    protected function allowAdd($data = []): bool
    {
        $categoryId = (int) ($data['catid'] ?? $this->input->getInt('filter_category_id'));

        return $categoryId
            ? $this->app->getIdentity()->authorise('core.create', 'com_dharmashop.category.' . $categoryId)
            : parent::allowAdd($data);
    }

    protected function allowEdit($data = [], $key = 'id'): bool
    {
        $recordId = (int) ($data[$key] ?? 0);
        $user = $this->app->getIdentity();

        if ($user->authorise('core.edit', 'com_dharmashop.product.' . $recordId)) {
            return true;
        }

        return $user->authorise('core.edit.own', 'com_dharmashop.product.' . $recordId)
            && (int) ($data['created_by'] ?? 0) === (int) $user->id;
    }

    protected function preprocessSaveData(array $data): array
    {
        $data = parent::preprocessSaveData($data);

        // A copied variant becomes an independent product; it must not be attached to
        // the original group as a duplicate combination.
        if ($this->getTask() === 'save2copy') {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $baseTitle = Text::sprintf('COM_DHARMASHOP_COPY_TITLE', (string) ($data['title'] ?? ''));
            $title = $baseTitle;
            $copyNumber = 2;

            do {
                $query = $db->createQuery()->select('id')->from($db->quoteName('#__dharmashop_products'))
                    ->where('catid = ' . (int) ($data['catid'] ?? 0))
                    ->where('title = ' . $db->quote($title));
                $exists = (bool) $db->setQuery($query, 0, 1)->loadResult();

                if ($exists) {
                    $title = Text::sprintf('COM_DHARMASHOP_COPY_TITLE_NUMBERED', $copyNumber++, $baseTitle);
                }
            } while ($exists);

            $data['title'] = mb_substr($title, 0, 255);
            $data['alias'] = '';
            $data['group_id'] = 0;
            $data['sku'] = '';
            $data['inherit_intro'] = 0;
            $data['inherit_full'] = 0;
            $data['inherit_images'] = 0;
        }

        return $data;
    }

    /** Save the entire product through Joomla before opening a prefilled, unsaved group. */
    public function createGroup(): void
    {
        $this->checkToken();
        $raw = (array) $this->input->post->get('jform', [], 'array');
        if (!$this->app->getIdentity()->authorise('core.create', 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }
        if ((int) ($raw['group_id'] ?? 0) > 0) {
            $this->app->setUserState('com_dharmashop.edit.product.data', $raw);
            $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=product&layout=edit&id=' . $this->input->getInt('id'), false), Text::_('COM_DHARMASHOP_GROUP_PICKER_ALREADY'), 'warning');
            return;
        }
        parent::save();
    }

    protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
    {
        parent::postSaveHook($model, $validData);
        $this->completeQuickCreate();
        if (strtolower($this->getTask()) !== 'creategroup') { return; }
        $id = (int) $model->getState('product.id');
        $seed = ['id' => 0, 'title' => $validData['title'], 'state' => 0, 'axes' => [], 'members' => [['product_id' => $id]], 'revision' => 0];
        foreach (['catid', 'extra_catids', 'brand_id', 'badge_ids'] as $name) { $seed[$name] = $validData[$name] ?? []; }
        $this->app->setUserState('com_dharmashop.group.selection', null);
        $this->app->setUserState('com_dharmashop.edit.productgroup.data', $seed);
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit', false));
    }

    /** Пакетная обработка выбранных товаров (окно «Пакетная обработка» на списке). */
    public function batch($model = null)
    {
        $this->checkToken();

        $model = $this->getModel('Product', 'Administrator', []);
        if ($this->input->getBool('batch_ajax')) {
            $user = $this->app->getIdentity();
            if (!$user->authorise('core.create', 'com_dharmashop') || !$user->authorise('core.edit', 'com_dharmashop')) {
                BatchProgressResponse::send(['success' => false, 'error' => Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN')], 403);
            }
            $ids = array_values(array_filter((array) $this->input->post->get('cid', [], 'int')));
            $contexts = [];
            foreach ($ids as $id) { $contexts[$id] = 'com_dharmashop.product.' . $id; }
            $ok = $model->batch((array) $this->input->post->get('batch', [], 'array'), $ids, $contexts);
            BatchProgressResponse::send($ok
                ? ['success' => true, 'processed' => count($ids)]
                : ['success' => false, 'error' => $model->getError()], $ok ? 200 : 400);
        }
        $this->setRedirect(Route::_('index.php?option=com_dharmashop&view=products' . $this->getRedirectToListAppend(), false));

        return parent::batch($model);
    }
}
