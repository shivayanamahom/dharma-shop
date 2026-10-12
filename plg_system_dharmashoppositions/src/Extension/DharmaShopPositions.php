<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopPositions\Extension;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
\defined('_JEXEC') or die;
/** Expose component-owned positions beside native template positions. No site handler. */
final class DharmaShopPositions extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;
    public static function getSubscribedEvents(): array { return ['onContentPrepareForm' => 'prepareForm']; }
    public function prepareForm(EventInterface $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) { return; }
        $form = $event->getArgument('subject');
        if (!$form instanceof Form || $form->getName() !== 'com_modules.module') { return; }
        $data = (array) $event->getArgument('data', []);
        if ((int) ($data['client_id'] ?? $form->getValue('client_id', null, 0)) !== 0) { return; }
        $this->getApplication()->bootComponent('com_dharmashop');
        FormHelper::addFieldPrefix('Dharma\\Component\\DharmaShop\\Administrator\\Field');
        $form->setFieldAttribute('position', 'type', 'Shopmoduleposition');
    }
}
