<?php

/**
 * Dharma Shop — переход из карточки к созданию поля после её сохранения.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Controller;

use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

trait QuickCreateTrait
{
    private ?string $quickCreateKind = null;
    private array $quickFieldIds = [];

    public function quickField(): void
    {
        $this->queueQuickCreate('field');
    }

    public function quickFieldGroup(): void
    {
        $this->queueQuickCreate('fieldgroup');
    }

    public function quickFields(): void
    {
        $this->queueQuickCreate('fields');
    }

    private function queueQuickCreate(string $kind): void
    {
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.create', 'com_dharmashop') || !$user->authorise('dharmashop.fields.manage', 'com_dharmashop')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'), 403);
        }

        $target = $this instanceof CategoryController ? 'category' : 'product';
        $filterKey = $kind === 'field' ? 'fields.filter' : 'fieldgroups.filter';
        $this->app->setUserState('com_dharmashop.' . $filterKey, ['target' => $target]);
        $this->app->setUserState('com_dharmashop.' . ($kind === 'field' ? 'fieldgroups.filter' : 'fields.filter'), ['target' => $target]);
        if ($kind === 'fields') {
            $this->quickFieldIds = $this->collectFieldIds((array) $this->input->post->get('jform', [], 'array'));
        }
        $this->quickCreateKind = $kind;

        // Use the regular FormController apply flow so the complete edit form is saved and
        // checked in before the dialog is opened.
        $this->execute('apply');
    }

    protected function completeQuickCreate(): void
    {
        if ($this->quickCreateKind !== null) {
            $this->app->setUserState('com_dharmashop.quick_create', $this->quickCreateKind);
            $this->app->setUserState('com_dharmashop.quick_field_ids', $this->quickFieldIds);
        }
    }

    private function collectFieldIds(array $value): array
    {
        $ids = [];
        $walk = static function (array $node) use (&$walk, &$ids): void {
            foreach ($node as $key => $child) {
                if (preg_match('/^f_(\d+)$/', (string) $key, $match)) {
                    $ids[] = (int) $match[1];
                } elseif (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($value);

        return array_values(array_unique(array_filter($ids)));
    }
}
