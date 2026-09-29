-- ============================================================================
-- AMIRTHAM INTEGRATED MANAGEMENT - FULL DATABASE v16
-- Starter Kit v5 core + 10 themes + SMTP + stateless security
-- Amirtham modules: Common Expenses + Food Supplementary + Community College + Community Clinic
-- HSN Master is normalized and food_products.hsn_id references it.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Remove Amirtham business tables first when re-importing this schema.
DROP TABLE IF EXISTS `clinic_treatment_sessions`;
DROP TABLE IF EXISTS `clinic_treatment_plans`;
DROP TABLE IF EXISTS `clinic_protocol_days`;
DROP TABLE IF EXISTS `clinic_protocol_versions`;
DROP TABLE IF EXISTS `clinic_protocols`;
DROP TABLE IF EXISTS `clinic_consultation_diagnoses`;
DROP TABLE IF EXISTS `clinic_consultations`;
DROP TABLE IF EXISTS `clinic_appointments`;
DROP TABLE IF EXISTS `clinic_diseases`;
DROP TABLE IF EXISTS `clinic_patients`;
DROP TABLE IF EXISTS `college_student_attendance`;
DROP TABLE IF EXISTS `college_attendance_sessions`;
DROP TABLE IF EXISTS `college_fee_receipt_allocations`;
DROP TABLE IF EXISTS `college_fee_receipts`;
DROP TABLE IF EXISTS `college_fee_installments`;
DROP TABLE IF EXISTS `college_student_fee_plan_items`;
DROP TABLE IF EXISTS `college_student_fee_plans`;
DROP TABLE IF EXISTS `college_fee_structure_items`;
DROP TABLE IF EXISTS `college_fee_structures`;
DROP TABLE IF EXISTS `college_admissions`;
DROP TABLE IF EXISTS `college_students`;
DROP TABLE IF EXISTS `college_batches`;
DROP TABLE IF EXISTS `college_courses`;
DROP TABLE IF EXISTS `food_sale_return_items`;
DROP TABLE IF EXISTS `food_sale_returns`;
DROP TABLE IF EXISTS `food_purchase_return_items`;
DROP TABLE IF EXISTS `food_purchase_returns`;
DROP TABLE IF EXISTS `food_customer_payment_allocations`;
DROP TABLE IF EXISTS `food_customer_payments`;
DROP TABLE IF EXISTS `food_sale_items`;
DROP TABLE IF EXISTS `food_sales`;
DROP TABLE IF EXISTS `food_supplier_payment_allocations`;
DROP TABLE IF EXISTS `food_supplier_payments`;
DROP TABLE IF EXISTS `food_purchase_items`;
DROP TABLE IF EXISTS `food_purchases`;
DROP TABLE IF EXISTS `food_product_batches`;
DROP TABLE IF EXISTS `food_products`;
DROP TABLE IF EXISTS `hsn_master`;
DROP TABLE IF EXISTS `food_product_categories`;
DROP TABLE IF EXISTS `food_customers`;
DROP TABLE IF EXISTS `food_suppliers`;
DROP TABLE IF EXISTS `expenses`;
DROP TABLE IF EXISTS `expense_categories`;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS email_settings;
DROP TABLE IF EXISTS employees;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS menus;
DROP TABLE IF EXISTS app_settings;
DROP TABLE IF EXISTS permission_actions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS companies;

