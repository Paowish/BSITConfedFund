-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 30, 2026 at 02:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bsit_fund_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `adviser_account`
--

CREATE TABLE `adviser_account` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adviser_account`
--

INSERT INTO `adviser_account` (`id`, `username`, `password_hash`, `name`, `created_at`, `profile_picture`) VALUES
(1, 'adviser', '$2y$10$.YEgr9dVLuFlG0iEFe6IMeypjkBZeFgijjnwDa2Y/J4ca1w.1ZAsm', 'Rey Aguilar', '2026-04-30 14:05:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `auditor_account`
--

CREATE TABLE `auditor_account` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `auditor_account`
--

INSERT INTO `auditor_account` (`id`, `username`, `password_hash`, `name`, `created_at`) VALUES
(1, 'auditor', '$2y$10$AIodEc46u4hSM3ZZ7HOSdOHRRMUMOvokmJtjCpEE7YBt1cLiv.uYW', 'JANA SANGALANG', '2026-05-14 13:20:38');

-- --------------------------------------------------------

--
-- Table structure for table `budget_plan_items`
--

CREATE TABLE `budget_plan_items` (
  `id` int(11) NOT NULL,
  `budget_id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `estimated_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_plan_items`
--

INSERT INTO `budget_plan_items` (`id`, `budget_id`, `item_name`, `quantity`, `unit_cost`, `estimated_total`) VALUES
(3, 1, 'prizes', 50, 50.00, 2500.00),
(4, 1, 'food', 500, 50.00, 25000.00),
(5, 2, 'colors', 100, 10.00, 1000.00),
(6, 2, 'food', 100, 99999999.99, 99999999.99),
(7, 3, 'prizes', 100, 99999999.99, 99999999.99),
(8, 4, 'paper', 100, 900000.00, 90000000.00),
(9, 5, 'paper', 10000, 90000.00, 99999999.99);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'approved',
  `created_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `status`, `created_by`, `reviewed_by`, `reviewed_at`, `review_comment`) VALUES
(1, 'Supplies', 'Office and event supplies', '2026-04-30 14:05:30', 'approved', NULL, NULL, NULL, NULL),
(2, 'Events', 'Event-related expenses', '2026-04-30 14:05:30', 'approved', NULL, NULL, NULL, NULL),
(3, 'Travel', 'Transportation and fare', '2026-04-30 14:05:30', 'approved', NULL, NULL, NULL, NULL),
(4, 'Miscellaneous', 'Other expenses', '2026-04-30 14:05:30', 'approved', NULL, NULL, NULL, NULL),
(6, 'aa', 'aaa', '2026-08-21 21:28:24', 'approved', 1, 1, '2026-08-21 21:28:41', NULL),
(7, 'bb', 'b', '2026-08-22 16:02:24', 'denied', 1, 1, '2026-08-22 16:03:02', NULL),
(10, 'ss', '', '2026-10-24 16:10:50', 'denied', 1, 1, '2026-10-24 22:52:37', 'xzxzxz'),
(12, 'sssa', '', '2026-10-24 22:54:50', 'approved', 1, 1, '2026-10-24 22:55:12', '');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `school_year` varchar(20) NOT NULL,
  `event_datetime` datetime DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `expected_participants` int(11) DEFAULT NULL,
  `proposed_activities` text DEFAULT NULL,
  `initial_requirements` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','denied','pending_budget_planning','pending_treasurer_review','pending_adviser_review','adviser_rejected','budget_approved','returned_for_revision','funds_released','event_ongoing','event_completed','pending_liquidation','pending_audit','audited_compliant','audited_with_findings','closed') NOT NULL DEFAULT 'pending_budget_planning',
  `created_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `name`, `description`, `purpose`, `school_year`, `event_datetime`, `venue`, `expected_participants`, `proposed_activities`, `initial_requirements`, `created_at`, `status`, `created_by`, `reviewed_by`, `reviewed_at`, `review_comment`) VALUES
