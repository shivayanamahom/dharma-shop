-- Посадочные страницы (C85): сохранённое состояние фильтра и/или ручной список товаров со своим содержимым и SEO.
-- Таблица без asset_id: права задаёт компонент (access.xml), Table::store не берёт LOCK TABLES.
CREATE TABLE IF NOT EXISTS `#__dharmashop_landing_pages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `catid` int unsigned NOT NULL DEFAULT 0,
  `source` varchar(8) NOT NULL DEFAULT 'filter',
  `conditions` mediumtext NULL,
  `conditions_hash` char(40) NOT NULL DEFAULT '',
  `sort` varchar(16) NOT NULL DEFAULT '',
  `product_view` varchar(8) NOT NULL DEFAULT '',
  `image` varchar(500) NULL,
  `image_alt` varchar(255) NULL,
  `introtext` mediumtext NULL,
  `fulltext` mediumtext NULL,
  `meta_title` varchar(255) NOT NULL DEFAULT '',
  `meta_description` varchar(500) NOT NULL DEFAULT '',
  `robots` varchar(32) NOT NULL DEFAULT '',
  `canonical` varchar(500) NOT NULL DEFAULT '',
  `broken` tinyint unsigned NOT NULL DEFAULT 0,
  `params` text NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`),
  KEY `idx_state_access_order` (`state`, `access`, `ordering`),
  KEY `idx_hash` (`conditions_hash`),
  KEY `idx_cat` (`catid`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ручной список товаров страницы: порядок задаёт администратор.
CREATE TABLE IF NOT EXISTS `#__dharmashop_landing_products` (
  `landing_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`landing_id`, `product_id`),
  KEY `idx_order` (`landing_id`, `ordering`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