CREATE TABLE companies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_name VARCHAR(150) NOT NULL,
    company_code VARCHAR(50) NOT NULL,
    email VARCHAR(190) NULL,
    mobile VARCHAR(20) NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_companies_code (company_code),
    KEY idx_companies_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    role_name VARCHAR(100) NOT NULL,
    role_type TINYINT UNSIGNED NOT NULL COMMENT '1=Plan/Branch Admin, 2=Platform, 3=Tenant-created',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_roles_company (company_id),
    KEY idx_roles_type_status (role_type, status),
    CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES companies (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NOT NULL,
    branch_name VARCHAR(150) NOT NULL,
    branch_code VARCHAR(50) NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL COMMENT 'Current Basic/Medium/Premium plan role',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_branch_company_code (company_id, branch_code),
    KEY idx_branches_role (role_id),
    KEY idx_branches_status (status),
    CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_branches_plan_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL COMMENT 'Not unique',
    mobile VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    last_login_at DATETIME NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    KEY idx_users_email (email),
    KEY idx_users_company (company_id),
    KEY idx_users_branch (branch_id),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies (id),
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE permission_actions (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_name VARCHAR(120) NOT NULL,
    purpose VARCHAR(255) NULL,
    group_id TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Numeric display group only; permission logic uses id',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permission_actions_name (action_name),
    KEY idx_permission_actions_group_sort (group_id, sort_order, id),
    KEY idx_permission_actions_status (status),
    KEY idx_permission_actions_created_by (created_by),
    KEY idx_permission_actions_updated_by (updated_by),
    CONSTRAINT fk_permission_actions_created_by FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_permission_actions_updated_by FOREIGN KEY (updated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NULL COMMENT 'NULL means Platform default',
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    description VARCHAR(255) NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_app_settings_branch_key (branch_id, setting_key),
    KEY idx_app_settings_branch (branch_id),
    KEY idx_app_settings_updated_by (updated_by),
    CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users (id),
    CONSTRAINT fk_app_settings_branch FOREIGN KEY (branch_id) REFERENCES branches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE menus (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id BIGINT UNSIGNED NULL,
    menu_name VARCHAR(120) NOT NULL,
    menu_path VARCHAR(120) NOT NULL,
    icon VARCHAR(100) NULL,
    available_action_ids VARCHAR(1000) NOT NULL COMMENT 'Comma-separated numeric action IDs, e.g. 1,2,3,7,20',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_menus_path (menu_path),
    KEY idx_menus_parent (parent_id),
    KEY idx_menus_status_sort (status, sort_order),
    CONSTRAINT fk_menus_parent FOREIGN KEY (parent_id) REFERENCES menus (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NOT NULL,
    action_ids VARCHAR(1000) NOT NULL COMMENT 'Comma-separated numeric action IDs',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_menu (role_id, menu_id),
    KEY idx_role_permissions_status (status),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles (id),
    CONSTRAINT fk_role_permissions_menu FOREIGN KEY (menu_id) REFERENCES menus (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE employees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'Login account linked to this employee',
    branch_id BIGINT UNSIGNED NOT NULL,
    employee_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NULL,
    mobile VARCHAR(20) NULL,
    pan VARCHAR(10) NULL,
    aadhaar VARCHAR(12) NULL,
    media_files LONGTEXT NULL COMMENT 'JSON array of uploaded image/video metadata',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employees_user (user_id),
    UNIQUE KEY uq_employee_branch_code (branch_id, employee_code),
    KEY idx_employees_status (status),
    KEY idx_employees_created_by (created_by),
    CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id),
    CONSTRAINT fk_employees_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_employees_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    branch_id BIGINT UNSIGNED NULL COMMENT 'NULL means platform/default SMTP settings',
    smtp_host VARCHAR(190) NOT NULL,
    smtp_port INT UNSIGNED NOT NULL,
    smtp_encryption VARCHAR(20) NOT NULL DEFAULT 'tls' COMMENT 'tls, ssl or none',
    smtp_auth TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=No SMTP auth, 1=Use username/password',
    smtp_username VARCHAR(190) NULL,
    smtp_password TEXT NULL COMMENT 'AES-256-GCM encrypted value; never returned by API',
    from_email VARCHAR(190) NOT NULL,
    from_name VARCHAR(150) NOT NULL,
    reply_to_email VARCHAR(190) NULL,
    reply_to_name VARCHAR(150) NULL,
    timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_email_settings_branch_status (branch_id, status),
    CONSTRAINT fk_email_settings_branch FOREIGN KEY (branch_id) REFERENCES branches (id),
    CONSTRAINT fk_email_settings_creator FOREIGN KEY (created_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    menu_id BIGINT UNSIGNED NULL,
    action_id SMALLINT UNSIGNED NOT NULL,
    record_id BIGINT UNSIGNED NULL,
    old_data LONGTEXT NULL,
    new_data LONGTEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_company (company_id),
    KEY idx_audit_branch (branch_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_menu (menu_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ============================================================================
-- 3. AMIRTHAM BUSINESS MODULES
-- Common Expenses + Food Supplementary + Community College + Community Clinic
-- ============================================================================

CREATE TABLE `expense_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `module_code` varchar(50) DEFAULT NULL COMMENT 'NULL means shared company category',
  `category_code` varchar(50) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_categories_company_code` (`company_id`,`category_code`),
  KEY `idx_expense_categories_module_status` (`module_code`,`status`),
  KEY `idx_expense_categories_created_by` (`created_by`),
  CONSTRAINT `fk_expense_categories_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_expense_categories_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `expenses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `module_code` varchar(50) DEFAULT NULL COMMENT 'NULL means shared branch expense',
  `expense_category_id` bigint(20) UNSIGNED NOT NULL,
  `expense_date` date NOT NULL,
  `payee_name` varchar(150) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `tax_mode` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Non-GST, 1=GST',
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `attachment_meta` longtext DEFAULT NULL COMMENT 'JSON array of attachment metadata',
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_module_branch_date` (`module_code`,`branch_id`,`expense_date`),
  KEY `idx_expenses_category_date` (`expense_category_id`,`expense_date`),
  KEY `idx_expenses_posting_status` (`posting_status`,`status`),
  KEY `idx_expenses_created_by` (`created_by`),
  KEY `idx_expenses_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_expenses_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_expenses_category` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`),
  CONSTRAINT `fk_expenses_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_expenses_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_suppliers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_suppliers_branch_code` (`branch_id`,`supplier_code`),
  KEY `idx_food_suppliers_branch_status` (`branch_id`,`status`),
  KEY `idx_food_suppliers_name` (`supplier_name`),
  KEY `idx_food_suppliers_created_by` (`created_by`),
  CONSTRAINT `fk_food_suppliers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_suppliers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_customers` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `customer_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_code` varchar(2) DEFAULT NULL,
  `credit_limit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_customers_branch_code` (`branch_id`,`customer_code`),
  KEY `idx_food_customers_branch_status` (`branch_id`,`status`),
  KEY `idx_food_customers_name_mobile` (`customer_name`,`mobile`),
  KEY `idx_food_customers_created_by` (`created_by`),
  CONSTRAINT `fk_food_customers_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_customers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_product_categories` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_code` varchar(50) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_product_categories_branch_code` (`branch_id`,`category_code`),
  KEY `idx_food_product_categories_parent` (`parent_id`),
  KEY `idx_food_product_categories_scope` (`branch_id`,`status`),
  KEY `idx_food_product_categories_created_by` (`created_by`),
  CONSTRAINT `fk_food_product_categories_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_product_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `food_product_categories` (`id`),
  CONSTRAINT `fk_food_product_categories_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hsn_master` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `hsn_code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `gst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cess_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
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

CREATE TABLE `food_products` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(180) NOT NULL,
  `product_type` varchar(30) NOT NULL DEFAULT 'finished' COMMENT 'raw_material, finished or consumable',
  `unit_name` varchar(30) NOT NULL,
  `hsn_id` bigint(20) UNSIGNED DEFAULT NULL,
  `hsn_code` varchar(20) DEFAULT NULL COMMENT 'Legacy/snapshot HSN code; new UI should select hsn_id',
  `barcode` varchar(100) DEFAULT NULL,
  `default_tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(15,3) NOT NULL DEFAULT 0.000,
  `track_batch` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `track_expiry` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `image_meta` longtext DEFAULT NULL COMMENT 'JSON image metadata',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_products_branch_code` (`branch_id`,`product_code`),
  UNIQUE KEY `uq_food_products_branch_barcode` (`branch_id`,`barcode`),
  KEY `idx_food_products_scope_status` (`branch_id`,`status`),
  KEY `idx_food_products_category` (`category_id`),
  KEY `idx_food_products_hsn` (`hsn_id`),
  KEY `idx_food_products_name` (`product_name`),
  KEY `idx_food_products_created_by` (`created_by`),
  CONSTRAINT `fk_food_products_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_products_category` FOREIGN KEY (`category_id`) REFERENCES `food_product_categories` (`id`),
  CONSTRAINT `fk_food_products_hsn` FOREIGN KEY (`hsn_id`) REFERENCES `hsn_master` (`id`),
  CONSTRAINT `fk_food_products_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_product_batches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `manufactured_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `purchase_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_product_batches_scope` (`branch_id`,`product_id`,`batch_number`),
  KEY `idx_food_product_batches_branch` (`branch_id`),
  KEY `idx_food_product_batches_expiry` (`expiry_date`,`status`),
  KEY `idx_food_product_batches_created_by` (`created_by`),
  CONSTRAINT `fk_food_product_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_product_batches_product` FOREIGN KEY (`product_id`) REFERENCES `food_products` (`id`),
  CONSTRAINT `fk_food_product_batches_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_purchases` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_invoice_number` varchar(100) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `tax_mode` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Non-GST, 1=GST',
  `supply_type` varchar(20) DEFAULT NULL COMMENT 'intra_state or inter_state',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `round_off` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_purchases_supplier_invoice` (`branch_id`,`supplier_id`,`supplier_invoice_number`),
  KEY `idx_food_purchases_scope_date` (`branch_id`,`purchase_date`),
  KEY `idx_food_purchases_supplier` (`supplier_id`,`purchase_date`),
  KEY `idx_food_purchases_posting_status` (`posting_status`,`status`),
  KEY `idx_food_purchases_created_by` (`created_by`),
  KEY `idx_food_purchases_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_purchases_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_purchases_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `food_suppliers` (`id`),
  CONSTRAINT `fk_food_purchases_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_purchases_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_purchase_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL COMMENT 'Captured input before/while batch is created',
  `manufactured_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `free_quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_purchase_items_purchase` (`purchase_id`,`status`),
  KEY `idx_food_purchase_items_branch_product` (`branch_id`,`product_id`),
  KEY `idx_food_purchase_items_batch` (`batch_id`),
  KEY `idx_food_purchase_items_created_by` (`created_by`),
  CONSTRAINT `fk_food_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `food_purchases` (`id`),
  CONSTRAINT `fk_food_purchase_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_purchase_items_product` FOREIGN KEY (`product_id`) REFERENCES `food_products` (`id`),
  CONSTRAINT `fk_food_purchase_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `food_product_batches` (`id`),
  CONSTRAINT `fk_food_purchase_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_supplier_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_supplier_payments_supplier_date` (`supplier_id`,`payment_date`),
  KEY `idx_food_supplier_payments_scope` (`branch_id`,`posting_status`),
  KEY `idx_food_supplier_payments_created_by` (`created_by`),
  KEY `idx_food_supplier_payments_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_supplier_payments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_supplier_payments_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `food_suppliers` (`id`),
  CONSTRAINT `fk_food_supplier_payments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_supplier_payments_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_supplier_payment_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_payment_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_supplier_payment_allocations_pair` (`supplier_payment_id`,`purchase_id`),
  KEY `idx_food_supplier_payment_allocations_purchase` (`purchase_id`,`status`),
  KEY `idx_food_supplier_payment_allocations_branch` (`branch_id`),
  KEY `idx_food_supplier_payment_allocations_created_by` (`created_by`),
  CONSTRAINT `fk_food_supplier_payment_allocations_payment` FOREIGN KEY (`supplier_payment_id`) REFERENCES `food_supplier_payments` (`id`),
  CONSTRAINT `fk_food_supplier_payment_allocations_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `food_purchases` (`id`),
  CONSTRAINT `fk_food_supplier_payment_allocations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_supplier_payment_allocations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_sales` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL means walk-in customer',
  `invoice_date` date NOT NULL,
  `customer_name_snapshot` varchar(150) DEFAULT NULL,
  `customer_gstin_snapshot` varchar(15) DEFAULT NULL,
  `tax_mode` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Non-GST, 1=GST',
  `supply_type` varchar(20) DEFAULT NULL COMMENT 'intra_state or inter_state',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `round_off` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_sales_scope_date` (`branch_id`,`invoice_date`),
  KEY `idx_food_sales_customer_date` (`customer_id`,`invoice_date`),
  KEY `idx_food_sales_tax_date` (`tax_mode`,`invoice_date`),
  KEY `idx_food_sales_posting_status` (`posting_status`,`status`),
  KEY `idx_food_sales_created_by` (`created_by`),
  KEY `idx_food_sales_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_sales_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_sales_customer` FOREIGN KEY (`customer_id`) REFERENCES `food_customers` (`id`),
  CONSTRAINT `fk_food_sales_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_sales_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_sale_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_sale_items_sale` (`sale_id`,`status`),
  KEY `idx_food_sale_items_branch_product` (`branch_id`,`product_id`),
  KEY `idx_food_sale_items_batch` (`batch_id`),
  KEY `idx_food_sale_items_created_by` (`created_by`),
  CONSTRAINT `fk_food_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `food_sales` (`id`),
  CONSTRAINT `fk_food_sale_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_sale_items_product` FOREIGN KEY (`product_id`) REFERENCES `food_products` (`id`),
  CONSTRAINT `fk_food_sale_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `food_product_batches` (`id`),
  CONSTRAINT `fk_food_sale_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_customer_payments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_customer_payments_customer_date` (`customer_id`,`payment_date`),
  KEY `idx_food_customer_payments_scope` (`branch_id`,`posting_status`),
  KEY `idx_food_customer_payments_created_by` (`created_by`),
  KEY `idx_food_customer_payments_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_customer_payments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_customer_payments_customer` FOREIGN KEY (`customer_id`) REFERENCES `food_customers` (`id`),
  CONSTRAINT `fk_food_customer_payments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_customer_payments_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_customer_payment_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_payment_id` bigint(20) UNSIGNED NOT NULL,
  `sale_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_food_customer_payment_allocations_pair` (`customer_payment_id`,`sale_id`),
  KEY `idx_food_customer_payment_allocations_sale` (`sale_id`,`status`),
  KEY `idx_food_customer_payment_allocations_branch` (`branch_id`),
  KEY `idx_food_customer_payment_allocations_created_by` (`created_by`),
  CONSTRAINT `fk_food_customer_payment_allocations_payment` FOREIGN KEY (`customer_payment_id`) REFERENCES `food_customer_payments` (`id`),
  CONSTRAINT `fk_food_customer_payment_allocations_sale` FOREIGN KEY (`sale_id`) REFERENCES `food_sales` (`id`),
  CONSTRAINT `fk_food_customer_payment_allocations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_customer_payment_allocations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_purchase_returns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `supplier_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `return_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `taxable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_purchase_returns_scope_date` (`branch_id`,`return_date`),
  KEY `idx_food_purchase_returns_supplier` (`supplier_id`,`return_date`),
  KEY `idx_food_purchase_returns_purchase` (`purchase_id`),
  KEY `idx_food_purchase_returns_created_by` (`created_by`),
  KEY `idx_food_purchase_returns_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_purchase_returns_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_purchase_returns_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `food_suppliers` (`id`),
  CONSTRAINT `fk_food_purchase_returns_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `food_purchases` (`id`),
  CONSTRAINT `fk_food_purchase_returns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_purchase_returns_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_purchase_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `purchase_return_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_purchase_return_items_return` (`purchase_return_id`,`status`),
  KEY `idx_food_purchase_return_items_product` (`branch_id`,`product_id`),
  KEY `idx_food_purchase_return_items_purchase_item` (`purchase_item_id`),
  KEY `idx_food_purchase_return_items_batch` (`batch_id`),
  KEY `idx_food_purchase_return_items_created_by` (`created_by`),
  CONSTRAINT `fk_food_purchase_return_items_return` FOREIGN KEY (`purchase_return_id`) REFERENCES `food_purchase_returns` (`id`),
  CONSTRAINT `fk_food_purchase_return_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_purchase_return_items_purchase_item` FOREIGN KEY (`purchase_item_id`) REFERENCES `food_purchase_items` (`id`),
  CONSTRAINT `fk_food_purchase_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `food_products` (`id`),
  CONSTRAINT `fk_food_purchase_return_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `food_product_batches` (`id`),
  CONSTRAINT `fk_food_purchase_return_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_sale_returns` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `customer_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_id` bigint(20) UNSIGNED DEFAULT NULL,
  `return_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `taxable_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `cgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_sale_returns_scope_date` (`branch_id`,`return_date`),
  KEY `idx_food_sale_returns_customer` (`customer_id`,`return_date`),
  KEY `idx_food_sale_returns_sale` (`sale_id`),
  KEY `idx_food_sale_returns_created_by` (`created_by`),
  KEY `idx_food_sale_returns_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_food_sale_returns_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_sale_returns_customer` FOREIGN KEY (`customer_id`) REFERENCES `food_customers` (`id`),
  CONSTRAINT `fk_food_sale_returns_sale` FOREIGN KEY (`sale_id`) REFERENCES `food_sales` (`id`),
  CONSTRAINT `fk_food_sale_returns_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_food_sale_returns_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `food_sale_return_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sale_return_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `sale_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `quantity` decimal(15,3) NOT NULL DEFAULT 0.000,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_food_sale_return_items_return` (`sale_return_id`,`status`),
  KEY `idx_food_sale_return_items_product` (`branch_id`,`product_id`),
  KEY `idx_food_sale_return_items_sale_item` (`sale_item_id`),
  KEY `idx_food_sale_return_items_batch` (`batch_id`),
  KEY `idx_food_sale_return_items_created_by` (`created_by`),
  CONSTRAINT `fk_food_sale_return_items_return` FOREIGN KEY (`sale_return_id`) REFERENCES `food_sale_returns` (`id`),
  CONSTRAINT `fk_food_sale_return_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_food_sale_return_items_sale_item` FOREIGN KEY (`sale_item_id`) REFERENCES `food_sale_items` (`id`),
  CONSTRAINT `fk_food_sale_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `food_products` (`id`),
  CONSTRAINT `fk_food_sale_return_items_batch` FOREIGN KEY (`batch_id`) REFERENCES `food_product_batches` (`id`),
  CONSTRAINT `fk_food_sale_return_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_courses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(180) NOT NULL,
  `duration_value` int(10) UNSIGNED NOT NULL,
  `duration_unit` varchar(20) NOT NULL COMMENT 'days, weeks, months or years',
  `eligibility` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `maximum_students` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_courses_branch_code` (`branch_id`,`course_code`),
  KEY `idx_college_courses_scope_status` (`branch_id`,`status`),
  KEY `idx_college_courses_name` (`course_name`),
  KEY `idx_college_courses_created_by` (`created_by`),
  CONSTRAINT `fk_college_courses_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_courses_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_batches` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `batch_name` varchar(150) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `working_days` varchar(100) DEFAULT NULL COMMENT 'Comma-separated weekday numbers or configured codes',
  `faculty_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `maximum_students` int(10) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_batches_branch_code` (`branch_id`,`batch_code`),
  KEY `idx_college_batches_scope_dates` (`branch_id`,`start_date`,`end_date`),
  KEY `idx_college_batches_course_status` (`course_id`,`status`),
  KEY `idx_college_batches_faculty` (`faculty_user_id`),
  KEY `idx_college_batches_created_by` (`created_by`),
  CONSTRAINT `fk_college_batches_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_batches_course` FOREIGN KEY (`course_id`) REFERENCES `college_courses` (`id`),
  CONSTRAINT `fk_college_batches_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_college_batches_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_students` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Optional student login account',
  `student_code` varchar(50) NOT NULL,
  `student_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `guardian_mobile` varchar(20) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `identity_number` varchar(100) DEFAULT NULL,
  `media_files` longtext DEFAULT NULL COMMENT 'JSON array of photo/document metadata',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_students_branch_code` (`branch_id`,`student_code`),
  UNIQUE KEY `uq_college_students_user` (`user_id`),
  KEY `idx_college_students_scope_status` (`branch_id`,`status`),
  KEY `idx_college_students_name_mobile` (`student_name`,`mobile`),
  KEY `idx_college_students_created_by` (`created_by`),
  CONSTRAINT `fk_college_students_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_college_students_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_admissions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `admission_date` date NOT NULL,
  `completion_date` date DEFAULT NULL,
  `admission_state` varchar(30) NOT NULL DEFAULT 'active' COMMENT 'active, completed, discontinued or cancelled',
  `remarks` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_admissions_student_batch` (`student_id`,`batch_id`),
  KEY `idx_college_admissions_scope_date` (`branch_id`,`admission_date`),
  KEY `idx_college_admissions_course_batch` (`course_id`,`batch_id`,`status`),
  KEY `idx_college_admissions_created_by` (`created_by`),
  CONSTRAINT `fk_college_admissions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_admissions_student` FOREIGN KEY (`student_id`) REFERENCES `college_students` (`id`),
  CONSTRAINT `fk_college_admissions_course` FOREIGN KEY (`course_id`) REFERENCES `college_courses` (`id`),
  CONSTRAINT `fk_college_admissions_batch` FOREIGN KEY (`batch_id`) REFERENCES `college_batches` (`id`),
  CONSTRAINT `fk_college_admissions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_fee_structures` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `structure_code` varchar(50) NOT NULL,
  `structure_name` varchar(150) NOT NULL,
  `effective_from` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `default_installment_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_fee_structures_branch_code` (`branch_id`,`structure_code`),
  KEY `idx_college_fee_structures_scope` (`branch_id`,`status`),
  KEY `idx_college_fee_structures_course_effective` (`course_id`,`effective_from`),
  KEY `idx_college_fee_structures_created_by` (`created_by`),
  CONSTRAINT `fk_college_fee_structures_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_fee_structures_course` FOREIGN KEY (`course_id`) REFERENCES `college_courses` (`id`),
  CONSTRAINT `fk_college_fee_structures_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_fee_structure_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_structure_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `fee_head` varchar(120) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_college_fee_structure_items_structure` (`fee_structure_id`,`status`,`sort_order`),
  KEY `idx_college_fee_structure_items_branch` (`branch_id`),
  KEY `idx_college_fee_structure_items_created_by` (`created_by`),
  CONSTRAINT `fk_college_fee_structure_items_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `college_fee_structures` (`id`),
  CONSTRAINT `fk_college_fee_structure_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_fee_structure_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_student_fee_plans` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `fee_structure_id` bigint(20) UNSIGNED DEFAULT NULL,
  `plan_date` date NOT NULL,
  `gross_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `scholarship_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `late_fee_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_payable` decimal(15,2) NOT NULL DEFAULT 0.00,
  `installment_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_student_fee_plans_admission` (`admission_id`),
  KEY `idx_college_student_fee_plans_scope_date` (`branch_id`,`plan_date`),
  KEY `idx_college_student_fee_plans_structure` (`fee_structure_id`),
  KEY `idx_college_student_fee_plans_created_by` (`created_by`),
  CONSTRAINT `fk_college_student_fee_plans_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_student_fee_plans_admission` FOREIGN KEY (`admission_id`) REFERENCES `college_admissions` (`id`),
  CONSTRAINT `fk_college_student_fee_plans_structure` FOREIGN KEY (`fee_structure_id`) REFERENCES `college_fee_structures` (`id`),
  CONSTRAINT `fk_college_student_fee_plans_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_student_fee_plan_items` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_fee_plan_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `source_fee_structure_item_id` bigint(20) UNSIGNED DEFAULT NULL,
  `fee_head_snapshot` varchar(120) NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_college_student_fee_plan_items_plan` (`student_fee_plan_id`,`status`,`sort_order`),
  KEY `idx_college_student_fee_plan_items_source` (`source_fee_structure_item_id`),
  KEY `idx_college_student_fee_plan_items_branch` (`branch_id`),
  KEY `idx_college_student_fee_plan_items_created_by` (`created_by`),
  CONSTRAINT `fk_college_student_fee_plan_items_plan` FOREIGN KEY (`student_fee_plan_id`) REFERENCES `college_student_fee_plans` (`id`),
  CONSTRAINT `fk_college_student_fee_plan_items_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_student_fee_plan_items_source` FOREIGN KEY (`source_fee_structure_item_id`) REFERENCES `college_fee_structure_items` (`id`),
  CONSTRAINT `fk_college_student_fee_plan_items_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_fee_installments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_fee_plan_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `installment_number` int(10) UNSIGNED NOT NULL,
  `due_date` date NOT NULL,
  `due_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `waived_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_fee_installments_plan_number` (`student_fee_plan_id`,`installment_number`),
  KEY `idx_college_fee_installments_branch_due` (`branch_id`,`due_date`,`status`),
  KEY `idx_college_fee_installments_created_by` (`created_by`),
  CONSTRAINT `fk_college_fee_installments_plan` FOREIGN KEY (`student_fee_plan_id`) REFERENCES `college_student_fee_plans` (`id`),
  CONSTRAINT `fk_college_fee_installments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_fee_installments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_fee_receipts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `student_fee_plan_id` bigint(20) UNSIGNED NOT NULL,
  `receipt_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(30) NOT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `posting_status` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Draft, 1=Posted',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_college_fee_receipts_scope_date` (`branch_id`,`receipt_date`),
  KEY `idx_college_fee_receipts_admission` (`admission_id`,`receipt_date`),
  KEY `idx_college_fee_receipts_plan` (`student_fee_plan_id`),
  KEY `idx_college_fee_receipts_created_by` (`created_by`),
  KEY `idx_college_fee_receipts_reversed_by` (`reversed_by`),
  CONSTRAINT `fk_college_fee_receipts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_fee_receipts_admission` FOREIGN KEY (`admission_id`) REFERENCES `college_admissions` (`id`),
  CONSTRAINT `fk_college_fee_receipts_plan` FOREIGN KEY (`student_fee_plan_id`) REFERENCES `college_student_fee_plans` (`id`),
  CONSTRAINT `fk_college_fee_receipts_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_college_fee_receipts_reverser` FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_fee_receipt_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `fee_receipt_id` bigint(20) UNSIGNED NOT NULL,
  `fee_installment_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_fee_receipt_allocations_pair` (`fee_receipt_id`,`fee_installment_id`),
  KEY `idx_college_fee_receipt_allocations_installment` (`fee_installment_id`,`status`),
  KEY `idx_college_fee_receipt_allocations_branch` (`branch_id`),
  KEY `idx_college_fee_receipt_allocations_created_by` (`created_by`),
  CONSTRAINT `fk_college_fee_receipt_allocations_receipt` FOREIGN KEY (`fee_receipt_id`) REFERENCES `college_fee_receipts` (`id`),
  CONSTRAINT `fk_college_fee_receipt_allocations_installment` FOREIGN KEY (`fee_installment_id`) REFERENCES `college_fee_installments` (`id`),
  CONSTRAINT `fk_college_fee_receipt_allocations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_fee_receipt_allocations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_attendance_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_date` date NOT NULL,
  `session_number` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `faculty_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_attendance_sessions_batch_date_number` (`batch_id`,`attendance_date`,`session_number`),
  KEY `idx_college_attendance_sessions_scope_date` (`branch_id`,`attendance_date`),
  KEY `idx_college_attendance_sessions_faculty` (`faculty_user_id`),
  KEY `idx_college_attendance_sessions_created_by` (`created_by`),
  CONSTRAINT `fk_college_attendance_sessions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_attendance_sessions_batch` FOREIGN KEY (`batch_id`) REFERENCES `college_batches` (`id`),
  CONSTRAINT `fk_college_attendance_sessions_faculty` FOREIGN KEY (`faculty_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_college_attendance_sessions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `college_student_attendance` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_session_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `admission_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_code` varchar(10) NOT NULL COMMENT 'P=Present, A=Absent, L=Leave, LT=Late',
  `check_in_time` time DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_college_student_attendance_session_admission` (`attendance_session_id`,`admission_id`),
  KEY `idx_college_student_attendance_branch` (`branch_id`,`status`),
  KEY `idx_college_student_attendance_admission` (`admission_id`),
  KEY `idx_college_student_attendance_created_by` (`created_by`),
  CONSTRAINT `fk_college_student_attendance_session` FOREIGN KEY (`attendance_session_id`) REFERENCES `college_attendance_sessions` (`id`),
  CONSTRAINT `fk_college_student_attendance_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_college_student_attendance_admission` FOREIGN KEY (`admission_id`) REFERENCES `college_admissions` (`id`),
  CONSTRAINT `fk_college_student_attendance_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_patients` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Optional patient login account',
  `patient_code` varchar(50) NOT NULL,
  `patient_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_mobile` varchar(20) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `consent_recorded_at` datetime DEFAULT NULL,
  `media_files` longtext DEFAULT NULL COMMENT 'JSON array of report/document metadata',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_patients_branch_code` (`branch_id`,`patient_code`),
  UNIQUE KEY `uq_clinic_patients_user` (`user_id`),
  KEY `idx_clinic_patients_scope_status` (`branch_id`,`status`),
  KEY `idx_clinic_patients_name_mobile` (`patient_name`,`mobile`),
  KEY `idx_clinic_patients_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_patients_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_clinic_patients_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_diseases` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `disease_code` varchar(50) NOT NULL,
  `disease_name` varchar(180) NOT NULL,
  `category_name` varchar(120) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `common_symptoms` text DEFAULT NULL,
  `precautions` text DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_diseases_branch_code` (`branch_id`,`disease_code`),
  KEY `idx_clinic_diseases_scope_status` (`branch_id`,`status`),
  KEY `idx_clinic_diseases_name` (`disease_name`),
  KEY `idx_clinic_diseases_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_diseases_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_diseases_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_appointments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `practitioner_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 30,
  `visit_type` varchar(30) NOT NULL DEFAULT 'consultation' COMMENT 'consultation, treatment or follow_up',
  `chief_complaint` text DEFAULT NULL,
  `appointment_state` varchar(30) NOT NULL DEFAULT 'booked' COMMENT 'booked, confirmed, arrived, consultation, treatment, completed, rescheduled, cancelled or no_show',
  `notes` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clinic_appointments_scope_datetime` (`branch_id`,`appointment_date`,`appointment_time`),
  KEY `idx_clinic_appointments_patient_date` (`patient_id`,`appointment_date`),
  KEY `idx_clinic_appointments_practitioner_date` (`practitioner_user_id`,`appointment_date`,`appointment_time`),
  KEY `idx_clinic_appointments_state` (`appointment_state`,`status`),
  KEY `idx_clinic_appointments_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_appointments_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `clinic_patients` (`id`),
  CONSTRAINT `fk_clinic_appointments_practitioner` FOREIGN KEY (`practitioner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_clinic_appointments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_consultations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `practitioner_user_id` bigint(20) UNSIGNED NOT NULL,
  `consultation_at` datetime NOT NULL,
  `chief_complaint` text DEFAULT NULL,
  `clinical_findings` text DEFAULT NULL,
  `diagnosis_summary` text DEFAULT NULL,
  `advice` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_consultations_appointment` (`appointment_id`),
  KEY `idx_clinic_consultations_scope_date` (`branch_id`,`consultation_at`),
  KEY `idx_clinic_consultations_patient` (`patient_id`,`consultation_at`),
  KEY `idx_clinic_consultations_practitioner` (`practitioner_user_id`,`consultation_at`),
  KEY `idx_clinic_consultations_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_consultations_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_consultations_patient` FOREIGN KEY (`patient_id`) REFERENCES `clinic_patients` (`id`),
  CONSTRAINT `fk_clinic_consultations_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `clinic_appointments` (`id`),
  CONSTRAINT `fk_clinic_consultations_practitioner` FOREIGN KEY (`practitioner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_clinic_consultations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_consultation_diagnoses` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `consultation_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `disease_id` bigint(20) UNSIGNED NOT NULL,
  `diagnosis_type` varchar(20) NOT NULL DEFAULT 'primary' COMMENT 'primary or secondary',
  `notes` varchar(255) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_consultation_diagnoses_pair` (`consultation_id`,`disease_id`),
  KEY `idx_clinic_consultation_diagnoses_branch` (`branch_id`,`status`),
  KEY `idx_clinic_consultation_diagnoses_disease` (`disease_id`),
  KEY `idx_clinic_consultation_diagnoses_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_consultation_diagnoses_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `clinic_consultations` (`id`),
  CONSTRAINT `fk_clinic_consultation_diagnoses_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_consultation_diagnoses_disease` FOREIGN KEY (`disease_id`) REFERENCES `clinic_diseases` (`id`),
  CONSTRAINT `fk_clinic_consultation_diagnoses_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_protocols` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `disease_id` bigint(20) UNSIGNED NOT NULL,
  `protocol_code` varchar(50) NOT NULL,
  `protocol_name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_protocols_branch_code` (`branch_id`,`protocol_code`),
  KEY `idx_clinic_protocols_scope_status` (`branch_id`,`status`),
  KEY `idx_clinic_protocols_disease` (`disease_id`,`status`),
  KEY `idx_clinic_protocols_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_protocols_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_protocols_disease` FOREIGN KEY (`disease_id`) REFERENCES `clinic_diseases` (`id`),
  CONSTRAINT `fk_clinic_protocols_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_protocol_versions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `protocol_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL,
  `effective_from` date NOT NULL,
  `duration_days` int(10) UNSIGNED NOT NULL,
  `total_sessions` int(10) UNSIGNED NOT NULL,
  `general_instructions` text DEFAULT NULL,
  `general_precautions` text DEFAULT NULL,
  `is_current` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Historical, 1=Current',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_protocol_versions_protocol_version` (`protocol_id`,`version_number`),
  KEY `idx_clinic_protocol_versions_branch_current` (`branch_id`,`is_current`,`status`),
  KEY `idx_clinic_protocol_versions_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_protocol_versions_protocol` FOREIGN KEY (`protocol_id`) REFERENCES `clinic_protocols` (`id`),
  CONSTRAINT `fk_clinic_protocol_versions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_protocol_versions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_protocol_days` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `protocol_version_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `day_number` int(10) UNSIGNED NOT NULL,
  `session_title` varchar(180) DEFAULT NULL,
  `planned_points` text DEFAULT NULL,
  `planned_procedure` text NOT NULL,
  `duration_minutes` int(10) UNSIGNED DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `precautions` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_protocol_days_version_day` (`protocol_version_id`,`day_number`),
  KEY `idx_clinic_protocol_days_branch` (`branch_id`,`status`),
  KEY `idx_clinic_protocol_days_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_protocol_days_version` FOREIGN KEY (`protocol_version_id`) REFERENCES `clinic_protocol_versions` (`id`),
  CONSTRAINT `fk_clinic_protocol_days_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_protocol_days_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_treatment_plans` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` bigint(20) UNSIGNED NOT NULL,
  `consultation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `disease_id` bigint(20) UNSIGNED NOT NULL,
  `protocol_version_id` bigint(20) UNSIGNED NOT NULL,
  `practitioner_user_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `total_sessions` int(10) UNSIGNED NOT NULL,
  `treatment_state` varchar(30) NOT NULL DEFAULT 'planned' COMMENT 'planned, active, paused, completed or discontinued',
  `patient_specific_instructions` text DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clinic_treatment_plans_scope_state` (`branch_id`,`treatment_state`,`status`),
  KEY `idx_clinic_treatment_plans_patient` (`patient_id`,`start_date`),
  KEY `idx_clinic_treatment_plans_consultation` (`consultation_id`),
  KEY `idx_clinic_treatment_plans_disease` (`disease_id`),
  KEY `idx_clinic_treatment_plans_protocol_version` (`protocol_version_id`),
  KEY `idx_clinic_treatment_plans_practitioner` (`practitioner_user_id`),
  KEY `idx_clinic_treatment_plans_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_treatment_plans_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_patient` FOREIGN KEY (`patient_id`) REFERENCES `clinic_patients` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `clinic_consultations` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_disease` FOREIGN KEY (`disease_id`) REFERENCES `clinic_diseases` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_protocol_version` FOREIGN KEY (`protocol_version_id`) REFERENCES `clinic_protocol_versions` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_practitioner` FOREIGN KEY (`practitioner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_clinic_treatment_plans_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clinic_treatment_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `treatment_plan_id` bigint(20) UNSIGNED NOT NULL,
  `protocol_day_id` bigint(20) UNSIGNED DEFAULT NULL,
  `appointment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `practitioner_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_number` int(10) UNSIGNED NOT NULL,
  `planned_date` date NOT NULL,
  `actual_start_at` datetime DEFAULT NULL,
  `actual_end_at` datetime DEFAULT NULL,
  `planned_points_snapshot` text DEFAULT NULL,
  `planned_procedure_snapshot` text DEFAULT NULL,
  `actual_points` text DEFAULT NULL,
  `actual_procedure` text DEFAULT NULL,
  `patient_response` text DEFAULT NULL,
  `clinical_observations` text DEFAULT NULL,
  `symptom_score_before` decimal(5,2) DEFAULT NULL,
  `symptom_score_after` decimal(5,2) DEFAULT NULL,
  `next_session_date` date DEFAULT NULL,
  `session_state` varchar(30) NOT NULL DEFAULT 'scheduled' COMMENT 'scheduled, completed, missed, rescheduled or cancelled',
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clinic_treatment_sessions_plan_number` (`treatment_plan_id`,`session_number`),
  KEY `idx_clinic_treatment_sessions_scope_date` (`branch_id`,`planned_date`),
  KEY `idx_clinic_treatment_sessions_state` (`session_state`,`status`),
  KEY `idx_clinic_treatment_sessions_protocol_day` (`protocol_day_id`),
  KEY `idx_clinic_treatment_sessions_appointment` (`appointment_id`),
  KEY `idx_clinic_treatment_sessions_practitioner` (`practitioner_user_id`,`planned_date`),
  KEY `idx_clinic_treatment_sessions_created_by` (`created_by`),
  CONSTRAINT `fk_clinic_treatment_sessions_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_clinic_treatment_sessions_plan` FOREIGN KEY (`treatment_plan_id`) REFERENCES `clinic_treatment_plans` (`id`),
  CONSTRAINT `fk_clinic_treatment_sessions_protocol_day` FOREIGN KEY (`protocol_day_id`) REFERENCES `clinic_protocol_days` (`id`),
  CONSTRAINT `fk_clinic_treatment_sessions_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `clinic_appointments` (`id`),
  CONSTRAINT `fk_clinic_treatment_sessions_practitioner` FOREIGN KEY (`practitioner_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_clinic_treatment_sessions_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



SET FOREIGN_KEY_CHECKS = 1;
