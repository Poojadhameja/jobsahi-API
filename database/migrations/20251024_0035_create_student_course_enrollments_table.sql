CREATE TABLE `student_course_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `enrollment_date` datetime NOT NULL,
  `status` enum('enrolled','completed','dropped') NOT NULL DEFAULT 'enrolled',
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
