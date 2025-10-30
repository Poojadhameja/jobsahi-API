CREATE TABLE `interview_panel` (
  `id` int(10) UNSIGNED NOT NULL,
  `interview_id` int(10) UNSIGNED NOT NULL,
  `panelist_name` varchar(100) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `created_at` datetime DEFAULT current_timestamp(),
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
