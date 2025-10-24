CREATE TABLE `referrals` (
  `id` int(10) UNSIGNED NOT NULL,
  `referrer_id` int(10) UNSIGNED NOT NULL,
  `referee_email` varchar(100) DEFAULT NULL,
  `job_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','applied','hired') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
