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
use Dharma\Plugin\DharmaShopField\Basic\Type\VideoMetadata;
use Dharma\Plugin\DharmaShopField\Basic\Type\VideoType;

\defined('_JEXEC') or die;

/**
 * Загрузка изображений из форм админки (поле «imageupload»): task=media.upload&format=json.
 */
class MediaController extends BaseController
{
    protected $option = 'com_dharmashop';

    public function uploadFile(): void { $this->fileRequest(true, 'files'); }
    public function selectFile(): void { $this->fileRequest(false, 'files'); }
    public function uploadVideo(): void { $this->fileRequest(true, 'video'); }
    public function selectVideo(): void { $this->fileRequest(false, 'video'); }

    /** Resolve a service URL and optionally fetch its cover; arbitrary iframe URLs are never accepted. */
    public function resolveVideo(): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff');
        $this->app->setHeader('Cache-Control', 'no-store');
        $this->app->sendHeaders();
        try {
            $this->app->getLanguage()->load('plg_dharmashopfield_basic', JPATH_ADMINISTRATOR);
            if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken('post')) { throw new \RuntimeException('JINVALID_TOKEN_NOTICE'); }
            $user = $this->app->getIdentity();
            if (!$user->authorise('core.create', 'com_dharmashop') && !$user->authorise('core.edit', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            $definition = $this->videoDefinition($this->input->post->getInt('field_id'), $user);
            $settings = VideoType::currentSettings((new Registry($definition->params))->toArray());
            $settings['field_id'] = (int) $definition->id;
            $result = VideoMetadata::resolve($this->input->post->getCmd('provider'), $this->input->post->getString('url'),
                $settings, (string) ComponentHelper::getParams('com_dharmashop')->get('video_youtube_api_key', ''));
            echo new JsonResponse($result);
        } catch (\RuntimeException|\InvalidArgumentException $error) {
            echo new JsonResponse(null, Text::_($error->getMessage()), true);
        } catch (\Throwable $error) {
            Log::add($error->getMessage(), Log::ERROR, 'com_dharmashop');
            echo new JsonResponse(null, Text::_('COM_DHARMASHOP_UPLOAD_ERROR_STORE'), true);
        }
        $this->app->close();
    }

