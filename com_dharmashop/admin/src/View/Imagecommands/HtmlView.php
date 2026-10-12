<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\View\Imagecommands;
use Dharma\Component\DharmaShop\Administrator\Image\ImageContexts;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
\defined('_JEXEC') or die;
final class HtmlView extends BaseHtmlView
{
    protected $option = 'com_dharmashop';
    protected string $operation;
    protected array $job;
    protected Form $form;
    protected string $contextLabel;
    public function display($tpl = null): void
    {
        $this->getDocument()->getWebAssetManager()->useScript('com_dharmashop.admin-batch-progress');
        foreach (['COM_DHARMASHOP_PROGRESS_COMMAND', 'COM_DHARMASHOP_PROGRESS_RECORDS', 'COM_DHARMASHOP_PROGRESS_RELOAD', 'COM_DHARMASHOP_PROGRESS_RESPONSE_ERROR', 'COM_DHARMASHOP_PROGRESS_FAILED', 'COM_DHARMASHOP_PROGRESS_ERROR', 'COM_DHARMASHOP_PROGRESS_BAR', 'COM_DHARMASHOP_PROGRESS_RUNNING', 'COM_DHARMASHOP_PROGRESS_DONE', 'COM_DHARMASHOP_PROGRESS_REVIEW', 'COM_DHARMASHOP_PROGRESS_CONFIRM', 'COM_DHARMASHOP_PROGRESS_DISMISS', 'COM_DHARMASHOP_PROGRESS_DONE_HELP', 'COM_DHARMASHOP_PROGRESS_REVIEW_HELP', 'COM_DHARMASHOP_PROGRESS_RUNNING_HELP', 'COM_DHARMASHOP_IMAGE_COMMAND_COUNT_FILES', 'COM_DHARMASHOP_IMAGE_COMMAND_COUNT_DELETED', 'COM_DHARMASHOP_IMAGE_COMMAND_COUNT_PROTECTED'] as $languageKey) { Text::script($languageKey); }
        $app = Factory::getApplication();
        if (!$this->getCurrentUser()->authorise('core.manage','com_dharmashop')) throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);
        $this->operation = $app->getInput()->getCmd('operation','prepare');
        if (!in_array($this->operation,['prepare','clear'],true)) throw new \RuntimeException(Text::_('JERROR_LAYOUT_PAGE_NOT_FOUND'),404);
        $this->job = (array)$app->getUserState('com_dharmashop.images.'.$this->operation,[]);
        $this->form = Form::getInstance('com_dharmashop.image_commands',JPATH_ADMINISTRATOR.'/components/com_dharmashop/forms/image_commands.xml',['control'=>'']);
        $context = (string)($this->job['context'] ?? '');
        $this->contextLabel = Text::_($context !== '' ? ImageContexts::label($context) : 'COM_DHARMASHOP_IMAGE_COMMAND_ALL');
        $title = $this->operation==='prepare' ? 'COM_DHARMASHOP_IMAGE_COMMAND_PREPARE' : 'COM_DHARMASHOP_IMAGE_COMMAND_CLEAR';
        ToolbarHelper::title(Text::_($title),'images');
        $toolbar = Toolbar::getInstance();
        $phase = $this->job['phase'] ?? '';
        if (!$this->job) $toolbar->standardButton('images-start', $this->operation==='prepare' ? 'COM_DHARMASHOP_IMAGE_COMMAND_START' : 'COM_DHARMASHOP_IMAGE_COMMAND_PREVIEW','imagecommands.start')->icon('icon-play')->listCheck(false);
        elseif ($phase==='review') $toolbar->standardButton('images-confirm','COM_DHARMASHOP_IMAGE_COMMAND_CONFIRM','imagecommands.confirm')->icon('icon-trash')->listCheck(false);
        elseif ($phase!=='done') $toolbar->standardButton('images-proceed','COM_DHARMASHOP_IMAGE_COMMAND_CONTINUE','imagecommands.proceed')->icon('icon-play')->listCheck(false);
        if ($this->job) $toolbar->standardButton('images-reset','COM_DHARMASHOP_IMAGE_COMMAND_RESET','imagecommands.reset')->icon('icon-refresh')->listCheck(false);
        $toolbar->linkButton('images-close','JTOOLBAR_CLOSE')->url(Route::_('index.php?option=com_dharmashop&view=products',false))->icon('icon-times');
        parent::display($tpl);
    }
}
