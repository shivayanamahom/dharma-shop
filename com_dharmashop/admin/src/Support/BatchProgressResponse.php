<?php

/**
 * Dharma Shop — JSON-ответы шагов пакетной обработки.
 * @copyright (C) 2026 Dharma.
 * @license GNU General Public License версии 2 или новее; см. LICENSE.txt
 */

declare(strict_types=1);

namespace Dharma\Component\DharmaShop\Administrator\Support;

use Joomla\CMS\Factory;

\defined('_JEXEC') or die;

/** Завершает AJAX-запрос одним коротким JSON-ответом, не рендеря всю админ-страницу. */
final class BatchProgressResponse
{
    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        Factory::getApplication()->close();
    }
}
