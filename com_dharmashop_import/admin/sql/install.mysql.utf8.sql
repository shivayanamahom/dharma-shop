CREATE TABLE IF NOT EXISTS `#__dharmashop_import_profiles` (
 `id` int unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL, `state` tinyint NOT NULL DEFAULT 1,
 `entity` varchar(24) NOT NULL DEFAULT 'categories', `format` varchar(32) NOT NULL DEFAULT 'csv', `params` mediumtext NOT NULL,
 `checked_out` int unsigned NULL, `checked_out_time` datetime NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_import_jobs` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `profile_id` int unsigned NOT NULL, `active_profile` int unsigned NULL,
 `state` varchar(24) NOT NULL, `snapshot` mediumtext NOT NULL, `cursor` bigint unsigned NOT NULL DEFAULT 0,
 `line` bigint unsigned NOT NULL DEFAULT 0, `total` bigint unsigned NOT NULL DEFAULT 0, `processed` bigint unsigned NOT NULL DEFAULT 0,
 `created_count` bigint unsigned NOT NULL DEFAULT 0, `updated_count` bigint unsigned NOT NULL DEFAULT 0,
 `unchanged_count` bigint unsigned NOT NULL DEFAULT 0, `errors` bigint unsigned NOT NULL DEFAULT 0,
 `created_by` int unsigned NOT NULL, `approved_by` int unsigned NULL, `approved_at` datetime NULL,
 `created` datetime NOT NULL, `updated` datetime NOT NULL, `finished` datetime NULL, `error` text NULL,
 `lease` char(48) NULL, `lease_until` datetime NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_active` (`active_profile`), KEY `idx_profile` (`profile_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_import_rows` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `job_id` bigint unsigned NOT NULL, `line` bigint unsigned NOT NULL,
 `state` varchar(24) NOT NULL DEFAULT 'raw', `source_key` varchar(255) NULL, `key_hash` char(64) NULL,
 `target_id` int unsigned NOT NULL DEFAULT 0, `action` varchar(16) NULL, `payload` mediumtext NOT NULL, `error` text NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_line` (`job_id`,`line`), UNIQUE KEY `uq_key` (`job_id`,`key_hash`), KEY `idx_state` (`job_id`,`state`,`id`), KEY `idx_target` (`job_id`,`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_import_files` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT, `job_id` bigint unsigned NULL, `created_by` int unsigned NOT NULL,
 `kind` varchar(16) NOT NULL, `state` varchar(16) NOT NULL, `token` char(48) NULL,
 `folder` varchar(255) NOT NULL DEFAULT 'files/import', `name` varchar(255) NOT NULL, `bytes` bigint unsigned NOT NULL DEFAULT 0, `sha256` char(64) NULL,
 `mime` varchar(127) NULL, `url` text NULL, `url_hash` char(64) NULL, `error` text NULL, `created` datetime NOT NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_token` (`token`), UNIQUE KEY `uq_url` (`job_id`,`url_hash`), KEY `idx_job` (`job_id`,`state`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