(1, 'Techno Day', 'asd', 'asd', '2026-2027', '2026-11-24 09:59:00', 'neust tal off campus', 500, 'techno games,...', 'payment', '2026-10-24 16:14:46', 'closed', 1, NULL, NULL, NULL),
(2, 'color run', 'xzxzx', 'wsww', '2026-2027', '2028-01-09 12:35:00', 'neust tal off campus', 100, 'games', 'payment', '2026-10-25 12:36:32', 'pending_treasurer_review', 1, NULL, NULL, NULL),
(3, 'xmas party', 'aa', 'aaa', '2026-2027', '2028-12-20 12:00:00', 'neust tal off campus', 200, 'games, etc', 'payment', '2026-10-25 12:48:34', 'pending_treasurer_review', 1, NULL, NULL, NULL),
(4, 'zz', '', '', '2026-2027', '2026-11-25 12:51:00', 'neust tal off campus', 200, 'zzz', 'payment', '2026-10-25 12:51:21', 'adviser_rejected', 1, NULL, NULL, NULL),
(5, 'cvcv', 'cccc', 'cc', '2026-2027', '2026-11-26 12:54:00', 'neust tal off campus', 50, 'cx', 'cxbb', '2026-10-25 12:54:31', 'closed', 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_budgets`
--

CREATE TABLE `event_budgets` (
  `id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `budget_name` varchar(100) NOT NULL,
  `allocated_amount` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending_treasurer_review','pending_adviser_review','adviser_rejected','returned_for_revision','approved','denied') NOT NULL DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_comment` text DEFAULT NULL,
  `adviser_reviewed_by` int(11) DEFAULT NULL,
  `adviser_reviewed_at` datetime DEFAULT NULL,
  `adviser_comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `mayor_created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_budgets`
--

INSERT INTO `event_budgets` (`id`, `event_id`, `school_year`, `budget_name`, `allocated_amount`, `notes`, `attachments`, `category_id`, `status`, `created_by`, `reviewed_by`, `reviewed_at`, `review_comment`, `adviser_reviewed_by`, `adviser_reviewed_at`, `adviser_comment`, `created_at`, `updated_at`, `mayor_created_by`) VALUES
(1, 1, '2026-2027', 'Techno Day - Budget Plan', 27500.00, '', 'uploads/budget_attachments/1792812038_0_receipt.png', NULL, 'approved', 5, 6, '2026-10-24 21:42:47', ' | RELEASED on 2026-10-24 Ref: ', 1, '2026-10-24 16:29:52', '', '2026-10-24 16:20:38', '2026-10-24 21:42:47', NULL),
(2, 2, '2026-2027', 'color run - Budget Plan', 99999999.99, '', 'uploads/budget_attachments/1792885212_0_receipt.png', NULL, 'pending_treasurer_review', 5, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-25 12:40:12', NULL, NULL),
(3, 3, '2026-2027', 'xmas party - Budget Plan', 99999999.99, '', 'uploads/budget_attachments/1792885804_0_receipt.png', NULL, 'pending_treasurer_review', 5, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-25 12:50:04', NULL, NULL),
(4, 4, '2026-2027', 'zz - Budget Plan', 90000000.00, '', 'uploads/budget_attachments/1792885968_0_Liquidation_Report_20261025.pdf', NULL, 'adviser_rejected', 5, 6, '2026-10-25 12:58:29', '', 1, '2026-10-25 13:04:36', 'cz', '2026-10-25 12:52:48', '2026-10-25 13:04:36', NULL),
(5, 5, '2026-2027', 'cvcv - Budget Plan', 99999999.99, '', 'uploads/budget_attachments/1792886141_0_pngtree-coffee-bean-icon-design-template-vector-isolated-free-and-png-png-image_4748028-removebg-preview.png', NULL, 'approved', 5, 6, '2026-10-25 13:13:07', ' | RELEASED on 2026-10-25 Ref: ', 1, '2026-10-25 13:08:05', '', '2026-10-25 12:55:41', '2026-10-25 13:13:07', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `category_id` int(11) NOT NULL,
  `payee` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `expense_date` date NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `event` varchar(100) DEFAULT NULL,
  `event_id` int(11) DEFAULT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `fund_source` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `officer_id` int(11) NOT NULL,
  `status` enum('pending_audit','audited','mayor_approved','approved','denied') NOT NULL DEFAULT 'pending_audit',
  `reviewed_by` int(11) DEFAULT NULL,
  `audited_by` int(11) DEFAULT NULL,
  `mayor_approved_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `audited_at` datetime DEFAULT NULL,
  `mayor_approved_at` datetime DEFAULT NULL,
  `review_comment` text DEFAULT NULL,
  `audit_comment` text DEFAULT NULL,
  `mayor_comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `amount`, `category_id`, `payee`, `description`, `expense_date`, `school_year`, `event`, `event_id`, `budget_id`, `fund_source`, `receipt_path`, `officer_id`, `status`, `reviewed_by`, `audited_by`, `mayor_approved_by`, `reviewed_at`, `audited_at`, `mayor_approved_at`, `review_comment`, `audit_comment`, `mayor_comment`, `created_at`, `updated_at`) VALUES
(1, 2000.00, 2, 'GUILA FAITH FULGENCIO', '', '2026-10-24', '', 'Techno Day', 1, 1, 'Sponsorship Income', 'uploads/receipts/1792831556_0_receipt.png', 6, 'approved', 1, 4, NULL, '2026-10-24 21:50:28', '2026-10-24 21:50:05', NULL, '', '', NULL, '2026-10-24 21:45:56', '2026-10-24 21:50:28'),
(2, 5000.00, 4, 'GUILA FAITH FULGENCIO', '', '2026-10-24', '2026-2027', '', 0, NULL, 'Sponsorship Income', 'uploads/receipts/1792832125_0_receipt.png', 6, 'approved', 1, 4, NULL, '2026-10-24 22:00:22', '2026-10-24 21:58:33', NULL, '', '', NULL, '2026-10-24 21:55:25', '2026-10-24 22:00:22'),
(3, 1500.00, 1, 'LORENZO MIGUEL P. SEBASTIAN', '', '2026-10-25', '2026-2027', '', 0, NULL, 'Sponsorship Income', 'uploads/receipts/1792875094_0_receipt.png', 6, 'approved', 1, 4, NULL, '2026-10-25 09:53:28', '2026-10-25 09:52:55', NULL, '', '', NULL, '2026-10-25 09:51:34', '2026-10-25 09:53:28'),
(4, 5000.00, 4, 'GUILA FAITH FULGENCIO', '', '2026-10-25', '2026-2027', '', 0, NULL, 'Sponsorship Income', 'uploads/receipts/1792884842_0_receipt.png', 6, 'audited', NULL, 4, NULL, NULL, '2026-10-25 12:34:54', NULL, NULL, '', NULL, '2026-10-25 12:34:02', '2026-10-25 12:34:54'),
(5, 1000000.00, 2, 'GUILA FAITH FULGENCIO', '', '2026-10-25', '', 'cvcv', 5, 5, 'Sponsorship Income', 'uploads/receipts/1792889238_0_pngtree-coffee-bean-icon-design-template-vector-isolated-free-and-png-png-image_4748028-removebg-preview.png', 6, 'approved', 1, 4, NULL, '2026-10-29 20:15:56', '2026-10-29 20:14:33', NULL, '', '', NULL, '2026-10-25 13:47:18', '2026-10-29 20:15:56');

-- --------------------------------------------------------

--
-- Table structure for table `funds`
--

CREATE TABLE `funds` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `source` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `fund_date` date NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `event` varchar(100) DEFAULT NULL,
  `officer_id` int(11) NOT NULL,
  `status` enum('pending_audit','audited','mayor_approved','approved','denied') NOT NULL DEFAULT 'pending_audit',
  `reviewed_by` int(11) DEFAULT NULL,
  `audited_by` int(11) DEFAULT NULL,
  `mayor_approved_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `audited_at` datetime DEFAULT NULL,
  `mayor_approved_at` datetime DEFAULT NULL,
  `review_comment` text DEFAULT NULL,
  `audit_comment` text DEFAULT NULL,
  `mayor_comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `funds`
--

INSERT INTO `funds` (`id`, `amount`, `source`, `description`, `receipt_path`, `fund_date`, `school_year`, `event`, `officer_id`, `status`, `reviewed_by`, `audited_by`, `mayor_approved_by`, `reviewed_at`, `audited_at`, `mayor_approved_at`, `review_comment`, `audit_comment`, `mayor_comment`, `created_at`, `updated_at`) VALUES
(1, 20000.00, 'Fundraising Income', '', 'uploads/receipts/1792367371_0_receipt.png', '2026-10-19', '2026-2027', NULL, 6, 'denied', NULL, 4, NULL, NULL, '2026-10-19 12:50:35', NULL, NULL, 'vv', NULL, '2026-10-19 12:49:31', '2026-10-19 12:50:35'),
(2, 15000.00, 'University Financial Assistance', '', 'uploads/receipts/1792367836_0_receipt.png', '2026-10-19', '2026-2027', NULL, 6, 'approved', 1, 4, NULL, '2026-10-19 13:15:26', '2026-10-19 13:14:30', NULL, '', '', NULL, '2026-10-19 12:57:16', '2026-10-19 13:15:26'),
(3, 20000.00, 'Sponsorship Income', '', 'uploads/receipts/1792832060_0_receipt.png', '2026-10-24', '2025-2026', NULL, 6, 'approved', 1, 4, NULL, '2026-10-24 22:00:14', '2026-10-24 21:57:13', NULL, '', '', NULL, '2026-10-24 21:54:20', '2026-10-24 22:00:14');

-- --------------------------------------------------------

--
-- Table structure for table `inquiries`
--

CREATE TABLE `inquiries` (
  `id` int(11) NOT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `student_email` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `reply` text DEFAULT NULL,
  `replied_by_name` varchar(100) DEFAULT NULL,
  `replied_by_role` enum('adviser','officer') DEFAULT NULL,
  `replied_at` datetime DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiries`
--

INSERT INTO `inquiries` (`id`, `student_name`, `student_email`, `message`, `category_id`, `reply`, `replied_by_name`, `replied_by_role`, `replied_at`, `is_public`, `created_at`) VALUES
(1, '', '', 'how much fund do we need for techno day?', 1, 'ahdgaytct', 'LORENZO MIGUEL P. SEBASTIAN', 'officer', '2026-10-24 16:25:22', 1, '2026-10-24 16:24:03'),
(2, '', '', 'bxyuasy', 2, NULL, NULL, NULL, NULL, 1, '2026-10-24 22:33:12'),
(3, '', '', 'cxcccx', 1, NULL, NULL, NULL, NULL, 0, '2026-10-25 13:26:47'),
(4, '', '', 'ssdds', 2, NULL, NULL, NULL, NULL, 1, '2026-10-25 13:28:30');

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_categories`
--

CREATE TABLE `inquiry_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiry_categories`
--

INSERT INTO `inquiry_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Budget Inquiry', '2026-04-30 14:05:30'),
(2, 'Event Funds', '2026-04-30 14:05:30'),
(3, 'Expense Clarification', '2026-04-30 14:05:30'),
(4, 'Other Concerns', '2026-04-30 14:05:30');

-- --------------------------------------------------------

--
-- Table structure for table `inquiry_replies`
--

CREATE TABLE `inquiry_replies` (
  `id` int(11) NOT NULL,
  `inquiry_id` int(11) NOT NULL,
  `reply` text NOT NULL,
  `replied_by_name` varchar(150) NOT NULL,
  `replied_by_role` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inquiry_replies`
--

INSERT INTO `inquiry_replies` (`id`, `inquiry_id`, `reply`, `replied_by_name`, `replied_by_role`, `created_at`) VALUES
(1, 1, 'ahdgaytct', 'LORENZO MIGUEL P. SEBASTIAN', 'officer', '2026-10-24 16:25:22'),
(2, 1, 'hshhshs', 'Rey Aguilar', 'adviser', '2026-10-24 22:33:34'),
(3, 2, 'aaaa', 'LORENZO MIGUEL P. SEBASTIAN', 'officer', '2026-10-24 22:35:00'),
(4, 1, 'zz', 'LORENZO MIGUEL P. SEBASTIAN', 'officer', '2026-10-24 22:35:09'),
(5, 3, 'vcvc', 'Rey Aguilar', 'adviser', '2026-10-25 13:27:18');

-- --------------------------------------------------------

--
-- Table structure for table `liquidations`
--

CREATE TABLE `liquidations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `submitted_by` int(11) NOT NULL,
  `total_budget` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_expenses` decimal(10,2) NOT NULL DEFAULT 0.00,
  `variance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `attachments` varchar(255) DEFAULT NULL,
  `status` enum('pending_audit','audited_compliant','audited_with_findings','closed') NOT NULL DEFAULT 'pending_audit',
  `audited_by` int(11) DEFAULT NULL,
  `audited_at` datetime DEFAULT NULL,
  `audit_result` enum('compliant','with_findings') DEFAULT NULL,
  `audit_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `liquidations`
--

INSERT INTO `liquidations` (`id`, `event_id`, `budget_id`, `submitted_by`, `total_budget`, `total_expenses`, `variance`, `notes`, `attachments`, `status`, `audited_by`, `audited_at`, `audit_result`, `audit_notes`, `approved_by`, `approved_at`, `approval_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 6, 27500.00, 0.00, 27500.00, 'bvbvhgyt', 'uploads/liquidations/1792831630_0_receipt.png', 'closed', 4, '2026-10-24 21:48:31', 'compliant', '', 1, '2026-10-24 21:49:01', '', '2026-10-24 21:47:10', '2026-10-24 21:49:01'),
(2, 5, 5, 6, 99999999.99, 1000000.00, 98999999.99, '', 'uploads/liquidations/1793258289_0_pngtree-coffee-bean-icon-design-template-vector-isolated-free-and-png-png-image_4748028-removebg-preview.png', 'closed', 4, '2026-10-29 20:18:51', 'compliant', '', 1, '2026-10-29 20:19:11', '', '2026-10-29 20:18:09', '2026-10-29 20:19:11');

-- --------------------------------------------------------

--
-- Table structure for table `officers`
--

CREATE TABLE `officers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `position` varchar(50) NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `officers`
--

INSERT INTO `officers` (`id`, `name`, `position`, `school_year`, `is_active`, `username`, `email`, `password_hash`, `created_at`, `profile_picture`) VALUES
(1, 'JOHN MARLOU C. CASTILLO', 'Mayor', '2026-2027', 1, 'mayor', 'mayor@gmail.com', '$2y$10$dD2H36kzQ8dkjn3JHtaqLesD/75Oiv.fbh.okRo./7Gyhtdt8kJBS', '2026-04-30 14:05:30', NULL),
(2, 'GUILA FAITH FULGENCIO', 'Vice Mayor', '2025-2026', 0, 'vicemayor', 'vicemayor@gmail.com', '$2y$10$3OP8cuPgrDsFE2a6DI9eceSGFgN3hw5D5H.behCPawZZUlSFdoL46', '2026-04-30 14:05:30', NULL),
(3, 'PRINCESS SAN PEDRO', 'Treasurer', '2025-2026', 0, 'treasurer', 'treasurer@gmail.com', '$2y$10$zN4CR450zbtA7IU2Fnh2Ee4yL6gtp0TYoa0c7kbaViBLma387NyYG', '2026-04-30 14:05:30', NULL),
(4, 'JANA SANGALANG', 'Auditor', '2026-2027', 1, 'auditor', 'auditor@gmail.com', '$2y$10$sm6DGJejcC05xRQMvtSWMeCTU6QT3m3q2b7BSPYSflLAD1R1rDsaS', '2026-08-31 17:03:08', NULL),
(5, 'GUILA FAITH FULGENCIO', 'Business Manager', '2026-2027', 1, 'bm', 'bm@gmail.com', '$2y$10$dLQ3MYxoEs6PFcirVwJAouVRTmC.zbx2mq3k0oSQ8vKezSCB9Wrsi', '2026-08-31 17:59:49', NULL),
(6, 'LORENZO MIGUEL P. SEBASTIAN', 'Treasurer', '2026-2027', 1, 'treasurer lorenzo', 'treas@gmail.com', '$2y$10$xlJVSdDPocpieNEA3/8Se.GSyxasOPOtUoAbuHdZbaFSgpI/p3pwC', '2026-08-31 23:06:44', NULL),
(7, 'JUMER  MACALANDAG', 'Vice Mayor', '2026-2027', 1, 'vice', 'vice@gmail.com', '$2y$10$ztA50USjCwW.Vn5rvAsrNO0wNQwoue1iYpXUjzukPcA7PC0WsdtHm', '2026-10-06 10:08:32', 'uploads/profiles/officers_7_1792811823.png');

-- --------------------------------------------------------

--
-- Table structure for table `school_years`
--

CREATE TABLE `school_years` (
  `id` int(11) NOT NULL,
  `year_label` varchar(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `school_years`
--

INSERT INTO `school_years` (`id`, `year_label`, `created_at`) VALUES
(1, '2024-2025', '2026-08-21 20:47:36'),
(2, '2025-2026', '2026-08-21 20:47:36'),
(3, '2026-2027', '2026-08-21 20:47:36'),
(4, '2027-2028', '2026-08-21 20:47:36'),
(5, '2028-2029', '2026-10-24 16:15:32');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `org_name` varchar(100) NOT NULL DEFAULT 'BSIT Confederation',
  `org_short_name` varchar(50) NOT NULL DEFAULT 'BSIT Confed Fund',
  `logo_path` varchar(255) NOT NULL DEFAULT 'assets/images/logo.png.webp',
  `primary_color` varchar(7) NOT NULL DEFAULT '#1a3c5e',
  `recovery_key` varchar(100) NOT NULL DEFAULT 'BSIT-ADMIN-2026'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `org_name`, `org_short_name`, `logo_path`, `primary_color`, `recovery_key`) VALUES
(1, 'BSIT Confederation', 'BSIT Confed Fund', 'uploads/branding/logo_1792628629.png', '#1a3c5e', 'BSIT2026');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adviser_account`
--
ALTER TABLE `adviser_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `auditor_account`
--
ALTER TABLE `auditor_account`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `budget_plan_items`
--
ALTER TABLE `budget_plan_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `budget_id` (`budget_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_budgets`
--
ALTER TABLE `event_budgets`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `officer_id` (`officer_id`);

--
-- Indexes for table `funds`
--
ALTER TABLE `funds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `officer_id` (`officer_id`);

--
-- Indexes for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `inquiry_categories`
--
ALTER TABLE `inquiry_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inquiry_id` (`inquiry_id`);

--
-- Indexes for table `liquidations`
--
ALTER TABLE `liquidations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `officers`
--
ALTER TABLE `officers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `school_years`
--
ALTER TABLE `school_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `year_label` (`year_label`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adviser_account`
--
ALTER TABLE `adviser_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `auditor_account`
--
ALTER TABLE `auditor_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `budget_plan_items`
--
ALTER TABLE `budget_plan_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `event_budgets`
--
ALTER TABLE `event_budgets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `funds`
--
ALTER TABLE `funds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inquiries`
--
ALTER TABLE `inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inquiry_categories`
--
ALTER TABLE `inquiry_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `liquidations`
--
ALTER TABLE `liquidations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `officers`
--
ALTER TABLE `officers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `school_years`
--
ALTER TABLE `school_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budget_plan_items`
--
ALTER TABLE `budget_plan_items`
  ADD CONSTRAINT `budget_plan_items_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `event_budgets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`id`);

--
-- Constraints for table `funds`
--
ALTER TABLE `funds`
  ADD CONSTRAINT `funds_ibfk_1` FOREIGN KEY (`officer_id`) REFERENCES `officers` (`id`);

--
-- Constraints for table `inquiries`
--
ALTER TABLE `inquiries`
  ADD CONSTRAINT `inquiries_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inquiry_categories` (`id`);

--
-- Constraints for table `inquiry_replies`
--
ALTER TABLE `inquiry_replies`
  ADD CONSTRAINT `inquiry_replies_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `inquiries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `liquidations`
--
ALTER TABLE `liquidations`
  ADD CONSTRAINT `liquidations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
