-- FurnitureCraft ERP Database Backup
-- Generated: 2026-04-17 09:02:09
-- Database: furniture_erp

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_email` (`email`),
  KEY `idx_contact_status` (`status`),
  KEY `idx_contact_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `furn_activity_logs`;
CREATE TABLE `furn_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_analytics_cache`;
CREATE TABLE `furn_analytics_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cache_key` varchar(100) NOT NULL,
  `cache_data` longtext NOT NULL,
  `data_type` varchar(20) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cache_key` (`cache_key`),
  KEY `idx_cache_key` (`cache_key`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_attendance`;
CREATE TABLE `furn_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `check_in_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `check_out_time` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  `late_minutes` int(11) DEFAULT NULL,
  `status` enum('present','late','absent','half_day') NOT NULL DEFAULT 'absent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date` date DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_emp_date` (`employee_id`,`date`),
  KEY `fk_attendance_employee` (`employee_id`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_attendance` VALUES
('3','4','0000-00-00 00:00:00','0000-00-00 00:00:00','127.0.0.1',NULL,'0',NULL,'absent','oo','2026-04-02 10:44:02','2026-04-02 10:50:37',NULL,NULL,'0.00'),
('4','4','2026-04-02 01:52:00','2026-04-02 03:13:00','127.0.0.1',NULL,'0',NULL,'present','done','2026-04-02 10:50:11','2026-04-02 14:19:14','2026-04-02','2','3.00'),
('5','4','2026-04-03 11:30:00','2026-04-03 11:30:00','127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-03 22:30:50','2026-04-03 22:30:50','2026-04-03','2','0.00'),
('6','26','2026-04-03 00:30:00','2026-04-03 23:30:00','127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-03 22:30:50','2026-04-03 22:30:50','2026-04-03','2','15.00'),
('7','29','2026-04-16 08:00:00',NULL,'127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-16 16:26:20','2026-04-16 16:26:20','2026-04-16','2','3.00'),
('8','30','2026-04-16 08:00:00',NULL,'127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-16 16:26:21','2026-04-16 16:26:21','2026-04-16','2','7.00'),
('9','4','2026-04-16 08:00:00',NULL,'127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-16 16:26:21','2026-04-16 16:26:21','2026-04-16','2','6.00'),
('10','26','2026-04-16 08:00:00',NULL,'127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-16 16:26:21','2026-04-16 16:26:21','2026-04-16','2','8.00'),
('11','28','2026-04-16 08:00:00',NULL,'127.0.0.1',NULL,'0',NULL,'present',NULL,'2026-04-16 16:26:21','2026-04-16 16:26:21','2026-04-16','2','8.00');

DROP TABLE IF EXISTS `furn_attendance_disputes`;
CREATE TABLE `furn_attendance_disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','resolved') NOT NULL DEFAULT 'pending',
  `manager_reply` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reason` text NOT NULL DEFAULT '',
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_attendance` (`attendance_id`),
  CONSTRAINT `fk_dispute_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `furn_attendance` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dispute_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_attendance_disputes` VALUES
('1','4','4','','pending',NULL,'2026-04-03 22:27:44','2026-04-03 22:27:44','hhh',NULL),
('2','4','4','','pending',NULL,'2026-04-03 22:28:10','2026-04-03 22:28:10','hhh',NULL);

DROP TABLE IF EXISTS `furn_attendance_reports`;
CREATE TABLE `furn_attendance_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `report_month` date NOT NULL,
  `total_days` int(11) NOT NULL DEFAULT 0,
  `present_days` int(11) NOT NULL DEFAULT 0,
  `late_days` int(11) NOT NULL DEFAULT 0,
  `absent_days` int(11) NOT NULL DEFAULT 0,
  `total_late_minutes` int(11) NOT NULL DEFAULT 0,
  `attendance_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_month` (`employee_id`,`report_month`),
  KEY `fk_report_employee` (`employee_id`),
  CONSTRAINT `fk_report_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_attendance_settings`;
CREATE TABLE `furn_attendance_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_attendance_settings` VALUES
('1','check_in_start_time','07:00:00','Daily check-in start time (24-hour format)','2026-03-30 13:50:42','2026-03-30 13:50:42'),
('2','check_in_end_time','09:00:00','Daily check-in end time (24-hour format)','2026-03-30 13:50:42','2026-03-30 13:50:42'),
('3','company_ip_address','192.168.1.100','Authorized company IP address for check-in','2026-03-30 13:50:42','2026-03-30 13:50:42'),
('4','late_threshold_minutes','30','Minutes after start time to mark as late','2026-03-30 13:50:42','2026-03-30 13:50:42'),
('5','working_days_per_month','22','Expected working days per month for calculations','2026-03-30 13:50:42','2026-03-30 13:50:42');

DROP TABLE IF EXISTS `furn_audit_logs`;
CREATE TABLE `furn_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_audit_logs` VALUES
('1',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 13:50:44'),
('2',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 13:50:44'),
('3',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 13:53:40'),
('4',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 13:53:40'),
('5',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 14:01:07'),
('6',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 14:01:07'),
('7',NULL,'invoice_system_created','database','1',NULL,'{\"message\": \"Professional PDF invoice system tables created\"}',NULL,NULL,'2026-03-30 14:01:08'),
('8',NULL,'schema_update','furn_orders',NULL,NULL,'{\"columns_added\": [\"invoice_generated\", \"invoice_id\"]}',NULL,NULL,'2026-03-30 14:01:08'),
('9',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 14:03:40'),
('10',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 14:03:40'),
('11',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 14:08:49'),
('12',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 14:08:49'),
('13',NULL,'invoice_system_created','database','1',NULL,'{\"message\": \"Professional PDF invoice system tables created\"}',NULL,NULL,'2026-03-30 14:08:50'),
('14',NULL,'schema_update','furn_orders',NULL,NULL,'{\"columns_added\": [\"invoice_generated\", \"invoice_id\"]}',NULL,NULL,'2026-03-30 14:08:50'),
('15',NULL,'attendance_system_created','database','1',NULL,'{\"message\": \"Attendance system tables created\"}',NULL,NULL,'2026-03-30 14:11:26'),
('16',NULL,'schema_update','furn_users',NULL,NULL,'{\"columns_added\": [\"employee_id\", \"department\", \"position\"]}',NULL,NULL,'2026-03-30 14:11:26'),
('17',NULL,'invoice_system_created','database','1',NULL,'{\"message\": \"Professional PDF invoice system tables created\"}',NULL,NULL,'2026-03-30 14:11:27'),
('18',NULL,'schema_update','furn_orders',NULL,NULL,'{\"columns_added\": [\"invoice_generated\", \"invoice_id\"]}',NULL,NULL,'2026-03-30 14:11:27'),
('19',NULL,'production_system_updated','database','1',NULL,'{\"message\": \"Production system tables created\"}',NULL,NULL,'2026-03-30 14:11:30'),
('20',NULL,'schema_update','furn_production_assignments',NULL,NULL,'{\"columns\": [\"id\", \"order_id\", \"employee_id\", \"assigned_by\", \"assigned_at\", \"started_at\", \"completed_at\", \"estimated_hours\", \"actual_hours\", \"status\", \"notes\", \"completion_notes\"]}',NULL,NULL,'2026-03-30 14:11:30'),
('21',NULL,'material_management_updated','database','1',NULL,'{\"message\": \"Material management system tables created\"}',NULL,NULL,'2026-03-30 14:11:32'),
('22',NULL,'schema_update','furn_materials',NULL,NULL,'{\"columns_added\": [\"category_id\", \"reorder_point\", \"last_purchase_date\", \"last_purchase_price\", \"average_cost\", \"shelf_life_days\", \"storage_location\", \"notes\"]}',NULL,NULL,'2026-03-30 14:11:32');

DROP TABLE IF EXISTS `furn_bank_accounts`;
CREATE TABLE `furn_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_holder` varchar(100) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `bank_address` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_bank_accounts` VALUES
('1','Commercial Bank of Ethiopia','1000123456789','SmartWorkshop Furniture PLC','Bole Branch','CBETETAA','Bole Road, Addis Ababa, Ethiopia','+251911000001','payments@smartworkshop.com','1','2026-04-01 15:40:51'),
('2','Awash Bank','0123456789012','SmartWorkshop Furniture PLC','Kazanchis Branch','AWINETAA','Kazanchis, Addis Ababa, Ethiopia','+251911000002','payments@smartworkshop.com','1','2026-04-01 15:40:51'),
('3','Dashen Bank','0987654321098','SmartWorkshop Furniture PLC','Meskel Square Branch','DASHETAA','Meskel Square, Addis Ababa, Ethiopia','+251911000003','payments@smartworkshop.com','1','2026-04-01 15:40:51'),
('4','Bank of Abyssinia','1234567890123','SmartWorkshop Furniture PLC','Piassa Branch','ABYSETAA','Piassa, Addis Ababa, Ethiopia','+251911000004','payments@smartworkshop.com','1','2026-04-01 15:40:51');

DROP TABLE IF EXISTS `furn_categories`;
CREATE TABLE `furn_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_categories` VALUES
('1','Sofa','Comfortable seating solutions for living rooms','1','2026-03-30 13:44:50'),
('2','Bed','Quality beds and bedroom furniture','1','2026-03-30 13:44:50'),
('3','Table','Dining tables, coffee tables, and work desks','1','2026-03-30 13:44:50'),
('4','Chair','Various types of chairs for different purposes','1','2026-03-30 13:44:50'),
('26','Cabinet','Cabinet furniture','1','2026-04-02 15:21:34'),
('27','shelf',NULL,'1','2026-04-06 16:35:37');

DROP TABLE IF EXISTS `furn_company_info`;
CREATE TABLE `furn_company_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Ethiopia',
  `phone_primary` varchar(20) DEFAULT NULL,
  `phone_secondary` varchar(20) DEFAULT NULL,
  `email_primary` varchar(100) DEFAULT NULL,
  `email_secondary` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_company_info` VALUES
('1','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 13:44:56','2026-03-30 13:44:56'),
('2','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 13:50:42','2026-03-30 13:50:42'),
('3','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 13:53:39','2026-03-30 13:53:39'),
('4','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 14:01:05','2026-03-30 14:01:05'),
('5','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 14:03:39','2026-03-30 14:03:39'),
('6','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 14:08:48','2026-03-30 14:08:48'),
('7','FurnitureCraft Workshop','Addis Ababa',NULL,'Addis Ababa',NULL,NULL,'Ethiopia','+251-911-123456',NULL,'info@furniturecraft.com',NULL,'www.furniturecraft.com',NULL,NULL,NULL,'2026-03-30 14:11:25','2026-03-30 14:11:25');

DROP TABLE IF EXISTS `furn_complaints`;
CREATE TABLE `furn_complaints` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `manager_response` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_complaints` VALUES
('1','13','3','on the  cost','the cost is not fair','resolved','the cost is fair you must pay the cost if you need as it sart for you','2','2026-04-03 12:01:10','2026-04-03 11:59:58'),
('2','18','3','on my estimated cost','the cost is not balanced','resolved','ok let i check and inform you','2','2026-04-06 13:44:50','2026-04-06 13:43:17'),
('3','18','3','on my estimated cost','cost is not fair','resolved','ok i will check and inform you','2','2026-04-06 13:48:29','2026-04-06 13:47:38');

DROP TABLE IF EXISTS `furn_dashboard_config`;
CREATE TABLE `furn_dashboard_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `widget_key` varchar(50) NOT NULL,
  `widget_name` varchar(100) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `chart_type` varchar(20) NOT NULL DEFAULT 'line',
  `data_source` varchar(100) NOT NULL,
  `refresh_interval` int(11) NOT NULL DEFAULT 300,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `widget_key` (`widget_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_dashboard_config` VALUES
('1','monthly_revenue','Monthly Revenue','1','1','line','orders','300','2026-03-30 13:50:47','2026-03-30 13:50:47'),
('2','orders_by_status','Orders by Status','1','2','pie','orders','300','2026-03-30 13:50:47','2026-03-30 13:50:47'),
('3','employee_hours','Employee Working Hours','1','3','bar','attendance','300','2026-03-30 13:50:47','2026-03-30 13:50:47'),
('4','low_stock_alerts','Low Stock Alerts','1','4','doughnut','materials','300','2026-03-30 13:50:47','2026-03-30 13:50:47'),
('5','top_products','Top Selling Products','1','5','bar','orders','300','2026-03-30 13:50:47','2026-03-30 13:50:47'),
('6','monthly_profit','Monthly Profit','1','6','line','profit','300','2026-03-30 13:50:47','2026-03-30 13:50:47');

DROP TABLE IF EXISTS `furn_dashboard_widgets`;
CREATE TABLE `furn_dashboard_widgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `widget_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`widget_config`)),
  `position_x` int(11) NOT NULL DEFAULT 0,
  `position_y` int(11) NOT NULL DEFAULT 0,
  `width` int(11) NOT NULL DEFAULT 4,
  `height` int(11) NOT NULL DEFAULT 3,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_dashboard_user` (`user_id`),
  CONSTRAINT `fk_dashboard_user` FOREIGN KEY (`user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_email_config`;
CREATE TABLE `furn_email_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) DEFAULT 587,
  `smtp_username` varchar(255) NOT NULL,
  `smtp_password` varchar(255) NOT NULL,
  `smtp_encryption` enum('none','tls','ssl') DEFAULT 'tls',
  `from_email` varchar(255) NOT NULL,
  `from_name` varchar(255) DEFAULT 'FurnitureCraft Workshop',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `furn_employee_reports`;
