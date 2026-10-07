<?php

/**
 * Dharma Shop — универсальный лёгкий интернет-магазин для Joomla 6+.
 *
 * @package    com_dharmashop
 * @copyright  (C) 2026 Dharma. Все права защищены.
 * @license    GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Model;

use Dharma\Component\DharmaShop\Administrator\Seo\SeoHints;
use Dharma\Component\DharmaShop\Administrator\Seo\SeoSettings;
use Dharma\Component\DharmaShop\Administrator\Support\CategoryTypes;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Table;
use Joomla\Registry\Registry;

\defined('_JEXEC') or die;

/**
 * Страница корня раздела (корневая категория, корневой бренд): заголовок, тексты, изображение и SEO.
 * Данные лежат JSON-ом в настройках компонента: root_category, root_brand (ARCHITECTURE.md §5.18).
 */
class RootModel extends FormModel
{
    /** Ключ раздела: category или brand (бейджи плоские — корня у них нет). */
    public function getKind(): string
    {
        return CategoryTypes::normalize((string) Factory::getApplication()->getInput()->getString('extension', CategoryTypes::CATEGORY)) === CategoryTypes::BRAND ? 'brand' : 'category';
    }

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_dharmashop.root.' . $this->getKind(), 'root', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        SeoHints::apply($form, new SeoSettings(ComponentHelper::getParams('com_dharmashop')), $this->getKind());

        return $form;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_dharmashop.edit.root.' . $this->getKind() . '.data', []);

        return $data ?: $this->getItem();
    }

    /** @return array<string, mixed> сохранённые данные страницы корня */
    public function getItem(): array
    {
        $value = ComponentHelper::getParams('com_dharmashop')->get('root_' . $this->getKind());

        return $value === null ? [] : (json_decode((string) json_encode($value), true) ?: []);
    }

    /** Проверяет данные формы и записывает в настройки компонента. */
    public function save(array $data): bool
    {
        $form = $this->getForm([], false);

        if (!$form) {
            return false;
        }

        $data = $form->filter($data);

        if ($form->validate($data) === false) {
            foreach ($form->getErrors() as $error) {
                $this->setError($error instanceof \Exception ? $error->getMessage() : (string) $error);
            }

            return false;
        }

        $table = Table::getInstance('extension');

        if (!$table->load(['element' => 'com_dharmashop', 'type' => 'component'])) {
            $this->setError(Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));

            return false;
        }

        $params = new Registry($table->params);
        $params->set('root_' . $this->getKind(), $data);
        $table->params = $params->toString();

        if (!$table->check() || !$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        $this->cleanCache('_system');

        return true;
    }
}
