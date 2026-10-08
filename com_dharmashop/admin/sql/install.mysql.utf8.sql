CREATE TABLE IF NOT EXISTS `#__dharmashop_products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `asset_id` int unsigned NOT NULL DEFAULT 0,
  `catid` int unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `introtext` text NULL,
  `fulltext` mediumtext NULL,
  `search_text` text NULL,
  `state` tinyint NOT NULL DEFAULT 0,
  `variants_mode` tinyint unsigned NOT NULL DEFAULT 1,
  `unit` varchar(20) NULL,
  `qty_min` decimal(12,3) NULL,
  `qty_step` decimal(12,3) NULL,
  `qty_max` decimal(12,3) NULL,
  `stock_tracked` tinyint unsigned NULL,
  `backorder` tinyint unsigned NULL,
  `price_min` decimal(15,2) NULL,
  `price_max` decimal(15,2) NULL,
  `in_stock` tinyint unsigned NOT NULL DEFAULT 1,
  `variants_count` smallint unsigned NOT NULL DEFAULT 1,
  `cover` varchar(500) NULL,
  `cover_alt` varchar(255) NULL,
  `metakey` text NULL,
  `metadesc` text NULL,
  `metadata` text NULL,
  `params` text NULL,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_alias` (`catid`, `alias`),
  KEY `idx_state_created` (`state`, `created`),
  KEY `idx_modified` (`modified`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_variants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `sku` varchar(100) NULL,
  `title` varchar(255) NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `stock_tracked` tinyint unsigned NOT NULL DEFAULT 0,
  `backorder` tinyint unsigned NOT NULL DEFAULT 0,
  `stock_total` decimal(12,3) NOT NULL DEFAULT 0,
  `in_stock` tinyint unsigned NOT NULL DEFAULT 1,
  `weight` decimal(10,3) NULL,
  `weight_unit` varchar(20) NOT NULL DEFAULT 'кг',
  `length` decimal(10,2) NULL,
  `width` decimal(10,2) NULL,
  `height` decimal(10,2) NULL,
  `dimension_unit` varchar(20) NOT NULL DEFAULT 'см',
  `params` text NULL,
  `created` datetime NOT NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sku` (`sku`),
  KEY `idx_product` (`product_id`, `state`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_price_lists` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `currency` char(3) NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `params` text NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_variant_prices` (
  `variant_id` int unsigned NOT NULL,
  `price_list_id` int unsigned NOT NULL,
  `price` decimal(15,2) NULL,
  `discount` decimal(15,2) NULL,
  `discount_percent` tinyint unsigned NOT NULL DEFAULT 0,
  `discount_start` datetime NULL,
  `discount_end` datetime NULL,
  `price_final` decimal(15,2) NULL,
  PRIMARY KEY (`variant_id`, `price_list_id`),
  KEY `idx_discount_start` (`discount_start`),
  KEY `idx_discount_end` (`discount_end`),
  KEY `idx_list_final` (`price_list_id`, `price_final`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_stock_locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_stock_levels` (
  `variant_id` int unsigned NOT NULL,
  `location_id` int unsigned NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 0,
  `reserved` decimal(12,3) NOT NULL DEFAULT 0,
  PRIMARY KEY (`variant_id`, `location_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `variant_id` int unsigned NOT NULL,
  `location_id` int unsigned NOT NULL,
  `delta` decimal(12,3) NOT NULL,
  `reason` varchar(20) NOT NULL DEFAULT 'manual',
  `order_id` int unsigned NULL,
  `user_id` int unsigned NULL,
  `note` varchar(255) NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_variant_created` (`variant_id`, `created`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_product_categories` (
  `catid` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `direct` tinyint unsigned NOT NULL,
  `is_main` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `price_sort` decimal(15,2) NULL,
  `in_stock` tinyint unsigned NOT NULL,
  `created` datetime NOT NULL,
  `title_sort` varchar(60) NOT NULL,
  PRIMARY KEY (`catid`, `product_id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_l_order` (`catid`, `state`, `sort_order`, `product_id`),
  KEY `idx_l_price` (`catid`, `state`, `price_sort`, `product_id`),
  KEY `idx_l_new` (`catid`, `state`, `created`, `product_id`),
  KEY `idx_l_title` (`catid`, `state`, `title_sort`, `product_id`),
  KEY `idx_l_stock` (`catid`, `state`, `in_stock`, `sort_order`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_fieldgroups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `target` varchar(16) NOT NULL DEFAULT 'product',
  `tab_mode` varchar(16) NOT NULL DEFAULT 'fields',
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_fields` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `target` varchar(16) NOT NULL DEFAULT 'product',
  `group_id` int unsigned NOT NULL DEFAULT 0,
  `alias` varchar(190) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `store` varchar(10) NOT NULL DEFAULT 'string',
  `level` tinyint unsigned NOT NULL DEFAULT 1,
  `all_categories` tinyint unsigned NOT NULL DEFAULT 1,
  `filterable` tinyint unsigned NOT NULL DEFAULT 0,
  `sortable` tinyint unsigned NOT NULL DEFAULT 0,
  `in_card` tinyint unsigned NOT NULL DEFAULT 0,
  `in_page` tinyint unsigned NOT NULL DEFAULT 1,
  `is_cover` tinyint unsigned NOT NULL DEFAULT 0,
  `required` tinyint unsigned NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT '',
  `ordering` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `params` text NULL,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`),
  KEY `idx_group` (`group_id`, `ordering`),
  KEY `idx_state_level` (`state`, `level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_field_options` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `field_id` int unsigned NOT NULL,
  `alias` varchar(190) NOT NULL,
  `title` varchar(255) NOT NULL,
  `color` varchar(20) NULL,
  `image` varchar(500) NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field_alias` (`field_id`, `alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_category_fields` (
  `catid` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  PRIMARY KEY (`catid`, `field_id`),
  KEY `idx_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_product_axes` (
  `product_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`product_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_field_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `variant_id` int unsigned NULL,
  `field_id` int unsigned NOT NULL,
  `option_id` int unsigned NULL,
  `num` decimal(18,6) NULL,
  `num_end` decimal(18,6) NULL,
  `str` varchar(190) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`, `field_id`),
  KEY `idx_variant` (`variant_id`, `field_id`),
  KEY `idx_option` (`field_id`, `option_id`, `product_id`),
  KEY `idx_num` (`field_id`, `num`, `product_id`),
  KEY `idx_num_end` (`field_id`, `num_end`, `product_id`),
  KEY `idx_str` (`field_id`, `str`, `product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_field_texts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `variant_id` int unsigned NULL,
  `field_id` int unsigned NOT NULL,
  `value` mediumtext NULL,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`, `field_id`),
  KEY `idx_variant` (`variant_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_values` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `option_id` int unsigned NULL,
  `num` decimal(18,6) NULL,
  `num_end` decimal(18,6) NULL,
  `str` varchar(190) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`, `field_id`),
  KEY `idx_option` (`field_id`, `option_id`, `category_id`),
  KEY `idx_num` (`field_id`, `num`, `category_id`),
  KEY `idx_num_end` (`field_id`, `num_end`, `category_id`),
  KEY `idx_str` (`field_id`, `str`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_texts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `value` mediumtext NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_field_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `variant_id` int unsigned NULL,
  `field_id` int unsigned NOT NULL,
  `path` varchar(500) NOT NULL,
  `filename` varchar(255) NULL,
  `bytes` bigint unsigned NULL,
  `mime` varchar(127) NULL,
  `alt` varchar(255) NULL,
  `title` varchar(255) NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`, `field_id`, `ordering`),
  KEY `idx_variant` (`variant_id`, `field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_category_field_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` int unsigned NOT NULL,
  `field_id` int unsigned NOT NULL,
  `path` varchar(500) NOT NULL,
  `filename` varchar(255) NULL,
  `bytes` bigint unsigned NULL,
  `mime` varchar(127) NULL,
  `alt` varchar(255) NULL,
  `title` varchar(255) NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`, `field_id`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_search` (
  `product_id` int unsigned NOT NULL,
  `title_norm` varchar(255) NOT NULL DEFAULT '',
  `body` mediumtext NULL,
  PRIMARY KEY (`product_id`),
  FULLTEXT KEY `ft_search` (`title_norm`, `body`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_external_ids` (
  `entity` varchar(30) NOT NULL,
  `source` varchar(50) NOT NULL,
  `external_id` varchar(100) NOT NULL,
  `id` int unsigned NOT NULL,
  PRIMARY KEY (`entity`, `source`, `external_id`),
  KEY `idx_id` (`entity`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `phone_norm` varchar(20) NOT NULL DEFAULT '',
  `contact_key` char(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `note` text NULL,
  `params` text NULL,
  `created` datetime NOT NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_id`),
  UNIQUE KEY `uq_guest_contact` (`contact_key`),
  KEY `idx_email` (`email`(100)),
  KEY `idx_phone` (`phone_norm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_carts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `token` char(32) NOT NULL,
  `user_id` int unsigned NULL,
  `customer_id` int unsigned NULL,
  `price_list_id` int unsigned NOT NULL DEFAULT 1,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  `expires` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_cart_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `qty` decimal(12,3) NOT NULL DEFAULT 1,
  `line_hash` char(32) NOT NULL,
  `params` text NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cart_line` (`cart_id`, `line_hash`),
  KEY `idx_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_statuses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'secondary',
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `is_final` tinyint unsigned NOT NULL DEFAULT 0,
  `stock_effect` tinyint unsigned NOT NULL DEFAULT 0,
  `notify_customer` tinyint unsigned NOT NULL DEFAULT 0,
  `notify_admin` tinyint unsigned NOT NULL DEFAULT 0,
  `customer_message` text NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_status_transitions` (
  `from_status_id` int unsigned NOT NULL,
  `to_status_id` int unsigned NOT NULL,
  PRIMARY KEY (`from_status_id`, `to_status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_warehouses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `image` varchar(500) NOT NULL DEFAULT '',
  `address` text NULL,
  `phone` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(190) NOT NULL DEFAULT '',
  `hours` text NULL,
  `info` text NULL,
  `ready_term` varchar(100) NOT NULL DEFAULT '',
  `lat` decimal(9,6) NULL,
  `lon` decimal(9,6) NULL,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`),
  KEY `idx_state_ordering` (`state`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_shipping_methods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `plugin` varchar(100) NOT NULL,
  `description` text NULL,
  `image` varchar(500) NOT NULL DEFAULT '',
  `params` text NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_payment_methods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `alias` varchar(190) NOT NULL,
  `plugin` varchar(100) NOT NULL,
  `description` text NULL,
  `image` varchar(500) NOT NULL DEFAULT '',
  `params` text NULL,
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `state` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `number` varchar(40) NULL,
  `secret` char(32) NOT NULL,
  `kind` tinyint unsigned NOT NULL DEFAULT 1,
  `customer_id` int unsigned NULL,
  `user_id` int unsigned NULL,
  `status_id` int unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0,
  `adjustments_total` decimal(15,2) NOT NULL DEFAULT 0,
  `total` decimal(15,2) NOT NULL DEFAULT 0,
  `contact_name` varchar(255) NOT NULL DEFAULT '',
  `contact_email` varchar(255) NOT NULL DEFAULT '',
  `contact_phone` varchar(50) NOT NULL DEFAULT '',
  `contacts` text NULL,
  `note` text NULL,
  `shipping_method_id` int unsigned NULL,
  `shipping_title` varchar(255) NOT NULL DEFAULT '',
  `shipping_data` text NULL,
  `payment_method_id` int unsigned NULL,
  `payment_title` varchar(255) NOT NULL DEFAULT '',
  `payment_data` text NULL,
  `cart_id` int unsigned NULL,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned NULL,
  `checked_out_time` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_number` (`number`),
  UNIQUE KEY `uq_secret` (`secret`),
  KEY `idx_status_created` (`status_id`, `created`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created`),
  KEY `idx_email` (`contact_email`(100)),
  KEY `idx_phone` (`contact_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `variant_id` int unsigned NULL,
  `product_id` int unsigned NULL,
  `sku` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT '',
  `qty` decimal(12,3) NOT NULL DEFAULT 1,
  `price` decimal(15,2) NULL,
  `total` decimal(15,2) NULL,
  `params` text NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`, `ordering`),
  KEY `idx_variant` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_order_adjustments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `code` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0,
  `params` text NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_order_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `status_id` int unsigned NOT NULL,
  `user_id` int unsigned NULL,
  `comment` text NULL,
  `notified` tinyint unsigned NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_created` (`order_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__dharmashop_payments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `method_id` int unsigned NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0,
  `currency` char(3) NOT NULL,
  `state` varchar(20) NOT NULL DEFAULT 'pending',
  `external_id` varchar(100) NULL,
  `data` text NULL,
  `created` datetime NOT NULL,
  `modified` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_method_external` (`method_id`, `external_id`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__dharmashop_price_lists` (`id`, `title`, `currency`, `is_default`, `state`, `ordering`) VALUES (1, 'Основной', NULL, 1, 1, 1);

INSERT IGNORE INTO `#__dharmashop_stock_locations` (`id`, `title`, `is_default`, `state`, `ordering`) VALUES (1, 'Основной склад', 1, 1, 1);

CREATE TABLE IF NOT EXISTS `#__dharmashop_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `history_id` int unsigned NOT NULL,
  `channel` varchar(32) NOT NULL,
  `audience` varchar(16) NOT NULL,
  `recipient` varchar(254) NOT NULL,
  `recipient_hash` char(64) NOT NULL,
  `claim_key` char(32) NOT NULL,
  `state` tinyint unsigned NOT NULL DEFAULT 0,
  `attempts` int unsigned NOT NULL DEFAULT 1,
  `error_code` varchar(100) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  `sent` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_delivery` (`history_id`, `channel`, `audience`, `recipient_hash`),
  KEY `idx_order` (`order_id`, `history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
