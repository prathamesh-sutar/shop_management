-- ============================================================
-- Surveillance Shop Management System - Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS `surveillance_shop`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `surveillance_shop`;

-- ============================================================
-- Table: users
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `user_id`    INT(11) NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `role`       ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    `phone`      VARCHAR(20) DEFAULT NULL,
    `status`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: employees
-- ============================================================
CREATE TABLE IF NOT EXISTS `employees` (
    `employee_id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id`     INT(11) DEFAULT NULL COMMENT 'linked user account if any',
    `name`        VARCHAR(100) NOT NULL,
    `phone`       VARCHAR(20) NOT NULL,
    `email`       VARCHAR(150) DEFAULT NULL,
    `role`        VARCHAR(80) NOT NULL DEFAULT 'Technician',
    `join_date`   DATE NOT NULL,
    `status`      TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`employee_id`),
    INDEX `idx_emp_name`  (`name`),
    INDEX `idx_emp_phone` (`phone`),
    CONSTRAINT `fk_emp_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: products
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
    `product_id`     INT(11) NOT NULL AUTO_INCREMENT,
    `product_name`   VARCHAR(150) NOT NULL,
    `brand`          VARCHAR(100) DEFAULT NULL,
    `category`       ENUM('CCTV Camera','DVR','NVR','Hard Disk','Cable','Accessories') NOT NULL,
    `price`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT(11) NOT NULL DEFAULT 0,
    `supplier`       VARCHAR(150) DEFAULT NULL,
    `description`    TEXT DEFAULT NULL,
    `image`          VARCHAR(255) DEFAULT NULL,
    `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`),
    INDEX `idx_prod_name`     (`product_name`),
    INDEX `idx_prod_category` (`category`),
    INDEX `idx_prod_stock`    (`stock_quantity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: customers
