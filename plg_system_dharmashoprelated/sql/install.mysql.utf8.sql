CREATE TABLE IF NOT EXISTS `#__dharmashop_related_products` (
  `source_id` int unsigned NOT NULL,
  `block_alias` varchar(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_id` int unsigned NOT NULL,
  PRIMARY KEY (`source_id`, `block_alias`, `target_id`),
  KEY `idx_reverse` (`block_alias`, `target_id`, `source_id`),
  KEY `idx_target` (`target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
