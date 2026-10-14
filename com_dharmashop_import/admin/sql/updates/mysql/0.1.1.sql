-- 0.1.1: память о скачанных файлах (повторный импорт не качает файлы заново). Повторный запуск безопасен.
CREATE TABLE IF NOT EXISTS `#__dharmashop_import_media` (
 `source` varchar(64) NOT NULL, `entity` varchar(24) NOT NULL, `target_id` int unsigned NOT NULL, `url_hash` char(64) NOT NULL,
 `path` varchar(500) NOT NULL, `created` datetime NOT NULL,
 PRIMARY KEY (`source`,`entity`,`target_id`,`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
