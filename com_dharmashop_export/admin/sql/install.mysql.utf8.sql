CREATE TABLE IF NOT EXISTS `#__dharmashop_export_profiles` (
 `id` int unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL,
 `state` tinyint NOT NULL DEFAULT 1, `entity` varchar(24) NOT NULL DEFAULT 'products',
 `format` varchar(32) NOT NULL DEFAULT 'csv', `params` mediumtext NOT NULL,
 `checked_out` int unsigned NULL, `checked_out_time` datetime NULL,
 PRIMARY KEY (`id`), KEY `idx_state` (`state`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_export_jobs` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `profile_id` int unsigned NOT NULL,
 `active_profile` int unsigned NULL, `state` varchar(16) NOT NULL DEFAULT 'running',
 `snapshot` mediumtext NOT NULL, `cursor` int unsigned NOT NULL DEFAULT 0,
 `ceiling` int unsigned NOT NULL, `segments` int unsigned NOT NULL DEFAULT 0,
 `total` bigint unsigned NOT NULL DEFAULT 0, `processed` bigint unsigned NOT NULL DEFAULT 0,
 `written` bigint unsigned NOT NULL DEFAULT 0, `skipped` bigint unsigned NOT NULL DEFAULT 0,
 `lease` varchar(64) NULL, `lease_until` datetime NULL, `created` datetime NOT NULL,
 `updated` datetime NOT NULL, `finished` datetime NULL, `created_by` int unsigned NOT NULL DEFAULT 0,
 `bytes` bigint unsigned NOT NULL DEFAULT 0, `sha256` char(64) NULL,
 `issues` mediumtext NULL, `artifact` varchar(500) NULL, `error` varchar(255) NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_active` (`active_profile`), KEY `idx_profile` (`profile_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
