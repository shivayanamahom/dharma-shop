<?php
/** Dharma Shop — импорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopImport\Administrator\Controller;
use Joomla\CMS\Language\Text;
\defined('_JEXEC') or die;
/**
 * Любая задача контроллера требует действия прав экспорта (`dharmashop.import.check`, `core.manage`…). Проверка стоит на входе (`execute`), до разбора
 * задачи, поэтому её не обойти прямым адресом. Образец — `RequiresActionTrait` компонента магазина; здесь действие проверяется на ресурсе самого
 * компонента экспорта, чтобы компоненты не зависели друг от друга.
 */
trait RequiresActionTrait {
 /** Задача (строчными) => действие из `access.xml`; ключ `*` — для всех остальных задач. Задача без действия и без `*` отклоняется. */
 abstract protected function requiredActions(): array;
 public function execute($task) {
  $actions=$this->requiredActions();$action=$actions[strtolower((string)$task)]??$actions['*']??null;
  if($action===null || !$this->app->getIdentity()->authorise($action,'com_dharmashop_import')){throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'),403);}
  return parent::execute($task);
 }
}
