-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 06, 2025 at 02:27 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `easyrent_db`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_dashboard_stats`
-- (See below for the actual view)
--
CREATE TABLE `admin_dashboard_stats` (
);

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_settings`
--

INSERT INTO `admin_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'max_login_attempts', '5', 'Maximum failed login attempts before account lockout', '2025-07-01 10:20:02', '2025-07-01 10:20:02'),
(2, 'lockout_duration', '900', 'Account lockout duration in seconds (15 minutes)', '2025-07-01 10:20:02', '2025-07-01 10:20:02'),
(3, 'session_timeout', '3600', 'Session timeout in seconds (1 hour)', '2025-07-01 10:20:02', '2025-07-01 10:20:02'),
(4, 'maintenance_mode', '0', 'Enable/disable maintenance mode (0=disabled, 1=enabled)', '2025-07-01 10:20:02', '2025-07-01 10:20:02'),
(5, 'allow_registration', '1', 'Allow new user registration (0=disabled, 1=enabled)', '2025-07-01 10:20:02', '2025-07-01 10:20:02');

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `icon`, `category`) VALUES
(1, 'Swimming Pool', 'fas fa-swimmer', 'recreation'),
(2, 'Gym/Fitness Center', 'fas fa-dumbbell', 'recreation'),
(3, 'Security Guard', 'fas fa-shield-alt', 'security'),
(4, 'CCTV Surveillance', 'fas fa-video', 'security'),
(5, 'Parking Bay', 'fas fa-car', 'parking'),
(6, 'Garden/Yard', 'fas fa-tree', 'outdoor'),
(7, 'Balcony', 'fas fa-building', 'outdoor'),
(8, 'Air Conditioning', 'fas fa-snowflake', 'utilities'),
(9, 'WiFi Internet', 'fas fa-wifi', 'utilities'),
(10, 'Laundry Facilities', 'fas fa-tshirt', 'utilities'),
(11, 'Dishwasher', 'fas fa-utensils', 'appliances'),
(12, 'Microwave', 'fas fa-microwave', 'appliances'),
(13, 'Refrigerator', 'fas fa-refrigerator', 'appliances'),
(14, 'Washing Machine', 'fas fa-washing-machine', 'appliances'),
(15, 'Pet Friendly', 'fas fa-paw', 'policies'),
(16, 'Elevator', 'fas fa-elevator', 'building'),
(17, 'Backup Generator', 'fas fa-bolt', 'utilities'),
(18, 'Water Tank', 'fas fa-tint', 'utilities');

-- --------------------------------------------------------

--
-- Table structure for table `landlord_documents`
--

CREATE TABLE `landlord_documents` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `landlord_documents`
--

INSERT INTO `landlord_documents` (`id`, `landlord_id`, `document_type`, `document_name`, `file_path`, `status`, `approval_status`, `rejection_reason`, `uploaded_at`) VALUES
(1, 11, 'id_document', 'Identity Document', 'id_document_11_1756763376_68b614f07206d.pdf', 'approved', 'approved', NULL, '2025-09-01 21:49:36'),
(2, 11, 'business_license', 'Business License', 'business_license_11_1756763528_68b6158853e00.pdf', 'approved', 'pending', NULL, '2025-09-01 21:52:08'),
(3, 11, 'proof_of_address', 'Proof of Address', 'proof_of_address_11_1756764079_68b617afab9e4.pdf', 'approved', 'pending', NULL, '2025-09-01 22:01:19'),
(4, 11, 'tax_clearance', 'Tax Clearance Certificate', 'tax_clearance_11_1756765288_68b61c68da05a.pdf', 'approved', 'pending', NULL, '2025-09-01 22:21:28'),
(5, 11, 'bank_statement', 'Bank Statement', 'bank_statement_11_1756765372_68b61cbc7df38.pdf', 'approved', 'pending', NULL, '2025-09-01 22:22:52'),
(6, 11, 'property_deed', 'Property Deed/Title', 'property_deed_11_1756765372_68b61cbc7ffbf.pdf', 'approved', 'pending', NULL, '2025-09-01 22:22:52'),
(7, 11, 'insurance_certificate', 'Insurance Certificate', 'insurance_certificate_11_1756887752_68b7fac88d6c8.pdf', 'approved', '', 'jhj', '2025-09-03 08:22:32'),
(8, 11, 'credit_report', 'Credit Report', 'credit_report_11_1756765372_68b61cbc8abd7.pdf', 'approved', 'pending', NULL, '2025-09-01 22:22:52'),
(14, 10, 'id_document', 'Identity Document', 'id_document_10_1756965109_68b928f57dcdf.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(15, 10, 'proof_of_address', 'Proof of Address', 'proof_of_address_10_1756965109_68b928f57fc36.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(16, 10, 'bank_statement', 'Bank Statement', 'bank_statement_10_1756965109_68b928f5818b3.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(17, 10, 'tax_clearance', 'Tax Clearance Certificate', 'tax_clearance_10_1756965109_68b928f58310c.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(18, 10, 'business_license', 'Business License', 'business_license_10_1756965109_68b928f58469d.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(19, 10, 'property_deed', 'Property Deed/Title', 'property_deed_10_1756965109_68b928f5861a6.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(20, 10, 'insurance_certificate', 'Insurance Certificate', 'insurance_certificate_10_1756965109_68b928f5877d7.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49'),
(21, 10, 'credit_report', 'Credit Report', 'credit_report_10_1756965109_68b928f589108.pdf', 'pending', 'pending', NULL, '2025-09-04 05:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `landlord_profiles`
--

CREATE TABLE `landlord_profiles` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `branch_code` varchar(10) NOT NULL,
  `account_holder` varchar(255) NOT NULL,
  `business_registration` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `property_count` int(11) DEFAULT 0,
  `about` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `landlord_profiles`
--

INSERT INTO `landlord_profiles` (`id`, `landlord_id`, `full_name`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `id_number`, `tax_number`, `bank_name`, `account_number`, `branch_code`, `account_holder`, `business_registration`, `experience_years`, `property_count`, `about`, `profile_image`, `approval_status`, `created_at`, `updated_at`) VALUES
(3, 11, 'JOEL HAMESE', 'joelhamese@gmail.com', '0842700536', '5 Fortuna, Bedworth park jjhkj', 'Yes', 'Gauteng', '9899', '0411070279088', '78978798', 'Capitec', '99999999999', '470010', 'hghj', '99', 2, 1, 'jkhjk', 'profile_11_1756959650.jpg', 'pending', '2025-09-01 21:33:41', '2025-09-04 04:20:50'),
(4, 10, 'rge', 'joelhamese@gmail.com', '0842700536', '5 Fortuna, Bedworth park jjhkj', 'Yes', 'Gauteng', '9899', '0411070279088', '78978798', 'ABSA', '99999999999', '632005', 'hghj', '99', 5, 3, 'fdf', 'profile_10_1756965109.jpg', 'pending', '2025-09-04 05:51:49', '2025-09-04 05:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `leases`
--

CREATE TABLE `leases` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `lease_start_date` date NOT NULL,
  `lease_end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) NOT NULL,
  `deposit_paid` tinyint(1) DEFAULT 0,
  `lease_document` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending','active','terminated') NOT NULL DEFAULT 'draft',
  `signed_at` datetime DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `custom_terms` text DEFAULT NULL,
  `tenant_signature` text DEFAULT NULL,
  `signature_date` datetime DEFAULT NULL,
  `landlord_signature` varchar(255) DEFAULT NULL,
  `signed_by_landlord_at` datetime DEFAULT NULL,
  `signed_by_tenant_at` datetime DEFAULT NULL,
  `landlord_signature_date` timestamp NULL DEFAULT NULL COMMENT 'When the landlord signature was saved',
  `signature_path` varchar(255) DEFAULT NULL,
  `signed_date` datetime DEFAULT NULL,
  `tenant_signature_path` varchar(255) DEFAULT NULL,
  `tenant_signed_date` datetime DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `tenant_signed_at` datetime DEFAULT NULL,
  `landlord_signed_at` datetime DEFAULT NULL,
  `landlord_signature_path` varchar(255) DEFAULT NULL,
  `tenant_signed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_templates`
