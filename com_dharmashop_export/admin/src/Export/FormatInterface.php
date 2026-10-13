<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
\defined('_JEXEC') or die;
/** Потоковый формат: head/tail вызываются однократно, rows — независимо для каждой порции.
 * seal получает временный файл вне webroot и контекст профиля; не должен загружать весь каталог в память.
 * В контексте tail и seal доступны settings, columns, processed (всего обработанных записей) и остальные ключи, общие для всех вызовов.
 */
interface FormatInterface {
 public function extension(): string;
 public function supports(string $entity): bool;
 public function required(array $settings): array;
 public function head($stream, array $context): void;
 public function rows($stream, array $rows, array $context): int;
 public function tail($stream, array $context): void;
 public function seal(string $source, string $destination, array $context): void;
}
