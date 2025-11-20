-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 19, 2025 at 10:00 PM
-- Server version: 5.7.23-23
-- PHP Version: 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mxbttmmy_fidels_pizza`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password_hash`, `email`, `created_at`) VALUES
(1, 'admin', '$2y$12$TC0TOUJPXUnDG3/sHBy6d.ARjJCZsPFAZ9G39KdkloLdSFlg2bor2', 'fidel@montoyahome.com', '2025-09-16 06:39:32'),
(2, 'Noriko', '$2y$12$vyvcQovCKog.WnY6.CEJqemGfDyC8stOHGZbsriA5W08OeTSizuo.', 'norikomontoya@yahoo.co.jp', '2025-09-19 08:02:04');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `template_name`, `subject`, `body`, `created_at`, `updated_at`) VALUES
(1, 'confirmation_email', 'Confirm Your Pizza Event Registration', 'Hello {{first_name}},\n\nThank you for registering for Fidel\'s Pizza Event!\n\nPlease click the link below to confirm your account:\n{{confirmation_link}}\n\nBest regards,\nFidel\'s Pizza Team', '2025-09-16 06:39:32', '2025-09-16 06:39:32'),
(2, 'order_confirmation', 'Your Pizza Order Confirmation - #{{order_number}}', 'Hello {{first_name}},\n\nThank you for your pizza order!\n\nOrder Number: {{order_number}}\nTotal Amount: ¥{{total_amount}}\nEvent Date: {{event_date}}\nLocation: {{event_location}}\n\nOrder Details:\n{{order_items}}\n\nPlease bring your order number and ¥{{total_amount}} when picking up your pizza.\n\nBest regards,\nFidel\'s Pizza Team', '2025-09-16 06:39:32', '2025-09-16 06:39:32'),
(3, 'admin_order_notification', 'New Pizza Order Received - #{{order_number}}', 'A new pizza order has been received:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nTotal Amount: ¥{{total_amount}}\n\nOrder Details:\n{{order_items}}\n\nPlease log in to the admin panel to manage this order.', '2025-09-16 06:39:32', '2025-09-16 06:39:32'),
(4, 'order_updated', 'Your Pizza Order #{{order_number}} Has Been Updated', 'Hello {{first_name}},\n\nThis is a confirmation that your pizza order has been updated.\n\nOrder Number: {{order_number}}\nNew Total Amount: {{total_amount}}\n\nUpdated Order Details:\n{{order_items}}\n\nBest regards,\nFidel\'s Pizza Team', '2025-09-17 11:08:30', '2025-09-17 11:08:30'),
(5, 'admin_order_updated_notification', 'Order Updated - #{{order_number}}', 'An existing pizza order has been updated:\n\nOrder Number: {{order_number}}\nCustomer: {{customer_name}} ({{customer_email}})\nNew Total Amount: {{total_amount}}\n\nUpdated Order Details:\n{{order_items}}\n\nPlease log in to the admin panel to review this order.', '2025-09-17 11:08:30', '2025-09-17 11:08:30');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `price`, `image_path`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Margherita Pizza', 'Tomato sauce, mozzarella, fresh basil, extra virgin olive oil', 1500.00, 'images/img_68cbd7556c3055.25391292.png', 1, 1, '2025-09-16 06:39:32', '2025-11-06 02:06:47'),
(2, 'Pepperoni Pizza', 'New York style sauce, mozzarella, pepperoni, Parmigiano Reggiano cheese', 1500.00, 'images/img_68cbd765b76bd8.48982574.png', 1, 2, '2025-09-16 06:39:32', '2025-11-06 02:06:51'),
(3, 'Cheese Pizza', 'New York style sauce, mozzarella, Parmigiano Reggiano cheese', 1500.00, 'images/img_68cbd77b4a4083.35441017.png', 1, 3, '2025-09-16 06:39:32', '2025-11-06 02:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled','archived') NOT NULL DEFAULT 'pending',
  `notes` text,
  `pickup_time` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_number`, `total_amount`, `status`, `notes`, `pickup_time`, `created_at`, `updated_at`) VALUES
(1, 10, 'PZ20255288', 4500.00, 'pending', '', '2025-11-02 14:30:00', '2025-10-06 15:00:53', '2025-10-29 11:12:42'),
(2, 17, 'PZ20259668', 4500.00, 'pending', NULL, '2025-11-02 12:30:00', '2025-10-10 03:29:55', '2025-10-10 03:29:55'),
(3, 20, 'PZ20257045', 4500.00, 'pending', NULL, '2025-11-02 12:30:00', '2025-10-10 03:40:59', '2025-10-10 03:40:59'),
(4, 24, 'PZ20251825', 15000.00, 'pending', '', '2025-11-02 14:30:00', '2025-10-17 10:02:15', '2025-10-29 11:13:27'),
(5, 7, 'PZ20252930', 4500.00, 'pending', 'Une-San', '2025-11-02 12:30:00', '2025-10-18 04:13:09', '2025-10-18 04:21:13'),
(6, 7, 'PZ20259181', 6000.00, 'pending', 'Akai-San', '2025-11-02 12:30:00', '2025-10-18 04:13:30', '2025-10-18 04:19:28'),
(7, 7, 'PZ20251072', 3000.00, 'pending', 'Fujita-San', '2025-11-02 12:30:00', '2025-10-18 04:13:46', '2025-10-18 04:22:39'),
(8, 7, 'PZ20256693', 3000.00, 'pending', 'Chou-San (Eikyoku)', '2025-11-02 12:30:00', '2025-10-18 04:14:01', '2025-10-18 04:24:12'),
(9, 7, 'PZ20254426', 3000.00, 'pending', 'Yoshida-San', '2025-11-02 12:30:00', '2025-10-18 04:14:18', '2025-10-18 04:22:17'),
(10, 7, 'PZ20255565', 3000.00, 'pending', 'Yamamoto-San (Nami)', '2025-11-02 12:30:00', '2025-10-18 04:14:29', '2025-10-18 04:29:48'),
(11, 7, 'PZ20253112', 4500.00, 'pending', 'Hirata-San', '2025-11-02 12:30:00', '2025-10-18 04:14:44', '2025-10-18 04:20:31'),
(12, 7, 'PZ20255775', 7500.00, 'pending', 'Onishi-San', '2025-11-02 12:30:00', '2025-10-18 04:15:03', '2025-10-18 04:20:03'),
(13, 7, 'PZ20254399', 4500.00, 'pending', 'Hirai-San', '2025-11-02 12:30:00', '2025-10-18 04:15:20', '2025-10-18 04:18:37'),
(14, 7, 'PZ20258903', 6000.00, 'pending', 'Takata-San', '2025-11-02 11:00:00', '2025-10-18 04:25:13', '2025-10-18 04:31:27'),
(15, 7, 'PZ20259965', 4500.00, 'pending', 'Okeguchi-San', '2025-11-02 11:00:00', '2025-10-18 04:25:26', '2025-10-18 04:31:02'),
(16, 7, 'PZ20252055', 4500.00, 'pending', 'Yamamoto-San (Iroha)', '2025-11-02 11:00:00', '2025-10-18 04:25:35', '2025-10-18 04:30:45'),
(17, 21, 'PZ20251498', 1500.00, 'pending', NULL, '2025-11-02 12:30:00', '2025-10-22 15:31:39', '2025-10-22 15:31:39'),
(18, 25, 'PZ20259614', 3000.00, 'pending', NULL, '2025-11-02 12:30:00', '2025-10-22 22:57:45', '2025-10-22 22:57:45');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 1, 1, 1500.00, 1500.00),
(2, 1, 2, 1, 1500.00, 1500.00),
(3, 1, 3, 1, 1500.00, 1500.00),
(4, 2, 1, 1, 1500.00, 1500.00),
(5, 2, 2, 1, 1500.00, 1500.00),
(6, 2, 3, 1, 1500.00, 1500.00),
(7, 3, 1, 1, 1500.00, 1500.00),
(8, 3, 2, 1, 1500.00, 1500.00),
(9, 3, 3, 1, 1500.00, 1500.00),
(10, 4, 1, 3, 1500.00, 4500.00),
(11, 4, 2, 3, 1500.00, 4500.00),
(12, 4, 3, 4, 1500.00, 6000.00),
(13, 5, 1, 1, 1500.00, 1500.00),
(14, 5, 2, 1, 1500.00, 1500.00),
(15, 5, 3, 1, 1500.00, 1500.00),
(16, 6, 1, 2, 1500.00, 3000.00),
(17, 6, 2, 1, 1500.00, 1500.00),
(18, 6, 3, 1, 1500.00, 1500.00),
(19, 7, 1, 2, 1500.00, 3000.00),
(20, 8, 1, 1, 1500.00, 1500.00),
(21, 8, 3, 1, 1500.00, 1500.00),
(22, 9, 1, 1, 1500.00, 1500.00),
(23, 9, 2, 1, 1500.00, 1500.00),
(24, 10, 1, 1, 1500.00, 1500.00),
(25, 10, 2, 1, 1500.00, 1500.00),
(26, 11, 1, 1, 1500.00, 1500.00),
(27, 11, 2, 1, 1500.00, 1500.00),
(28, 11, 3, 1, 1500.00, 1500.00),
(29, 12, 1, 3, 1500.00, 4500.00),
(30, 12, 2, 1, 1500.00, 1500.00),
(31, 12, 3, 1, 1500.00, 1500.00),
(32, 13, 1, 1, 1500.00, 1500.00),
(33, 13, 2, 1, 1500.00, 1500.00),
(34, 13, 3, 1, 1500.00, 1500.00),
(35, 14, 1, 2, 1500.00, 3000.00),
(36, 14, 2, 1, 1500.00, 1500.00),
(37, 14, 3, 1, 1500.00, 1500.00),
(38, 15, 1, 1, 1500.00, 1500.00),
(39, 15, 2, 1, 1500.00, 1500.00),
(40, 15, 3, 1, 1500.00, 1500.00),
(41, 16, 1, 1, 1500.00, 1500.00),
(42, 16, 2, 1, 1500.00, 1500.00),
(43, 16, 3, 1, 1500.00, 1500.00),
(44, 17, 2, 1, 1500.00, 1500.00),
(45, 18, 1, 1, 1500.00, 1500.00),
(46, 18, 3, 1, 1500.00, 1500.00);

-- --------------------------------------------------------

--
-- Table structure for table `site_config`
--

CREATE TABLE `site_config` (
  `id` int(11) NOT NULL,
  `site_title` varchar(255) DEFAULT 'Fidel''s Pizza Event',
  `event_location` text,
  `event_date` date DEFAULT NULL,
  `registration_code` varchar(4) DEFAULT NULL,
  `landing_content` text,
  `landing_images` text,
  `menu_content` text,
  `admin_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `site_config`