--

CREATE TABLE `lease_templates` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `property_type` varchar(50) DEFAULT 'All',
  `template_name` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `placeholders` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lease_templates`
--

INSERT INTO `lease_templates` (`id`, `landlord_id`, `property_type`, `template_name`, `title`, `content`, `is_default`, `placeholders`, `created_at`, `updated_at`) VALUES
(39, 11, 'All', 'ff', '', '<h4 style=\"text-align: center; margin-bottom: 1.5rem;\">RESIDENTIAL LEASE AGREEMENT</h4>\r\n\r\n<div class=\"template-section\">\r\n    <p>This Residential Lease Agreement (the \"Agreement\") is made and entered into on <strong>[DATE]</strong>, by and between:</p>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>LANDLORD:</h5>\r\n        <p>[LANDLORD_NAME]<br>\r\n        [LANDLORD_ADDRESS]</p>\r\n    </div>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>TENANT:</h5>\r\n        <p>[TENANT_NAME]<br>\r\n        [TENANT_ADDRESS]</p>\r\n    </div>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>PROPERTY:</h5>\r\n        <p>[PROPERTY_ADDRESS]</p>\r\n    </div>\r\n</div>\r\n\r\n<div class=\"template-section\">\r\n    <h5 style=\"margin-bottom: 0.5rem;\">1. TERM</h5>\r\n    <p>The lease term will begin on <strong>[START_DATE]</strong> and end on <strong>[END_DATE]</strong>.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">2. RENT</h5>\r\n    <p>The monthly rent for the Property is <strong>R[RENT_AMOUNT]</strong>, payable in advance on the first day of each calendar month.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">3. SECURITY DEPOSIT</h5>\r\n    <p>Upon execution of this Agreement, Tenant shall deposit with Landlord the sum of <strong>R[DEPOSIT_AMOUNT]</strong> as security.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">4. UTILITIES</h5>\r\n    <p>Tenant shall be responsible for all utilities including water, electricity, gas, and internet.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">5. MAINTENANCE</h5>\r\n    <p>Tenant shall keep the premises in clean, sanitary, and good condition.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">6. OCCUPANTS</h5>\r\n    <p>The premises shall not be occupied by any person other than the Tenant and the following individuals: [LIST OCCUPANTS].</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">7. PETS</h5>\r\n    <p>No pets shall be allowed on the premises without Landlord\'s prior written consent.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">8. SUBLETTING</h5>\r\n    <p>Tenant shall not sublet any portion of the Property without Landlord\'s prior written consent.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">9. DEFAULT</h5>\r\n    <p>If Tenant fails to pay rent when due, Landlord may terminate this Agreement upon providing proper notice.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">10. GOVERNING LAW</h5>\r\n    <p>This Agreement shall be governed by the laws of the Republic of South Africa.</p>\r\n</div>\r\n                    ', 0, NULL, '2025-09-04 05:48:35', '2025-09-04 05:48:35'),
(40, 11, 'All', 'ff', '', '<h4 style=\"text-align: center; margin-bottom: 1.5rem;\">RESIDENTIAL LEASE AGREEMENT</h4>\r\n\r\n<div class=\"template-section\">\r\n    <p>This Residential Lease Agreement (the \"Agreement\") is made and entered into on <strong>[DATE]</strong>, by and between:</p>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>LANDLORD:</h5>\r\n        <p>[LANDLORD_NAME]<br>\r\n        [LANDLORD_ADDRESS]</p>\r\n    </div>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>TENANT:</h5>\r\n        <p>[TENANT_NAME]<br>\r\n        [TENANT_ADDRESS]</p>\r\n    </div>\r\n    \r\n    <div class=\"signature-block\" style=\"margin: 1.5rem 0;\">\r\n        <h5>PROPERTY:</h5>\r\n        <p>[PROPERTY_ADDRESS]</p>\r\n    </div>\r\n</div>\r\n\r\n<div class=\"template-section\">\r\n    <h5 style=\"margin-bottom: 0.5rem;\">1. TERM</h5>\r\n    <p>The lease term will begin on <strong>[START_DATE]</strong> and end on <strong>[END_DATE]</strong>.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">2. RENT</h5>\r\n    <p>The monthly rent for the Property is <strong>R[RENT_AMOUNT]</strong>, payable in advance on the first day of each calendar month.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">3. SECURITY DEPOSIT</h5>\r\n    <p>Upon execution of this Agreement, Tenant shall deposit with Landlord the sum of <strong>R[DEPOSIT_AMOUNT]</strong> as security.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">4. UTILITIES</h5>\r\n    <p>Tenant shall be responsible for all utilities including water, electricity, gas, and internet.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">5. MAINTENANCE</h5>\r\n    <p>Tenant shall keep the premises in clean, sanitary, and good condition.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">6. OCCUPANTS</h5>\r\n    <p>The premises shall not be occupied by any person other than the Tenant and the following individuals: [LIST OCCUPANTS].</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">7. PETS</h5>\r\n    <p>No pets shall be allowed on the premises without Landlord\'s prior written consent.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">8. SUBLETTING</h5>\r\n    <p>Tenant shall not sublet any portion of the Property without Landlord\'s prior written consent.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">9. DEFAULT</h5>\r\n    <p>If Tenant fails to pay rent when due, Landlord may terminate this Agreement upon providing proper notice.</p>\r\n    \r\n    <h5 style=\"margin: 1rem 0 0.5rem;\">10. GOVERNING LAW</h5>\r\n    <p>This Agreement shall be governed by the laws of the Republic of South Africa.</p>\r\n</div>\r\n                    ', 0, NULL, '2025-09-04 05:48:58', '2025-09-04 05:48:58');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_images`
--

