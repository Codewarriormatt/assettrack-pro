-- AssetTrack Pro: demo database (schema + FAKE sample data only)
--
-- Import with phpMyAdmin (Import tab) or: mysql -u root < database/assettrack_demo.sql
-- It creates the database `asset_management` if it does not exist.
-- Demo login: admin / Demo@1234 (local demo only, change it if you deploy).
-- All names, emails (@example.com) and phone numbers (555-01xx) are fake.
-- Dates are relative to the import day (CURDATE()), so due dates and warranties stay current.
-- Built from a phpMyAdmin 5.2.1 dump (MariaDB 10.4.32, PHP 8.2.12).

CREATE DATABASE IF NOT EXISTS `asset_management` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `asset_management`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `asset_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action_type` enum('CREATE','READ','UPDATE','DELETE','LOGIN','LOGOUT') NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- No data for table `activity_log` (starts empty)
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL,
  `asset_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `control_number` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `cost` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) DEFAULT NULL,
  `lifetime_years` int(11) NOT NULL,
  `depreciation_method` enum('straight-line','declining-balance') DEFAULT 'straight-line',
  `status` enum('Active','Depreciated','Disposed','Under Maintenance') DEFAULT 'Active',
  `assigned_to_name` varchar(255) DEFAULT NULL,
  `assigned_to_email` varchar(255) DEFAULT NULL,
  `assigned_to_contact` varchar(50) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `asset_location` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `insurance_type` varchar(255) DEFAULT NULL,
  `insurance_provider` varchar(255) DEFAULT NULL,
  `insurance_start` date DEFAULT NULL,
  `insurance_end` date DEFAULT NULL,
  `insurance_details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`asset_id`, `asset_name`, `description`, `control_number`, `purchase_date`, `cost`, `current_value`, `lifetime_years`, `depreciation_method`, `status`, `assigned_to_name`, `assigned_to_email`, `assigned_to_contact`, `department_id`, `asset_location`, `location`, `insurance_type`, `insurance_provider`, `insurance_start`, `insurance_end`, `insurance_details`) VALUES
