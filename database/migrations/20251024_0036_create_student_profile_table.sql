CREATE TABLE `student_profile` (
`id` int(10) unsigned
,`user_id` int(10) unsigned
,`skills` text
,`education` text
,`resume` varchar(255)
,`certificates` text
,`portfolio_link` varchar(255)
,`linkedin_url` varchar(255)
,`dob` date
,`gender` enum('male','female','other','prefer_not_to_say')
,`job_type` enum('full_time','part_time','internship','contract')
,`trade` varchar(100)
,`location` varchar(255)
,`created_at` datetime
,`modified_at` datetime
,`deleted_at` datetime
,`admin_action` enum('pending','approved','rejected')
);

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `skills` text NOT NULL,
  `education` text NOT NULL,
  `resume` varchar(255) NOT NULL,
  `certificates` text DEFAULT NULL,
  `portfolio_link` varchar(255) NOT NULL,
  `linkedin_url` varchar(255) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('male','female','other','prefer_not_to_say') DEFAULT NULL,
  `job_type` enum('full_time','part_time','internship','contract') DEFAULT NULL,
  `trade` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `modified_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `admin_action` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `bio` text DEFAULT NULL,
  `experience` text DEFAULT NULL,
  `projects` varchar(255) DEFAULT NULL,
  `languages` varchar(255) DEFAULT NULL,
  `aadhar_number` varchar(20) DEFAULT NULL,
  `graduation_year` int(11) DEFAULT NULL,
  `cgpa` decimal(3,2) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_nopad_ci;