CREATE TABLE `furn_employee_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `report_type` enum('task_progress','material_usage','incident','daily_summary','leave_request') NOT NULL,
  `title` varchar(255) NOT NULL,
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Flexible JSON payload per report type' CHECK (json_valid(`report_data`)),
  `status` enum('submitted','reviewed','acknowledged') DEFAULT 'submitted',
  `manager_note` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `furn_employee_reports_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_employee_reports` VALUES
('1','4','incident','Incident: hnjkm,l','{\"incident_title\":\"hnjkm,l\",\"incident_datetime\":\"2026-03-30T21:04\",\"incident_type\":\"workplace_injury\",\"severity\":\"medium\",\"description\":\"gyhujiko\",\"action_taken\":\"hujiko\",\"injuries\":\"minor\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:05:14','2026-03-30 21:05:14'),
('2','4','leave_request','Leave Request: Sick (27 days)','{\"leave_type\":\"sick\",\"leave_from\":\"2026-04-04\",\"leave_to\":\"2026-04-30\",\"days\":27,\"reason\":\"hjnkml\",\"coverage\":\"jkl\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:42:12','2026-03-30 21:42:12');

DROP TABLE IF EXISTS `furn_employee_salary`;
CREATE TABLE `furn_employee_salary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `working_days_per_month` int(11) NOT NULL DEFAULT 26,
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `employee_id_2` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_employee_salary` VALUES
('1','4','8000.00','26','200.00','2026-04-02 13:46:01'),
('8','26','12000.00','26','150.00','2026-04-03 11:37:46'),
('9','30','30000.00','26','200.00','2026-04-16 23:44:24'),
('12','29','45000.00','26','200.00','2026-04-16 23:44:57');

DROP TABLE IF EXISTS `furn_gallery`;
CREATE TABLE `furn_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `category` enum('finished_products','customer_inspiration','showcase') DEFAULT 'finished_products',
  `furniture_type` varchar(100) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `employee_name` varchar(255) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `materials_used` text DEFAULT NULL,
  `production_hours` decimal(5,2) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `status` enum('active','inactive','featured') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category` (`category`),
  KEY `status` (`status`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_gallery` VALUES