--

INSERT INTO `site_config` (`id`, `site_title`, `event_location`, `event_date`, `registration_code`, `landing_content`, `landing_images`, `menu_content`, `admin_email`, `created_at`, `updated_at`) VALUES
(1, 'Fidel\'s Pizza Event', 'Pier English School', '2025-11-02', '1234', 'Welcome to our annual pizza event! Join us for delicious handmade Neapolitan pizzas.', NULL, 'このピザイベントの注文期間は終了しました。', 'fidelgmontoya@icloud.com', '2025-09-16 06:39:32', '2025-10-23 06:21:46');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_confirmed` tinyint(1) DEFAULT '0',
  `confirmation_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `is_confirmed`, `confirmation_token`, `created_at`, `updated_at`) VALUES
(1, 'testuser@example.com', '$2y$10$kcEY1SsiTVf3oN2ehLc/ne6gKnd6KkX9U2BkGIkQcPQEIPp/i.uNa', 'Adult', 'Class', '090-1234-5678', 1, NULL, '2025-09-16 09:36:15', '2025-09-29 12:38:54'),
(4, 'joe@himself.com', '$2y$10$tuiaGcgNx5TtKaxfijg/uOccw33VKn/aVkJ8xoEF7PpGtJLswK2.q', 'Kids', 'Class', '987-654-3210', 1, NULL, '2025-09-16 14:57:41', '2025-09-29 12:38:09'),
(7, 'f.montoya@gmail.com', '$2y$12$0tj538/.uRe0Hu7YF63LcO.QBOAGl85r819t3Ly6bC3cXgvHeZMCC', 'fideltest', 'Montoya', '0791425212', 1, NULL, '2025-09-17 07:41:07', '2025-09-17 07:41:20'),
(9, 'aakansaitours@gmail.com', '$2y$12$YEnL8lPIoKj7DESufoC6NuUNpVsXKUZUd7PBvfl6vGISxpg08aIhW', 'G', 'OSULLIVAN', '8109017197826', 1, NULL, '2025-10-06 13:07:57', '2025-10-06 13:08:25'),
(10, 'p.allan.moss@gmail.com', '$2y$12$VyUjhxXaEu1oO1eooL3Th.U5d1LIiYRXAtiypYlSgsHg2noZoejki', 'Allan', 'Moss', '08047641813', 1, NULL, '2025-10-06 14:57:41', '2025-10-06 14:58:15'),
(11, 'nao_141@i.softbank.jp', '$2y$12$S5R.8Xo28vFibKZoo1lrgOi.CT.WsiLHp29RSc9Dh/EhcubcFvdn6', 'Naoko', 'Ishii', '09039482721', 1, NULL, '2025-10-07 11:30:24', '2025-10-07 11:32:09'),
(12, 'yukina.2005.yukina@gmail.com', '$2y$12$mrz3jXBQ6u0u3kxS24WW/OUYQarwwxhKyPK27kC51kZOZ3JQ7GWgC', '大谷', '雪奈', '07044939375', 1, NULL, '2025-10-08 03:43:14', '2025-10-08 03:43:30'),
(13, 'sumisumi20050429@gmail.com', '$2y$12$QjnOeX1Y0kjhaf7Dly.ieubg72kKg0KJ5tNRTFqZyOspiPHMDxLPW', '岡', '澄蓮', '08061860913', 1, NULL, '2025-10-08 03:50:54', '2025-10-08 03:58:07'),
(14, 'mirusumi@icloud.com', '$2y$12$OCp2y3jHGGkfMF34jdVefuL2gH4mRDEpxhHHpACVJiV6Vv355.7y6', '尾﨑', '明依奈', '08014953058', 1, NULL, '2025-10-08 03:57:37', '2025-10-08 03:58:02'),
(15, '12607@himesekikan.ac.jo', '$2y$12$B10a6P7vfNGBlrGyr1kOHeNRnqTarzxDUs5OYFHaEEHHVtLsVRTcO', '岡', '澄蓮', '08061890913', 0, '0b9459a3b81138e532e66292c63efc9d', '2025-10-08 03:57:40', '2025-10-08 03:57:40'),
(16, '12607@himesekikan.ac.jp', '$2y$12$bIdHDOPRWHrpyS8VdaxZr.CpnpJJGwQiWkmPN44aozXHrzATEa9nK', '岡', '澄蓮', '08061860913', 1, '90b42b17ab471a31d6fa51174d952d2c', '2025-10-08 04:05:29', '2025-10-08 12:30:42'),
(17, '38.0118.saya.ky@gmail.com', '$2y$12$rxyBzzLtXleZN/Se6vzUiuhihBGOyNPM35uyXKSKxmkdZxgdMgPGm', '咲耶', '喜多村', '08095118103', 1, NULL, '2025-10-10 02:53:40', '2025-10-10 02:54:16'),
(18, 'yuna.4027@icloud.com', '$2y$12$itB3R60VjogWC7Rejd.vBueCsyM3ul36hP/syYtITbL/cVpoPszAK', 'ゆな', 'まえかわ', '09097736658', 1, NULL, '2025-10-10 03:26:56', '2025-10-10 03:27:27'),
(19, 'takaohimari@icloud.com', '$2y$12$vZIL5LdzsYyQGuGmCelghOv0XodsSuF4wkT3jKPLXPIbhD99UUkAS', '高尾', 'ひまり', '08014566733', 1, NULL, '2025-10-10 03:27:04', '2025-10-10 03:28:53'),
(20, 'harunaf146@i.softbank.jp', '$2y$12$gKRuT3W34IldWi3fin8pKuRkU75udkJSffrvSqWuF1oIgwjiVmFda', '藤川', '陽菜', '', 1, NULL, '2025-10-10 03:28:04', '2025-10-10 03:29:04'),
(21, 'tiaki20016800@gmail.com', '$2y$12$FNTk8aIHPFfbkDwaTje4qeyv5opwJRzjF7.eSKLnB/1bLsa0J85ti', '千晶', '黒田', '07022142001', 1, NULL, '2025-10-10 03:39:36', '2025-10-10 03:40:37'),
(22, 'pipirobo2@gmail.com', '$2y$12$7/iCl98bLrHKnmSgIPb68uA4qQaK4CwWIvkI153YvPBEMAalPSV2C', '清水', '菜々子', '', 1, NULL, '2025-10-10 04:04:09', '2025-10-10 04:04:29'),
(23, 'r2005722r@gmail.com', '$2y$12$fj7DjRaTcff.IrAqbjBD1OUGkaE1oLUH3/DV4TdvAv4TH1O13Flbq', '八木', '亮', '08058733754', 1, NULL, '2025-10-10 08:23:21', '2025-10-10 08:23:33'),
(24, 'english.man1981@gmail.com', '$2y$12$8osnkEZQweBa.n989/F3I.MNsNvL.wLWFnlfYRjiB5bUk3fBzNJcS', 'Jeff', 'Moyse', '07013461981', 1, NULL, '2025-10-17 09:57:29', '2025-10-17 09:57:44'),
(25, 'ayayasumisumi@gmail.com', '$2y$12$HAlG8LEk7Iyt0wegS7oo0.l51L/YO88vGAocZZwz43kspImR/1A3S', '岡', '澄蓮', '08061860913', 1, NULL, '2025-10-22 22:54:43', '2025-10-22 22:56:35');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `admin_id`, `expires_at`, `created_at`) VALUES
('00dc84eddd22f6a4c06e0b1bf0350cb5', 17, NULL, '2025-10-10 04:54:59', '2025-10-10 02:54:59'),
('0mtld0m2qq7qt7koj76u1kedl7', NULL, 1, '2025-09-16 11:27:14', '2025-09-16 17:27:14'),
('1641f5f97e030b03e3b0e271049fa43d', NULL, 2, '2025-10-18 10:20:57', '2025-10-18 09:20:57'),
('302340e285fd16a1417dbab41da49ab4', 21, NULL, '2025-10-23 01:50:04', '2025-10-22 23:50:04'),
('31d1471f96e313de766ef935cbabeeab', 21, NULL, '2025-10-22 17:30:49', '2025-10-22 15:30:49'),
('32ec12a2d41c5ab89127598c5be31b6d', 7, NULL, '2025-09-19 11:20:36', '2025-09-19 09:20:36'),
('3c41e68818260550ccf6e0920bb98145', 18, NULL, '2025-10-17 11:52:07', '2025-10-17 09:52:07'),
('4295a20bb3af97b714de6d2142251ffb', 11, NULL, '2025-10-09 22:26:33', '2025-10-09 20:26:33'),
('446cec36459b2306fc6a7e4ac875b87c', NULL, 1, '2025-10-30 06:08:00', '2025-10-30 05:08:00'),
('46ecd381208262ee358c4e4eae2c15e5', 21, NULL, '2025-10-10 05:40:47', '2025-10-10 03:40:47'),
('47e4dda074220ac2f38852c9b6c589b0', 10, NULL, '2025-10-06 16:59:06', '2025-10-06 14:59:06'),
('4c046ccec5f07f91aca8b79b878deeb4', 9, NULL, '2025-10-06 15:11:14', '2025-10-06 13:11:14'),
('59fd54c9b7f7af3881c95122a3720203', 25, NULL, '2025-10-23 00:56:43', '2025-10-22 22:56:43'),
('64d0595ff0915901610c93b47065112a', 7, NULL, '2025-09-18 15:10:14', '2025-09-18 13:10:14'),
('6b04396ae5ef66cb2a74dc3184f8dc2b', NULL, 1, '2025-10-26 14:42:28', '2025-10-26 13:42:28'),
('738cd95155e118aa0e12def0a3b6c35e', NULL, 1, '2025-10-17 10:55:42', '2025-10-17 09:55:42'),
('76b7ea962885e97a62dcb42d15993bf8', 21, NULL, '2025-10-20 04:12:42', '2025-10-20 02:12:42'),
('7a342a87e4bbae9eca0d5816415325c5', 18, NULL, '2025-10-10 05:27:33', '2025-10-10 03:27:33'),
('8448a87d22ef38bddb41c2a9796f2c0d', 7, NULL, '2025-09-28 06:48:50', '2025-09-28 04:48:50'),
('8a70ea9d0b8f591ae83ac88221f213ea', 20, NULL, '2025-10-13 12:23:05', '2025-10-13 10:23:05'),
('9224a9a81e423086a406ffce8023ef41', NULL, 1, '2025-10-25 18:47:23', '2025-10-25 17:47:23'),
('95005d4448f68839ca59b2896fae2fba', 21, NULL, '2025-10-14 15:14:30', '2025-10-14 13:14:30'),
('9b092ae9c0b0425fab8716fdfd54e798', 22, NULL, '2025-10-15 10:26:46', '2025-10-15 08:26:46'),
('9d3308878f1901cb933b077ab8164fb3', 19, NULL, '2025-10-10 05:29:08', '2025-10-10 03:29:08'),
('a5e6590a198d6bdc8bcefcadbee18be7', 7, NULL, '2025-11-06 04:07:15', '2025-11-06 02:07:15'),
('abcde1aa1636332e0605f2f134f3ae24', 21, NULL, '2025-10-10 06:02:18', '2025-10-10 04:02:18'),
('aeae40f900733e8b56194e7d3857c004', 14, NULL, '2025-10-23 06:23:58', '2025-10-23 04:23:58'),
('b5c178e80f35ceaf386bea873573e832', 21, NULL, '2025-10-26 17:02:14', '2025-10-26 15:02:14'),
('c29e8e2c2b4ce776d8c47884ee691141', NULL, 1, '2025-10-10 16:54:24', '2025-10-10 15:54:24'),
('c5169c26b7c221ee2fe3e5ec05a9cc34', 20, NULL, '2025-10-10 05:29:33', '2025-10-10 03:29:33'),
('c6a34fef6bd17448b84a60d164cb4424', NULL, 1, '2025-09-28 16:02:13', '2025-09-28 15:02:13'),
('c95e1217d3968a7286c22ec7abfa068e', 24, NULL, '2025-10-17 11:58:12', '2025-10-17 09:58:12'),
('ca5c107697b262918bf7c18d1575ae1f', 11, NULL, '2025-10-07 13:32:37', '2025-10-07 11:32:37'),
('cfce5b9576a6cc1351a985131044ab11', NULL, 1, '2025-10-23 10:12:18', '2025-10-23 09:12:18'),
('d80b84d54d06869fc97e3ae613106d1b', NULL, 1, '2025-10-29 15:17:09', '2025-10-29 08:43:05'),
('db96341905185b7e8333bee6f43849db', 17, NULL, '2025-10-10 05:28:14', '2025-10-10 03:28:14'),
('e549d7e9759886628184a768b3ece0da', 22, NULL, '2025-10-10 06:04:40', '2025-10-10 04:04:40'),
('e71950b62d08e0952d71e34668973464', 21, NULL, '2025-10-13 16:58:56', '2025-10-13 14:58:56'),
('ed256f1e5244bbb1dbf7eb79c5c87649', 23, NULL, '2025-10-10 10:23:45', '2025-10-10 08:23:45'),
('f54118ddc17b469de3fb9e7a53ebfbf6', NULL, 1, '2025-09-17 15:00:28', '2025-09-17 14:00:28'),
('f6997b581892bbf48082b51a567413d8', 12, NULL, '2025-10-08 05:43:40', '2025-10-08 03:43:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_name` (`template_name`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `site_config`
--
ALTER TABLE `site_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `site_config`
--
ALTER TABLE `site_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_sessions_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
