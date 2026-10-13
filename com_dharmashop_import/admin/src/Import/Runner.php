<?php
declare(strict_types=1);
namespace Dharma\Component\DharmaShopImport\Administrator\Import;
\defined('_JEXEC') or die;

/**
 * Запуск импорта без интерфейса (команда Joomla CLI и задача планировщика): те же шаги и права, что в окне прогресса.
 * Проверка и применение идут порциями до заданного времени; применение — только если оно явно разрешено.
 */
final class Runner
{
    /**
     * @param callable|null $progress fn(array $job): void — вызывается после каждого шага
     * @return array{state:string,job:int,summary:array,applied:bool,message:string} state: done | checked | running | failed | cancelled | rejected | busy
     */
    public static function run(Workflow $workflow, int $profile, bool $apply, float $seconds, ?callable $progress = null): array
    {
        $deadline = microtime(true) + $seconds;
        $id = $workflow->start($profile);
        $resumed = false;
        $stalled = 0;
        $applied = false;
        while (true) {
            $job = $workflow->job($id);
            $state = $job['state'];
            if ($state === 'failed' && !$resumed) {
                // Прерванный запуск продолжается один раз за вызов; повторный сбой оставляет его в failed до следующего срабатывания.
                $resumed = true;
                $workflow->resume($id);
                continue;
            }
            if ($state === 'checked') {
                if (!$apply) { break; }
                $skip = !empty($job['snapshot']['profile']['params']['skip_errors']);
                if ($job['errors'] && !$skip) { return self::result('rejected', $id, $workflow, $applied, 'COM_DHARMASHOP_IMPORT_REVIEW_ERRORS'); }
                $workflow->approve($id);
                $applied = true;
                continue;
            }
            if (!in_array($state, ['reading', 'checking', 'media', 'finalizing', 'applying'], true)) { break; }
            if (microtime(true) >= $deadline) { return self::result('running', $id, $workflow, $applied, ''); }
            $before = [$job['state'], $job['updated'], $job['cursor'], $job['processed']];
            $job = $workflow->step($id);
            if ($progress) { $progress($job); }
            $stalled = [$job['state'], $job['updated'], $job['cursor'], $job['processed']] === $before ? $stalled + 1 : 0;
            if ($stalled >= 3) { return self::result('busy', $id, $workflow, $applied, 'COM_DHARMASHOP_IMPORT_BUSY'); }
        }
        $job = $workflow->job($id);

        return self::result($job['state'], $id, $workflow, $applied, (string) ($job['error'] ?? ''));
    }

    private static function result(string $state, int $id, Workflow $workflow, bool $applied, string $message): array
    {
        return ['state' => $state, 'job' => $id, 'summary' => $workflow->summary($id), 'applied' => $applied, 'message' => $message];
    }
}