(1, 'Dell Latitude 5440', '14-inch business laptop, Core i7, 16GB RAM, 512GB SSD', 'IT-001-DEMO', CURDATE() - INTERVAL 400 DAY, 68000.00, 53106.09, 5, 'straight-line', 'Active', 'Jordan Lee', 'jordan.lee@example.com', '555-0102', 2, 'IT Office Desk 1', NULL, 'Equipment Insurance', 'Example Insurance Co.', CURDATE() - INTERVAL 400 DAY, CURDATE() + INTERVAL 5 DAY, 'Expires soon (demo)'),
(2, 'HP ProBook 450 G10', '15-inch laptop, Core i5, 16GB RAM', 'IT-002-DEMO', CURDATE() - INTERVAL 250 DAY, 52000.00, 44881.59, 5, 'straight-line', 'Active', 'Alex Rivera', 'alex.rivera@example.com', '555-0101', 1, 'Finance Room A', NULL, 'Equipment Insurance', 'Example Insurance Co.', CURDATE() - INTERVAL 250 DAY, CURDATE() + INTERVAL 20 DAY, 'Standard coverage'),
(3, 'Dell OptiPlex 7010 Desktop', 'Desktop, Core i5, 16GB RAM, 512GB SSD', 'IT-003-DEMO', CURDATE() - INTERVAL 900 DAY, 45000.00, 9720.00, 5, 'declining-balance', 'Under Maintenance', 'Sam Carter', 'sam.carter@example.com', '555-0103', 3, 'HR Office', NULL, 'Equipment Insurance', 'Sample Assurance Ltd.', CURDATE() - INTERVAL 900 DAY, CURDATE() + INTERVAL 300 DAY, 'Onsite support'),
(4, 'LG 24-inch IPS Monitor', '24-inch Full HD monitor', 'IT-004-DEMO', CURDATE() - INTERVAL 120 DAY, 8500.00, 7941.48, 5, 'straight-line', 'Under Maintenance', 'Taylor Brooks', 'taylor.brooks@example.com', '555-0104', 4, 'Operations Bay', NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Canon Laser Printer', 'Monochrome laser printer with duplex', 'IT-005-DEMO', CURDATE() - INTERVAL 1500 DAY, 18500.00, 3304.93, 5, 'straight-line', 'Active', 'Taylor Brooks', 'taylor.brooks@example.com', '555-0104', 4, 'Operations Print Station', NULL, 'Equipment Insurance', 'Sample Assurance Ltd.', CURDATE() - INTERVAL 1500 DAY, CURDATE() - INTERVAL 1135 DAY, 'Warranty expired'),
(6, 'BenQ Full HD Projector', 'Projector for the training room', 'EQP-001-DEMO', CURDATE() - INTERVAL 1700 DAY, 28500.00, 1970.23, 5, 'straight-line', 'Active', 'Casey Morgan', 'casey.morgan@example.com', '555-0105', 5, 'Training Room', NULL, 'Equipment Insurance', 'Example Insurance Co.', CURDATE() - INTERVAL 1700 DAY, CURDATE() - INTERVAL 1335 DAY, 'Warranty expired'),
(7, 'Office Suite License', 'Annual license, 10 seats', 'SW-001-DEMO', CURDATE() - INTERVAL 200 DAY, 42000.00, 19002.05, 1, 'straight-line', 'Active', 'Jordan Lee', 'jordan.lee@example.com', '555-0102', 2, 'License Pool', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Ergonomic Office Chair', 'Adjustable lumbar support', 'FUR-001-DEMO', CURDATE() - INTERVAL 500 DAY, 7800.00, 6465.30, 8, 'straight-line', 'Under Maintenance', 'Riley Quinn', 'riley.quinn@example.com', '555-0106', 6, 'Procurement Office', NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Conference Table (10 seats)', 'Walnut finish', 'FUR-002-DEMO', CURDATE() - INTERVAL 1200 DAY, 35000.00, 27334.02, 15, 'straight-line', 'Active', 'Alex Rivera', 'alex.rivera@example.com', '555-0101', 1, 'Boardroom', NULL, 'Property Insurance', 'Sample Assurance Ltd.', CURDATE() - INTERVAL 1200 DAY, CURDATE() + INTERVAL 600 DAY, 'Accidental damage cover'),
(10, 'Digital Thermometer', 'Infrared thermometer', 'CLI-001-DEMO', CURDATE() - INTERVAL 1400 DAY, 2500.00, 0.00, 3, 'straight-line', 'Depreciated', 'Jamie Ellis', 'jamie.ellis@example.com', '555-0107', 7, 'Clinic Storage', NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Old Desktop PC', 'Core i3, 4GB RAM, 500GB HDD', 'IT-006-DEMO', CURDATE() - INTERVAL 2400 DAY, 25000.00, 0.00, 5, 'straight-line', 'Disposed', NULL, NULL, NULL, 2, 'IT Storage Room', NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'DSLR Camera Kit', 'DSLR camera with 18-55mm lens', 'MKT-001-DEMO', CURDATE() - INTERVAL 700 DAY, 100000.00, 36000.00, 5, 'declining-balance', 'Active', 'Casey Morgan', 'casey.morgan@example.com', '555-0105', 5, 'Marketing Equipment Room', NULL, 'Equipment Insurance', 'Example Insurance Co.', CURDATE() - INTERVAL 700 DAY, CURDATE() + INTERVAL 25 DAY, 'Covers accidental damage');

-- --------------------------------------------------------

--
-- Stand-in structure for view `asset_monitoring`
-- (See below for the actual view)
--
CREATE TABLE `asset_monitoring` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`purchase_date` date
,`cost` decimal(15,2)
,`current_value` decimal(15,2)
,`lifetime_years` int(11)
,`depreciation_method` enum('straight-line','declining-balance')
,`status` enum('Active','Depreciated','Disposed','Under Maintenance')
,`insurance_end` date
,`assigned_to_name` varchar(255)
,`department_name` varchar(255)
,`depreciation_percentage` decimal(22,2)
,`days_since_purchase` int(7)
,`days_until_fully_depreciated` int(7)
,`warranty_status` varchar(16)
,`days_until_warranty_expires` int(7)
,`maintenance_due_count` bigint(21)
,`age_years` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `asset_summary`
-- (See below for the actual view)
--
CREATE TABLE `asset_summary` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`description` text
,`control_number` varchar(100)
,`location` varchar(255)
,`purchase_date` date
,`cost` decimal(15,2)
,`lifetime_years` int(11)
,`depreciation_method` enum('straight-line','declining-balance')
,`status` enum('Active','Depreciated','Disposed','Under Maintenance')
,`assigned_to_name` varchar(255)
,`assigned_to_email` varchar(255)
,`assigned_to_contact` varchar(50)
,`department_name` varchar(255)
,`department_manager` varchar(255)
,`warranty_status` varchar(16)
,`depreciation_status` varchar(11)
,`current_value` double
,`depreciation_percentage` double
);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `manager` varchar(255) DEFAULT NULL,
  `manager_email` varchar(255) DEFAULT NULL,
  `manager_contact` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `description`, `manager`, `manager_email`, `manager_contact`, `status`) VALUES