    /** Image uploads for positive-ID custom gallery fields; separate from the reserved product gallery and files. */
    public function uploadFieldGallery(): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff');
        $this->app->setHeader('Cache-Control', 'no-store');
        $this->app->sendHeaders();
        try {
            if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken('post')) { throw new \RuntimeException('JINVALID_TOKEN_NOTICE'); }
            $user = $this->app->getIdentity();
            if (!$user->authorise('core.create', 'com_dharmashop') && !$user->authorise('core.edit', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            TypeRegistry::current()->get('gallery');
            $fieldId = $this->input->post->getInt('field_id');
            if ($fieldId < 1) { $fieldId = $this->input->getInt('field_id', 0); }
            if ($fieldId < 1) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $levels = array_map('intval', $user->getAuthorisedViewLevels()) ?: [0];
            $query = $db->createQuery()->select($db->quoteName(['f.params', 'f.target']))->from($db->quoteName('#__dharmashop_fields', 'f'))
                ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = f.group_id')
                ->where('f.id = :field_id')->bind(':field_id', $fieldId, \Joomla\Database\ParameterType::INTEGER)
                ->where('f.state = 1')->where('f.level = 1')
                ->where('f.type = ' . $db->quote('gallery'))->where('f.store = ' . $db->quote('media'))
                ->where('f.access IN (' . implode(',', $levels) . ')')
                ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))');
            $definition = $db->setQuery($query)->loadObject();
            if (!$definition || !in_array($definition->target, ['product', 'category'], true)) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            $settings = (new Registry($definition->params))->toArray();
            $gallery = TypeRegistry::current()->get('gallery')->normalizeSettings($settings);
            $scope = $fieldId . '/' . $gallery['gallery_folder'];
            $file = $this->input->files->get('file', [], 'raw');
            $uploader = new ImageUploader(ComponentHelper::getParams('com_media'), JPATH_ROOT, ComponentHelper::getParams('com_dharmashop'));
            echo new JsonResponse($uploader->store(is_array($file) ? $file : [], 'field-galleries', $scope));
        } catch (\RuntimeException $e) {
            echo new JsonResponse(null, Text::_($e->getMessage()), true);
        } catch (\Throwable $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_dharmashop');
            echo new JsonResponse(null, Text::_('COM_DHARMASHOP_UPLOAD_ERROR_STORE'), true);
        }
        $this->app->close();
    }

    /** Same ACL/token rules as images; field limits are always loaded server-side. */
    private function fileRequest(bool $upload, string $type): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->app->setHeader('X-Content-Type-Options', 'nosniff');
        $this->app->setHeader('Cache-Control', 'no-store');
        $this->app->sendHeaders();
        try {
            $this->app->getLanguage()->load('plg_dharmashopfield_basic', JPATH_ADMINISTRATOR);
            if (strtoupper($this->input->getMethod()) !== 'POST' || !Session::checkToken('post')) { throw new \RuntimeException('JINVALID_TOKEN_NOTICE'); }
            $user = $this->app->getIdentity();
            if (!$user->authorise('core.create', 'com_dharmashop') && !$user->authorise('core.edit', 'com_dharmashop')) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            TypeRegistry::current()->get($type);
            $fieldId = $this->input->post->getInt('field_id');
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $levels = array_map('intval', $user->getAuthorisedViewLevels()) ?: [0];
            $query = $db->createQuery()->select($db->quoteName(['f.params', 'f.target', 'f.alias']))->from($db->quoteName('#__dharmashop_fields','f'))
                ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups','g') . ' ON g.id = f.group_id')
                ->where('f.id = ' . $fieldId)->where('f.state = 1')->where('f.level = 1')
                ->where('f.type = ' . $db->quote($type))->where('f.store = ' . $db->quote('media'))
                ->where('f.access IN (' . implode(',', $levels) . ')')
                ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))');
            $definition = $db->setQuery($query)->loadObject();
            if (!$definition || !in_array($definition->target, ['product', 'category'], true)) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
            $params = (new Registry($definition->params))->toArray();
            $videoSettings = $type === 'video' ? VideoType::currentSettings($params) : [];
            $settings = $type === 'video' ? VideoType::fileSettings($videoSettings) : FileStorage::settings($params);
            $storage = new FileStorage(JPATH_ROOT, $fieldId, $settings, $type === 'video' ? 'videos' : 'files', $type !== 'video');
            $file = $this->input->files->get('file', [], 'raw');
            if ($upload) {
                $entityId = max(0, $this->input->post->getInt('entity_id'));
                $entityAlias = $this->input->post->getString('entity_title');
                if ($entityId > 0) {
                    $table = $definition->target === 'product' ? '#__dharmashop_products' : '#__categories';
                    $entityQuery = $db->createQuery()->select($db->quoteName(['alias', 'title']))
                        ->from($db->quoteName($table))->where('id = ' . $entityId);
                    if ($definition->target === 'category') { $entityQuery->where('extension = ' . $db->quote('com_dharmashop')); }
                    $entity = $db->setQuery($entityQuery)->loadObject();
                    if (!$entity) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
                    $entityAlias = (string) ($entity->alias ?: $entity->title);
                }
                $result = $storage->store(is_array($file) ? $file : [], [
                    'target'=>$definition->target, 'id'=>$entityId, 'alias'=>$entityAlias,
                    'field_alias'=>$definition->alias . ($type === 'video' ? '-' . $videoSettings['video_folder'] : ''),
                ]);
            } else {
                $result = $storage->inspect(rawurldecode($this->input->post->getString('path')));
            }
            if ($type === 'video' && empty($result['poster'])) {
                $result['poster'] = VideoMetadata::localPoster((string) ($result['path'] ?? ''), $fieldId, (string) ($videoSettings['video_folder'] ?? 'product'));
            }
            echo new JsonResponse($result);
        } catch (\RuntimeException|\InvalidArgumentException $error) {
            echo new JsonResponse(null, Text::_($error->getMessage()), true);
        } catch (\Throwable $error) {
            Log::add($error->getMessage(), Log::ERROR, 'com_dharmashop');
            echo new JsonResponse(null, Text::_('COM_DHARMASHOP_UPLOAD_ERROR_STORE'), true);
        }
        $this->app->close();
    }

    private function videoDefinition(int $fieldId, object $user): object
    {
        if ($fieldId < 1) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $levels = array_map('intval', $user->getAuthorisedViewLevels()) ?: [0];
        $query = $db->createQuery()->select($db->quoteName(['f.id','f.params','f.target']))->from($db->quoteName('#__dharmashop_fields', 'f'))
            ->join('LEFT', $db->quoteName('#__dharmashop_fieldgroups', 'g') . ' ON g.id = f.group_id')
            ->where('f.id = ' . $fieldId)->where('f.state = 1')->where('f.level = 1')
            ->where('f.type = ' . $db->quote('video'))->where('f.store = ' . $db->quote('media'))
            ->where('f.access IN (' . implode(',', $levels) . ')')
            ->where('(f.group_id = 0 OR (g.state = 1 AND g.target = f.target))');
        $definition = $db->setQuery($query)->loadObject();
        if (!$definition || !in_array($definition->target, ['product','category'], true)) { throw new \RuntimeException('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'); }
        return $definition;
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
