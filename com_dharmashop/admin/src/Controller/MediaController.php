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
use Dharma\Component\DharmaShop\Administrator\Fields\FileStorage;
use Dharma\Component\DharmaShop\Administrator\Fields\TypeRegistry;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
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

    public function uploadFile(): void { $this->fileRequest(true); }
    public function selectFile(): void { $this->fileRequest(false); }

    /** Same ACL/token rules as images; field limits are always loaded server-side. */
    private function fileRequest(bool $upload): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff');
        $this->app->setHeader('Cache-Control', 'no-store');
        $this->app->sendHeaders();
        try {
            if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken('post')) { throw new \RuntimeException('JINVALID_TOKEN_NOTICE'); }
            $user = $this->app->getIdentity();
            if (!$user->authorise('core.create', 'com_dharmashop') && !$user->authorise('core.edit', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            TypeRegistry::current()->get('files');
            $fieldId = $this->input->post->getInt('field_id');
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $levels = array_map('intval', $user->getAuthorisedViewLevels()) ?: [0];
            $query = $db->createQuery()->select('f.params')->from($db->quoteName('#__dharmashop_fields','f'))
                ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups','g') . ' ON g.id = f.group_id')
                ->where('f.id = ' . $fieldId)->where('f.state = 1')->where('f.level = 1')
                ->where('f.type = ' . $db->quote('files'))->where('f.store = ' . $db->quote('media'))
                ->where('f.access IN (' . implode(',', $levels) . ')')
                ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))');
            $params = $db->setQuery($query)->loadResult();
            if ($params === null) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            $storage = new FileStorage(JPATH_ROOT, $fieldId, FileStorage::settings((new Registry($params))->toArray()));
            $file = $this->input->files->get('file', [], 'raw');
            echo new JsonResponse($upload ? $storage->store(is_array($file) ? $file : []) : $storage->inspect(rawurldecode($this->input->post->getString('path'))));
        } catch (\RuntimeException|\InvalidArgumentException $error) {
            echo new JsonResponse(null, Text::_($error->getMessage()), true);
        } catch (\Throwable $error) {
            Log::add($error->getMessage(), Log::ERROR, 'com_dharmashop');
            echo new JsonResponse(null, Text::_('COM_DHARMASHOP_UPLOAD_ERROR_STORE'), true);
        }
        $this->app->close();
    }

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
