CREATE TABLE `applications` (
  `id` int(10) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED DEFAULT NULL,
  `interview_id` int(10) UNSIGNED DEFAULT NULL,
  `job_selected` tinyint(1) DEFAULT 0,
  `student_id` int(10) UNSIGNED NOT NULL,
  `status` enum('applied','shortlisted','rejected','selected') NOT NULL DEFAULT 'applied',
  `applied_at` datetime NOT NULL,
  `resume_link` varchar(255) NOT NULL,
  `cover_letter` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
