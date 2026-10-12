<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShop\Administrator\Image;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
\defined('_JEXEC') or die;

/** Manual batches: the session holds cursors, never a list of the entire catalog/cache. */
final class ImageMaintenance
{
    private ImageInventory $inventory;
    private ImageCache $cache;
    private array $presets;
    public function __construct(DatabaseInterface $db, private readonly Registry $settings)
    {
        $this->inventory = new ImageInventory($db,$settings);
        $this->cache = new ImageCache((string)$settings->get('image_resize_cache','images/dharmashop/cache/resized'));
        $this->presets = ImageContexts::presets($settings);
    }
    public function fingerprint(): string
    {
        return hash('sha256',json_encode([$this->cache->relative, $this->presets, $this->settings->get('image_resize_enabled',1), PluginHelper::isEnabled('dharmashop','imageresize')], JSON_THROW_ON_ERROR));
    }
    public function start(string $operation, string $context = ''): array
    {
        if (!in_array($operation,['prepare','clear'],true)) throw new \InvalidArgumentException('COM_DHARMASHOP_IMAGE_COMMAND_RESTART');
        if ($operation === 'prepare' && (!(int)$this->settings->get('image_resize_enabled',1) || !PluginHelper::isEnabled('dharmashop','imageresize') || !extension_loaded('gd') || !$this->presets)) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_DISABLED');
        if ($context !== '' && !isset($this->presets[$context])) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_CONTEXT');
        return ['operation'=>$operation, 'context'=>$context, 'fingerprint'=>$this->fingerprint(),
            'phase'=>$operation === 'prepare' ? 'prepare' : 'protect', 'inventory'=>[], 'walk'=>[],
            'protected'=>[], 'pending'=>[], 'records'=>0, 'record_total'=>$this->inventory->countRecords(), 'processed'=>0, 'ready'=>0, 'skipped'=>0, 'failed'=>0,
            'files'=>0, 'bytes'=>0, 'deleted'=>0, 'cutoff'=>time()-1];
    }
    public function confirm(array $state): array
    {
        $this->check($state);
        if ($state['operation'] !== 'clear' || $state['phase'] !== 'review') throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_RESTART');
        // Refresh references before removing anything; the preview itself never mutates images.
        $state['records'] = 0; $state['inventory'] = []; $state['protected'] = []; $state['walk'] = []; $state['phase'] = 'protect_clear';
        return $state;
    }
    public function run(array $state): array
    {
        $this->check($state);
        $deadline = hrtime(true)+3500000000;
        for ($steps=0; $steps<400 && hrtime(true)<$deadline; $steps++) {
            if (in_array($state['phase'],['review','done'],true)) break;
            if ($state['phase'] === 'prepare') {
                if (!$state['pending']) {
                    $row = $this->inventory->next($state['inventory']);
                    if ($row === null) { $state['phase']='done'; break; }
                    $state['records']++;
                    foreach ($row as [$source,$contexts]) {
                        if (!(string)$source) continue;
                        $variants = [];
                        foreach ($contexts as $context) {
                            if (($state['context'] !== '' && $context !== $state['context']) || !isset($this->presets[$context])) continue;
                            $key = json_encode($this->presets[$context]);
                            if (isset($variants[$key])) continue;
                            $variants[$key] = true;
                            $state['pending'][] = [(string)$source,$context];
                        }
                    }
                    continue;
                }
                [$source,$context] = array_shift($state['pending']);
                $state['processed']++;
                try {
                    $result = ImageUrl::resolve($source,$context);
                    if ($result !== ImageUrl::absolute($source) && $this->cache->referencedPath($result) !== null) $state['ready']++;
                    else $state['skipped']++;
                } catch (\Throwable) { $state['failed']++; }
            } elseif (str_starts_with($state['phase'],'protect')) {
                $row = $this->inventory->next($state['inventory']);
                if ($row === null) { $state['phase'] = $state['phase']==='protect_clear' ? 'clear' : 'preview'; continue; }
                $state['records']++;
                foreach ($row as [$source]) {
                    $path = $this->cache->referencedPath((string)$source);
                    if ($path !== null) $state['protected'][$path] = true;
                }
                if (count($state['protected']) > 5000) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_REFERENCES');
            } else {
                $path = $this->cache->next($state['walk']);
                if ($path === false) { $state['phase'] = $state['phase']==='preview' ? 'review' : 'done'; break; }
                if ($path === null) continue;
                if (isset($state['protected'][$path]) || $this->cache->modified($path)>$state['cutoff']) continue;
                if ($state['phase']==='preview') { $state['files']++; $state['bytes'] += $this->cache->size($path); }
                elseif ($this->cache->remove($path,$state['cutoff'])) $state['deleted']++;
                else $state['failed']++;
            }
        }
        return $state;
    }
    private function check(array $state): void
    {
        if (($state['fingerprint'] ?? '') !== $this->fingerprint()) throw new \RuntimeException('COM_DHARMASHOP_IMAGE_COMMAND_RESTART');
    }
}
