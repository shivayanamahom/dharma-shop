<?php
declare(strict_types=1);

namespace Dharma\Plugin\Console\DharmaShopExport\Console;

use Dharma\Component\DharmaShopExport\Administrator\Export\Files;
use Dharma\Component\DharmaShopExport\Administrator\Export\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Console\Command\AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

\defined('_JEXEC') or die;

final class ExportCommand extends AbstractCommand
{
	protected static $defaultName = 'dharmashop:export';
	protected static $defaultDescription = 'Экспортировать или продолжить профиль Dharma Shop';

	protected function configure(): void
	{
		$this->addArgument('profile', InputArgument::REQUIRED, 'ID профиля экспорта');
		$this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Скопировать готовый файл в указанный абсолютный путь');
	}

	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$profileId = filter_var($input->getArgument('profile'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($profileId === false) {
			$io->error(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_PROFILE_REQUIRED'));

			return 2;
		}

		try {
			$app = Factory::getApplication();
			$app->bootComponent('com_dharmashop_export');
			$workflow = new Workflow(Factory::getContainer()->get(DatabaseInterface::class));
			$jobId = $workflow->start((int) $profileId);
			$job = $workflow->job($jobId);
			if ($job['state'] === 'failed') {
				$workflow->change($jobId, 'resume');
			}

			$io->title(sprintf(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_TITLE'), $jobId));
			do {
				$before = $workflow->job($jobId);
				$job = $workflow->step($jobId);
				$progress = sprintf('%d / %d; %d записано; %d пропущено', $job['processed'], $job['total'], $job['written'], $job['skipped']);
				if ($job['state'] === 'running') {
					if ($before['updated'] === $job['updated'] && $before['cursor'] === $job['cursor']) {
						$io->error(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_BUSY'));

						return 2;
					}
					$io->text($progress);
				}
			} while ($job['state'] === 'running');

			if ($job['state'] !== 'done') {
				$io->error(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_FAILED') . ': ' . Text::_((string) ($job['error'] ?: 'COM_DHARMASHOP_EXPORT_FAILURE')));

				return 1;
			}

			$io->success(sprintf(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_DONE'), $job['written'], $job['skipped']));
			$snapshot = $job['snapshot'];
			if (($snapshot['profile']['params']['visibility'] ?? '') === 'public') {
				$io->text(Files::publicLink($snapshot));
			}

			$target = $input->getOption('output');
			if (is_string($target) && $target !== '') {
				$target = $this->copyArtifact($job, $target);
				$io->text(sprintf(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_SAVED'), $target));
			}

			return 0;
		} catch (\Throwable $exception) {
			$io->error(Text::_($exception->getMessage()));

			return 1;
		}
	}

	private function copyArtifact(array $job, string $target): string
	{
		if ($target[0] !== DIRECTORY_SEPARATOR || str_contains($target, "\0")) {
			throw new \InvalidArgumentException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_ABSOLUTE'));
		}

		$parent = realpath(dirname($target));
		$name = basename($target);
		if ($parent === false || $name === '' || $name === '.' || $name === '..') {
			throw new \InvalidArgumentException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_INVALID'));
		}
		$destination = $parent . DIRECTORY_SEPARATOR . $name;
		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		if (is_link($destination) || $extension !== (string) $job['snapshot']['extension']) {
			throw new \InvalidArgumentException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_INVALID'));
		}

		$temp = tempnam($parent, '.dharmashop-export-');
		if ($temp === false) {
			throw new \RuntimeException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_WRITE'));
		}
		$source = null;
		$stream = null;
		try {
			$source = (new Files($job['snapshot']['storage']))->stream((int) $job['id'], 'artifact.php');
			if (fread($source, strlen(Files::GUARD)) !== Files::GUARD || ($stream = fopen($temp, 'wb')) === false || stream_copy_to_stream($source, $stream) === false) {
				throw new \RuntimeException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_WRITE'));
			}
			fclose($stream);
			$stream = null;
			fclose($source);
			$source = null;
			if (!rename($temp, $destination)) {
				throw new \RuntimeException(Text::_('PLG_CONSOLE_DHARMASHOPEXPORT_OUTPUT_WRITE'));
			}
		} finally {
			if (is_resource($source)) {
				fclose($source);
			}
			if (is_resource($stream)) {
				fclose($stream);
			}
			if (is_file($temp)) {
				@unlink($temp);
			}
		}

		return $destination;
	}
}