CREATE TABLE `maintenance_images` (
  `id` int(11) NOT NULL,
  `maintenance_request_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_description` varchar(200) DEFAULT NULL,
  `uploaded_by` enum('tenant','landlord') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `category` varchar(100) DEFAULT NULL,
  `status` enum('submitted','acknowledged','in_progress','completed','cancelled') DEFAULT 'submitted',
  `reported_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `acknowledged_date` timestamp NULL DEFAULT NULL,
  `started_date` timestamp NULL DEFAULT NULL,
  `completed_date` timestamp NULL DEFAULT NULL,
  `contractor_assigned` varchar(100) DEFAULT NULL,
  `contractor_contact` varchar(50) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `landlord_notes` text DEFAULT NULL,
  `tenant_rating` int(11) DEFAULT NULL CHECK (`tenant_rating` >= 1 and `tenant_rating` <= 5),
  `tenant_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `related_type`, `is_read`, `action_url`, `created_at`) VALUES
(45, 9, '', 'Your application for property #40 has been approved! Please sign your lease agreement.', 'application', NULL, NULL, 0, NULL, '2025-09-04 05:15:27');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `property_type` enum('apartment','house','studio','townhouse','flat','room') NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `bedrooms` int(11) DEFAULT 0,
  `available_units` int(11) NOT NULL DEFAULT 1,
  `bathrooms` int(11) DEFAULT 0,
  `square_meters` decimal(8,2) DEFAULT NULL,
  `rent_amount` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) NOT NULL,
  `utilities_included` tinyint(1) DEFAULT 0,
  `parking_available` tinyint(1) DEFAULT 0,
  `pet_friendly` tinyint(1) DEFAULT 0,
  `furnished` tinyint(1) DEFAULT 0,
  `available_from` date DEFAULT NULL,
  `lease_duration_months` int(11) DEFAULT 12,
  `is_available` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_approved` tinyint(1) DEFAULT 0,
  `approved_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `is_occupied` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `landlord_id`, `title`, `description`, `property_type`, `address`, `city`, `state`, `postal_code`, `latitude`, `longitude`, `bedrooms`, `available_units`, `bathrooms`, `square_meters`, `rent_amount`, `deposit_amount`, `utilities_included`, `parking_available`, `pet_friendly`, `furnished`, `available_from`, `lease_duration_months`, `is_available`, `is_featured`, `view_count`, `created_at`, `updated_at`, `admin_approved`, `approved_date`, `approved_by`, `is_occupied`) VALUES