('1','L-Shaped Sofa','33

hh','uploads/finished_products/FINISHED_8_1775482537.jpg','finished_products','Shelf','Metal Frame','2.00 × 2.00 × 2.00 cm','4','John Employee','11','Glass Tabletop: 4 pieces
Premium Leather: 4 square_feet',NULL,'0','0','active','2026-04-06 16:35:37','2026-04-06 16:35:37'),
('2','Premium Leather Sofa','ee

hh','uploads/finished_products/FINISHED_7_1775485783.jpg','finished_products','Chair','Particle Board','2.00 × 2.00 × 2.00 cm','4','John Employee','10','Fabric Upholstery: 3 yards
Fabric Upholstery: 2 yards',NULL,'0','0','active','2026-04-06 17:29:44','2026-04-06 17:29:44');

DROP TABLE IF EXISTS `furn_invoice_config`;
CREATE TABLE `furn_invoice_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(100) NOT NULL,
  `company_address` text NOT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `company_email` varchar(100) DEFAULT NULL,
  `company_website` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) NOT NULL,
  `bank_account_number` varchar(50) NOT NULL,
  `bank_account_name` varchar(100) NOT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `invoice_prefix` varchar(10) NOT NULL DEFAULT 'INV',
  `next_invoice_number` int(11) NOT NULL DEFAULT 1001,
  `due_days` int(11) NOT NULL DEFAULT 30,
  `currency` varchar(3) NOT NULL DEFAULT 'ETB',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_invoice_config` VALUES
('1','Custom Furniture Co.','123 Main Street, Addis Ababa, Ethiopia','+251 11 123 4567','info@customfurniture.com',NULL,NULL,'Commercial Bank of Ethiopia','1000123456789','Custom Furniture Co. Ltd',NULL,NULL,NULL,'INV','1001','30','ETB','0.00','Thank you for your business. Please make payment within 30 days.','2026-03-30 13:53:42','2026-03-30 13:53:42'),
('2','Custom Furniture Co.','123 Main Street, Addis Ababa, Ethiopia','+251 11 123 4567','info@customfurniture.com',NULL,NULL,'Commercial Bank of Ethiopia','1000123456789','Custom Furniture Co. Ltd',NULL,NULL,NULL,'INV','1001','30','ETB','0.00','Thank you for your business. Please make payment within 30 days.','2026-03-30 14:01:07','2026-03-30 14:01:07'),
('3','Custom Furniture Co.','123 Main Street, Addis Ababa, Ethiopia','+251 11 123 4567','info@customfurniture.com',NULL,NULL,'Commercial Bank of Ethiopia','1000123456789','Custom Furniture Co. Ltd',NULL,NULL,NULL,'INV','1001','30','ETB','0.00','Thank you for your business. Please make payment within 30 days.','2026-03-30 14:03:40','2026-03-30 14:03:40'),
('4','Custom Furniture Co.','123 Main Street, Addis Ababa, Ethiopia','+251 11 123 4567','info@customfurniture.com',NULL,NULL,'Commercial Bank of Ethiopia','1000123456789','Custom Furniture Co. Ltd',NULL,NULL,NULL,'INV','1001','30','ETB','0.00','Thank you for your business. Please make payment within 30 days.','2026-03-30 14:08:50','2026-03-30 14:08:50'),
('5','Custom Furniture Co.','123 Main Street, Addis Ababa, Ethiopia','+251 11 123 4567','info@customfurniture.com',NULL,NULL,'Commercial Bank of Ethiopia','1000123456789','Custom Furniture Co. Ltd',NULL,NULL,NULL,'INV','1001','30','ETB','0.00','Thank you for your business. Please make payment within 30 days.','2026-03-30 14:11:27','2026-03-30 14:11:27');

DROP TABLE IF EXISTS `furn_invoice_items`;
CREATE TABLE `furn_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_invoice_item_invoice` (`invoice_id`),
  KEY `fk_invoice_item_product` (`product_id`),
  CONSTRAINT `fk_invoice_item_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `furn_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_item_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_invoice_payments`;
CREATE TABLE `furn_invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_invoice_payment_invoice` (`invoice_id`),
  KEY `fk_invoice_payment_created_by` (`created_by`),
  CONSTRAINT `fk_invoice_payment_created_by` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `furn_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_invoices`;
CREATE TABLE `furn_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(20) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
  `pdf_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_invoice_order` (`order_id`),
  KEY `fk_invoice_customer` (`customer_id`),
  KEY `fk_invoice_created_by` (`created_by`),
  CONSTRAINT `fk_invoice_created_by` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_invoice_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_invoice_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_low_stock_alerts`;
CREATE TABLE `furn_low_stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `current_stock` decimal(10,2) NOT NULL,
  `minimum_stock` decimal(10,2) NOT NULL,
  `alert_level` enum('low','critical') NOT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_alert_material` (`material_id`),
  KEY `fk_alert_resolved_by` (`resolved_by`),
  CONSTRAINT `fk_alert_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alert_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_manager_reports`;
CREATE TABLE `furn_manager_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `manager_id` int(11) NOT NULL,
  `report_type` varchar(50) NOT NULL,
  `report_to_role` enum('admin','manager','employee') DEFAULT 'admin',
  `report_to_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `report_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`report_data`)),
  `status` enum('submitted','reviewed','acknowledged') DEFAULT 'submitted',
  `admin_feedback` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `manager_id` (`manager_id`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `furn_manager_reports_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `furn_manager_reports_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_manager_reports` VALUES
('1','2','','admin','1','Production Update — nm,','{\"order_ref\":\"nm,\",\"progress\":50,\"update\":\"jkml,\",\"blockers\":\"hbjnm\",\"est_completion\":\"2026-04-04\"}','submitted',NULL,NULL,NULL,'2026-03-30 20:15:43','2026-03-30 20:15:43'),
('2','2','','admin','1','Production Update — hbjn','{\"order_ref\":\"hbjn\",\"progress\":50,\"update\":\"ghjkl\",\"blockers\":\"gvhbjn\",\"est_completion\":\"2026-04-02\"}','submitted',NULL,NULL,NULL,'2026-03-30 20:16:32','2026-03-30 20:16:32'),
('3','2','','','4','Production Update — Mar 30, 2026','{\"order_ref\":\"\",\"progress\":50,\"update\":\"\",\"blockers\":\"\",\"est_completion\":\"\"}','submitted',NULL,NULL,NULL,'2026-03-30 20:18:51','2026-03-30 20:18:51'),
('4','2','','admin','1','Production Update — bnm,','{\"order_ref\":\"bnm,\",\"progress\":50,\"update\":\"ghjkl;\",\"blockers\":\"ghjkl\",\"est_completion\":\"2026-04-10\"}','submitted',NULL,NULL,NULL,'2026-03-30 20:21:14','2026-03-30 20:21:14'),
('5','2','','admin','1','Daily Summary — 2026-03-30','{\"report_date\":\"2026-03-30\",\"summary\":\"hbnjkml,;.\",\"challenges\":\"hjkl;\",\"tomorrow_plan\":\"jkl;\"}','submitted',NULL,NULL,NULL,'2026-03-30 20:29:04','2026-03-30 20:29:04'),
('6','2','','admin','1','Production Update — nkk','{\"order_ref\":\"nkk\",\"progress\":50,\"update\":\"bnjmk\",\"blockers\":\"hyujikol\",\"est_completion\":\"2026-04-03\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:02:51','2026-03-30 21:02:51'),
('7','2','','admin','1','Inventory Summary — Mar 30, 2026','{\"summary\":\"gvbhjk\",\"low_stock\":\"hjk\",\"action_needed\":\"ghjkl\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:06:58','2026-03-30 21:06:58'),
('8','1','business_performance','manager','2','Business Performance — hbjnkml;','{\"period\":\"hbjnkml;\",\"summary\":\"bhnjkm\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:09:36','2026-03-30 21:09:36'),
('9','2','','admin','1','Production Update — b nm,','{\"order_ref\":\"b nm,\",\"progress\":50,\"update\":\"bhnjkml,\",\"blockers\":\"ghbjkl\",\"est_completion\":\"2026-04-04\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:10:53','2026-03-30 21:10:53'),
('10','2','','admin','1','Inventory Summary — Mar 30, 2026','{\"summary\":\"njmkl,;\",\"low_stock\":\"hjkl\",\"action_needed\":\"bhnjkl\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:11:48','2026-03-30 21:11:48'),
('11','1','business_performance','manager','2','Business Performance — bhnj','{\"period\":\"bhnj\",\"summary\":\"ghbjnk\"}','reviewed',NULL,'2','2026-03-30 22:03:38','2026-03-30 21:21:32','2026-03-30 22:03:38'),
('12','2','','admin','1','Production Update — cvgbhnjm','{\"order_ref\":\"cvgbhnjm\",\"progress\":50,\"update\":\"fcgvhjk\",\"blockers\":\"ghjk\",\"est_completion\":\"2026-04-18\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:23:23','2026-03-30 21:23:23'),
('13','2','','admin','1','Inventory Summary — Mar 30, 2026','{\"summary\":\"gff\",\"low_stock\":\"ffffffffffffff\",\"action_needed\":\"ffffffffffffffff\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:24:29','2026-03-30 21:24:29'),
('14','2','','','4','Inventory Summary — Mar 30, 2026','{\"summary\":\"\",\"low_stock\":\"\",\"action_needed\":\"\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:25:13','2026-03-30 21:25:13'),
('15','2','','admin',NULL,'Incident: hnjkm,l','{\"incident_title\":\"hnjkm,l\",\"incident_datetime\":\"2026-03-30T21:25\",\"incident_type\":\"workplace_injury\",\"severity\":\"medium\",\"description\":\"zxcv\",\"action_taken\":\"dsfg\",\"injuries\":\"minor\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:26:24','2026-03-30 21:26:24'),
('16','2','production_update','admin','1','Production Update — bnm','{\"order_ref\":\"bnm\",\"progress\":50,\"update\":\"jkl\",\"blockers\":\"njkml\",\"est_completion\":\"2026-04-04\"}','reviewed',NULL,'1','2026-04-01 10:42:42','2026-03-30 21:36:15','2026-04-01 10:42:42'),
('17','2','production_update','employee','4','Production Update — Mar 30, 2026','{\"order_ref\":\"\",\"progress\":50,\"update\":\"\",\"blockers\":\"\",\"est_completion\":\"\"}','submitted',NULL,NULL,NULL,'2026-03-30 21:36:50','2026-03-30 21:36:50'),
('18','2','production_update','employee','4','Production Update — Mar 30, 2026','{\"order_ref\":\"\",\"progress\":50,\"update\":\"\",\"blockers\":\"\",\"est_completion\":\"\"}','reviewed',NULL,'4','2026-04-02 15:36:19','2026-03-30 21:40:40','2026-04-02 15:36:19'),
('19','2','inventory_summary','admin','1','Inventory Summary — Mar 31, 2026','{\"summary\":\"fhvjckoxl\",\"low_stock\":\"ghyuji\",\"action_needed\":\"ty7u8i\"}','submitted',NULL,NULL,NULL,'2026-03-31 21:31:45','2026-03-31 21:31:45');

DROP TABLE IF EXISTS `furn_material_categories`;
CREATE TABLE `furn_material_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_material_categories` VALUES
('1','Wood','Various types of wood materials for furniture construction','1','2026-03-30 14:11:32'),
('2','Upholstery','Fabric, leather, and cushioning materials','1','2026-03-30 14:11:32'),
('3','Hardware','Metal components, fittings, and accessories','1','2026-03-30 14:11:32'),
('4','Glass','Glass materials for tabletops and decorative elements','1','2026-03-30 14:11:32'),
('5','Foam','Padding and cushioning materials','1','2026-03-30 14:11:32'),
('6','Finishing','Stains, paints, and protective coatings','1','2026-03-30 14:11:32');

DROP TABLE IF EXISTS `furn_material_purchases`;
CREATE TABLE `furn_material_purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `action` enum('restock','adjustment') NOT NULL DEFAULT 'restock',
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `invoice_number` varchar(100) DEFAULT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `purchase_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `furn_material_requests`;
CREATE TABLE `furn_material_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_requested` decimal(10,2) NOT NULL,
  `purpose` text DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `manager_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rejection_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `material_id` (`material_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_material_requests` VALUES
('1','4','1','1.00','for my order',NULL,'approved','2','2026-04-06 15:07:00',NULL,'2026-04-06 15:06:27',NULL),
('2','4','5','4.00','Required for assigned order production','18','approved','2','2026-04-14 17:28:53',NULL,'2026-04-14 16:46:56',NULL),
('3','4','3','5.00','Required for assigned order production','18','approved','2','2026-04-14 17:30:07',NULL,'2026-04-14 16:46:56',NULL),
('4','4','6','6.00','Required for assigned order production','18','approved','2','2026-04-14 17:29:47',NULL,'2026-04-14 16:46:56',NULL);

DROP TABLE IF EXISTS `furn_material_reservations`;
CREATE TABLE `furn_material_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `status` enum('reserved','used','cancelled') NOT NULL DEFAULT 'reserved',
  PRIMARY KEY (`id`),
  KEY `fk_reservation_order` (`order_id`),
  KEY `fk_reservation_material` (`material_id`),
  CONSTRAINT `fk_reservation_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reservation_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_material_transactions`;
CREATE TABLE `furn_material_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','usage','adjustment','transfer_in','transfer_out','return') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_transaction_material` (`material_id`),
  KEY `fk_transaction_user` (`created_by`),
  CONSTRAINT `fk_transaction_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transaction_user` FOREIGN KEY (`created_by`) REFERENCES `furn_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_material_usage`;
CREATE TABLE `furn_material_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_used` decimal(10,2) NOT NULL,
  `waste_amount` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `employee_id` (`employee_id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_material_usage` VALUES
('1','4','8','4','4.00','1.00','','2026-04-06 15:22:46'),
('2','4','8','6','4.00','1.00','','2026-04-06 15:22:46'),
('3','4','8','6','4.00','0.00','Used in task #8 — order #11','2026-04-06 16:35:37'),
('4','4','8','1','4.00','0.00','Used in task #8 — order #11','2026-04-06 16:35:37'),
('5','4','7','5','3.00','0.00','Used in task #7 — order #10','2026-04-06 17:29:43'),
('6','4','7','5','2.00','0.00','Used in task #7 — order #10','2026-04-06 17:29:43');

DROP TABLE IF EXISTS `furn_materials`;
CREATE TABLE `furn_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pieces',
  `current_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reserved_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `minimum_stock` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `reorder_point` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_purchase_date` date DEFAULT NULL,
  `last_purchase_price` decimal(10,2) DEFAULT NULL,
  `average_cost` decimal(10,2) DEFAULT NULL,
  `shelf_life_days` int(11) DEFAULT NULL,
  `storage_location` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_material_name` (`name`),
  KEY `fk_material_category` (`category_id`),
  CONSTRAINT `fk_material_category` FOREIGN KEY (`category_id`) REFERENCES `furn_material_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_materials` VALUES
('1','Premium Leather','High-quality full-grain leather for upholstery','square_feet','495.00','0.00','100.00','150.00','Ethiopian Leather Co.','1','2026-03-30 14:11:30','2026-04-06 15:06:59','2','50.00','2026-02-01','150.00','145.00',NULL,'Warehouse A-Section 1',NULL),
('2','Oak Wood','Premium solid oak wood for furniture frames','board_feet','200.00','0.00','50.00','85.00','Addis Ababa Timber','1','2026-03-30 14:11:30','2026-03-30 14:11:32','1','25.00','2026-02-05','85.00','82.50',NULL,'Warehouse B-Section 2',NULL),
('3','Steel Frame','Industrial steel frames for structural support','pieces','50.00','5.00','10.00','1200.00','Metal Works Ltd','1','2026-03-30 14:11:30','2026-04-14 17:30:07',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,NULL),
('4','Foam Padding','High-density foam for cushioning','pieces','40.00','0.00','20.00','75.00','Comfort Materials Inc','1','2026-03-30 14:11:30','2026-04-06 19:08:16',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,NULL),
('5','Fabric Upholstery','Premium fabric for seating surfaces','yards','4.00','4.00','50.00','45.00','Textile Solutions','1','2026-03-30 14:11:30','2026-04-14 17:28:53',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,NULL),
('6','Glass Tabletop','Tempered glass for table surfaces','pieces','25.00','6.00','5.00','350.00','Glass Manufacturing Co.','1','2026-03-30 14:11:30','2026-04-14 17:29:47',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,NULL),
('7','Stainless Steel Hardware','Quality hardware and fittings','pieces','500.00','0.00','100.00','12.00','Hardware Distributors','1','2026-03-30 14:11:30','2026-03-30 14:11:30',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,NULL);

DROP TABLE IF EXISTS `furn_messages`;
CREATE TABLE `furn_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  KEY `is_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_messages` VALUES
('1','1','2','to knew about my order','vccccc','1','2026-04-02 15:46:25','2026-04-01 10:21:46');

DROP TABLE IF EXISTS `furn_notifications`;
CREATE TABLE `furn_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `related_id` int(11) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` timestamp NULL DEFAULT NULL,
  `priority` enum('low','normal','high') DEFAULT 'normal',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_notifications` VALUES
('1','2','order','New Order Pending Review','New custom furniture order from Alice Customer','2','/manager/cost-estimation','1','2026-04-01 12:35:34',NULL,'normal'),
('2','2','order','New Order Pending Review','New custom furniture order from Alice Customer','5','/manager/cost-estimation','1','2026-04-01 12:52:16',NULL,'normal'),
('3','3','order','Cost Estimation Ready','Your order ORD-2026-75116 has been reviewed. Estimated cost: $5,555.00. Deposit required: $2,222.00','5','/customer/my-orders','1','2026-04-01 12:54:53',NULL,'normal'),
('4','3','order','Cost Estimation Ready','Your order ORD-2026-87009 has been reviewed. Estimated cost: $7,744.00. Deposit required: $3,097.60','2','/customer/my-orders','1','2026-04-01 14:01:56',NULL,'normal'),
('5','3','order','Cost Estimation Ready','Your order ORD-2026-87009 has been reviewed. Estimated cost: $7,744.00. Deposit required: $3,097.60','2','/customer/my-orders','1','2026-04-01 14:06:40',NULL,'normal'),
('6','2','order','New Order Pending Review','New custom furniture order from Alice Customer','6','/manager/cost-estimation','1','2026-04-01 15:12:47',NULL,'normal'),
('7','3','order','Cost Estimation Ready','Your order ORD-2026-52412 has been reviewed. Estimated cost: $5,000.00. Deposit required: $2,000.00','6','/customer/my-orders','1','2026-04-01 15:13:49',NULL,'normal'),
('8','2','payment','Remaining Balance Payment','Customer submitted remaining balance payment','5',NULL,'1','2026-04-01 18:57:32',NULL,'normal'),
('9','3','order','Cost Estimation Ready','Your order ORD-2026-24713 has been reviewed. Estimated cost: $5,005.00. Deposit required: $2,002.00','1','/customer/my-orders','1','2026-04-01 19:20:54',NULL,'normal'),
('10','2','payment','Remaining Balance Payment','Customer submitted remaining balance payment','6',NULL,'1','2026-04-01 20:45:33',NULL,'normal'),
('11','2','rating','New Customer Rating','Alice Customer rated ORD-2026-52412 — ★★★★☆ (4/5)','6','/manager/completed-tasks','1','2026-04-01 21:01:56',NULL,'normal'),
('12','4','rating','You received a rating!','Alice Customer rated your work on ORD-2026-52412 — ★★★★☆ (4/5)','6','/employee/tasks','1','2026-04-01 21:01:56',NULL,'normal'),
('13','2','order','New Order Pending Review','New custom furniture order from Alice Customer','7','/manager/cost-estimation','1','2026-04-02 09:37:00',NULL,'normal'),
('14','3','order','Cost Estimation Ready','Your order ORD-2026-36487 has been reviewed. Estimated cost: $5,590.00. Deposit required: $2,236.00','7','/customer/my-orders','1','2026-04-02 09:38:20',NULL,'normal'),
('15','2','payment','Remaining Balance Payment','Customer submitted remaining balance payment','7',NULL,'1','2026-04-02 09:46:46',NULL,'normal'),
('16','2','order','New Order Pending Review','New custom furniture order from Alice Customer','8','/manager/cost-estimation','1','2026-04-02 15:12:03',NULL,'normal'),
('17','3','order','Cost Estimation Ready','Your order ORD-2026-84945 has been reviewed. Estimated cost: $5,000.00. Deposit required: $2,000.00','8','/customer/my-orders','1','2026-04-02 15:13:55',NULL,'normal'),
('18','2','payment','Remaining Balance Payment','Customer submitted remaining balance payment','8',NULL,'1','2026-04-02 15:22:40',NULL,'normal'),
('19','2','rating','New Customer Rating','Alice Customer rated ORD-2026-84945 — ★★★★☆ (4/5)','8','/manager/completed-tasks','1','2026-04-02 15:27:53',NULL,'normal'),
('20','4','rating','You received a rating!','Alice Customer rated your work on ORD-2026-84945 — ★★★★☆ (4/5)','8','/employee/tasks','1','2026-04-02 15:27:53',NULL,'normal'),
('21','2','order','New Order Pending Review','New custom furniture order from Alice Customer','9','/manager/cost-estimation','1','2026-04-02 15:29:35',NULL,'normal'),
('22','3','order','Cost Estimation Ready','Your order ORD-2026-42530 has been reviewed. Estimated cost: $3,333.00. Deposit required: $1,333.20','9','/customer/my-orders','1','2026-04-02 15:30:54',NULL,'normal'),
('23','2','order','New Order Pending Review','New order created by employee John Employee','10','/manager/cost-estimation','1','2026-04-03 09:09:02',NULL,'normal'),
('24','25','order','Cost Estimation Ready','Your order ORD-2026-90043 has been reviewed. Estimated cost: $5,000.00. Deposit required: $2,000.00','10','/customer/my-orders','0','2026-04-03 09:10:27',NULL,'normal'),
('25','2','order','New Order Pending Review','New order created by employee John Employee','11','/manager/cost-estimation','1','2026-04-03 09:22:18',NULL,'normal'),
('26','25','order','Cost Estimation Ready','Your order ORD-2026-23329 has been reviewed. Estimated cost: $3,333.00. Deposit required: $1,333.20','11','/customer/my-orders','0','2026-04-03 09:23:07',NULL,'normal'),
('27','2','order','New Order Pending Review','New order created by employee John Employee','12','/manager/cost-estimation','1','2026-04-03 09:34:59',NULL,'normal'),
('28','25','order','Cost Estimation Ready','Your order ORD-2026-38528 has been reviewed. Estimated cost: $7,777.00. Deposit required: $3,110.80','12','/customer/my-orders','0','2026-04-03 09:36:41',NULL,'normal'),
('29','2','order','New Order Pending Review','New custom furniture order from Alice Customer','13','/manager/cost-estimation','1','2026-04-03 11:14:36',NULL,'normal'),
('30','2','complaint','Customer Complaint: on the  cost','Complaint from Alice Customer regarding Order ORD-2026-05129:

Subject: on the  cost

the cost is not fear','13','/manager/orders','1','2026-04-03 11:23:14',NULL,'normal'),
('31','2','complaint','⚠ Customer Complaint: on the  cost','Alice Customer — Order ORD-2026-05129','13','/manager/orders','1','2026-04-03 11:59:58',NULL,'normal'),
('32','2','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','14','/manager/cost-estimation','1','2026-04-04 14:12:11',NULL,'normal'),
('33','27','order','Cost Estimation Ready','Your order ORD-2026-47249 has been reviewed. Estimated cost: $3,650.00. Deposit required: $1,460.00','14','/customer/my-orders','0','2026-04-04 14:13:43',NULL,'normal'),
('34','2','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','15','/manager/cost-estimation','1','2026-04-05 14:22:25',NULL,'normal'),
('35','27','order','Cost Estimation Ready','Your order ORD-2026-83433 has been reviewed. Estimated cost: $5,555.00. Deposit required: $2,222.00','15','/customer/my-orders','0','2026-04-05 14:24:38',NULL,'normal'),
('36','1','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','16','/manager/cost-estimation','1','2026-04-05 14:40:02','2026-04-07 16:37:52','normal'),
('37','2','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','16','/manager/cost-estimation','1','2026-04-05 14:40:02','2026-04-07 17:49:21','normal'),
('39','1','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','17','/manager/cost-estimation','1','2026-04-05 14:51:57','2026-04-07 16:39:00','normal'),
('40','2','order','New Order Pending Review','New custom furniture order from DEREJE AYELE','17','/manager/cost-estimation','1','2026-04-05 14:51:57',NULL,'normal'),
('41','2','rating','New Customer Rating','Alice Customer rated ORD-2026-36487 — ★★★★★ (5/5)','7','/manager/completed-tasks','1','2026-04-05 19:29:10',NULL,'normal'),
('42','4','rating','You received a rating!','Alice Customer rated your work on ORD-2026-36487 — ★★★★★ (5/5)','7','/employee/tasks','1','2026-04-05 19:29:10',NULL,'normal'),
('43','2','order','New Order Pending Review','New custom furniture order from Alice Customer','18','/manager/cost-estimation','1','2026-04-06 13:26:39',NULL,'normal'),
('44','3','order','Cost Estimation Ready','Your order ORD-2026-59707 has been reviewed. Estimated cost: $6,000.00. Deposit required: $2,400.00','18','/customer/my-orders','1','2026-04-06 13:27:41','2026-04-12 23:29:36','normal'),
('45','2','complaint','⚠ Customer Complaint: on my estimated cost','Alice Customer — Order ORD-2026-59707','18','/manager/orders','1','2026-04-06 13:43:17',NULL,'normal'),
('46','2','complaint','⚠ Customer Complaint: on my estimated cost','Alice Customer — Order ORD-2026-59707','18','/manager/orders','1','2026-04-06 13:47:38',NULL,'normal'),
('47','27','order','Cost Estimation Ready','Your order ORD-2026-54957 has been reviewed. Estimated cost: $5,000.00. Deposit required: $2,000.00','16','/customer/my-orders','0','2026-04-06 14:13:57',NULL,'normal'),
('48','2','order','New Order Pending Review','New custom furniture order from Alice Customer','19','/manager/cost-estimation','1','2026-04-06 16:27:53','2026-04-07 17:49:10','normal'),
('49','3','order','Cost Estimation Ready','Your order ORD-2026-39219 has been reviewed. Estimated cost: $5,000.00. Deposit required: $2,000.00','19','/customer/my-orders','1','2026-04-06 16:30:20','2026-04-07 16:36:33','normal'),
('50','2','order','New Order Pending Review','New custom furniture order from Alice Customer','20','/manager/cost-estimation','1','2026-04-06 16:40:53','2026-04-07 17:44:58','normal'),
('51','3','order','Cost Estimation Ready','Your order ORD-2026-27246 has been reviewed. Estimated cost: $6,000.00. Deposit required: $2,400.00','20','/customer/my-orders','1','2026-04-06 16:41:31','2026-04-07 16:28:13','normal'),
('52','2','order','New Order Pending Review','New custom furniture order from Alice Customer','21','/manager/cost-estimation','1','2026-04-06 17:22:26','2026-04-07 17:38:30','normal'),
('53','3','order','Cost Estimation Ready','Your order ORD-2026-46975 has been reviewed. Estimated cost: $9,000.00. Deposit required: $3,600.00','21','/customer/my-orders','1','2026-04-06 17:24:50','2026-04-07 16:26:38','normal'),
('54','1','order','New Order Pending Review','New custom furniture order from Alice Customer','22','/manager/cost-estimation','1','2026-04-12 23:04:27','2026-04-13 16:29:01','normal'),
('55','2','order','New Order Pending Review','New custom furniture order from Alice Customer','22','/manager/cost-estimation','1','2026-04-12 23:04:27','2026-04-12 23:06:00','normal'),
('57','3','order','Cost Estimation Ready','Your order ORD-2026-13546 has been reviewed. Estimated cost: $10,500.00. Deposit required: $4,200.00','22','/customer/my-orders','1','2026-04-12 23:07:25','2026-04-16 18:43:23','normal'),
('58','4','production','New Task Assigned','You have been assigned to produce: L-Shaped Sofa (Order ORD-2026-27246)','20','/employee/tasks','1','2026-04-14 15:46:02','2026-04-14 15:49:56','high'),
('59','4','production','New Task Assigned','You have been assigned to produce: Premium Leather Sofa (Order ORD-2026-59707)','18','/employee/tasks','1','2026-04-14 15:46:16','2026-04-14 15:49:50','high');

DROP TABLE IF EXISTS `furn_order_customizations`;
CREATE TABLE `furn_order_customizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `size_modifications` text DEFAULT NULL,
  `color_selection` varchar(50) DEFAULT NULL,
  `material_upgrade` varchar(100) DEFAULT NULL,
  `additional_features` text DEFAULT NULL,
  `reference_image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `adjusted_price` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_order` (`order_id`),
  KEY `fk_product_customization` (`product_id`),
  CONSTRAINT `fk_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_customization` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_order_materials`;
CREATE TABLE `furn_order_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `material_name` varchar(255) NOT NULL,
  `quantity_used` decimal(10,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(50) NOT NULL DEFAULT '',
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `task_id` (`task_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_order_materials` VALUES
('5','5','1','5','Fabric Upholstery','6.000','yards','45.00','270.00','2026-04-01 18:53:55'),
('6','5','1','2','Oak Wood','7.000','board_feet','85.00','595.00','2026-04-01 18:53:55'),
('7','6','2','4','Foam Padding','4.000','pieces','75.00','300.00','2026-04-01 19:26:38'),
('8','6','2','5','Fabric Upholstery','5.000','yards','45.00','225.00','2026-04-01 19:26:38'),
('9','7','3','5','Fabric Upholstery','4.000','yards','45.00','180.00','2026-04-02 09:44:10'),
('10','7','3','5','Fabric Upholstery','5.000','yards','45.00','225.00','2026-04-02 09:44:10'),
('11','8','4','5','Fabric Upholstery','3.000','yards','45.00','135.00','2026-04-02 15:19:51'),
('12','8','4','5','Fabric Upholstery','3.000','yards','45.00','135.00','2026-04-02 15:19:51'),
('15','12','6','5','Fabric Upholstery','2.000','yards','45.00','90.00','2026-04-06 14:20:12'),
('16','12','6','4','Foam Padding','3.000','pieces','75.00','225.00','2026-04-06 14:20:12'),
('17','1','5','4','Foam Padding','2.000','pieces','75.00','150.00','2026-04-06 14:21:15'),
('18','1','5','6','Glass Tabletop','2.000','pieces','350.00','700.00','2026-04-06 14:21:15'),
('19','11','8','6','Glass Tabletop','4.000','pieces','350.00','1400.00','2026-04-06 16:35:37'),
('20','11','8','1','Premium Leather','4.000','square_feet','150.00','600.00','2026-04-06 16:35:37'),
('21','10','7','5','Fabric Upholstery','3.000','yards','45.00','135.00','2026-04-06 17:29:43'),
('22','10','7','5','Fabric Upholstery','2.000','yards','45.00','90.00','2026-04-06 17:29:43');

DROP TABLE IF EXISTS `furn_orders`;
CREATE TABLE `furn_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `status` enum('pending_review','pending_cost_approval','cost_estimated','waiting_for_deposit','deposit_paid','payment_verified','in_production','ready_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending_review',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `deposit_paid` decimal(10,2) DEFAULT NULL,
  `deposit_paid_at` timestamp NULL DEFAULT NULL,
  `production_started_at` timestamp NULL DEFAULT NULL,
  `production_completed_at` timestamp NULL DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `invoice_generated` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_id` int(11) DEFAULT NULL,
  `estimated_completion_date` date DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `furniture_type` varchar(100) DEFAULT NULL,
  `furniture_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `budget_range` varchar(50) DEFAULT NULL,
  `preferred_delivery_date` date DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `material` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `design_description` text DEFAULT NULL,
  `design_image` varchar(255) DEFAULT NULL,
  `special_notes` text DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `estimated_production_days` int(11) DEFAULT NULL,
  `manager_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `remaining_balance` decimal(12,2) DEFAULT NULL,
  `final_payment_paid` decimal(12,2) DEFAULT NULL,
  `final_payment_paid_at` timestamp NULL DEFAULT NULL,
  `profit_calculated` tinyint(1) DEFAULT 0,
  `profit_calculation_date` timestamp NULL DEFAULT NULL,
  `assigned_employee_id` int(11) DEFAULT NULL,
  `created_by_employee_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `fk_customer` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_number` (`order_number`),
  KEY `fk_order_invoice` (`invoice_id`),
  CONSTRAINT `fk_customer` FOREIGN KEY (`customer_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `furn_invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_orders` VALUES
('1','3','ORD-2026-24713','ready_for_delivery','0.00','2002.00','2002.00',NULL,NULL,'2026-04-06 14:24:15',NULL,NULL,'2026-04-01 11:30:19','2026-04-06 14:24:15','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-23','6.00','7.00','7.00','Leather','White','dfrghjk','uploads/designs/ORD_2026_24713_1775032219.jpg','','5005.00','5','bnjmk,','2','2026-04-01 19:20:53','3003.00',NULL,NULL,'0',NULL,'4',NULL),
('2','3','ORD-2026-87009','completed','0.00','3097.60','3097.60',NULL,NULL,NULL,NULL,NULL,'2026-04-01 12:35:29','2026-04-01 20:47:55','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-11','22.00','2.00','2.00','MDF','White','i want a modern','uploads/designs/ORD_2026_87009_1775036129.jpg','','7744.00','9','','2','2026-04-01 14:06:40','0.00',NULL,NULL,'0',NULL,NULL,NULL),
('5','3','ORD-2026-75116','ready_for_delivery','0.00','2222.00','2222.00',NULL,NULL,'2026-04-06 14:35:27',NULL,NULL,'2026-04-01 12:52:16','2026-04-06 14:35:27','0',NULL,NULL,NULL,'Chair','L-Shaped Sofa','1','Under ETB 5,000','2026-04-30','2.00','2.00','2.00','Fabric','White','i wnt the modrn one','uploads/designs/ORD_2026_75116_1775037136.jpg','','5555.00','7','b nm,','2','2026-04-01 12:54:53','0.00',NULL,NULL,'0',NULL,'4',NULL),
('6','3','ORD-2026-52412','completed','0.00','2000.00','2000.00','2026-04-01 16:52:23',NULL,'2026-04-01 19:27:45',NULL,NULL,'2026-04-01 15:12:46','2026-04-01 20:47:31','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 10,000 - ETB 20,000','2026-04-10','5.00','5.00','5.00','Leather','Brown','Luxurious 3-seater leather sofa with premium cushioning','uploads/designs/ORD_2026_52412_1775045566.jpg','','5000.00','5','','2','2026-04-01 15:13:49','0.00',NULL,NULL,'0',NULL,'4',NULL),
('7','3','ORD-2026-36487','ready_for_delivery','0.00','2236.00','2236.00',NULL,NULL,'2026-04-06 14:31:44',NULL,NULL,'2026-04-02 09:37:00','2026-04-06 14:31:44','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-30','3.00','3.00','3.00','Leather','Natural Wood','Luxurious 3-seater leather sofa with premium cushioning

444','uploads/designs/ORD_2026_36487_1775111820.jpg','','5590.00','1','emngffg','2','2026-04-02 09:38:19','0.00',NULL,NULL,'0',NULL,'4',NULL),
('8','3','ORD-2026-84945','completed','0.00','2000.00','2000.00',NULL,NULL,'2026-04-02 15:21:34',NULL,NULL,'2026-04-02 15:12:03','2026-04-02 15:25:53','0',NULL,NULL,NULL,'Cabinet','Premium Leather Sofa','1','ETB 10,000 - ETB 20,000','2026-04-23','2.00','22.00','2.00','Glass','Gray','hhhh','uploads/designs/ORD_2026_84945_1775131923.jpg','','5000.00','1','','2','2026-04-02 15:13:55','0.00',NULL,NULL,'0',NULL,'4',NULL),
('9','3','ORD-2026-42530','cancelled','0.00','1333.20',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-02 15:29:35','2026-04-06 14:00:47','0',NULL,NULL,NULL,'Shelf','L-Shaped Sofa2','1','Under ETB 5,000','2026-04-10','22.00','22.00','21.50','Glass','Gray','mm','uploads/designs/ORD_2026_42530_1775132975.jpg','','3333.00','5','t','2','2026-04-02 15:30:54',NULL,NULL,NULL,'0',NULL,NULL,NULL),
('10','25','ORD-2026-90043','ready_for_delivery','0.00','2000.00','2000.00',NULL,NULL,'2026-04-06 17:29:43',NULL,NULL,'2026-04-03 09:09:01','2026-04-06 17:29:43','0',NULL,NULL,NULL,'Chair','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-25','2.00','2.00','2.00','Particle Board','Gray','ee','uploads/designs/ORD_2026_90043_1775196541.jpg','','5000.00','5','','2','2026-04-03 09:10:26','3000.00',NULL,NULL,'0',NULL,'4','4'),
('11','25','ORD-2026-23329','ready_for_delivery','0.00','1333.20','1333.20',NULL,NULL,'2026-04-06 16:36:06',NULL,NULL,'2026-04-03 09:22:18','2026-04-06 16:36:06','0',NULL,NULL,NULL,'Shelf','L-Shaped Sofa','1','Under ETB 5,000','2026-04-25','2.00','2.00','2.00','Metal Frame','Brown','33','uploads/designs/ORD_2026_23329_1775197338.jpg','3','3333.00','1','','2','2026-04-03 09:23:06','1999.80',NULL,NULL,'0',NULL,'4','4'),
('12','25','ORD-2026-38528','ready_for_delivery','0.00','3110.80','3110.80',NULL,NULL,'2026-04-06 14:24:23',NULL,NULL,'2026-04-03 09:34:59','2026-04-06 14:24:23','0',NULL,NULL,NULL,'Cabinet','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-11','5.00','6.00','7.00','Mixed Materials','Gray','gyhuij','uploads/designs/ORD_2026_38528_1775198099.jpg','','7777.00','7','gvhjkl','2','2026-04-03 09:36:41','4666.20',NULL,NULL,'0',NULL,'4','4'),
('13','3','ORD-2026-05129','cancelled','0.00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-03 11:14:36','2026-04-06 14:00:56','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','0000-00-00','3.00','3.00','3.00','Fabric','Gray','Luxurious 3-seater leather sofa with premium cushioning

444
444
Dimensions: 3.00cm × 3.00cm × 3.00cm
Color: Natural Wood','uploads/designs/ORD_2026_05129_gallery_1775204075.jpg','',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'0',NULL,NULL,NULL),
('14','27','ORD-2026-47249','pending_review','0.00','1460.00','1460.00',NULL,NULL,NULL,NULL,NULL,'2026-04-04 14:12:11','2026-04-05 14:40:04','0',NULL,NULL,NULL,'Sofa Frame','L-Shaped Sofa','1','Above ETB 20,000','2026-04-16','2.00','2.00','2.00','Leather','Custom Color','Comfortable 5-seater L-shaped sofa',NULL,'','3650.00','3','','2','2026-04-04 14:13:43','2190.00',NULL,NULL,'0',NULL,NULL,NULL),
('15','27','ORD-2026-83433','ready_for_delivery','0.00','2222.00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-05 14:22:25','2026-04-16 16:17:30','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 10,000 - ETB 20,000','2026-04-15','9.00','9.00','9.00','Leather','Natural Wood','Luxurious 3-seater leather sofa with premium cushioning','uploads/designs/ORD_2026_83433_1775388145.jpg','','5555.00','4','','2','2026-04-05 14:24:37',NULL,NULL,NULL,'0',NULL,'26',NULL),
('16','27','ORD-2026-54957','cost_estimated','0.00','2000.00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-05 14:40:01','2026-04-06 14:13:57','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-29','6.00','6.00','6.00','Leather','White','Luxurious 3-seater leather sofa with premium cushioning

444','uploads/designs/ORD_2026_54957_1775389201.jpg','','5000.00','4','','2','2026-04-06 14:13:57',NULL,NULL,NULL,'0',NULL,NULL,NULL),
('17','27','ORD-2026-65922','pending_review','0.00',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-05 14:51:56','2026-04-05 14:51:56','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 5,000 - ETB 10,000','2026-04-28','7.00','89.00','8.80','Leather','Gray','Luxurious 3-seater leather sofa with premium cushioning

444',NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'0',NULL,NULL,NULL),
('18','3','ORD-2026-59707','ready_for_delivery','0.00','2400.00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-06 13:26:39','2026-04-16 16:16:48','0',NULL,NULL,NULL,'Sofa Frame','Premium Leather Sofa','1','ETB 10,000 - ETB 20,000','2026-04-29','6.00','6.00','6.00','Leather','Dark Brown','Luxurious 3-seater leather sofa with premium cushioning',NULL,'','6000.00','4','','2','2026-04-06 13:27:41',NULL,NULL,NULL,'0',NULL,'4',NULL),
('19','3','ORD-2026-39219','ready_for_delivery','0.00','2000.00','2000.00',NULL,NULL,NULL,NULL,NULL,'2026-04-06 16:27:52','2026-04-07 17:52:05','0',NULL,NULL,NULL,'Table','JUKL','1','Under ETB 5,000','2026-04-30','3.00','4.00','1.40','Metal Frame','White','mmmmm','uploads/designs/ORD_2026_39219_1775482072.jpg','','5000.00','5','ttt','2','2026-04-06 16:30:20','3000.00',NULL,NULL,'0',NULL,'26',NULL),
('20','3','ORD-2026-27246','ready_for_delivery','0.00','2400.00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-06 16:40:53','2026-04-16 16:17:17','0',NULL,NULL,NULL,'Chair','L-Shaped Sofa','1','ETB 5,000 - ETB 10,000','2026-04-16','4.00','4.00','4.00','Fabric','Gray','i wnt the modrn one
fgbhjnkm
Dimensions: 2.00cm × 2.00cm × 2.00cm
Color: White','uploads/designs/ORD_2026_27246_1775482853.jpg','','6000.00','4','5','2','2026-04-06 16:41:31',NULL,NULL,NULL,'0',NULL,'4',NULL),
('21','3','ORD-2026-46975','ready_for_delivery','0.00','3600.00','3600.00',NULL,NULL,NULL,NULL,NULL,'2026-04-06 17:22:26','2026-04-06 19:04:35','0',NULL,NULL,NULL,'Chair','Premium Leather Sofa','1','Under ETB 5,000','2026-04-20','6.00','6.00','6.00','Glass','White','hhh','uploads/designs/ORD_2026_46975_1775485346.jpg','','9000.00','1','','2','2026-04-06 17:24:50','5400.00',NULL,NULL,'0',NULL,'4',NULL),
('22','3','ORD-2026-13546','cost_estimated','0.00','4200.00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-12 23:04:27','2026-04-12 23:07:25','0',NULL,NULL,NULL,'Table','Table','1','ETB 10,000 - ETB 20,000','2026-04-24','3.00','3.00','3.00','Metal Frame','Brown','','uploads/designs/ORD_2026_13546_1776024267.jpg','','10500.00','4','','2','2026-04-12 23:07:25',NULL,NULL,NULL,'0',NULL,NULL,NULL);

DROP TABLE IF EXISTS `furn_payment_methods`;
CREATE TABLE `furn_payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` varchar(100) NOT NULL,
  `method_type` enum('cash','bank_transfer','card','mobile_money','check','other') DEFAULT 'cash',
  `account_details` text DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_payment_methods` VALUES
('1','Cash','cash',NULL,NULL,'1','1','2026-04-13 14:55:31'),
('2','Bank Transfer','bank_transfer',NULL,NULL,'1','2','2026-04-13 14:55:31');

DROP TABLE IF EXISTS `furn_payments`;
CREATE TABLE `furn_payments` (
  `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `payment_type` enum('prepayment','postpayment','deposit','final','final_payment','full_payment') NOT NULL DEFAULT 'prepayment',
  `payment_method` enum('bank','cash') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `receipt_file` varchar(255) DEFAULT NULL COMMENT 'Path to uploaded receipt file',
  `transaction_reference` varchar(100) DEFAULT NULL COMMENT 'Bank transaction reference number',
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'Name of bank for transfer',
  `payment_date` date NOT NULL,
  `payment_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL COMMENT 'Manager who verified payment',
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt_image` varchar(255) DEFAULT NULL,
  `transaction_notes` text DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_payment_date` (`payment_date`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Payment records for custom furniture orders';

INSERT INTO `furn_payments` VALUES
('11','6','3','prepayment','bank','2000.00','uploads/receipts/69cd129f8611e_1775047327.png','l7878888888','Awash Bank','2026-03-31','','approved','2','2026-04-01 16:52:23',NULL,'2026-04-01 15:42:07','2026-04-01 16:52:23',NULL,NULL,NULL),
('12','6','3','prepayment','bank','2000.00','uploads/receipts/69cd14ed1a945_1775047917.png','l7878888888','Commercial Bank of Ethiopia','2026-03-30','','approved',NULL,NULL,NULL,'2026-04-01 15:51:57','2026-04-01 16:48:04',NULL,NULL,NULL),
('13','6','3','prepayment','bank','2000.00','uploads/receipts/69cd18a2c801c_1775048866.png','l7878888888','Awash Bank','2026-03-30','','approved','2','2026-04-01 17:02:36',NULL,'2026-04-01 16:07:46','2026-04-01 17:02:36',NULL,NULL,NULL),
('14','5','3','prepayment','bank','2222.00','uploads/receipts/69cd264a09416_1775052362.png','l7878888888','Awash Bank','2026-03-30','','approved','2','2026-04-01 17:06:47',NULL,'2026-04-01 17:06:02','2026-04-01 17:06:47',NULL,NULL,NULL),
('15','5','3','final','','3333.00',NULL,NULL,NULL,'2026-04-01',NULL,'approved','2','2026-04-01 19:02:26',NULL,'2026-04-01 18:57:31','2026-04-01 19:02:26','uploads/payments/payment_remaining_5_1775059051.png','ghjkl',NULL),
('16','2','3','prepayment','bank','3097.60','uploads/receipts/69cd45a3bf336_1775060387.png','l7878888888','Awash Bank','2026-03-31','','approved','2','2026-04-01 19:23:38',NULL,'2026-04-01 19:19:47','2026-04-01 19:23:38',NULL,NULL,NULL),
('17','1','3','prepayment','bank','2002.00','uploads/receipts/69cd464c50022_1775060556.png','l7878888888','Awash Bank','2026-03-30','','approved','2','2026-04-02 13:12:38',NULL,'2026-04-01 19:22:36','2026-04-02 13:12:38',NULL,NULL,NULL),
('18','2','3','postpayment','bank','4646.40','uploads/receipts/69cd48f123e99_1775061233.png','l7878888888','Bank of Abyssinia','2026-03-30','','approved','2','2026-04-01 20:47:55',NULL,'2026-04-01 19:33:53','2026-04-01 20:47:55',NULL,NULL,NULL),
('19','6','3','final','','3000.00',NULL,NULL,NULL,'2026-04-01',NULL,'approved','2','2026-04-01 20:47:31',NULL,'2026-04-01 20:45:33','2026-04-01 20:47:31','uploads/payments/payment_remaining_6_1775065533.png','hujiko',NULL),
('20','7','3','prepayment','bank','2236.00','uploads/receipts/69ce0f458db9a_1775112005.png','l7878888888','Bank of Abyssinia','2026-03-31','','approved','2','2026-04-02 09:40:54',NULL,'2026-04-02 09:40:05','2026-04-02 09:40:54',NULL,NULL,NULL),
('21','7','3','final','','3354.00',NULL,NULL,NULL,'2026-04-02',NULL,'approved','2','2026-04-02 13:12:33',NULL,'2026-04-02 09:46:45','2026-04-02 13:12:33','uploads/payments/payment_remaining_7_1775112405.png','hhhhhh',NULL),
('22','8','3','prepayment','bank','2000.00','uploads/receipts/69ce5df156076_1775132145.png','l7878888888','Awash Bank','2026-03-30','','approved','2','2026-04-02 15:16:47',NULL,'2026-04-02 15:15:45','2026-04-02 15:16:47',NULL,NULL,NULL),
('23','8','3','final','cash','3000.00',NULL,NULL,NULL,'2026-04-02',NULL,'approved','2','2026-04-02 15:25:53',NULL,'2026-04-02 15:22:40','2026-04-02 15:25:53','uploads/payments/payment_remaining_8_1775132560.png','jj',NULL),
('24','10','25','prepayment','bank','2000.00','uploads/receipts/69cf5a6180826_1775196769.png','l7878888888','Commercial Bank of Ethiopia','2026-04-03','','approved','2','2026-04-03 09:13:40',NULL,'2026-04-03 09:12:49','2026-04-03 09:13:40',NULL,NULL,NULL),
('25','11','25','prepayment','bank','1333.20','uploads/receipts/69cf5d32d5388_1775197490.png','l7878888888','Awash Bank','2026-04-01','','approved','2','2026-04-03 09:25:22',NULL,'2026-04-03 09:24:50','2026-04-03 09:25:22',NULL,NULL,NULL),
('26','12','25','prepayment','bank','3110.80','uploads/receipts/69cf6039c6de6_1775198265.png','l7878888888','Dashen Bank','2026-04-02','','approved','2','2026-04-03 09:38:22',NULL,'2026-04-03 09:37:45','2026-04-03 09:38:22',NULL,NULL,NULL),
('27','9','3','full_payment','cash','3333.00',NULL,NULL,NULL,'2026-04-02','','approved','2','2026-04-03 10:21:58',NULL,'2026-04-03 10:10:18','2026-04-03 10:21:58',NULL,NULL,NULL),
('28','14','27','prepayment','bank','1460.00','uploads/receipts/69d0f2dd9fc52_1775301341.png','l7878888888','Awash Bank','2026-04-02','','approved','2','2026-04-04 14:16:55',NULL,'2026-04-04 14:15:41','2026-04-04 14:16:55',NULL,NULL,NULL),
('29','15','27','prepayment','cash','2222.00',NULL,NULL,NULL,'2026-04-02','','pending',NULL,NULL,NULL,'2026-04-05 14:28:16','2026-04-05 14:28:16',NULL,NULL,NULL),
('30','14','27','postpayment','cash','2190.00',NULL,NULL,NULL,'2026-04-01','','pending',NULL,NULL,NULL,'2026-04-05 14:36:32','2026-04-05 14:36:32',NULL,NULL,NULL),
('31','18','3','prepayment','cash','2400.00',NULL,NULL,NULL,'2026-04-02','','pending',NULL,NULL,NULL,'2026-04-06 14:12:16','2026-04-06 14:12:16',NULL,NULL,NULL),
('32','19','3','prepayment','bank','2000.00','uploads/receipts/69d3b5c14bda5_1775482305.png','l7878888888','Awash Bank','2026-04-03','','approved','2','2026-04-06 16:32:37',NULL,'2026-04-06 16:31:45','2026-04-06 16:32:37',NULL,NULL,NULL),
('33','20','3','prepayment','bank','2400.00','uploads/receipts/69d3b84c322d2_1775482956.png','l7878888888','Bank of Abyssinia','2026-04-02','','pending',NULL,NULL,NULL,'2026-04-06 16:42:36','2026-04-06 16:42:36',NULL,NULL,NULL),
('34','21','3','prepayment','bank','3600.00','uploads/receipts/69d3c27b17a8f_1775485563.png','l7878888888','Bank of Abyssinia','2026-04-03','','approved','2','2026-04-06 17:26:50',NULL,'2026-04-06 17:26:03','2026-04-06 17:26:50',NULL,NULL,NULL);

DROP TABLE IF EXISTS `furn_payroll`;
CREATE TABLE `furn_payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `month` int(11) NOT NULL COMMENT 'Month number (1-12)',
  `year` int(11) NOT NULL COMMENT 'Year (e.g., 2026)',
  `base_salary` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Base monthly salary in ETB',
  `bonus` decimal(10,2) DEFAULT 0.00 COMMENT 'Additional bonus amount in ETB',
  `deductions` decimal(10,2) DEFAULT 0.00 COMMENT 'Deductions (tax, insurance, etc.) in ETB',
  `net_salary` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Final amount paid (base + bonus - deductions)',
  `payment_date` date DEFAULT NULL,
  `status` varchar(50) DEFAULT 'paid' COMMENT 'Payment status: paid, pending, cancelled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `basic_earned` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_pay` decimal(10,2) NOT NULL DEFAULT 0.00,
  `calculated_by` int(11) DEFAULT NULL,
  `present_days` int(11) NOT NULL DEFAULT 0,
  `half_day_count` int(11) NOT NULL DEFAULT 0,
  `absent_days` int(11) NOT NULL DEFAULT 0,
  `late_days` int(11) NOT NULL DEFAULT 0,
  `overtime_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `working_days_per_month` int(11) NOT NULL DEFAULT 26,
  `overtime_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gross_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_deductions` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_date` (`payment_date`),
  KEY `idx_month_year` (`month`,`year`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Employee payroll records with salary, bonus, and deductions';

INSERT INTO `furn_payroll` VALUES
('1','4','4','2026','8000.00','0.00','0.00','7310.00',NULL,'approved','2026-04-02 14:48:43','1','2026-04-06 15:45:43',NULL,'8000.00','600.00','2','0','0','0','0','3.00','26','200.00','8600.00','1290.00','0.00',''),
('2','4','4','2026','8000.00','0.00','0.00','7310.00',NULL,'approved','2026-04-02 15:42:05','1','2026-04-06 15:45:37',NULL,'8000.00','600.00','2','0','0','0','0','3.00','26','200.00','8600.00','1290.00','0.00',''),
('3','4','4','2026','8000.00','0.00','0.00','7309.14',NULL,'draft','2026-04-16 16:28:17',NULL,NULL,NULL,'8000.00','600.00','2','0','0','0','0','3.00','26','200.00','8600.00','1290.86','0.00','');

DROP TABLE IF EXISTS `furn_product_images`;
CREATE TABLE `furn_product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_product_image` (`product_id`),
  CONSTRAINT `fk_product_image` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_product_materials`;
CREATE TABLE `furn_product_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `quantity_required` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_product_material_product` (`product_id`),
  KEY `fk_product_material_material` (`material_id`),
  CONSTRAINT `fk_product_material_material` FOREIGN KEY (`material_id`) REFERENCES `furn_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_material_product` FOREIGN KEY (`product_id`) REFERENCES `furn_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_production_assignments`;
CREATE TABLE `furn_production_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `estimated_hours` decimal(5,2) DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  `status` enum('assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'assigned',
  `notes` text DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `progress` int(11) NOT NULL DEFAULT 0,
  `deadline` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_production_order` (`order_id`),
  KEY `fk_production_employee` (`employee_id`),
  KEY `fk_production_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_production_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_production_employee` FOREIGN KEY (`employee_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_production_order` FOREIGN KEY (`order_id`) REFERENCES `furn_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_production_logs`;
CREATE TABLE `furn_production_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `production_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_log_production` (`production_id`),
  CONSTRAINT `fk_log_production` FOREIGN KEY (`production_id`) REFERENCES `furn_production_assignments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_production_logs` VALUES
('1','1','order_assigned','{\"employee_ids\":[\"2\",\"3\"],\"deadline\":\"2026-03-08\"}','2026-03-30 14:11:32'),
('2','1','progress_updated','{\"progress\":25,\"notes\":\"Frame completed\"}','2026-03-25 14:11:32'),
('3','1','progress_updated','{\"progress\":45,\"notes\":\"Upholstery 50% complete\"}','2026-03-28 14:11:32'),
('4','2','order_assigned','{\"employee_ids\":[\"4\",\"5\"],\"deadline\":\"2026-03-15\"}','2026-03-30 14:11:32'),
('5','2','progress_updated','{\"progress\":25,\"notes\":\"Initial cutting and preparation done\"}','2026-03-27 14:11:32'),
('6','3','order_assigned','{\"employee_ids\":[\"2\"],\"deadline\":\"2026-03-04\"}','2026-03-30 14:11:32'),
('7','3','progress_updated','{\"progress\":75,\"notes\":\"Assembly complete, finishing work\"}','2026-03-29 14:11:32');

DROP TABLE IF EXISTS `furn_production_tasks`;
CREATE TABLE `furn_production_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `progress` int(3) DEFAULT 0,
  `deadline` date DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `finished_image` varchar(255) DEFAULT NULL,
  `materials_used` text DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `actual_hours` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_production_tasks` VALUES
('1','5','4',NULL,'completed','100','2026-04-30','2026-04-01 18:42:28','2026-04-01 18:53:55','

[COMPLETION] fgbhjnkm','2026-04-01 18:21:58','2026-04-01 18:53:55','uploads/finished_products/FINISHED_1_1775058835.jpg','Fabric Upholstery: 6 yards
Oak Wood: 7 board_feet','fgbhjnkm','7.00'),
('2','6','4',NULL,'completed','100','2026-04-10','2026-04-01 19:25:23','2026-04-01 19:26:38','

[COMPLETION] 444','2026-04-01 19:23:56','2026-04-01 19:26:38','uploads/finished_products/FINISHED_2_1775060798.jpg','Foam Padding: 4 pieces
Fabric Upholstery: 5 yards','444','4.00'),
('3','7','4',NULL,'completed','100','2026-04-30','2026-04-02 09:42:31','2026-04-02 09:44:09','

[COMPLETION] 444','2026-04-02 09:41:17','2026-04-02 09:44:09','uploads/finished_products/FINISHED_3_1775112249.jpg','Fabric Upholstery: 4 yards
Fabric Upholstery: 5 yards','444','4.00'),
('4','8','4',NULL,'completed','100','2026-04-23','2026-04-02 15:18:34','2026-04-02 15:19:51','

[COMPLETION] hh','2026-04-02 15:17:28','2026-04-02 15:19:51','uploads/finished_products/FINISHED_4_1775132391.jpg','Fabric Upholstery: 03 yards
Fabric Upholstery: 3 yards','hh','4.00'),
('5','1','4',NULL,'completed','100','2026-04-23','2026-04-06 14:20:21','2026-04-06 14:21:15','

[COMPLETION] 3','2026-04-03 09:32:17','2026-04-06 14:21:15','uploads/finished_products/FINISHED_5_1775474475.jpg','Foam Padding: 2 pieces
Glass Tabletop: 2 pieces','3','3.00'),
('6','12','4',NULL,'completed','100','2026-04-11','2026-04-06 14:15:01','2026-04-06 14:20:12','

[COMPLETION] tyre

[COMPLETION] tyre','2026-04-03 09:45:41','2026-04-06 14:20:12','uploads/finished_products/FINISHED_6_1775474412.jpg','Fabric Upholstery: 2 yards
Foam Padding: 3 pieces','tyre','5.00'),
('7','10','4',NULL,'completed','100','2026-04-25','2026-04-06 15:38:31','2026-04-06 17:29:43','

[COMPLETION] hh','2026-04-03 10:42:22','2026-04-06 17:29:43','uploads/finished_products/FINISHED_7_1775485783.jpg','Fabric Upholstery: 3 yards
Fabric Upholstery: 2 yards','hh',NULL),
('8','11','4',NULL,'completed','100','2026-04-25','2026-04-06 15:24:46','2026-04-06 16:35:37','

[COMPLETION] hh','2026-04-06 14:14:14','2026-04-06 16:35:37','uploads/finished_products/FINISHED_8_1775482537.jpg','Glass Tabletop: 4 pieces
Premium Leather: 4 square_feet','hh',NULL),
('9','15','26',NULL,'completed','100','2026-04-15',NULL,NULL,NULL,'2026-04-06 14:14:31','2026-04-16 16:17:30',NULL,NULL,NULL,NULL),
('10','19','26',NULL,'completed','100','2026-04-30',NULL,NULL,NULL,'2026-04-06 16:32:57','2026-04-07 17:52:05',NULL,NULL,NULL,NULL),
('11','21','4',NULL,'completed','100','2026-04-20',NULL,NULL,NULL,'2026-04-06 17:27:14','2026-04-06 19:04:35',NULL,NULL,NULL,NULL),
('12','20','4',NULL,'completed','100','2026-04-16',NULL,NULL,NULL,'2026-04-14 15:46:02','2026-04-16 16:17:17',NULL,NULL,NULL,NULL),
('13','18','4',NULL,'completed','100','2026-04-29','2026-04-16 15:52:29',NULL,'','2026-04-14 15:46:16','2026-04-16 16:16:48',NULL,NULL,NULL,NULL);

DROP TABLE IF EXISTS `furn_products`;
CREATE TABLE `furn_products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `estimated_production_time` int(11) NOT NULL COMMENT 'In days',
  `materials_used` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `order_id` int(11) DEFAULT NULL,
  `image_main` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `material` varchar(255) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `estimated_price` decimal(12,2) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `product_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_category` (`category_id`),
  CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `furn_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_products` VALUES
('32','4','Executive Office Chair','Ergonomic office chair with lumbar support','3500.00','10','Premium fabric, steel frame, adjustable mechanisms','1','2026-03-30 14:01:04','2026-04-03 21:50:48',NULL,NULL,NULL,'Chair',NULL,NULL,NULL,'active',NULL),
('34','2','King Size Wooden Bed','Handcrafted king size bed with premium wood finish','25000.00','30','Solid oak wood, premium mattress support, eco-friendly finish','1','2026-03-30 14:03:38','2026-04-03 21:50:48',NULL,NULL,NULL,'Bed',NULL,NULL,NULL,'active',NULL),
('35','3','Modern Dining Table','Contemporary 6-seater dining table with glass top','12000.00','18','Tempered glass, stainless steel frame, wooden base','1','2026-03-30 14:03:38','2026-04-03 21:50:48',NULL,NULL,NULL,'Table',NULL,NULL,NULL,'active',NULL),
('36','4','Executive Office Chair','Ergonomic office chair with lumbar support','3500.00','10','Premium fabric, steel frame, adjustable mechanisms','1','2026-03-30 14:03:38','2026-04-03 21:50:48',NULL,NULL,NULL,'Chair',NULL,NULL,NULL,'active',NULL),
('40','4','Executive Office Chair','Ergonomic office chair with lumbar support','3500.00','10','Premium fabric, steel frame, adjustable mechanisms','1','2026-03-30 14:08:47','2026-04-03 21:50:48',NULL,NULL,NULL,'Chair',NULL,NULL,NULL,'active',NULL),
('41','1','Premium Leather Sofa','Luxurious 3-seater leather sofa with premium cushioning','15000.00','21','Full-grain leather, solid wood frame, high-density foam','1','2026-03-30 14:11:24','2026-04-03 21:50:47',NULL,NULL,NULL,'Sofa',NULL,NULL,NULL,'active',NULL),
('42','2','King Size Wooden Bed','Handcrafted king size bed with premium wood finish','25000.00','30','Solid oak wood, premium mattress support, eco-friendly finish','1','2026-03-30 14:11:24','2026-04-03 21:50:47',NULL,NULL,NULL,'Bed',NULL,NULL,NULL,'active',NULL),
('43','3','Modern Dining Table','Contemporary 6-seater dining table with glass top','12000.00','18','Tempered glass, stainless steel frame, wooden base','1','2026-03-30 14:11:24','2026-04-03 21:50:47',NULL,NULL,NULL,'Table',NULL,NULL,NULL,'active',NULL),
('44','4','Executive Office Chair','Ergonomic office chair with lumbar support','3500.00','10','Premium fabric, steel frame, adjustable mechanisms','1','2026-03-30 14:11:24','2026-04-03 21:50:47',NULL,NULL,NULL,'Chair',NULL,NULL,NULL,'active',NULL),
('52','26','Premium Leather Sofa','gyhuij

tyre','7777.00','0','Mixed Materials','1','2026-04-06 14:15:52','2026-04-06 17:32:45','12','uploads/finished_products/FINISHED_6_1775474152.jpg',NULL,'Sofa',NULL,'5.00 × 6.00 × 7.00 cm',NULL,'active',NULL),
('53','1','Premium Leather Sofa','dfrghjk

3','5005.00','0','Leather','1','2026-04-06 14:21:15','2026-04-06 17:32:45','1','uploads/finished_products/FINISHED_5_1775474475.jpg',NULL,'Sofa',NULL,'6.00 × 7.00 × 7.00 cm',NULL,'active',NULL),
('54','1','Premium Leather Sofa','Luxurious 3-seater leather sofa with premium cushioning

444
444
Dimensions: 3.00cm × 3.00cm × 3.00cm
Color: Natural Wood','5590.00','0','Fabric Upholstery: 4 yards
Fabric Upholstery: 5 yards','1','2026-04-06 14:31:44','2026-04-06 17:32:45','7','uploads/finished_products/FINISHED_3_1775112249.jpg',NULL,'Sofa',NULL,NULL,NULL,'active',NULL),
('55','4','L-Shaped Sofa','i wnt the modrn one
fgbhjnkm
Dimensions: 2.00cm × 2.00cm × 2.00cm
Color: White','5555.00','0','Fabric Upholstery: 6 yards
Oak Wood: 7 board_feet','1','2026-04-06 14:32:04','2026-04-06 17:32:45','5','uploads/finished_products/FINISHED_1_1775058835.jpg',NULL,'Sofa',NULL,NULL,NULL,'active',NULL),
('56','27','L-Shaped Sofa','33

hh','3333.00','0','Metal Frame','1','2026-04-06 16:35:37','2026-04-06 17:32:45','11','uploads/finished_products/FINISHED_8_1775482537.jpg',NULL,'Sofa',NULL,'2.00 × 2.00 × 2.00 cm',NULL,'active',NULL),
('57','4','Premium Leather Sofa','ee

hh','5000.00','0','Particle Board','1','2026-04-06 17:29:43','2026-04-06 17:32:44','10','uploads/finished_products/FINISHED_7_1775485783.jpg',NULL,'Sofa',NULL,'2.00 × 2.00 × 2.00 cm',NULL,'active',NULL);

DROP TABLE IF EXISTS `furn_ratings`;
CREATE TABLE `furn_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL DEFAULT 5,
  `review` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `employee_id` int(11) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_customer` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_ratings` VALUES
('1','6','3','4',NULL,'2026-04-01 21:01:55','4','fff'),
('2','8','3','4',NULL,'2026-04-02 15:27:53','4',''),
('3','7','3','5',NULL,'2026-04-05 19:29:09','4','their product is good and available on time recomand you to see');

DROP TABLE IF EXISTS `furn_report_feedback`;
CREATE TABLE `furn_report_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `from_user_role` enum('admin','manager','employee') NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `feedback` text NOT NULL,
  `feedback_type` enum('praise','note','warning') DEFAULT 'note',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `from_user_id` (`from_user_id`),
  KEY `to_user_id` (`to_user_id`),
  CONSTRAINT `furn_report_feedback_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `furn_manager_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `furn_report_feedback_ibfk_2` FOREIGN KEY (`from_user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `furn_report_feedback_ibfk_3` FOREIGN KEY (`to_user_id`) REFERENCES `furn_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_report_feedback` VALUES
('1','11','2','manager','2','hjk','note','0','2026-03-30 22:03:38'),
('2','16','1','admin','2','ghju','note','0','2026-03-30 22:04:29'),
('3','16','1','admin','2','FGVHB','note','0','2026-03-30 22:41:42'),
('4','16','1','admin','2','ghjk','note','0','2026-04-01 10:25:28'),
('5','16','1','admin','2','fghj','note','0','2026-04-01 10:42:42'),
('6','18','4','employee','2','vv','note','0','2026-04-02 15:36:19');

DROP TABLE IF EXISTS `furn_sessions`;
CREATE TABLE `furn_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `furn_settings`;
CREATE TABLE `furn_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `category` varchar(50) DEFAULT 'general',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_category` (`category`),
  KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=227 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_settings` VALUES
('1','site_name','FurnitureCraft Workshop','text','general','2026-03-30 13:44:55'),
('2','currency','ETB','text','general','2026-03-30 13:44:55'),
('3','timezone','Africa/Addis_Ababa','text','general','2026-03-30 13:44:55'),
('4','date_format','Y-m-d','text','general','2026-03-30 13:44:55'),
('5','language','en','text','general','2026-04-13 14:55:41'),
('6','fiscal_year_start','01-01','text','business','2026-03-30 13:44:55'),
('7','default_deposit_percentage','50','number','business','2026-03-30 13:44:55'),
('8','allow_backorders','0','boolean','business','2026-03-30 13:44:55'),
('9','auto_approve_orders','0','boolean','business','2026-03-30 13:44:55'),
('10','email_notifications','1','boolean','notifications','2026-03-30 13:44:55'),
('11','sms_notifications','1','boolean','notifications','2026-04-05 12:55:00'),
('12','order_confirmation_email','1','boolean','notifications','2026-03-30 13:44:55'),
('13','order_status_updates','1','boolean','notifications','2026-03-30 13:44:55'),
('14','payment_received_email','1','boolean','notifications','2026-03-30 13:44:55'),
('15','low_stock_alert','1','boolean','notifications','2026-03-30 13:44:55'),
('16','new_order_alert','1','boolean','notifications','2026-03-30 13:44:55'),
('17','session_timeout','3600','number','security','2026-03-30 13:44:55'),
('18','password_min_length','8','number','security','2026-03-30 13:44:55'),
('19','require_special_char','1','boolean','security','2026-03-30 13:44:55'),
('20','max_login_attempts','5','number','security','2026-03-30 13:44:55'),
('21','lockout_duration','900','number','security','2026-03-30 13:44:55'),
('22','maintenance_mode','0','boolean','system','2026-03-30 13:44:55'),
('23','cache_enabled','1','boolean','system','2026-03-30 13:44:55'),
('24','debug_mode','0','boolean','system','2026-03-30 13:44:55'),
('25','log_retention_days','30','number','system','2026-03-30 13:44:55'),
('206','payment_method_cash','1','text','payments','2026-04-13 15:53:35'),
('207','payment_method_bank_transfer','1','text','payments','2026-04-13 15:53:35'),
('208','bank_transfer_details','','text','payments','2026-04-13 15:53:35'),
('211','bank_accounts_json','[]','text','payments','2026-04-13 16:14:54');

DROP TABLE IF EXISTS `furn_suppliers`;
CREATE TABLE `furn_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_supplier_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_suppliers` VALUES
('1','Ethiopian Leather Co.','Abebe Kebede','abebe@leatherco.et','+251-11-123-4567','Addis Ababa, Industrial Zone',NULL,'30 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('2','Addis Ababa Timber','Mekonnen Haile','mekonnen@timber.et','+251-11-234-5678','Addis Ababa, Wood District',NULL,'15 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('3','Metal Works Ltd','Kebede Tesfaye','kebede@metalworks.et','+251-11-345-6789','Addis Ababa, Metal Industrial Park',NULL,'45 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('4','Comfort Materials Inc','Alemu Getachew','alemu@comfort.et','+251-11-456-7890','Addis Ababa, Textile Zone',NULL,'30 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('5','Textile Solutions','Berhane Weldu','berhane@textiles.et','+251-11-567-8901','Addis Ababa, Garment District',NULL,'60 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('6','Glass Manufacturing Co.','Tadesse Lemma','tadesse@glassco.et','+251-11-678-9012','Addis Ababa, Glass Industrial Area',NULL,'30 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31'),
('7','Hardware Distributors','Solomon Admassu','solomon@hardware.et','+251-11-789-0123','Addis Ababa, Hardware Market',NULL,'15 days','1','2026-03-30 14:11:31','2026-03-30 14:11:31');

DROP TABLE IF EXISTS `furn_tax_config`;
CREATE TABLE `furn_tax_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_name` varchar(100) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `tax_type` enum('percentage','fixed') DEFAULT 'percentage',
  `is_compound` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_tax_config` VALUES
('1','VAT','15.01','percentage','0','1','2026-03-30 13:44:56'),
('2','Service Charge','10.00','fixed','0','1','2026-03-30 13:44:56'),
('3','VAT','15.00','percentage','0','1','2026-03-30 13:50:42'),
('4','Service Charge','10.00','percentage','0','0','2026-03-30 13:50:42'),
('5','VAT','15.00','percentage','0','1','2026-03-30 13:53:39'),
('6','Service Charge','10.00','percentage','0','0','2026-03-30 13:53:39'),
('7','VAT','15.00','percentage','0','1','2026-03-30 14:01:05'),
('8','Service Charge','10.00','percentage','0','0','2026-03-30 14:01:05'),
('9','VAT','15.00','percentage','0','1','2026-03-30 14:03:39'),
('10','Service Charge','10.00','percentage','0','0','2026-03-30 14:03:39'),
('11','VAT','15.00','percentage','0','1','2026-03-30 14:08:49'),
('12','Service Charge','10.00','percentage','0','0','2026-03-30 14:08:49'),
('13','VAT','15.00','percentage','0','1','2026-03-30 14:11:25'),
('14','Service Charge','10.00','percentage','0','0','2026-03-30 14:11:25');

DROP TABLE IF EXISTS `furn_users`;
CREATE TABLE `furn_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','employee','customer') NOT NULL DEFAULT 'customer',
  `employee_id` varchar(20) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` datetime DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `invite_token` varchar(64) DEFAULT NULL,
  `invite_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `employee_id` (`employee_id`),
  KEY `idx_role` (`role`),
  KEY `idx_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_failed_attempts` (`failed_attempts`),
  KEY `idx_password_reset_token` (`password_reset_token`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `furn_users` VALUES
('1','1','System Administrator','admin','admin@furniture.com','$2y$10$R9Ene/egMPkE7i6wmR72puQYarFUGYn1EIORQ8ud67DhEbj7S/Ul2','admin',NULL,NULL,NULL,'System','Administrator',NULL,NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 13:50:40','2026-04-16 23:42:18','2026-04-16 23:42:18','user_1_1775032735_ffca925aeb5d053e.jpg',NULL,NULL),
('2','2',NULL,'manager','manager@furniture.com','$2y$10$mGabhp3LuWe8ZQF5oYcX/.5uZXivrmmWfMFgwWv4qa.qOmei7aVGO','manager',NULL,NULL,NULL,'John','Manager',NULL,NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 13:50:40','2026-04-16 22:02:30','2026-04-16 22:02:30','user_2_1775032692_ebf76fb9b9668742.jpg',NULL,NULL),
('3','4',NULL,'customer','customer@furniture.com','$2y$10$hSWa.iw9zcaOZSePhhISj.glaGDYPVsems6dXouFvTWv8M9.Vh4MK','customer',NULL,NULL,NULL,'Alice','Customer','+251911123456','Addis Ababa, Ethiopia','1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 13:50:40','2026-04-16 18:23:14','2026-04-16 18:23:14','user_3_1775031347_babb97d014a28d30.jpg',NULL,NULL),
('4','3','John Employee','employee','employee@furniture.com','$2y$10$J0d9EGNvRolPUg9qOfY6NOy/LDkG5acfF.wlbfjesFx3/Dvn2yN5e','employee','EMP0004','Production','Worker','John','Employee','09437777777',NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-03-30 13:50:41','2026-04-16 20:12:58','2026-04-16 20:12:58','user_4_1775193032_ae769dd4dd76cf8c.jpg',NULL,NULL),
('25',NULL,NULL,'dereje.ayele53','dereje@gmail.com','$2y$10$OfPnUKd2oUEDSKuPcjMhpeGlzLkIeh7MOZe6mNxb3juOC0QcOJI1.','customer',NULL,NULL,NULL,'DEREJE','AYELE','+251943778192','t','1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-03 09:07:57','2026-04-03 09:36:57','2026-04-03 09:36:57',NULL,NULL,NULL),
('26','3','tamu','tamu_1775196901','tamu@furniture.com','$2y$10$EC2jbwC.KWarIoj/xcCywOKhJZHoEEuySYDD/Eer4DoX77CGlkXQ.','employee',NULL,NULL,NULL,'tamu','','+251911123456',NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-03 09:15:01','2026-04-03 09:15:01',NULL,NULL,NULL,NULL),
('27','4','DEREJE AYELE','derejeayele292','derejeayele292@gmail.com','$2y$10$lX5S4CO/nXR/XErOqpwIZef.7sKd3vcl9JvyzpykQnyZ0qIZckyIO','customer',NULL,NULL,NULL,'DEREJE','AYELE','+25143778192',NULL,'1','0','active','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-04 14:10:47','2026-04-06 17:38:06','2026-04-05 19:08:18',NULL,NULL,NULL),
('28','3','tufa muna','em_1776088900','em@furniture.com','$2y$10$us5gop6X/lUTdSl.QTeuiu1lqyJfzRVekQna5hup1vhrtNJfS57na','employee',NULL,NULL,NULL,'tufa','muna','09437777777',NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-13 17:01:40','2026-04-13 17:02:11','2026-04-13 17:02:11',NULL,NULL,NULL),
('29','3','belay girma','balay_1776090143','balay@furniture.com','','employee',NULL,NULL,NULL,'belay','girma','09437777777',NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-13 17:22:23','2026-04-13 17:42:00',NULL,NULL,'ad47b4faee4a3f7b83693fc6ca00886736ea0503dc374da04c37997b02f30208','2026-04-15 17:22:23'),
('30','3','DEREJE AYELE','der_1776090274','derejeayele@gmail.com','$2y$10$.RwfSzts/dBdzCwhp8.pd.VwAxEyWzHAOOZVmg5QC9El7zZOyNobO','employee',NULL,NULL,NULL,'DEREJE','AYELE','09437777777',NULL,'1','0','active','0',NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-13 17:24:34','2026-04-14 09:27:51','2026-04-14 09:27:51',NULL,'691c7b26e69ecc2ab505e9ec61f31cad59320bc539a82768fee84922e47450fe','2026-04-15 17:24:34');

DROP TABLE IF EXISTS `furn_wishlist`;
CREATE TABLE `furn_wishlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_wishlist` (`customer_id`,`product_id`),
  KEY `idx_customer` (`customer_id`),
  KEY `idx_product` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `furn_wishlist` VALUES
('1','3','13','2026-04-01 14:35:56'),
('3','3','1','2026-04-01 14:35:59'),
('4','3','46','2026-04-02 09:35:37'),
('9','3','48','2026-04-03 10:54:37'),
('10','3','53','2026-04-06 14:23:17'),
('12','3','52','2026-04-06 14:48:37');

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` VALUES
('1','admin','System Administrator','2026-03-30 13:44:51'),
('2','manager','Production Manager','2026-03-30 13:44:51'),
('3','employee','Production Employee','2026-03-30 13:44:51'),
('4','customer','Customer','2026-03-30 13:44:51');

DROP TABLE IF EXISTS `vw_attendance_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_attendance_summary` AS select `u`.`id` AS `employee_id`,`u`.`employee_id` AS `emp_code`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `employee_name`,`u`.`department` AS `department`,`u`.`position` AS `position`,`a`.`check_in_time` AS `check_in_time`,`a`.`ip_address` AS `ip_address`,`a`.`status` AS `status`,cast(`a`.`check_in_time` as date) AS `attendance_date` from (`furn_users` `u` left join `furn_attendance` `a` on(`u`.`id` = `a`.`employee_id`)) where `u`.`role` = 'employee' or `u`.`role` = 'manager';

INSERT INTO `vw_attendance_summary` VALUES
('2',NULL,'John Manager',NULL,NULL,NULL,NULL,NULL,NULL),
('4','EMP0004','John Employee','Production','Worker','0000-00-00 00:00:00','127.0.0.1','absent',NULL),
('4','EMP0004','John Employee','Production','Worker','2026-04-02 01:52:00','127.0.0.1','present','2026-04-02'),
('4','EMP0004','John Employee','Production','Worker','2026-04-03 11:30:00','127.0.0.1','present','2026-04-03'),
('4','EMP0004','John Employee','Production','Worker','2026-04-16 08:00:00','127.0.0.1','present','2026-04-16'),
('26',NULL,'tamu ',NULL,NULL,'2026-04-03 00:30:00','127.0.0.1','present','2026-04-03'),
('26',NULL,'tamu ',NULL,NULL,'2026-04-16 08:00:00','127.0.0.1','present','2026-04-16'),
('28',NULL,'tufa muna',NULL,NULL,'2026-04-16 08:00:00','127.0.0.1','present','2026-04-16'),
('29',NULL,'belay girma',NULL,NULL,'2026-04-16 08:00:00','127.0.0.1','present','2026-04-16'),
('30',NULL,'DEREJE AYELE',NULL,NULL,'2026-04-16 08:00:00','127.0.0.1','present','2026-04-16');

DROP TABLE IF EXISTS `vw_employee_hours_summary`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_employee_hours_summary` AS select `u`.`id` AS `employee_id`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `employee_name`,count(`a`.`id`) AS `days_worked` from (`furn_users` `u` join `furn_attendance` `a` on(`u`.`id` = `a`.`employee_id`)) where `u`.`role` = 'employee' and `a`.`status` = 'present' group by `u`.`id`,`u`.`first_name`,`u`.`last_name` order by count(`a`.`id`) desc;

INSERT INTO `vw_employee_hours_summary` VALUES
('4','John Employee','3'),
('26','tamu ','2'),
('29','belay girma','1'),
('30','DEREJE AYELE','1'),
('28','tufa muna','1');

DROP TABLE IF EXISTS `vw_invoice_overview`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_invoice_overview` AS select `i`.`id` AS `id`,`i`.`invoice_number` AS `invoice_number`,`i`.`invoice_date` AS `invoice_date`,`i`.`due_date` AS `due_date`,`i`.`total_amount` AS `total_amount`,`i`.`deposit_paid` AS `deposit_paid`,`i`.`remaining_balance` AS `remaining_balance`,`i`.`status` AS `status`,`o`.`order_number` AS `order_number`,concat(`u`.`first_name`,' ',`u`.`last_name`) AS `customer_name`,`u`.`email` AS `customer_email`,`u`.`phone` AS `customer_phone`,to_days(`i`.`due_date`) - to_days(curdate()) AS `days_until_due`,case when `i`.`status` = 'paid' then 'success' when `i`.`status` = 'overdue' then 'danger' when to_days(`i`.`due_date`) - to_days(curdate()) <= 7 then 'warning' else 'info' end AS `status_color` from ((`furn_invoices` `i` join `furn_orders` `o` on(`i`.`order_id` = `o`.`id`)) join `furn_users` `u` on(`i`.`customer_id` = `u`.`id`));

DROP TABLE IF EXISTS `vw_monthly_revenue`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_monthly_revenue` AS select year(`o`.`created_at`) AS `year`,month(`o`.`created_at`) AS `month`,date_format(`o`.`created_at`,'%Y-%m') AS `month_year`,count(`o`.`id`) AS `order_count`,sum(`o`.`total_amount`) AS `total_revenue`,avg(`o`.`total_amount`) AS `avg_order_value` from `furn_orders` `o` where `o`.`status` in ('completed','delivered','paid') group by year(`o`.`created_at`),month(`o`.`created_at`) order by year(`o`.`created_at`) desc,month(`o`.`created_at`) desc;

INSERT INTO `vw_monthly_revenue` VALUES
('2026','4','2026-04','3','0.00','0.000000');

DROP TABLE IF EXISTS `vw_orders_by_status`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_orders_by_status` AS select `furn_orders`.`status` AS `status`,count(0) AS `count`,sum(`furn_orders`.`total_amount`) AS `total_value` from `furn_orders` group by `furn_orders`.`status`;

INSERT INTO `vw_orders_by_status` VALUES
('pending_review','2','0.00'),
('cost_estimated','2','0.00'),
('ready_for_delivery','11','0.00'),
('completed','3','0.00'),
('cancelled','2','0.00');

DROP TABLE IF EXISTS `vw_top_selling_products`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_top_selling_products` AS select `p`.`id` AS `product_id`,`p`.`name` AS `product_name`,`p`.`category_id` AS `category_id`,count(`oc`.`id`) AS `orders_count`,sum(`oc`.`quantity`) AS `total_quantity`,sum(`o`.`total_amount`) AS `total_revenue` from ((`furn_products` `p` join `furn_order_customizations` `oc` on(`p`.`id` = `oc`.`product_id`)) join `furn_orders` `o` on(`oc`.`order_id` = `o`.`id`)) where `o`.`status` in ('completed','delivered','paid') group by `p`.`id`,`p`.`name`,`p`.`category_id` order by sum(`o`.`total_amount`) desc limit 10;

SET FOREIGN_KEY_CHECKS=1;
