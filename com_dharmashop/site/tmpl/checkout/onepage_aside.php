<?php
/** Общая боковая панель итогов оформления «в один шаг» (C100): те же итоги, промокод, доставка, оплата и кнопка, что в `default_summary.php`. */
\defined('_JEXEC') or die;

if ($this->loginRequired || !$this->lines) {
    return;
}

echo $this->loadTemplate('summary');