(1, 'Finance', 'Budgeting, accounting, payroll and financial reporting.', 'Alex Rivera', 'alex.rivera@example.com', '555-0101', 'Active'),
(2, 'Information Technology', 'IT infrastructure, network, end-user support and systems administration.', 'Jordan Lee', 'jordan.lee@example.com', '555-0102', 'Active'),
(3, 'Human Resources', 'Recruitment, onboarding and employee relations.', 'Sam Carter', 'sam.carter@example.com', '555-0103', 'Active'),
(4, 'Operations', 'Day-to-day business processes.', 'Taylor Brooks', 'taylor.brooks@example.com', '555-0104', 'Active'),
(5, 'Marketing', 'Branding and campaign work.', 'Casey Morgan', 'casey.morgan@example.com', '555-0105', 'Active'),
(6, 'Procurement', 'Purchasing and vendor relations.', 'Riley Quinn', 'riley.quinn@example.com', '555-0106', 'Active'),
(7, 'Clinic', 'On-site first aid and employee wellness.', 'Jamie Ellis', 'jamie.ellis@example.com', '555-0107', 'Active'),
(8, 'Facilities', 'Offices, furniture and utilities.', 'Morgan Hayes', 'morgan.hayes@example.com', '555-0108', 'Inactive');

-- --------------------------------------------------------

