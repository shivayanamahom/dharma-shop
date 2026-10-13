<?php
declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Site\Controller;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\MVC\Controller\BaseController;

\defined('_JEXEC') or die;

/** Read-only каскад в режиме «Применить»: только форма назначенного модуля, без карточек товаров. */
class FilterController extends BaseController
{
    protected $option = 'com_dharmashop';

    /** Виды страниц списка, на которых работает обновление формы; расширения (посадочные страницы) дополняют. @return string[] */
    protected function listingViews(): array
    {
        return ['category', 'brand', 'badge', 'search'];
    }

    public function refresh(): void
    {
        $result = ['success' => false];
        try {
            $id = $this->input->getInt('filter_module');
            $module = ModuleHelper::getModuleById((string) $id);
            if ($id < 1 || (int) ($module->id ?? 0) !== $id || ($module->module ?? '') !== 'mod_dharmashop_filter') {
                throw new \RuntimeException('Module unavailable', 404);
            }
            if (!in_array($this->input->getCmd('view'), $this->listingViews(), true)) {
                throw new \RuntimeException('Unsupported listing', 404);
            }
            $html = ModuleHelper::renderModule(clone $module, ['style' => 'none']);
            if (!str_contains($html, 'data-dharmashop-filter')) { throw new \RuntimeException('Filter unavailable', 404); }
            $result = ['success' => true, 'html' => $html];
        } catch (\Throwable $error) {
            $this->app->setHeader('status', (int) $error->getCode() === 404 ? '404 Not Found' : '503 Service Unavailable', true);
            if ((int) $error->getCode() !== 404) {
                \Joomla\CMS\Log\Log::add('Filter refresh: ' . $error->getMessage(), \Joomla\CMS\Log\Log::ERROR, 'com_dharmashop');
            }
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
