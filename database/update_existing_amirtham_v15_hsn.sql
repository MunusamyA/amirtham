-- AMIRTHAM v14 -> v15 upgrade
-- Adds HSN Master, links existing food products, and adds the HSN sidebar menu.
-- Run ONCE on an existing Amirtham database after backing it up.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `hsn_master` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `hsn_code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_hsn_master_code` (`hsn_code`),
  KEY `idx_hsn_master_status_code` (`status`,`hsn_code`),
  KEY `idx_hsn_master_created_by` (`created_by`),
  KEY `idx_hsn_master_updated_by` (`updated_by`),
  CONSTRAINT `fk_hsn_master_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_hsn_master_updater` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `food_products`
  ADD COLUMN `hsn_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `unit_name`,
  ADD KEY `idx_food_products_hsn` (`hsn_id`),
  ADD CONSTRAINT `fk_food_products_hsn` FOREIGN KEY (`hsn_id`) REFERENCES `hsn_master` (`id`);

-- Convert current free-text product HSN codes into master rows. The highest existing
-- product default tax rate is used when the same HSN was entered with different rates.
INSERT INTO `hsn_master`
(`hsn_code`,`description`,`gst_rate`,`cgst_rate`,`sgst_rate`,`igst_rate`,`cess_rate`,`status`,`created_by`,`updated_by`,`created_at`,`updated_at`)
SELECT
  TRIM(p.`hsn_code`) AS `hsn_code`,
  CONCAT('Migrated from existing product HSN ', TRIM(p.`hsn_code`)) AS `description`,
  MAX(p.`default_tax_rate`) AS `gst_rate`,
  ROUND(MAX(p.`default_tax_rate`) / 2, 2) AS `cgst_rate`,
  ROUND(MAX(p.`default_tax_rate`) / 2, 2) AS `sgst_rate`,
  MAX(p.`default_tax_rate`) AS `igst_rate`,
  0.00 AS `cess_rate`,
  1 AS `status`,
  MIN(p.`created_by`) AS `created_by`,
  MIN(p.`created_by`) AS `updated_by`,
  NOW(), NOW()
FROM `food_products` p
WHERE p.`hsn_code` IS NOT NULL AND TRIM(p.`hsn_code`) <> ''
GROUP BY TRIM(p.`hsn_code`)
ON DUPLICATE KEY UPDATE
  `gst_rate` = VALUES(`gst_rate`),
  `cgst_rate` = VALUES(`cgst_rate`),
  `sgst_rate` = VALUES(`sgst_rate`),
  `igst_rate` = VALUES(`igst_rate`),
  `updated_at` = NOW();

UPDATE `food_products` p
JOIN `hsn_master` h ON h.`hsn_code` = TRIM(p.`hsn_code`)
SET p.`hsn_id` = h.`id`
WHERE p.`hsn_id` IS NULL AND p.`hsn_code` IS NOT NULL AND TRIM(p.`hsn_code`) <> '';

-- Add Amirtham Food Supplementary parent menu if it is not already present.
INSERT INTO `menus` (`parent_id`,`menu_name`,`menu_path`,`icon`,`available_action_ids`,`sort_order`,`status`,`created_at`,`updated_at`)
SELECT NULL,'Food Supplementary','food-supplementary','package','1',30,1,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_path`='food-supplementary');

INSERT INTO `menus` (`parent_id`,`menu_name`,`menu_path`,`icon`,`available_action_ids`,`sort_order`,`status`,`created_at`,`updated_at`)
SELECT p.`id`,'HSN Master','hsn-master.php','receipt-text','1,2,3,5,6,7,8,9,27,28',31,1,NOW(),NOW()
FROM `menus` p
WHERE p.`menu_path`='food-supplementary'
  AND NOT EXISTS (SELECT 1 FROM `menus` WHERE `menu_path`='hsn-master.php')
LIMIT 1;

-- Existing Platform Owner gets the new menu immediately. Other roles can be assigned
-- through Role Permissions in the UI.
INSERT INTO `role_permissions` (`role_id`,`menu_id`,`action_ids`,`status`,`created_at`,`updated_at`)
SELECT r.`id`,m.`id`,'1',1,NOW(),NOW()
FROM `roles` r JOIN `menus` m ON m.`menu_path`='food-supplementary'
WHERE r.`company_id` IS NULL AND r.`role_type`=2 AND r.`role_name`='Platform Owner'
ON DUPLICATE KEY UPDATE `action_ids`=VALUES(`action_ids`),`status`=1,`updated_at`=NOW();

INSERT INTO `role_permissions` (`role_id`,`menu_id`,`action_ids`,`status`,`created_at`,`updated_at`)
SELECT r.`id`,m.`id`,'1,2,3,5,6,7,8,9,27,28',1,NOW(),NOW()
FROM `roles` r JOIN `menus` m ON m.`menu_path`='hsn-master.php'
WHERE r.`company_id` IS NULL AND r.`role_type`=2 AND r.`role_name`='Platform Owner'
ON DUPLICATE KEY UPDATE `action_ids`=VALUES(`action_ids`),`status`=1,`updated_at`=NOW();

SET FOREIGN_KEY_CHECKS = 1;
