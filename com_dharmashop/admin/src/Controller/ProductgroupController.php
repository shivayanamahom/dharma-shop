<?php

/**
 * Dharma Shop — группы самостоятельных товаров.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;

\defined('_JEXEC') or die;

/** Группы товаров: нативные сохранение, отмена и блокировка формы Joomla. */
class ProductgroupController extends FormController
{
    use QuickCreateTrait;

    protected $option = 'com_dharmashop';
    protected $text_prefix = 'COM_DHARMASHOP';
    protected $view_list = 'productgroups';
    public function add()
    {
        $result = parent::add();
        if ($result && !$this->input->getBool('from_products')) {
            $this->app->setUserState('com_dharmashop.group.selection', null);
        }
        return $result;
    }

    public function cancel($key = null)
    {
        $result = parent::cancel($key);
        if ($result) { $this->app->setUserState('com_dharmashop.group.selection', null); }
        return $result;
    }

    protected function preprocessSaveData(array $data): array
    {
        $data = parent::preprocessSaveData($data);

        // Save As Copy duplicates the group's configuration, not its live product
        // assignments or an in-progress generation draft.
        if ($this->getTask() === 'save2copy') {
            $data['members'] = [];
            $data['executions'] = [];
            $data['revision'] = 0;
        }

        return $data;
    }

    protected function postSaveHook(\Joomla\CMS\MVC\Model\BaseDatabaseModel $model, $validData = [])
    {
        parent::postSaveHook($model, $validData);
        $this->completeQuickCreate();
    }

    public function generationFields(): void { $this->generatorAction('fields'); }
    public function prepare(): void { $this->generatorAction('prepare'); }
    public function generate(): void { $this->generatorAction('generate'); }

    private function generatorAction(string $action): void
    {
        $this->checkToken();
        if (!$this->input->post->getInt('generation_complete')) {
            $this->setMessage(\Joomla\CMS\Language\Text::_('COM_DHARMASHOP_GENERATOR_INCOMPLETE'), 'error');
            $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id=' . $this->input->getInt('id'), false));
            return;
        }
        $id = $this->input->getInt('id');
        $user = $this->app->getIdentity();
        if (!$this->checkEditId("$this->option.edit.$this->context", $id) || !$user->authorise('core.create', 'com_dharmashop')
            || !$user->authorise('core.edit', 'com_dharmashop')) {
            throw new \RuntimeException(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }
        $model = $this->getModel();
        $model->setState('productgroup.id', $id);
        $raw = (array) $this->input->post->get('jform', [], 'array');
        $raw['id'] = $id;
        $this->app->setUserState('com_dharmashop.edit.productgroup.data', $raw);
        try {
            $table = $model->getTable();
            if ($id && (!$table->load($id) || $table->isCheckedOut($user->id))) {
                throw new \RuntimeException('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED');
            }
            $form = $model->getForm($raw, false);
            $data = $model->validate($form, $raw);
            if ($data === false) { throw new \RuntimeException(implode('; ', array_map('strval', $model->getErrors()))); }
            if ($action === 'prepare') {
                $result = $model->prepareGeneration($id, $data);
                $raw['executions'] = $result['rows'];
                $this->setMessage(\Joomla\CMS\Language\Text::sprintf('COM_DHARMASHOP_GENERATOR_PREPARED', $result['added'], $result['skipped']));
            } elseif ($action === 'generate') {
                if (!$model->save($data)) { throw new \RuntimeException((string) $model->getError()); }
                $id = (int) $model->getState('productgroup.id');
                $raw['id'] = $id;
                $this->holdEditId("$this->option.edit.$this->context", $id);
                $saved = $model->getItem($id);
                $data['revision'] = (int) $saved->revision;
                $raw['alias'] = $saved->alias;
                // Keep all unfinished rows on an error, with the revision of the saved group.
                $raw['revision'] = $data['revision'];
                $result = $model->createExecutions($id, $data);
                $saved = $model->getItem($id);
                $raw['members'] = $saved->members;
                $raw['axes'] = $saved->axes;
                $raw['alias'] = $saved->alias;
                $raw['revision'] = (int) $saved->revision;
                $raw['executions'] = $result['remaining'];
                $message = \Joomla\CMS\Language\Text::sprintf('COM_DHARMASHOP_GROUP_CREATED', count($result['created']));
                $message .= ' ' . \Joomla\CMS\Language\Text::sprintf('COM_DHARMASHOP_GENERATOR_SKIPPED', $result['skipped']);
                if ($result['error'] !== '') { $message .= ' ' . $result['error']; }
                $this->setMessage($message, $result['error'] !== '' ? 'warning' : 'message');
            }
        } catch (\Throwable $e) {
            $this->setMessage(\Joomla\CMS\Language\Text::_($e->getMessage()), 'error');
        }
        $this->app->setUserState('com_dharmashop.edit.productgroup.data', $raw);
        $this->setRedirect(\Joomla\CMS\Router\Route::_('index.php?option=com_dharmashop&view=productgroup&layout=edit&id=' . $id, false));
    }
}
