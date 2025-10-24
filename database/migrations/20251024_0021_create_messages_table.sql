CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_role` enum('student','recruiter','institute','admin') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `receiver_role` enum('student','recruiter','institute','admin') NOT NULL,
  `message` text DEFAULT NULL,
  `attachment_url` varchar(255) DEFAULT NULL,
  `attachment_type` enum('image','pdf','doc','other') DEFAULT NULL,
  `type` enum('text','file','system') DEFAULT 'text',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
