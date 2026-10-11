CREATE TABLE IF NOT EXISTS `#__dharmashop_product_groups` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `title` varchar(255) NOT NULL, `alias` varchar(190) NOT NULL,
 `copy_fields` tinyint unsigned NOT NULL DEFAULT 1,
 `title_template` varchar(1000) NOT NULL DEFAULT '{group.title} {variant.values}',
 `display_mode` varchar(8) NOT NULL DEFAULT 'single', `state` tinyint NOT NULL DEFAULT 1,
 `catid` int unsigned NULL, `brand_id` int unsigned NULL,
 `introtext` text NULL, `fulltext` mediumtext NULL, `cover` varchar(500) NULL, `cover_alt` varchar(255) NULL,
 `price_min` decimal(15,2) NULL, `price_max` decimal(15,2) NULL,
 `in_stock` tinyint unsigned NOT NULL DEFAULT 0, `members_count` int unsigned NOT NULL DEFAULT 0,
 `revision` int unsigned NOT NULL DEFAULT 1,
 `checked_out` int unsigned NULL, `checked_out_time` datetime NULL,
 PRIMARY KEY (`id`), UNIQUE KEY `uq_alias` (`alias`), KEY `idx_category` (`catid`, `state`, `id`), KEY `idx_brand` (`brand_id`, `state`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_group_axes` (
 `group_id` int unsigned NOT NULL, `field_id` int unsigned NOT NULL, `ordering` smallint unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (`group_id`, `field_id`), KEY `idx_field` (`field_id`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `#__dharmashop_group_media` (
 `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 `group_id` int unsigned NOT NULL, `ordering` smallint unsigned NOT NULL,
 `path` varchar(500) NOT NULL, `alt` varchar(255) NOT NULL DEFAULT '', `title` varchar(255) NOT NULL DEFAULT '',
 PRIMARY KEY (`id`), UNIQUE KEY `uq_group_order` (`group_id`, `ordering`), KEY `idx_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_group_categories` (
 `group_id` int unsigned NOT NULL, `catid` int unsigned NOT NULL, `is_main` tinyint unsigned NOT NULL DEFAULT 0,
 PRIMARY KEY (`group_id`, `catid`), KEY `idx_category` (`catid`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_group_field_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `option_id` int unsigned NULL,
  `num` decimal(18,6) NULL,
  `num_end` decimal(18,6) NULL,
  `str` varchar(190) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`, `field_id`),
  KEY `idx_option` (`field_id`, `option_id`, `group_id`),
  KEY `idx_num` (`field_id`, `num`, `group_id`),
  KEY `idx_num_end` (`field_id`, `num_end`, `group_id`),
  KEY `idx_str` (`field_id`, `str`, `group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_group_field_texts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `value` mediumtext NULL,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_group_field_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `path` varchar(500) NOT NULL,
  `filename` varchar(255) NULL,
  `bytes` bigint unsigned NULL,
  `mime` varchar(127) NULL,
  `alt` varchar(255) NULL,
  `title` varchar(255) NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_group` (`group_id`, `field_id`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
