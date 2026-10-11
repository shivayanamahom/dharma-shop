CREATE TABLE IF NOT EXISTS `#__dharmashop_product_groups` (
 `id` int unsigned NOT NULL AUTO_INCREMENT,
 `title` varchar(255) NOT NULL, `alias` varchar(190) NOT NULL,
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
