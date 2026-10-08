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
