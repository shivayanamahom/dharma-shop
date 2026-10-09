<?php
declare(strict_types=1);
namespace Dharma\Plugin\System\DharmaShopRelated\Extension;
use Dharma\Component\DharmaShop\Site\Support\ProductCardRenderer;
use Dharma\Plugin\System\DharmaShopRelated\Support\Blocks;
use Dharma\Plugin\System\DharmaShopRelated\Support\Products;
use Dharma\Plugin\System\DharmaShopRelated\Support\Relations;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;
/** Связанные товары: нативные формы Joomla и существующие точки вывода страницы товара. */
final class DharmaShopRelated extends CMSPlugin implements SubscriberInterface
{
    protected $allowLegacyListeners = false;
    private array $prepared = [];
    private array $rendered = [];
    private ?ProductCardRenderer $cards = null;
    private bool $stepperPrinted = false;
    public static function getSubscribedEvents(): array
    {
        $events = ['onContentPrepareForm' => 'prepareForm', 'onExtensionBeforeSave' => 'saveSettings',
            'onContentBeforeSave' => 'saveProduct', 'onContentAfterSave' => 'indexProduct',
            'onContentAfterDelete' => 'deleteProduct'];
        foreach (Blocks::POSITIONS as $position) {
            $events['onDharmaShopProduct' . ucfirst($position)] = 'renderProducts';
        }
        return $events;
    }
    private function root(): string { return dirname(__DIR__, 2); }
    public function prepareForm(EventInterface $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) { return; }
        $form = $event->getArgument('subject');
        if (!$form instanceof Form) { return; }
        $input = $this->getApplication()->getInput();
        if ($form->getName() === 'com_config.component' && $input->getCmd('component') === 'com_dharmashop') {
            $this->loadLanguage();
            $this->getApplication()->bootComponent('com_dharmashop');
            $form->loadFile($this->root() . '/forms/settings.xml', false);
            $form->setFieldAttribute('related_blocks', 'formsource', 'plugins/system/dharmashoprelated/forms/block.xml');
            // После отказа model->save com_config хранит данные как params/id/option, а форма ожидает плоские поля.
            $failed = (array) $event->getArgument('data', []);
            if (($failed['option'] ?? '') === 'com_dharmashop' && isset($failed['params']) && is_array($failed['params'])) {
                $form->bind($failed['params']);
            }
            return;
        }
        if ($form->getName() !== 'com_dharmashop.product') { return; }
        $settings = ComponentHelper::getParams('com_dharmashop');
        if ((int) $settings->get('related_enabled', 0) !== 1) { return; }
        $this->loadLanguage();
        $xml = new \SimpleXMLElement('<form><fields name="params"><fieldset name="extension_related" label="PLG_SYSTEM_DHARMASHOPRELATED_TAB" /></fields></form>');
        $fieldset = $xml->fields->fieldset;
        $data = $event->getArgument('data', []);
        $data = (array) $data;
        $currentId = (int) ($data['id'] ?? 0);
        $currentParams = $data['params'] ?? [];
        $currentParams = $currentParams instanceof Registry ? $currentParams : new Registry($currentParams);
        $manual = false;
        foreach (Blocks::normalize($settings->get('related_blocks', [])) as $block) {
            if ($block['mode'] !== 'manual') { continue; }
            $manual = true;
            $name = 'related_' . $block['alias'];
            $field = $fieldset->addChild('field');
            foreach (['name' => $name, 'type' => 'subform', 'label' => $block['title'], 'description' => 'PLG_SYSTEM_DHARMASHOPRELATED_MANUAL_DESC',
                'multiple' => 'true', 'layout' => 'joomla.form.field.subform.repeatable', 'validate' => 'Subform',
                'min' => '0', 'max' => '100', 'translate_label' => 'false',
                'formsource' => 'administrator/components/com_dharmashop/forms/product_selection.xml'] as $key => $value) {
                $field->addAttribute($key, $value);
            }
            $marker = $fieldset->addChild('field');
            foreach (['name' => $name . '_present', 'type' => 'hidden', 'default' => '1', 'filter' => 'integer'] as $key => $value) {
                $marker->addAttribute($key, $value);
            }
            if ($block['reciprocal'] && $currentId > 0) {
                $rows = $currentParams->get($name, []);
                if (is_string($rows)) { $rows = json_decode($rows, true) ?: []; }
                $direct = Blocks::ids(array_map(static fn ($row): int => (int) (((array) $row)['product_id'] ?? 0), (array) $rows));
                $incoming = (new Relations(Factory::getContainer()->get('DatabaseDriver')))->incoming($currentId, $block['alias'], $direct);
                if ($incoming) {
                    $description = Text::_('PLG_SYSTEM_DHARMASHOPRELATED_INCOMING_DESC') . '<ul>';
                    foreach (array_slice($incoming, 0, 20) as $source) {
                        $description .= '<li>' . htmlspecialchars($source->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' (ID ' . (int) $source->id . ')</li>';
                    }
                    $description .= '</ul>';
                    if (count($incoming) > 20) { $description .= Text::_('PLG_SYSTEM_DHARMASHOPRELATED_INCOMING_MORE'); }
                    $note = $fieldset->addChild('field');
                    foreach (['name' => $name . '_incoming', 'type' => 'note', 'label' => 'PLG_SYSTEM_DHARMASHOPRELATED_INCOMING',
                        'description' => $description, 'class' => 'alert alert-info', 'heading' => 'h3', 'filter' => 'unset'] as $key => $value) {
                        $note->addAttribute($key, $value);
                    }
                }
            }
        }
        if ($manual) { $form->load($xml, false); }
    }
    public function saveSettings(EventInterface $event): void
    {
        $table = $event->getArgument('subject');
        if ($event->getArgument('context') !== 'com_config.component' || ($table->element ?? '') !== 'com_dharmashop') { return; }
        $this->loadLanguage();
        $params = new Registry($table->params);
        $blocks = Blocks::normalize($params->get('related_blocks', []), true);
        foreach ($blocks as $block) {
            if ($block['title'] === '' || ($block['mode'] === 'fields' && !$block['fields'])) {
                $table->setError(Text::_('PLG_SYSTEM_DHARMASHOPRELATED_CONFIG_INVALID'));
                $event->addResult(false);
                return;
            }
        }
        $params->set('related_enabled', (int) $params->get('related_enabled', 0) === 1 ? 1 : 0);
        $params->set('related_blocks', $blocks);
        $table->params = (string) $params;
    }
    public function saveProduct(EventInterface $event): void
    {
        if ($event->getArgument('context') !== 'com_dharmashop.product') { return; }
        $settings = ComponentHelper::getParams('com_dharmashop');
        if ((int) $settings->get('related_enabled', 0) !== 1) { return; }
        $table = $event->getArgument('subject');
        $data = (array) $event->getArgument('data', []);
        $submitted = (array) ($data['params'] ?? []);
        $params = new Registry($table->params);
        foreach (Blocks::normalize($settings->get('related_blocks', [])) as $block) {
            if ($block['mode'] !== 'manual') { continue; }
            $name = 'related_' . $block['alias'];
            if (!array_key_exists($name . '_present', $submitted)) { continue; }
            $ids = Blocks::ids(array_map(static fn ($row): int => (int) (((array) $row)['product_id'] ?? 0), (array) ($submitted[$name] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== (int) $table->id));
            $params->set($name, array_map(static fn (int $id): array => ['product_id' => $id], array_slice($ids, 0, 100)));
            $params->remove($name . '_present');
        }
        $table->params = (string) $params;
    }
    public function indexProduct(EventInterface $event): void
    {
        if ($event->getArgument('context') !== 'com_dharmashop.product') { return; }
        (new Relations(Factory::getContainer()->get('DatabaseDriver')))->syncProduct((int) $event->getArgument('subject')->id);
    }
    public function deleteProduct(EventInterface $event): void
    {
        if ($event->getArgument('context') !== 'com_dharmashop.product') { return; }
        (new Relations(Factory::getContainer()->get('DatabaseDriver')))->removeProduct((int) $event->getArgument('subject')->id);
    }
    public function renderProducts(EventInterface $event): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('site') || !$app->getDocument() instanceof HtmlDocument || $event->getArgument('view') !== 'product') { return; }
        $settings = ComponentHelper::getParams('com_dharmashop');
        if ((int) $settings->get('related_enabled', 0) !== 1) { return; }
        $position = $event->getArgument('position');
        $blocks = Blocks::normalize($settings->get('related_blocks', []));
        $matching = array_filter($blocks, static fn (array $block): bool => $block['position'] === $position);
        if (!$matching) { return; }
        $current = $event->getArgument('item');
        $id = (int) $current->id;
        if (!isset($this->prepared[$id])) {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $repository = new Products($db);
            $union = [];
            $this->prepared[$id] = [];
            foreach ($blocks as $block) {
                if ($block['position'] === '') { continue; }
                $items = $repository->find($current, $block);
                $this->prepared[$id][$block['alias']] = [];
                foreach ($items as $item) {
                    $union[(int) $item->id] ??= $item;
                    $this->prepared[$id][$block['alias']][] = (int) $item->id;
                }
            }
            $limit = (int) $settings->get('product_card_images', 1);
            $model = $app->bootComponent('com_dharmashop')->getMVCFactory()->createModel('Category', 'Site', ['ignore_request' => true]);
            $model->attachCardData(array_values($union), in_array($limit, [0,1,2,3,4,5], true) ? $limit : 1);
            $this->prepared[$id]['items'] = $union;
        }
        foreach ($matching as $block) {
            $key = $id . ':' . $block['alias'];
            if (isset($this->rendered[$key])) { continue; }
            $items = array_map(fn (int $itemId): object => $this->prepared[$id]['items'][$itemId], $this->prepared[$id][$block['alias']] ?? []);
            if (!$items) { continue; }
            $this->rendered[$key] = true;
            $this->cards ??= new ProductCardRenderer($settings);
            $renderCard = function (object $item) use ($block): string {
                $cardLayout = $block['layout'] === 'list' ? 'list' : 'grid';
                $data = $this->cards->data($item, $cardLayout, 'product.related', $block['alias']);
                if ($block['component_layouts']) { return $this->cards->render($data); }
                $layout = new FileLayout($cardLayout, $this->root() . '/layouts', ['component' => 'none','client' => 0]);
                $layout->addIncludePath(JPATH_THEMES . '/' . $this->getApplication()->getTemplate() . '/html/layouts/plugins/system/dharmashoprelated');
                return $layout->render($data);
            };
            $stepper = '';
            if (!$this->stepperPrinted && (int) $settings->get('catalog_mode', 0) !== 1) {
                $this->stepperPrinted = true;
                $stepper = (new FileLayout('components.dharmashop.product-card-parts.stepper', null, ['component' => 'none','client' => 0]))->render(['qty' => 1,'step' => 1,'min' => 1]);
            }
            ob_start();
            try {
                require PluginHelper::getLayoutPath('system', 'dharmashoprelated');
                $html = (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if ($position === 'tabs') {
                $tabset = (string) ($event->getArgument('data', [])['tabset'] ?? '');
                if ($tabset === '') { continue; }
                $title = htmlspecialchars($block['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $html = HTMLHelper::_('uitab.addTab', $tabset, 'related-' . $block['alias'], $title) . $html . HTMLHelper::_('uitab.endTab');
            }
            $event->addResult($html);
        }
    }
}