(41, 11, '4-Bedroom Farm-Style House – Walkerville, Johannesburg', 'vxcc', 'house', 'Sponge', 'polokwane', 'Limpopo', '699', NULL, NULL, 1, 1, 1, 90.00, 2000.00, 500.00, 0, 0, 0, 0, '2025-09-04', 12, 1, 0, 0, '2025-09-04 08:12:49', '2025-09-04 08:12:49', 0, NULL, NULL, 0),
(42, 10, '4-Bedroom Farm-Style House – Walkerville, Johannesburg', 'gfbrg', 'apartment', 'Sponge', 'polokwane', 'Limpopo', '699', NULL, NULL, 1, 1, 2, 90.00, 2000.00, 500.00, 0, 0, 0, 0, '2025-09-04', 12, 1, 0, 0, '2025-09-04 08:14:16', '2025-09-04 08:14:16', 0, NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `property_amenities`
--

CREATE TABLE `property_amenities` (
  `property_id` int(11) NOT NULL,
  `amenity_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_amenities`
--

INSERT INTO `property_amenities` (`property_id`, `amenity_id`) VALUES
(41, 4);

-- --------------------------------------------------------

--
-- Table structure for table `property_favorites`
--

CREATE TABLE `property_favorites` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `property_images`
--

CREATE TABLE `property_images` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `property_images`
--

INSERT INTO `property_images` (`id`, `property_id`, `image_url`, `is_primary`, `created_at`) VALUES
(86, 41, 'img_68b94a0167995.jpg', 1, '2025-09-04 08:12:49'),
(87, 42, 'img_68b94a582a8b0.jpg', 1, '2025-09-04 08:14:16');

-- --------------------------------------------------------

--
-- Table structure for table `property_reviews`
--

CREATE TABLE `property_reviews` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lease_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `review_title` varchar(200) DEFAULT NULL,
  `review_text` text DEFAULT NULL,
  `landlord_response` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_agreements`
--

CREATE TABLE `rental_agreements` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `lease_start_date` date NOT NULL,
  `lease_end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) NOT NULL,
  `status` enum('draft','active','expired','terminated') DEFAULT 'draft',
  `agreement_document` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rental_applications`
--

CREATE TABLE `rental_applications` (
  `id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `application_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','withdrawn') DEFAULT 'pending',
  `move_in_date` date DEFAULT NULL,
  `lease_duration_months` int(11) DEFAULT NULL,
  `monthly_rent` decimal(10,2) DEFAULT NULL,
  `security_deposit` decimal(10,2) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `landlord_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rent_payments`
--

CREATE TABLE `rent_payments` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `paid_date` timestamp NULL DEFAULT NULL,
  `payment_method` enum('bank_transfer','eft','cash','cheque','debit_order') DEFAULT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','overdue','partial') DEFAULT 'pending',
  `late_fee` decimal(8,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'EasyRent', 'Website name', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(2, 'default_lease_duration', '12', 'Default lease duration in months', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(3, 'late_payment_fee', '500.00', 'Default late payment fee in ZAR', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(4, 'maintenance_priorities', 'low,medium,high,urgent', 'Available maintenance priority levels', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(5, 'supported_payment_methods', 'bank_transfer,eft,cash,cheque,debit_order', 'Supported payment methods', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(6, 'max_upload_size', '10485760', 'Maximum file upload size in bytes (10MB)', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(7, 'currency_symbol', 'R', 'Currency symbol', '2025-06-30 18:21:03', '2025-06-30 18:21:03'),
(8, 'timezone', 'Africa/Johannesburg', 'System timezone', '2025-06-30 18:21:03', '2025-06-30 18:21:03');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_documents`
--

CREATE TABLE `tenant_documents` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_documents`
--

INSERT INTO `tenant_documents` (`id`, `tenant_id`, `document_type`, `document_name`, `file_path`, `file_size`, `file_type`, `uploaded_at`) VALUES
(8, 9, 'id_document', 'Identity Document', 'id_document_9_1756960522_68b9170ab44e7.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(9, 9, 'proof_of_income', 'Proof of Income', 'proof_of_income_9_1756960522_68b9170ab6089.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(10, 9, 'bank_statement', 'Bank Statement', 'bank_statement_9_1756960522_68b9170ab7649.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(11, 9, 'employment_letter', 'Employment Letter', 'employment_letter_9_1756960522_68b9170ab9511.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(12, 9, 'credit_report', 'Credit Report', 'credit_report_9_1756960522_68b9170aba962.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(13, 9, 'reference_letter', 'Reference Letter', 'reference_letter_9_1756960522_68b9170abbf85.pdf', NULL, NULL, '2025-09-04 04:35:22'),
(14, 9, 'rental_history_doc', 'Rental History Document', 'rental_history_doc_9_1756960522_68b9170abd504.pdf', NULL, NULL, '2025-09-04 04:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_profiles`
--

CREATE TABLE `tenant_profiles` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(10) NOT NULL,
  `id_number` varchar(20) NOT NULL,
  `employment_status` enum('employed','self_employed','unemployed','student','retired') NOT NULL,
  `employer_name` varchar(255) DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `monthly_income` decimal(10,2) NOT NULL,
  `emergency_contact_name` varchar(255) NOT NULL,
  `emergency_contact_phone` varchar(20) NOT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `rental_history` text DEFAULT NULL,
  `additional_info` text DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant_profiles`
--

INSERT INTO `tenant_profiles` (`id`, `tenant_id`, `full_name`, `email`, `phone`, `address`, `city`, `province`, `postal_code`, `id_number`, `employment_status`, `employer_name`, `job_title`, `monthly_income`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `rental_history`, `additional_info`, `profile_image`, `created_at`, `updated_at`) VALUES
(2, 9, 'JOEL HAMESE gfdgd', 'joelhamese@gmail.com', '0842700536', '5 Fortuna, Bedworth park jjhkj', 'Yes', 'Gauteng', '9899', '0411070279088', 'employed', 'Vaal university of technology', 'yrty', 75646.00, 'Application for Information Technology Internship - Seja Onalenna Hamese', '0662658784', 'parent', 'ytuy', 'ytyt', 'tenant_profile_9_1756960522.jpg', '2025-09-04 04:35:22', '2025-09-04 04:35:22');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('tenant','landlord','admin') NOT NULL DEFAULT 'tenant',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `failed_login_attempts` int(11) DEFAULT 0,
  `last_failed_login` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `user_type`, `first_name`, `last_name`, `phone`, `profile_image`, `date_of_birth`, `is_verified`, `is_active`, `created_at`, `updated_at`, `failed_login_attempts`, `last_failed_login`, `last_login`, `status`) VALUES
(3, 'admin', 'admin@easyrent.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', NULL, NULL, NULL, 1, 1, '2025-07-01 10:28:34', '2025-07-01 10:28:34', 0, NULL, NULL, 'active'),
(9, 'onalenna', 'onalennahamese07@gmail.com', '$2y$10$S08ckwlZRrCLLOl/iLRa0.55Z.Nwqto46IbyIZ51FWzC.kG4STNni', 'tenant', 'Test', 'Test 2', '0662658784', 'user_9_1756124148.jpeg', '2004-11-07', 0, 1, '2025-08-24 23:32:34', '2025-08-25 12:15:48', 0, NULL, NULL, 'active'),
(10, 'OnaLandlord', 'yeppp285@gmail.com', '$2y$10$bhWDagtugxBElEOKEMKufear3aIoDk9G3RruJFpbIlLDXxmMptVyS', 'landlord', 'Test', 'Test 2', '0662658784', 'user_10_1756123956.jpg', '2004-11-07', 0, 1, '2025-08-24 23:34:06', '2025-08-25 12:12:36', 0, NULL, NULL, 'active'),
(11, 'TestLandlord', 'joelhamese@gmail.com', '$2y$10$IMzpq1g61mJkgYqcH0UKTOtfC8hs1YvyfPqTPC1dIRYGQHWoUWMpW', 'landlord', NULL, NULL, NULL, NULL, NULL, 0, 1, '2025-08-26 10:28:03', '2025-08-26 10:28:03', 0, NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `user_profiles`
--

CREATE TABLE `user_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'South Africa',
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(12,2) DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `identity_document` varchar(255) DEFAULT NULL,
  `proof_of_income` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_profiles`
--

INSERT INTO `user_profiles` (`id`, `user_id`, `address`, `city`, `state`, `postal_code`, `country`, `occupation`, `monthly_income`, `emergency_contact_name`, `emergency_contact_phone`, `identity_document`, `proof_of_income`, `created_at`, `updated_at`) VALUES
(8, 9, NULL, NULL, NULL, NULL, 'South Africa', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-24 23:32:34', '2025-08-24 23:32:34'),
(9, 10, NULL, NULL, NULL, NULL, 'South Africa', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-24 23:34:06', '2025-08-24 23:34:06'),
(10, 11, NULL, NULL, NULL, NULL, 'South Africa', NULL, NULL, NULL, NULL, NULL, NULL, '2025-08-26 10:28:03', '2025-08-26 10:28:03');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `admin_dashboard_stats`
--
DROP TABLE IF EXISTS `admin_dashboard_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_dashboard_stats`  AS SELECT (select count(0) from `users` where `users`.`user_type` = 'tenant' and `users`.`status` = 'active') AS `active_tenants`, (select count(0) from `users` where `users`.`user_type` = 'landlord' and `users`.`status` = 'active') AS `active_landlords`, (select count(0) from `users` where `users`.`status` = 'active') AS `total_active_users`, (select count(0) from `login_logs` where cast(`login_logs`.`created_at` as date) = curdate() and `login_logs`.`status` = 'success') AS `todays_logins`, (select count(0) from `login_logs` where cast(`login_logs`.`created_at` as date) = curdate() and `login_logs`.`status` = 'failure') AS `todays_failed_logins`, (select count(0) from `users` where cast(`users`.`created_at` as date) = curdate()) AS `new_registrations_today` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `landlord_documents`
--
ALTER TABLE `landlord_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doc` (`landlord_id`,`document_type`),
  ADD KEY `idx_landlord_id` (`landlord_id`);

--
-- Indexes for table `landlord_profiles`
--
ALTER TABLE `landlord_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_landlord` (`landlord_id`);

--
-- Indexes for table `leases`
--
ALTER TABLE `leases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `idx_leases_tenant` (`tenant_id`),
  ADD KEY `idx_leases_property` (`property_id`),
  ADD KEY `idx_leases_status` (`status`),
  ADD KEY `idx_leases_signatures` (`tenant_id`,`landlord_id`,`signature_date`,`landlord_signature_date`),
  ADD KEY `template_id` (`template_id`);

--
-- Indexes for table `lease_templates`
--
ALTER TABLE `lease_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_images`
--
ALTER TABLE `maintenance_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maintenance_request_id` (`maintenance_request_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `idx_maintenance_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`),
  ADD KEY `idx_notifications_read` (`is_read`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_properties_landlord` (`landlord_id`),
  ADD KEY `idx_properties_city` (`city`),
  ADD KEY `idx_properties_available` (`is_available`),
  ADD KEY `idx_properties_rent` (`rent_amount`);

--
-- Indexes for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD PRIMARY KEY (`property_id`,`amenity_id`),
  ADD KEY `amenity_id` (`amenity_id`);

--
-- Indexes for table `property_favorites`
--
ALTER TABLE `property_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`tenant_id`,`property_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_images`
--
ALTER TABLE `property_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `property_reviews`
--
ALTER TABLE `property_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`property_id`,`tenant_id`,`lease_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `lease_id` (`lease_id`);

--
-- Indexes for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `rental_agreements`
--
ALTER TABLE `rental_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `property_id` (`property_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `rental_applications`
--
ALTER TABLE `rental_applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`property_id`,`tenant_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `rent_payments`
--
ALTER TABLE `rent_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lease_id` (`lease_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `idx_rent_payments_due_date` (`due_date`),
  ADD KEY `idx_rent_payments_status` (`status`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tenant_documents`
--
ALTER TABLE `tenant_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_doc` (`tenant_id`,`document_type`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_document_type` (`document_type`),
  ADD KEY `idx_uploaded_at` (`uploaded_at`);

--
-- Indexes for table `tenant_profiles`
--
ALTER TABLE `tenant_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant` (`tenant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_type` (`user_type`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_users_email_type` (`email`,`user_type`);

--
-- Indexes for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_settings`
--
ALTER TABLE `admin_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `amenities`
--
ALTER TABLE `amenities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `landlord_documents`
--
ALTER TABLE `landlord_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `landlord_profiles`
--
ALTER TABLE `landlord_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leases`
--
ALTER TABLE `leases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `lease_templates`
--
ALTER TABLE `lease_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `maintenance_images`
--
ALTER TABLE `maintenance_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `property_favorites`
--
ALTER TABLE `property_favorites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `property_images`
--
ALTER TABLE `property_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `property_reviews`
--
ALTER TABLE `property_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rental_agreements`
--
ALTER TABLE `rental_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rental_applications`
--
ALTER TABLE `rental_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `rent_payments`
--
ALTER TABLE `rent_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tenant_documents`
--
ALTER TABLE `tenant_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tenant_profiles`
--
ALTER TABLE `tenant_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_profiles`
--
ALTER TABLE `user_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `leases`
--
ALTER TABLE `leases`
  ADD CONSTRAINT `leases_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leases_ibfk_2` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leases_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leases_ibfk_4` FOREIGN KEY (`application_id`) REFERENCES `rental_applications` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `leases_ibfk_5` FOREIGN KEY (`template_id`) REFERENCES `lease_templates` (`id`);

--
-- Constraints for table `maintenance_images`
--
ALTER TABLE `maintenance_images`
  ADD CONSTRAINT `maintenance_images_ibfk_1` FOREIGN KEY (`maintenance_request_id`) REFERENCES `maintenance_requests` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `properties_ibfk_1` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_amenities`
--
ALTER TABLE `property_amenities`
  ADD CONSTRAINT `property_amenities_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_amenities_ibfk_2` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_favorites`
--
ALTER TABLE `property_favorites`
  ADD CONSTRAINT `property_favorites_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_favorites_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_images`
--
ALTER TABLE `property_images`
  ADD CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_reviews`
--
ALTER TABLE `property_reviews`
  ADD CONSTRAINT `property_reviews_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_reviews_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `property_reviews_ibfk_3` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD CONSTRAINT `remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_agreements`
--
ALTER TABLE `rental_agreements`
  ADD CONSTRAINT `rental_agreements_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_agreements_ibfk_2` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_agreements_ibfk_3` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rental_applications`
--
ALTER TABLE `rental_applications`
  ADD CONSTRAINT `rental_applications_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_applications_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rental_applications_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `rent_payments`
--
ALTER TABLE `rent_payments`
  ADD CONSTRAINT `rent_payments_ibfk_1` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rent_payments_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rent_payments_ibfk_3` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_profiles`
--
ALTER TABLE `user_profiles`
  ADD CONSTRAINT `user_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
