CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','recruiter','institute','admin') NOT NULL DEFAULT 'student',
  `phone_number` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'true, false',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `last_activity` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
