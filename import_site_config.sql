-- Table structure for table `site_config`

CREATE TABLE IF NOT EXISTS `site_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_title` varchar(255) DEFAULT 'Fidel''s Pizza Event',
  `event_location` text,
  `event_date` date DEFAULT NULL,
  `registration_code` varchar(4) DEFAULT NULL,
  `landing_content` text,
  `landing_images` text,
  `menu_content` text,
  `admin_email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dumping data for table `site_config`

INSERT INTO `site_config` (`id`, `site_title`, `event_location`, `event_date`, `registration_code`, `landing_content`, `landing_images`, `menu_content`, `admin_email`, `created_at`, `updated_at`) VALUES
(1, 'Fidel\'s Pizza Event', 'Pier English School', '2025-11-02', '1234', 'Welcome to our annual pizza event! Join us for delicious handmade Neapolitan pizzas.', NULL, 'このピザイベントの注文期間は終了しました。', 'fidelgmontoya@icloud.com', '2025-09-16 06:39:32', '2025-10-23 06:21:46');
