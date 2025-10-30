CREATE TABLE `skill_tests` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `test_platform` enum('Zoom','Mettl') DEFAULT NULL,
  `test_name` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `max_score` int(11) DEFAULT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `badge_awarded` tinyint(1) DEFAULT 0,
  `passed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