-- ============================================================
CREATE TABLE IF NOT EXISTS `customers` (
    `customer_id` INT(11) NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL,
    `phone`       VARCHAR(20) NOT NULL,
    `email`       VARCHAR(150) DEFAULT NULL,
    `address`     TEXT DEFAULT NULL,
    `city`        VARCHAR(80) DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`customer_id`),
    INDEX `idx_cust_name`  (`name`),
    INDEX `idx_cust_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: sales (invoice header)
-- ============================================================
CREATE TABLE IF NOT EXISTS `sales` (
    `sale_id`        INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id`    INT(11) NOT NULL,
    `user_id`        INT(11) NOT NULL COMMENT 'who created the invoice',
    `total_amount`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `discount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax`            DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `grand_total`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_method` ENUM('Cash','UPI','Card') NOT NULL DEFAULT 'Cash',
    `payment_status` ENUM('Paid','Pending','Partial') NOT NULL DEFAULT 'Paid',
    `notes`          TEXT DEFAULT NULL,
    `sale_date`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sale_id`),
    INDEX `idx_sale_customer` (`customer_id`),
    INDEX `idx_sale_user`     (`user_id`),
    INDEX `idx_sale_date`     (`sale_date`),
    CONSTRAINT `fk_sale_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers`(`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sale_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: sale_items
-- ============================================================
CREATE TABLE IF NOT EXISTS `sale_items` (
    `item_id`    INT(11) NOT NULL AUTO_INCREMENT,
    `sale_id`    INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity`   INT(11) NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `subtotal`   DECIMAL(12,2) NOT NULL,
    PRIMARY KEY (`item_id`),
    INDEX `idx_si_sale`    (`sale_id`),
    INDEX `idx_si_product` (`product_id`),
    CONSTRAINT `fk_si_sale` FOREIGN KEY (`sale_id`)
        REFERENCES `sales`(`sale_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_si_product` FOREIGN KEY (`product_id`)
        REFERENCES `products`(`product_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: service_requests
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_requests` (
    `request_id`      INT(11) NOT NULL AUTO_INCREMENT,
    `customer_id`     INT(11) NOT NULL,
    `service_type`    ENUM('CCTV Installation','Camera Repair','Maintenance','System Upgrade') NOT NULL,
    `address`         TEXT DEFAULT NULL,
    `description`     TEXT DEFAULT NULL,
    `status`          ENUM('Pending','Assigned','In Progress','Completed') NOT NULL DEFAULT 'Pending',
    `assigned_employee` INT(11) DEFAULT NULL,
    `scheduled_date`  DATE DEFAULT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`request_id`),
    INDEX `idx_sr_customer`  (`customer_id`),
    INDEX `idx_sr_status`    (`status`),
    INDEX `idx_sr_employee`  (`assigned_employee`),
    CONSTRAINT `fk_sr_customer` FOREIGN KEY (`customer_id`)
        REFERENCES `customers`(`customer_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sr_employee` FOREIGN KEY (`assigned_employee`)
        REFERENCES `employees`(`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: inventory_logs
-- ============================================================
CREATE TABLE IF NOT EXISTS `inventory_logs` (
    `log_id`      INT(11) NOT NULL AUTO_INCREMENT,
    `product_id`  INT(11) NOT NULL,
    `change_qty`  INT(11) NOT NULL COMMENT 'positive=added, negative=deducted',
    `reason`      VARCHAR(255) DEFAULT NULL,
    `reference_id` INT(11) DEFAULT NULL COMMENT 'sale_id or purchase reference',
    `user_id`     INT(11) DEFAULT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    INDEX `idx_il_product` (`product_id`),
    INDEX `idx_il_user`    (`user_id`),
    CONSTRAINT `fk_il_product` FOREIGN KEY (`product_id`)
        REFERENCES `products`(`product_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_il_user` FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin user  (password: Admin@123)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`) VALUES
('Admin User',  'admin@surveillance.com',
 '$2y$10$MJ14a18AQ88buxF.Onhg8e2OZqYGe/Ec46XtI63v60kdd9RHdKY9a', 'admin',    '9876543210'),
('John Employee','employee@surveillance.com',
 '$2y$10$THXHfqzPBvCIveZgQOJadeA1SFSCTkvqTmAd7UrRFVYaAgV/ZFdSS', 'employee', '9876543211');

-- Employees
INSERT INTO `employees` (`name`, `phone`, `email`, `role`, `join_date`) VALUES
('Raj Kumar',   '9812345670', 'raj@surveillance.com',   'Technician',     '2023-01-15'),
('Priya Singh', '9812345671', 'priya@surveillance.com', 'Senior Tech',    '2022-06-01'),
('Amit Sharma', '9812345672', 'amit@surveillance.com',  'Field Engineer', '2023-07-20');

-- Products
INSERT INTO `products` (`product_name`, `brand`, `category`, `price`, `stock_quantity`, `supplier`, `description`) VALUES
('2MP Dome Camera',      'Hikvision',  'CCTV Camera', 2500.00,  25, 'Hikvision India Pvt Ltd',  '2MP Full HD indoor dome camera with IR 30m'),
('4MP Bullet Camera',    'Dahua',      'CCTV Camera', 3800.00,  18, 'Dahua Technology',         '4MP outdoor bullet with weatherproof IP67'),
('8CH DVR 1080p',        'Hikvision',  'DVR',         8500.00,  10, 'Hikvision India Pvt Ltd',  '8 channel digital video recorder'),
('16CH NVR H.265+',      'CP Plus',    'NVR',        12000.00,   8, 'CP Plus Technology',       '16 channel NVR supporting H.265+'),
('1TB Surveillance HDD', 'Seagate',    'Hard Disk',   4200.00,  20, 'Seagate Distribution',     'SkyHawk 1TB surveillance hard disk'),
('2TB Surveillance HDD', 'WD Purple',  'Hard Disk',   6500.00,  15, 'Western Digital',          'WD Purple 2TB for DVR/NVR'),
('CCTV Cable 3+1 (90m)', 'Polycab',    'Cable',        850.00,  50, 'Polycab Wires Ltd',        '3+1 copper co-axial cable 90m roll'),
('BNC Connector (Pack)', 'Generic',    'Accessories',  150.00, 200, 'Local Supplier',           'BNC male connectors pack of 10'),
('12V 5A Power Supply',  'Meco',       'Accessories',  450.00,  40, 'Meco Electronics',        '12V 5A SMPS power supply for cameras'),
('PTZ Speed Dome 2MP',   'Hikvision',  'CCTV Camera', 9800.00,   5, 'Hikvision India Pvt Ltd',  '2MP 20x optical zoom PTZ camera');

-- Customers
INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `city`) VALUES
('Ramesh Patel',    '9900112233', 'ramesh@gmail.com',    '12 Gandhi Nagar',  'Surat'),
('Sunil Mehta',     '9900112244', 'sunil@gmail.com',     '45 MG Road',       'Ahmedabad'),
('Neha Joshi',      '9900112255', 'neha@gmail.com',      '7 Park Street',    'Vadodara'),
('Vikram Shah',     '9900112266', 'vikram@gmail.com',    '23 Ring Road',     'Rajkot'),
('Pooja Desai',     '9900112277', 'pooja@gmail.com',     '88 Station Road',  'Surat');

-- Service Requests
INSERT INTO `service_requests` (`customer_id`, `service_type`, `address`, `description`, `status`, `assigned_employee`, `scheduled_date`) VALUES
(1, 'CCTV Installation', '12 Gandhi Nagar, Surat',      'New shop needs 4 cameras + 1 DVR', 'Pending',     NULL, '2026-04-01'),
(2, 'Camera Repair',     '45 MG Road, Ahmedabad',       'One camera stopped working',       'Assigned',    1,    '2026-03-28'),
(3, 'Maintenance',       '7 Park Street, Vadodara',     'Annual maintenance of 8 cameras',  'In Progress', 2,    '2026-03-25'),
(4, 'System Upgrade',    '23 Ring Road, Rajkot',        'Upgrade DVR to NVR',               'Completed',   1,    '2026-03-20');
