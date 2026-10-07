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

use Dharma\Component\DharmaShop\Administrator\Image\ImageUploader;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;

\defined('_JEXEC') or die;

/**
 * Загрузка изображений из форм админки (поле «imageupload»): task=media.upload&format=json.
 */
class MediaController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function upload(): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff');
        $this->app->setHeader('Cache-Control', 'no-store');
        $this->app->sendHeaders();

        try {
            if (strtoupper($this->input->getMethod()) !== 'POST') {
                throw new \RuntimeException('JERROR_ALERTNOAUTHOR');
            }

            if (!Session::checkToken('post')) {
                throw new \RuntimeException('JINVALID_TOKEN_NOTICE');
            }

            $user = $this->app->getIdentity();

            if (!$user->authorise('core.create', 'com_dharmashop') && !$user->authorise('core.edit', 'com_dharmashop')) {
                throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN');
            }

            $file = $this->input->files->get('file', [], 'raw');
            $folder = (string) $this->input->post->getString('folder', 'common');
            $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));

            echo new JsonResponse($uploader->store(is_array($file) ? $file : [], $folder));
        } catch (\RuntimeException $e) {
            echo new JsonResponse(null, Text::_($e->getMessage()), true);
        } catch (\Throwable $e) {
            // Подробности — в журнал, пользователю общее сообщение (без путей и текста исключения).
            Log::add($e->getMessage(), Log::ERROR, 'com_dharmashop');
            echo new JsonResponse(null, Text::_('COM_DHARMASHOP_UPLOAD_ERROR_STORE'), true);
        }

        $this->app->close();
    }
}