--
-- Stand-in structure for view `department_summary`
-- (See below for the actual view)
--
CREATE TABLE `department_summary` (
`department_id` int(11)
,`department_name` varchar(255)
,`description` text
,`manager` varchar(255)
,`manager_email` varchar(255)
,`manager_contact` varchar(50)
,`status` enum('Active','Inactive')
,`total_assets` bigint(21)
,`active_assets` bigint(21)
,`depreciated_assets` bigint(21)
,`total_users` bigint(21)
,`total_asset_value` decimal(37,2)
,`current_asset_value` double
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `depreciation_analysis`
-- (See below for the actual view)
--
CREATE TABLE `depreciation_analysis` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`purchase_date` date
,`cost` decimal(15,2)
,`current_value` decimal(15,2)
,`lifetime_years` int(11)
,`depreciation_method` enum('straight-line','declining-balance')
,`status` enum('Active','Depreciated','Disposed','Under Maintenance')
,`department_name` varchar(255)
,`depreciation_percentage` decimal(22,2)
,`total_depreciation` decimal(16,2)
,`annual_depreciation` decimal(16,2)
,`days_since_purchase` int(7)
,`age_years` decimal(11,2)
,`years_remaining` decimal(13,2)
,`depreciation_category` varchar(22)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `fully_depreciated_assets`
-- (See below for the actual view)
--
CREATE TABLE `fully_depreciated_assets` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`purchase_date` date
,`cost` decimal(15,2)
,`current_value` decimal(15,2)
,`lifetime_years` int(11)
,`assigned_to_name` varchar(255)
,`department_name` varchar(255)
,`depreciation_percentage` decimal(22,2)
,`total_depreciation` decimal(16,2)
,`days_since_purchase` int(7)
,`age_years` decimal(11,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `next_due_date` date NOT NULL,
  `details` text NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `cost` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`maintenance_id`, `asset_id`, `maintenance_date`, `next_due_date`, `details`, `status`, `cost`) VALUES
(1, 4, CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 3 DAY, 'Replace backlight and test display', 'Pending', 1500.00),
(2, 8, CURDATE() - INTERVAL 170 DAY, CURDATE() + INTERVAL 10 DAY, 'Replace gas lift cylinder', 'Pending', 800.00),
(3, 3, CURDATE() - INTERVAL 180 DAY, CURDATE() - INTERVAL 5 DAY, 'Clean fans and update BIOS', 'Pending', 0.00),
(4, 6, CURDATE() - INTERVAL 90 DAY, CURDATE() + INTERVAL 90 DAY, 'Replace projector lamp', 'Completed', 3500.00),
(5, 1, CURDATE() - INTERVAL 60 DAY, CURDATE() + INTERVAL 120 DAY, 'Battery health check', 'Completed', 0.00),
(6, 9, CURDATE() - INTERVAL 30 DAY, CURDATE() + INTERVAL 335 DAY, 'Table leg alignment and finish retouch', 'Completed', 600.00);

-- --------------------------------------------------------

--
-- Stand-in structure for view `maintenance_due`
-- (See below for the actual view)
--
CREATE TABLE `maintenance_due` (
`maintenance_id` int(11)
,`asset_id` int(11)
,`maintenance_date` date
,`next_due_date` date
,`details` text
,`status` enum('Pending','Completed')
,`cost` decimal(15,2)
,`created_at` timestamp
,`updated_at` timestamp
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`location` varchar(255)
,`assigned_to_name` varchar(255)
,`department_name` varchar(255)
,`days_until_due` int(7)
,`urgency_level` varchar(14)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `maintenance_due_enhanced`
-- (See below for the actual view)
--
CREATE TABLE `maintenance_due_enhanced` (
`maintenance_id` int(11)
,`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`assigned_to_name` varchar(255)
,`department_name` varchar(255)
,`next_due_date` date
,`details` text
,`status` enum('Pending','Completed')
,`maintenance_cost` decimal(15,2)
,`days_until_due` int(7)
,`urgency_level` varchar(14)
,`asset_cost` decimal(15,2)
,`current_value` decimal(15,2)
,`asset_depreciation_percentage` decimal(22,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `monitoring_stats`
--

CREATE TABLE `monitoring_stats` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `maintenance_due_30` int(11) DEFAULT 0,
  `warranty_expiring_30` int(11) DEFAULT 0,
  `highly_depreciated` int(11) DEFAULT 0,
  `replacement_candidates` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- No data for table `monitoring_stats` (filled by scripts/asset_monitor.php)
--


-- --------------------------------------------------------

--
-- Stand-in structure for view `replacement_candidates`
-- (See below for the actual view)
--
CREATE TABLE `replacement_candidates` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`cost` decimal(15,2)
,`current_value` decimal(15,2)
,`depreciation_percentage` decimal(22,2)
,`lifetime_years` int(11)
,`days_since_purchase` int(7)
,`age_years` decimal(11,2)
,`department_name` varchar(255)
,`assigned_to_name` varchar(255)
,`asset_location` varchar(255)
,`total_depreciation` decimal(16,2)
,`years_remaining` decimal(13,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin') NOT NULL DEFAULT 'Admin',
  `department_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `username`, `password`, `role`, `department_id`, `status`) VALUES
(1, 'Demo Administrator', 'admin', '$2y$10$EzUDy7Cr931qpwBuAPkLzuc5HbrWJnfyyOianbWvrhK5J0FDrzHT.', 'Admin', NULL, 'Active');

-- --------------------------------------------------------

--
-- Stand-in structure for view `warranty_status_enhanced`
-- (See below for the actual view)
--
CREATE TABLE `warranty_status_enhanced` (
`asset_id` int(11)
,`asset_name` varchar(255)
,`control_number` varchar(100)
,`assigned_to_name` varchar(255)
,`department_name` varchar(255)
,`insurance_end` date
,`insurance_provider` varchar(255)
,`insurance_type` varchar(255)
,`warranty_status` varchar(16)
,`days_until_expires` int(7)
,`alert_level` varchar(18)
,`cost` decimal(15,2)
,`current_value` decimal(15,2)
,`depreciation_percentage` decimal(22,2)
);

-- --------------------------------------------------------

--
-- Structure for view `asset_monitoring`
--
DROP TABLE IF EXISTS `asset_monitoring`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `asset_monitoring`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`purchase_date` AS `purchase_date`, `a`.`cost` AS `cost`, `a`.`current_value` AS `current_value`, `a`.`lifetime_years` AS `lifetime_years`, `a`.`depreciation_method` AS `depreciation_method`, `a`.`status` AS `status`, `a`.`insurance_end` AS `insurance_end`, `a`.`assigned_to_name` AS `assigned_to_name`, `d`.`department_name` AS `department_name`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `depreciation_percentage`, to_days(curdate()) - to_days(`a`.`purchase_date`) AS `days_since_purchase`, to_days(`a`.`purchase_date` + interval `a`.`lifetime_years` year) - to_days(curdate()) AS `days_until_fully_depreciated`, CASE WHEN `a`.`insurance_end` is null THEN 'No Warranty' WHEN `a`.`insurance_end` >= curdate() THEN 'Under Warranty' ELSE 'Warranty Expired' END AS `warranty_status`, to_days(`a`.`insurance_end`) - to_days(curdate()) AS `days_until_warranty_expires`, (select count(0) from `maintenance` `m` where `m`.`asset_id` = `a`.`asset_id` and `m`.`status` = 'Pending' and `m`.`next_due_date` <= curdate() + interval 30 day) AS `maintenance_due_count`, round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `age_years` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `asset_summary`
--
DROP TABLE IF EXISTS `asset_summary`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `asset_summary`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`description` AS `description`, `a`.`control_number` AS `control_number`, `a`.`location` AS `location`, `a`.`purchase_date` AS `purchase_date`, `a`.`cost` AS `cost`, `a`.`lifetime_years` AS `lifetime_years`, `a`.`depreciation_method` AS `depreciation_method`, `a`.`status` AS `status`, `a`.`assigned_to_name` AS `assigned_to_name`, `a`.`assigned_to_email` AS `assigned_to_email`, `a`.`assigned_to_contact` AS `assigned_to_contact`, `d`.`department_name` AS `department_name`, `d`.`manager` AS `department_manager`, CASE WHEN `a`.`insurance_end` >= curdate() THEN 'Under Warranty' ELSE 'Warranty Expired' END AS `warranty_status`, CASE WHEN to_days(curdate()) - to_days(`a`.`purchase_date`) > `a`.`lifetime_years` * 365 THEN 'Depreciated' ELSE 'Active' END AS `depreciation_status`, CASE WHEN `a`.`depreciation_method` = 'straight-line' THEN greatest(0,`a`.`cost` - `a`.`cost` / `a`.`lifetime_years` * ((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365)) WHEN `a`.`depreciation_method` = 'declining-balance' THEN greatest(0,`a`.`cost` * pow(0.8,(to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365)) ELSE `a`.`cost` END AS `current_value`, CASE WHEN `a`.`depreciation_method` = 'straight-line' THEN least(100,(to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365 / `a`.`lifetime_years` * 100) WHEN `a`.`depreciation_method` = 'declining-balance' THEN least(100,(1 - pow(0.8,(to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365)) * 100) ELSE 0 END AS `depreciation_percentage` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `department_summary`
--
DROP TABLE IF EXISTS `department_summary`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `department_summary`  AS SELECT `d`.`department_id` AS `department_id`, `d`.`department_name` AS `department_name`, `d`.`description` AS `description`, `d`.`manager` AS `manager`, `d`.`manager_email` AS `manager_email`, `d`.`manager_contact` AS `manager_contact`, `d`.`status` AS `status`, count(`a`.`asset_id`) AS `total_assets`, count(case when `a`.`status` = 'Active' then 1 end) AS `active_assets`, count(case when `a`.`status` = 'Depreciated' then 1 end) AS `depreciated_assets`, count(`u`.`user_id`) AS `total_users`, coalesce(sum(`a`.`cost`),0) AS `total_asset_value`, coalesce(sum(case when `a`.`depreciation_method` = 'straight-line' then greatest(0,`a`.`cost` - `a`.`cost` / `a`.`lifetime_years` * ((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365)) when `a`.`depreciation_method` = 'declining-balance' then greatest(0,`a`.`cost` * pow(0.8,(to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365)) else `a`.`cost` end),0) AS `current_asset_value` FROM ((`departments` `d` left join `assets` `a` on(`d`.`department_id` = `a`.`department_id`)) left join `users` `u` on(`d`.`department_id` = `u`.`department_id`)) GROUP BY `d`.`department_id`, `d`.`department_name`, `d`.`description`, `d`.`manager`, `d`.`manager_email`, `d`.`manager_contact`, `d`.`status` ;

-- --------------------------------------------------------

--
-- Structure for view `depreciation_analysis`
--
DROP TABLE IF EXISTS `depreciation_analysis`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `depreciation_analysis`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`purchase_date` AS `purchase_date`, `a`.`cost` AS `cost`, `a`.`current_value` AS `current_value`, `a`.`lifetime_years` AS `lifetime_years`, `a`.`depreciation_method` AS `depreciation_method`, `a`.`status` AS `status`, `d`.`department_name` AS `department_name`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `depreciation_percentage`, `a`.`cost`- `a`.`current_value` AS `total_depreciation`, round(`a`.`cost` / `a`.`lifetime_years`,2) AS `annual_depreciation`, to_days(curdate()) - to_days(`a`.`purchase_date`) AS `days_since_purchase`, round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `age_years`, `a`.`lifetime_years`- round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `years_remaining`, CASE WHEN `a`.`status` = 'Depreciated' THEN 'Fully Depreciated' WHEN (`a`.`cost` - `a`.`current_value`) / `a`.`cost` >= 0.9 THEN 'Replacement Candidate' WHEN (`a`.`cost` - `a`.`current_value`) / `a`.`cost` >= 0.8 THEN 'Highly Depreciated' WHEN (`a`.`cost` - `a`.`current_value`) / `a`.`cost` >= 0.5 THEN 'Moderately Depreciated' ELSE 'Low Depreciation' END AS `depreciation_category` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) ORDER BY round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `fully_depreciated_assets`
--
DROP TABLE IF EXISTS `fully_depreciated_assets`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `fully_depreciated_assets`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`purchase_date` AS `purchase_date`, `a`.`cost` AS `cost`, `a`.`current_value` AS `current_value`, `a`.`lifetime_years` AS `lifetime_years`, `a`.`assigned_to_name` AS `assigned_to_name`, `d`.`department_name` AS `department_name`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `depreciation_percentage`, `a`.`cost`- `a`.`current_value` AS `total_depreciation`, to_days(curdate()) - to_days(`a`.`purchase_date`) AS `days_since_purchase`, round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `age_years` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) WHERE `a`.`status` = 'Depreciated' ORDER BY `a`.`purchase_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `maintenance_due`
--
DROP TABLE IF EXISTS `maintenance_due`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `maintenance_due`  AS SELECT `m`.`maintenance_id` AS `maintenance_id`, `m`.`asset_id` AS `asset_id`, `m`.`maintenance_date` AS `maintenance_date`, `m`.`next_due_date` AS `next_due_date`, `m`.`details` AS `details`, `m`.`status` AS `status`, `m`.`cost` AS `cost`, `m`.`created_at` AS `created_at`, `m`.`updated_at` AS `updated_at`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`location` AS `location`, `a`.`assigned_to_name` AS `assigned_to_name`, `d`.`department_name` AS `department_name`, to_days(`m`.`next_due_date`) - to_days(curdate()) AS `days_until_due`, CASE WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) < 0 THEN 'Overdue' WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) <= 7 THEN 'Due Soon' WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) <= 30 THEN 'Due This Month' ELSE 'Scheduled' END AS `urgency_level` FROM ((`maintenance` `m` left join `assets` `a` on(`m`.`asset_id` = `a`.`asset_id`)) left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `maintenance_due_enhanced`
--
DROP TABLE IF EXISTS `maintenance_due_enhanced`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `maintenance_due_enhanced`  AS SELECT `m`.`maintenance_id` AS `maintenance_id`, `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`assigned_to_name` AS `assigned_to_name`, `d`.`department_name` AS `department_name`, `m`.`next_due_date` AS `next_due_date`, `m`.`details` AS `details`, `m`.`status` AS `status`, `m`.`cost` AS `maintenance_cost`, to_days(`m`.`next_due_date`) - to_days(curdate()) AS `days_until_due`, CASE WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) <= 0 THEN 'Overdue' WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) <= 7 THEN 'Due Soon' WHEN to_days(`m`.`next_due_date`) - to_days(curdate()) <= 30 THEN 'Due This Month' ELSE 'Future' END AS `urgency_level`, `a`.`cost` AS `asset_cost`, `a`.`current_value` AS `current_value`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `asset_depreciation_percentage` FROM ((`maintenance` `m` join `assets` `a` on(`m`.`asset_id` = `a`.`asset_id`)) left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) WHERE `m`.`status` = 'Pending' ORDER BY `m`.`next_due_date` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `replacement_candidates`
--
DROP TABLE IF EXISTS `replacement_candidates`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `replacement_candidates`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`cost` AS `cost`, `a`.`current_value` AS `current_value`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `depreciation_percentage`, `a`.`lifetime_years` AS `lifetime_years`, to_days(curdate()) - to_days(`a`.`purchase_date`) AS `days_since_purchase`, round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `age_years`, `d`.`department_name` AS `department_name`, `a`.`assigned_to_name` AS `assigned_to_name`, `a`.`asset_location` AS `asset_location`, `a`.`cost`- `a`.`current_value` AS `total_depreciation`, `a`.`lifetime_years`- round((to_days(curdate()) - to_days(`a`.`purchase_date`)) / 365.25,2) AS `years_remaining` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) WHERE `a`.`status` = 'Active' AND (`a`.`cost` - `a`.`current_value`) / `a`.`cost` >= 0.9 ORDER BY round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `warranty_status_enhanced`
--
DROP TABLE IF EXISTS `warranty_status_enhanced`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `warranty_status_enhanced`  AS SELECT `a`.`asset_id` AS `asset_id`, `a`.`asset_name` AS `asset_name`, `a`.`control_number` AS `control_number`, `a`.`assigned_to_name` AS `assigned_to_name`, `d`.`department_name` AS `department_name`, `a`.`insurance_end` AS `insurance_end`, `a`.`insurance_provider` AS `insurance_provider`, `a`.`insurance_type` AS `insurance_type`, CASE WHEN `a`.`insurance_end` is null THEN 'No Warranty' WHEN `a`.`insurance_end` >= curdate() THEN 'Under Warranty' ELSE 'Warranty Expired' END AS `warranty_status`, to_days(`a`.`insurance_end`) - to_days(curdate()) AS `days_until_expires`, CASE WHEN `a`.`insurance_end` is null THEN 'No Alert' WHEN to_days(`a`.`insurance_end`) - to_days(curdate()) <= 0 THEN 'Expired' WHEN to_days(`a`.`insurance_end`) - to_days(curdate()) <= 7 THEN 'Expires Soon' WHEN to_days(`a`.`insurance_end`) - to_days(curdate()) <= 30 THEN 'Expires This Month' ELSE 'Active' END AS `alert_level`, `a`.`cost` AS `cost`, `a`.`current_value` AS `current_value`, round((`a`.`cost` - `a`.`current_value`) / `a`.`cost` * 100,2) AS `depreciation_percentage` FROM (`assets` `a` left join `departments` `d` on(`a`.`department_id` = `d`.`department_id`)) WHERE `a`.`insurance_end` is not null ORDER BY `a`.`insurance_end` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_action` (`action_type`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD UNIQUE KEY `control_number` (`control_number`),
  ADD KEY `idx_control_number` (`control_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_purchase_date` (`purchase_date`),
  ADD KEY `idx_assets_cost` (`cost`),
  ADD KEY `idx_assets_current_value` (`current_value`),
  ADD KEY `idx_assets_status` (`status`),
  ADD KEY `idx_assets_insurance_end` (`insurance_end`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD KEY `idx_department_name` (`department_name`),
  ADD KEY `idx_departments_status` (`status`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `idx_asset` (`asset_id`),
  ADD KEY `idx_next_due` (`next_due_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_maintenance_next_due` (`next_due_date`);

--
-- Indexes for table `monitoring_stats`
--
ALTER TABLE `monitoring_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_date` (`date`),
  ADD KEY `idx_monitoring_stats_date` (`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_department` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `monitoring_stats`
--
ALTER TABLE `monitoring_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
