<?php
/** Dharma Shop — экспорт каталога. Copyright (C) 2026 Dharma. GPL-2.0-or-later. */
declare(strict_types=1);

namespace Dharma\Component\DharmaShopExport\Administrator\Export;
\defined('_JEXEC') or die;
final class Cells {
 public static function text(mixed $value, string $mode = '', string $separator = ' | '): string {
  if (is_array($value)) { $value=implode($separator,array_map(static fn($v)=>self::text($v),$value)); }
  $text=(string)($value ?? '');
  if ($mode === 'plain') { $text=html_entity_decode(strip_tags($text),ENT_QUOTES|ENT_HTML5,'UTF-8'); }
  if ($mode === 'yesno') { $text=(float)$text ? \Joomla\CMS\Language\Text::_('JYES') : \Joomla\CMS\Language\Text::_('JNO'); }
  if ($mode === 'emptyzero' && is_numeric($text) && (float)$text === 0.0) { $text=''; }
  return $text;
 }
 /** Защита от внедрения формул: текст, начинающийся (после пробельных символов) с `=`, `+`, `-`, `@`, получает ведущий апостроф. Обычное число со знаком — не формула. */
 public static function safe(string $text): string {
  if(preg_match('/^-?\d+(?:\.\d+)?$/D',$text)){return $text;}
  return preg_match('/^[\x00-\x20]*[=+@-]/',$text) ? "'".$text : $text;
 }
 public static function xml(string $text): string {
  return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u','',$text) ?? '',ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 }
 public static function put($stream, string $text): void {
  $length=strlen($text);$offset=0;
  while($offset<$length) { $n=fwrite($stream,substr($text,$offset)); if(!$n) { throw new \RuntimeException('COM_DHARMASHOP_EXPORT_WRITE_ERROR'); } $offset+=$n; }
 }
}
