CREATE TABLE `recruiter_company_info` (
  `id` int(11) UNSIGNED NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `recruiter_id` int(10) UNSIGNED NOT NULL,
  `person_name` varchar(255) NOT NULL COMMENT 'Contact person full name',
  `phone` varchar(15) NOT NULL COMMENT '10-digit mobile number',
  `additional_contact` varchar(255) DEFAULT NULL COMMENT 'Email address or alternate contact',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
