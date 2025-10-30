CREATE TABLE `interviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `application_id` int(10) UNSIGNED NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `mode` enum('online','offline') NOT NULL DEFAULT 'online',
  `location` text NOT NULL,
  `status` enum('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `feedback` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
