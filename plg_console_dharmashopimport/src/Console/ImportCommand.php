<?php
declare(strict_types=1);

namespace Dharma\Plugin\Console\DharmaShopImport\Console;

use Dharma\Component\DharmaShopImport\Administrator\Import\Access;
use Dharma\Component\DharmaShopImport\Administrator\Import\Runner;
use Dharma\Component\DharmaShopImport\Administrator\Import\Workflow;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

\defined('_JEXEC') or die;

final class ImportCommand extends AbstractCommand
{
	protected static $defaultName = 'dharmashop:import';
	protected static $defaultDescription = 'Проверить (и при --apply применить) профиль импорта Dharma Shop';

	protected function configure(): void
	{
		$this->addArgument('profile', InputArgument::REQUIRED, 'ID профиля импорта');
		$this->addOption('apply', 'a', InputOption::VALUE_NONE, 'Применить импорт после успешной проверки (без этого каталог не меняется)');
		$this->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'ID или логин пользователя, от имени которого идёт импорт (по умолчанию — первый суперпользователь)');
	}

	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$profileId = filter_var($input->getArgument('profile'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		if ($profileId === false) {
			$io->error(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_PROFILE_REQUIRED'));

			return 2;
		}

		try {
			Factory::getApplication()->bootComponent('com_dharmashop_import');
			$user = Access::actAs($input->getOption('user'));
			$io->text(sprintf(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_USER'), $user->username, $user->id));
			$workflow = new Workflow(Factory::getContainer()->get(DatabaseInterface::class));
			$apply = (bool) $input->getOption('apply');
			$result = Runner::run($workflow, (int) $profileId, $apply, 86400.0, static function (array $job) use ($io): void {
				$io->text(sprintf('%s: %d / %d', Text::_('COM_DHARMASHOP_IMPORT_PHASE_' . strtoupper($job['state'])), $job['processed'], $job['total']));
			});
		} catch (\Throwable $exception) {
			$io->error(Text::_(Workflow::message($exception)));

			return 1;
		}

		$s = $result['summary'];
		$io->text(sprintf(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_SUMMARY'), $result['job'], $s['create'], $s['update'], $s['unchanged'], $s['errors'], $s['skipped_files']));
		switch ($result['state']) {
			case 'done':
				$io->success(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_DONE'));

				return 0;
			case 'checked':
				$io->warning(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_CHECKED'));

				return 0;
			case 'rejected':
				$io->error(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_REJECTED'));

				return 1;
			case 'busy':
				$io->error(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_BUSY'));

				return 2;
			default:
				$io->error(Text::_('PLG_CONSOLE_DHARMASHOPIMPORT_FAILED') . ': ' . Text::_($result['message'] ?: 'COM_DHARMASHOP_IMPORT_FAILURE'));

				return 1;
		}
	}
}
