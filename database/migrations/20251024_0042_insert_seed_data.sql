INSERT INTO `applications` (`id`, `job_id`, `interview_id`, `job_selected`, `student_id`, `status`, `applied_at`, `resume_link`, `cover_letter`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 1, NULL, 0, 1, 'shortlisted', '2024-08-10 10:00:00', '/uploads/resumes/rahul_kumar_resume.pdf', 'I am very interested in this position and believe my Java and Spring Boot experience makes me a great fit.', '2024-08-10 10:00:00', '2025-09-02 15:57:10', NULL, 'pending'),
(2, 2, NULL, 0, 4, 'shortlisted', '2024-08-11 11:00:00', '/uploads/resumes/kavya_patel_resume.pdf', 'I am passionate about frontend development and have strong React skills.', '2024-08-11 11:00:00', '2025-09-01 16:21:34', NULL, 'pending'),
(4, 4, NULL, 0, 3, '', '2024-08-12 13:00:00', '/uploads/resumes/amit_sharma_resume.pdf', 'My experience with AWS and Docker aligns perfectly with your DevOps requirements.', '2024-08-12 13:00:00', '2025-09-03 22:54:52', NULL, 'pending'),
(5, 5, NULL, 0, 5, 'shortlisted', '2024-08-09 14:00:00', '/uploads/resumes/arjun_reddy_resume.pdf', 'I have strong Android development skills and experience with Kotlin.', '2024-08-09 14:00:00', '2025-08-26 13:35:24', NULL, 'pending'),
(6, 6, NULL, 0, 6, 'applied', '2024-08-13 15:00:00', '/uploads/resumes/neha_gupta_resume.pdf', 'My MBA in Marketing and digital marketing experience make me ideal for this position.', '2024-08-13 15:00:00', '2025-08-26 13:35:24', NULL, 'pending'),
(7, 7, NULL, 0, 1, 'applied', '2024-08-14 16:00:00', '/uploads/resumes/rahul_kumar_resume.pdf', 'Looking forward to gaining industry experience through this internship.', '2024-08-14 16:00:00', '2025-08-26 13:35:24', NULL, 'pending'),
(8, 7, NULL, 0, 3, 'applied', '2024-08-14 17:00:00', '/uploads/resumes/amit_sharma_resume.pdf', 'This internship aligns perfectly with my career goals.', '2024-08-14 17:00:00', '2025-08-26 13:35:24', NULL, 'pending'),
(9, 6, NULL, 0, 4, 'rejected', '2025-08-27 16:56:09', '/uploads/resumes/rahul_kumar_resume.pdf', 'Looking forward to gaining industry experience.', '2025-08-27 16:56:09', '2025-08-31 19:57:30', NULL, 'pending'),
(10, 4, NULL, 0, 4, 'shortlisted', '2025-08-27 16:56:18', '/uploads/resumes/rahul_kumar_resume.pdf', 'Looking forward to gaining industry experience.', '2025-08-27 16:56:18', '2025-08-31 19:57:38', NULL, 'pending'),
(12, 3, NULL, 0, 3, 'selected', '2025-08-27 16:56:51', '/uploads/resumes/rahul_kumar_resume.pdf', 'Looking forward to gaining industry experience.', '2025-08-27 16:56:51', '2025-08-31 19:57:46', NULL, 'pending'),
(13, 3, NULL, 0, 5, 'applied', '2025-08-27 17:06:14', '/uploads/resumes/rahul_kumar_resume.pdf', 'Looking forward to gaining industry experience.', '2025-08-27 17:06:14', '2025-08-31 19:57:55', NULL, 'pending'),
(20, 7, NULL, 0, 5, 'rejected', '2025-08-31 19:55:31', 'http://example.com/resume.pdf', 'I am really interested in this role.', '2025-08-31 19:55:31', '2025-08-31 19:58:02', NULL, 'pending'),
(21, 6, NULL, 0, 5, 'applied', '2025-08-31 19:56:58', 'http://example.com/resume.pdf', 'I am really interested in this role.', '2025-08-31 19:56:58', '2025-08-31 19:57:22', NULL, 'pending'),
(22, 3, NULL, 0, 6, '', '2025-09-04 09:49:26', 'https://example.com/my_resume.pdf', 'I am very interested in this position because...', '2025-09-04 09:49:26', '2025-09-04 09:49:26', NULL, 'pending'),
(23, 4, NULL, 0, 6, '', '2025-09-04 10:08:04', 'https://example.com/my_resume.pdf', 'I am very interested in this position because...', '2025-09-04 10:08:04', '2025-09-04 10:08:04', NULL, 'pending'),
(24, 4, NULL, 0, 5, '', '2025-09-04 10:08:30', 'https://example.com/my_resume.pdf', 'I am very interested in this position because...', '2025-09-04 10:08:30', '2025-09-04 10:08:30', NULL, 'pending'),
(25, 9, NULL, 0, 6, '', '2025-09-04 10:09:43', 'https://example.com/my_resume.pdf', 'I am very interested in this position because...', '2025-09-04 10:09:43', '2025-09-04 10:09:43', NULL, 'pending');
INSERT INTO `batches` (`id`, `course_id`, `name`, `batch_time_slot`, `start_date`, `end_date`, `media`, `instructor_id`, `admin_action`) VALUES
(1, 1, 'FSWD-Jan-2024 (Final Approved)', '10:00 AM - 12:00 AM', '2024-01-15', '2024-07-15', '[\"uploads\\/batches\\/syllabus.pdf\"]', 5, ''),
(2, 1, 'FSWD-Mar-2024 (Final Approved)', '11:00 AM - 1:00 AM', '2024-03-01', '2024-09-01', '[\"uploads\\/batches\\/syllabus1.pdf\"]', 20, ''),
(3, 2, 'DS-Feb-2024', NULL, '2024-02-01', '2024-06-01', NULL, NULL, 'pending'),
(4, 3, 'Java-Jan-2024', NULL, '2024-01-20', '2024-06-20', NULL, NULL, 'pending'),
(5, 3, 'DS-Feb-2024-Updated', NULL, '2024-02-05', '2024-08-05', NULL, 10, 'approved'),
(6, 4, 'RN-Feb-2024-Final', NULL, '2024-02-15', '2024-08-15', NULL, 20, 'pending'),
(8, 5, 'RN-Feb-2024-Final', NULL, '2024-02-15', '2024-08-15', NULL, 20, 'pending'),
(9, 2, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'pending'),
(10, 2, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'pending'),
(11, 2, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'pending'),
(12, 2, 'FSWD-Mar-2024 (Final Approved)', '09:00 AM - 11:00 AM', '2024-03-01', '2024-09-01', '[\"uploads\\/batches\\/syllabus.pdf\"]', 5, 'approved'),
(13, 2, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'pending'),
(14, 10, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'pending'),
(15, 10, 'FSWD-Mar-2024', NULL, '2024-03-01', '2024-09-01', NULL, NULL, 'approved'),
(16, 2, 'Full Stack Web Development - Updated', '02:00 PM - 04:00 PM', '2025-11-10', '2026-05-10', '[\"uploads\\/batches\\/updated.pdf\"]', 5, 'approved'),
(17, 1, 'Full Stack Web Development Batch - Nov 2025', '10:00 AM - 12:00 PM', '2025-11-10', '2026-05-10', '[\"uploads\\/batches\\/syllabus.pdf\",\"uploads\\/batches\\/schedule.png\"]', 5, 'approved');
INSERT INTO `blacklisted_tokens` (`id`, `token_hash`, `user_id`, `blacklisted_at`, `expires_at`) VALUES
(1, '3745088275104f752422493a8801f6edf2ea102cec646528b70cd414bba06246', 51, '2025-10-05 14:38:04', '2025-10-05 09:46:24'),
(6, 'becb96056263492efede625eb105d0d09824e452a64a4f0d3bd8f4042db50331', 51, '2025-10-14 05:11:58', '2025-10-14 00:39:56'),
(7, '4c754d9782b0868ac3aafecae3e71b4b957680b5a7ab1931f0a88bb5ee44e5ad', 51, '2025-10-15 07:35:48', '2025-10-15 02:59:54'),
(8, '503e17f8270515c23836309b3a1ce1202ee2d7838c8fec4ffa1df10e13123770', 51, '2025-10-15 10:05:03', '2026-10-15 04:34:08'),
(9, '38f64a7c788caa322a636b25b7ee4cf673302cad4fa36449ae3ee17c13d7c2fd', 51, '2025-10-15 10:05:23', '2026-10-15 04:35:13'),
(10, '2feebf6dcf3012cc91514e98893ffae581545adcb2b6abec7adfd4d8650bebaf', 51, '2025-10-15 10:22:40', '2026-10-15 04:51:48'),
(11, '97caa1e5ba5d1b4d3a714e09f68b636accaee93274c79906dc5a14a8592d219a', 51, '2025-10-17 09:32:28', NULL);
INSERT INTO `certificates` (`id`, `student_id`, `course_id`, `file_url`, `issue_date`, `admin_action`, `created_at`, `modified_at`) VALUES
(1, 1, 1, '/uploads/certificates/rahul_fswd_cert.pdf', '2024-07-20', 'pending', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(3, 3, 3, '/uploads/certificates/amit_java_cert.pdf', '2024-06-25', 'pending', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(4, 5, 5, '/uploads/certificates/arjun_rn_cert.pdf', '2024-06-15', 'pending', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(6, 1, 2, 'http://example.com/certificates/certificate1.pdf', '2025-09-04', 'approved', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(7, 3, 2, 'http://example.com/certificates/certificate1.pdf', '2025-09-04', 'approved', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(9, 7, 10, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(10, 7, 2, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2025-09-26 14:55:17', '2025-09-26 14:55:17'),
(11, 7, 3, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2025-09-26 15:18:36', '2025-09-26 15:18:36'),
(12, 7, 5, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2025-09-26 15:19:57', '2025-09-26 15:19:57'),
(13, 7, 9, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2025-09-26 15:40:57', '2025-09-26 15:40:57'),
(14, 6, 10, 'http://example.com/certificates/FSWD.pdf', '2025-02-04', 'approved', '2024-09-01 00:00:00', '2025-09-26 12:25:02');
INSERT INTO `certificate_templates` (`id`, `institute_id`, `template_name`, `logo_url`, `seal_url`, `signature_url`, `header_text`, `footer_text`, `background_image_url`, `is_active`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 1, 'Tech Institute Delhi Certificate', '/uploads/templates/tid_logo.png', '/uploads/templates/tid_seal.png', '/uploads/templates/director_signature.png', 'TECH INSTITUTE DELHI', 'Authorized by Tech Institute Delhi', '/uploads/templates/cert_bg_1.jpg', 1, '2025-08-26 13:32:59', '2025-08-26 13:32:59', NULL, 'pending'),
(2, 2, 'NIIT Mumbai Certificate', '/uploads/templates/niit_logo.png', '/uploads/templates/niit_seal.png', '/uploads/templates/niit_signature.png', 'NIIT MUMBAI', 'Certified by NIIT Mumbai', '/uploads/templates/cert_bg_2.jpg', 1, '2025-08-26 13:32:59', '2025-08-26 13:32:59', NULL, 'pending'),
(3, 3, 'Code Academy Bangalore Certificate', '/uploads/templates/cab_logo.png', '/uploads/templates/cab_seal.png', '/uploads/templates/cab_signature.png', 'CODE ACADEMY BANGALORE', 'Certified by Code Academy Bangalore', '/uploads/templates/cert_bg_3.jpg', 1, '2025-08-26 13:32:59', '2025-09-26 16:30:55', NULL, 'approved'),
(4, 1, 'Certificate of Excellence', 'https://example.com/logo.png', 'https://example.com/seal.png', 'https://example.com/signature.png', 'This certificate is awarded to', 'Congratulations!', 'https://example.com/bg.png', 1, '2025-09-04 17:18:55', '2025-09-04 17:18:55', NULL, 'approved'),
(5, 1, 'Certificate of Excellence', 'https://example.com/logo.png', 'https://example.com/seal.png', 'https://example.com/signature.png', 'This certificate is awarded to', 'Congratulations!', 'https://example.com/bg.png', 1, '2025-09-04 17:19:13', '2025-09-04 17:19:13', NULL, 'approved'),
(6, 4, 'Default Certificate', 'http://example.com/logo.png', 'http://example.com/seal.png', 'http://example.com/signature.png', 'Certificate of Excellence', 'Issued by ABC Institute', 'http://example.com/background.png', 1, '2025-09-26 16:22:27', '2025-09-26 16:22:27', NULL, 'approved');
INSERT INTO `courses` (`id`, `institute_id`, `title`, `description`, `duration`, `category_id`, `tagged_skills`, `batch_limit`, `status`, `instructor_name`, `mode`, `certification_allowed`, `module_title`, `module_description`, `media`, `created_at`, `updated_at`, `fee`, `admin_action`) VALUES
(1, 1, 'Java Enterprise Development', 'Updated advanced Java programming with Spring Boot', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 15:37:30', 42000.00, 'approved'),
(2, 1, 'Data Science with Python', 'Comprehensive data science course including Python, pandas, scikit-learn, and machine learning', '4 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 15:37:30', 35000.00, 'approved'),
(3, 0, 'Java Enterprise Development', 'Updated advanced Java programming with Spring Boot', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 42000.00, 'approved'),
(4, 0, 'Cloud Computing with AWS', 'Learn AWS services, deployment, and cloud architecture', '3 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 30000.00, 'approved'),
(5, 0, 'React Native Mobile Development', 'Build cross-platform mobile apps using React Native', '4 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 38000.00, 'pending'),
(6, 0, 'DevOps Engineering', 'Complete DevOps pipeline including Docker, Kubernetes, and CI/CD', '5 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 42000.00, 'pending'),
(7, 0, 'Full Stack Development', 'Learn React, Node, and PHP', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 12000.00, 'pending'),
(9, 0, 'Full Stack Development', 'Learn React, Node, and PHP', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 12000.00, 'pending'),
(10, 0, 'Full Stack Development', 'Learn React, Node, and PHP', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 12000.00, 'approved'),
(11, 0, 'Full Stack Development Using Java', 'Learn React, Node, and Java', '6 months', NULL, NULL, NULL, 'active', NULL, 'offline', 0, NULL, NULL, NULL, '2025-10-22 15:37:30', '2025-10-22 18:44:01', 12000.00, 'approved'),
(13, 0, 'Full Stack Web Development', 'Learn frontend and backend development using React and Spring Boot.', '6', 3, '0', 30, 'active', 'John Doe', 'offline', 1, 'Introduction to Web Development', 'Basics of HTML, CSS, and JavaScript.', 'https://example.com/course-banner.jpg', '2025-10-22 18:02:58', '2025-10-22 18:44:01', 4999.99, 'pending');
INSERT INTO `course_category` (`id`, `category_name`, `created_at`) VALUES
(1, 'Technical', '2025-10-22 12:29:50'),
(2, 'Non-Technical', '2025-10-22 12:29:50'),
(3, 'Vocational', '2025-10-22 12:29:50'),
(4, 'Professional', '2025-10-22 12:29:50');
INSERT INTO `course_feedback` (`id`, `student_id`, `course_id`, `rating`, `feedback`, `created_at`, `admin_action`) VALUES
(1, 1, 1, 5, 'Excellent course! The instructors were very knowledgeable and the hands-on projects were really helpful.', '2024-07-20 16:00:00', 'approved'),
(3, 3, 3, 5, 'Outstanding Java course. The Spring Boot module was particularly useful for my career.', '2024-06-25 18:00:00', 'pending'),
(4, 5, 5, 4, 'Good course for mobile development. The React Native concepts were well explained.', '2024-06-15 19:00:00', 'pending'),
(7, 1, 2, 5, 'Excellent course! Very useful.', '2025-08-29 13:20:48', 'pending'),
(9, 5, 2, 5, 'Excellent course! Very useful.', '2025-08-29 13:42:57', 'pending'),
(10, 6, 2, 5, 'This course was excellent!', '2025-09-01 00:04:08', 'pending'),
(11, 1, 1, 5, 'This course was very helpful!', '2025-09-03 17:25:53', 'pending'),
(12, 1, 1, 5, 'This course was very helpful!', '2025-09-03 17:26:57', 'pending'),
(14, 3, 1, 5, 'This course was very helpful!', '2025-09-03 17:27:59', 'pending'),
(15, 3, 1, 5, 'This course was very helpful!', '2025-09-03 17:28:30', 'pending'),
(16, 7, 5, 5, 'Excellent course! Very informative and well-structured.', '2025-09-29 12:30:40', 'approved'),
(17, 7, 10, 5, 'Excellent course! Very informative and well-structured.', '2025-09-29 12:32:31', 'approved');
INSERT INTO `course_payments` (`id`, `student_id`, `course_id`, `enrollment_id`, `amount`, `currency`, `status`, `method`, `transaction_ref`, `gateway_response_json`, `paid_at`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 1, 1, 1, 45000.00, 'INR', 'paid', 'UPI', 'TXN_FSW_001', NULL, '2024-01-15 10:30:00', '2024-01-15 10:00:00', '2025-08-26 13:32:09', NULL, 'pending'),
(3, 3, 3, 3, 40000.00, 'INR', 'paid', 'netbanking', 'TXN_JAVA_003', NULL, '2024-01-20 12:30:00', '2024-01-20 12:00:00', '2025-08-26 13:32:09', NULL, 'pending'),
(4, 4, 1, 4, 45000.00, 'INR', 'paid', 'UPI', 'TXN_FSW_004', NULL, '2024-03-01 13:30:00', '2024-03-01 13:00:00', '2025-08-26 13:32:09', NULL, 'pending'),
(5, 5, 5, 5, 38000.00, 'INR', 'paid', 'card', 'TXN_RN_005', NULL, '2024-02-10 14:30:00', '2024-02-10 14:00:00', '2025-08-26 13:32:09', NULL, 'pending'),
(6, 6, 2, 6, 35000.00, 'INR', 'pending', 'UPI', 'TXN_DS_006', NULL, '2024-04-24 13:52:58', '2024-02-01 15:00:00', '2025-08-29 13:53:23', NULL, 'pending');
INSERT INTO `faculty_users` (`id`, `institute_id`, `name`, `email`, `phone`, `password`, `role`, `admin_action`) VALUES
(1, 1, 'Dr. Rajesh Kumar', 'rajesh.kumar@techinstituteDelhi.com', '9876543221', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'admin', 'approved'),
(2, 1, 'Sunita Sharma', 'sunita.sharma@techinstituteDelhi.com', '9876543222', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'faculty', 'approved'),
(3, 2, 'Prof. Vikram Singh', 'vikram.singh@niitmumbai.com', '9876543223', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'admin', 'approved'),
(4, 2, 'Anjali Verma', 'anjali.verma@niitmumbai.com', '9876543224', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'faculty', 'pending'),
(5, 3, 'Ravi Krishnan', 'ravi.krishnan@codeacademybangalore.com', '9876543225', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'admin', 'pending'),
(6, 3, 'Meera Nair', 'meera.nair@codeacademybangalore.com', '9876543226', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'faculty', 'pending');
INSERT INTO `institute_profiles` (`id`, `user_id`, `institute_name`, `institute_type`, `website`, `description`, `address`, `city`, `state`, `country`, `postal_code`, `contact_person`, `contact_designation`, `accreditation`, `established_year`, `location`, `courses_offered`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 13, 'Tech Institute of Delhi', 'Private University', 'https://www.techinstitutedelhi.edu', 'Leading institute specializing in technology and software development education.', '123 Connaught Place, Block A', 'Delhi', 'Delhi', 'India', '110001', 'Dr. Rajesh Kumar', 'Director of Admissions', 'NAAC A+ Accredited, AICTE Approved', 2010, 'Delhi, India', 'Full Stack Development, Data Science, Machine Learning, Python Programming', '2024-01-15 09:00:00', '2025-09-30 00:59:15', NULL, 'approved'),
(2, 14, 'Mumbai Institute of Technology', 'Deemed University', 'https://www.mitbombay.edu', 'Premier institute for Java development and Spring Boot training.', '456 Andheri West, Main Road', 'Mumbai', 'Maharashtra', 'India', '400058', 'Prof. Priya Sharma', 'Head of Department', 'UGC Approved, NAAC A Accredited', 2008, 'Mumbai, India', 'Java Development, Spring Boot, Microservices, Cloud Computing', '2024-01-20 10:00:00', '2025-09-30 00:59:15', NULL, 'approved'),
(3, 15, 'Bangalore Tech Academy', 'Private College', 'https://www.bangaloretech.edu', 'Specialized training in Full Stack and Mobile App Development.', '789 MG Road, Sector 5', 'Bangalore', 'Karnataka', 'India', '560001', 'Mr. Arun Patel', 'Academic Coordinator', 'AICTE Approved, ISO 9001 Certified', 2012, 'Bangalore, India', 'React Development, Node.js, Mobile App Development, DevOps', '2024-01-25 11:00:00', '2025-09-30 00:59:15', NULL, 'approved'),
(4, 50, 'Mumbai Coding Institute', 'Training Center', 'https://www.mumbaicodingacademy.com', 'Professional training center for Java, PHP, and React development.', '321 Bandra East, Commercial Complex', 'Mumbai', 'Maharashtra', 'India', '400051', 'Ms. Neha Reddy', 'Training Manager', 'NSDC Affiliated, Skill India Partner', 2015, 'Mumbai', 'Java, PHP, React', '2025-09-25 15:30:31', '2025-09-30 00:59:15', NULL, 'approved');
INSERT INTO `interviews` (`id`, `application_id`, `scheduled_at`, `mode`, `location`, `status`, `feedback`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 1, '2024-08-20 14:00:00', 'online', 'Google Meet Link: meet.google.com/xyz-abc-def', 'scheduled', '', '2024-08-15 10:00:00', '2025-08-26 13:35:52', NULL, 'pending'),
(3, 5, '2024-08-22 10:00:00', 'offline', 'TechCorp Solutions Office, Bangalore', 'scheduled', '', '2024-08-16 12:00:00', '2025-08-26 13:35:52', NULL, 'pending'),
(4, 4, '2025-09-10 10:00:00', 'online', 'Zoom', 'scheduled', 'Candidate is promising', '2025-09-01 16:27:25', '2025-09-01 17:20:20', NULL, 'pending'),
(5, 4, '2025-09-05 10:00:00', 'online', 'Zoom', 'scheduled', '', '2025-09-01 16:27:40', '2025-09-01 16:27:40', NULL, 'pending'),
(6, 4, '2025-09-05 10:00:00', 'online', 'Zoom', 'scheduled', '', '2025-09-03 22:54:52', '2025-09-03 22:54:52', NULL, 'pending'),
(7, 4, '2025-09-05 10:00:00', 'online', 'Zoom', 'scheduled', '', '2025-09-03 23:00:13', '2025-09-03 23:00:13', NULL, 'pending');
INSERT INTO `interview_panel` (`id`, `interview_id`, `panelist_name`, `feedback`, `rating`, `created_at`, `admin_action`) VALUES
(4, 1, '5', 'Candidate has strong technical knowledge', 4, '2025-09-03 16:35:51', 'pending'),
(5, 1, '6', 'Candidate has strong technical knowledge', 4, '2025-09-01 22:19:26', 'pending');
INSERT INTO `jobs` (`id`, `recruiter_id`, `company_info_id`, `category_id`, `title`, `description`, `location`, `skills_required`, `salary_min`, `salary_max`, `job_type`, `experience_required`, `application_deadline`, `is_remote`, `no_of_vacancies`, `status`, `created_at`, `updated_at`, `admin_action`) VALUES
(1, 1, NULL, NULL, 'Senior Software Engineer', 'We are looking for a senior software engineer with expertise in backend development and system design.', 'Noida, India', 'Java, Spring Boot, Microservices, AWS, Docker', 800000.00, 999999.99, 'full_time', '3-5 years', '2025-09-30 23:59:59', 0, 2, '', '2024-08-01 10:00:00', NULL, 'pending'),
(2, 2, NULL, NULL, 'Frontend Developer', 'Join our team as a frontend developer to build amazing user experiences.', 'Bangalore, India', 'React, JavaScript, TypeScript, CSS, HTML', 600000.00, 900000.00, 'full_time', '2-4 years', '2025-09-15 23:59:59', 0, 3, '', '2024-08-02 11:00:00', NULL, 'pending'),
(3, 3, NULL, NULL, 'Data Scientist', 'Looking for a data scientist to work on machine learning models and analytics.', 'Hyderabad, India', 'Python, Machine Learning, TensorFlow, SQL, Statistics', 900000.00, 999999.99, 'full_time', '2-5 years', '2025-10-15 23:59:59', 0, 1, 'open', '2024-08-03 12:00:00', NULL, 'pending'),
(4, 1, NULL, NULL, 'DevOps Engineer', 'DevOps engineer position for managing CI/CD pipelines and infrastructure.', 'Noida, India', 'AWS, Docker, Kubernetes, Jenkins, Linux', 700000.00, 999999.99, 'full_time', '2-4 years', '2025-09-20 23:59:59', 0, 2, 'open', '2024-08-04 13:00:00', NULL, 'pending'),
(5, 2, NULL, NULL, 'Senior PHP Developer', 'Work on backend APIs and microservices', 'Remote', 'PHP, MySQL, JWT, REST API', 50000.00, 90000.00, '', '3+ years', '2025-09-30 00:00:00', 1, 2, 'closed', '2024-08-05 14:00:00', NULL, 'pending'),
(6, 3, NULL, NULL, 'Digital Marketing Specialist', 'Digital marketing role focusing on SEO, SEM, and social media marketing.', 'Hyderabad, India', 'Digital Marketing, SEO, Google Ads, Social Media, Analytics', 400000.00, 700000.00, 'full_time', '1-3 years', '2025-10-01 23:59:59', 1, 1, 'open', '2024-08-06 15:00:00', NULL, 'pending'),
(7, 1, NULL, NULL, 'Software Development Intern', 'Internship opportunity for computer science students.', 'Noida, India', 'Java, Python, Web Development, Database', 15000.00, 25000.00, 'internship', '0-1 years', '2025-08-31 23:59:59', 1, 5, 'open', '2024-08-07 16:00:00', NULL, 'pending'),
(9, NULL, NULL, NULL, 'Frontend Developer', 'React developer needed', 'Remote', 'React, JS, CSS, HTML', 50000.00, 80000.00, '', '2+ years', '2025-12-31 00:00:00', 1, 2, 'open', '2025-09-01 13:21:17', NULL, 'pending'),
(11, NULL, NULL, NULL, 'Frontend Developer', 'React developer needed', 'Remote', 'React, JS, CSS, HTML', 50000.00, 80000.00, '', '2+ years', '2025-12-31 00:00:00', 1, 2, 'open', '2025-09-01 13:23:27', NULL, 'pending'),
(12, NULL, NULL, NULL, 'Frontend Developer', 'React developer needed', 'Remote', 'React, JS, CSS, HTML', 50000.00, 80000.00, '', '2+ years', '2025-12-31 00:00:00', 1, 2, 'open', '2025-09-01 13:24:04', NULL, 'approved'),
(13, NULL, NULL, NULL, 'Full Stack Developer', 'We are looking for a skilled developer.', 'Bangalore', 'PHP, MySQL, React, Node.js', 50000.00, 80000.00, '', '2-4 years', '2025-12-31 00:00:00', 1, 3, 'open', '2025-09-25 18:27:55', NULL, 'approved'),
(15, 4, NULL, NULL, 'Full Stack Developer', 'We are looking for a skilled developer.', 'Bangalore', 'PHP, MySQL, React, Node.js', 50000.00, 80000.00, '', '2-4 years', '2025-12-31 00:00:00', 1, 3, 'open', '2025-09-25 18:34:51', NULL, 'approved');
INSERT INTO `job_category` (`id`, `category_name`, `created_at`) VALUES
(1, 'Technical', '2025-10-22 12:28:32'),
(2, 'Non-Technical', '2025-10-22 12:28:32'),
(3, 'Vocational', '2025-10-22 12:28:32'),
(4, 'Professional', '2025-10-22 12:28:32');
INSERT INTO `job_flags` (`id`, `job_id`, `flagged_by`, `reason`, `reviewed`, `created_at`, `admin_action`) VALUES
(1, 4, 9, 'Salary seems too low for the skill requirements', 1, '2025-08-26 13:58:06', '');
INSERT INTO `job_recommendations` (`id`, `student_id`, `job_id`, `source`, `score`, `created_at`, `admin_action`) VALUES
(1, 1, 1, 'ai', 95.50, '2024-08-01 09:00:00', 'pending'),
(2, 1, 4, 'ai', 85.30, '2024-08-01 09:01:00', 'pending'),
(4, 3, 4, 'ai', 88.90, '2024-08-03 09:00:00', 'pending'),
(5, 4, 2, 'ai', 92.40, '2024-08-04 09:00:00', 'pending'),
(6, 5, 5, 'ai', 89.60, '2024-08-05 09:00:00', 'approved'),
(7, 6, 6, 'ai', 94.20, '2024-08-06 09:00:00', 'approved');
INSERT INTO `job_views` (`id`, `job_id`, `student_id`, `viewed_at`, `admin_action`) VALUES
(1, 1, 1, '2024-08-01 10:30:00', 'pending'),
(2, 1, 2, '2024-08-01 11:00:00', 'pending'),
(3, 1, 3, '2024-08-01 12:00:00', 'pending'),
(4, 2, 1, '2024-08-02 13:00:00', 'pending'),
(5, 2, 4, '2024-08-02 14:00:00', 'pending'),
(6, 3, 2, '2024-08-03 15:00:00', 'pending'),
(7, 3, 5, '2024-08-03 16:00:00', 'pending'),
(8, 4, 3, '2024-08-04 17:00:00', 'pending'),
(9, 5, 5, '2024-08-05 18:00:00', 'pending'),
(10, 6, 6, '2024-08-06 19:00:00', 'pending'),
(11, 4, 7, '2025-08-27 12:49:30', 'pending'),
(12, 4, 19, '2025-08-27 12:49:50', 'pending'),
(13, 5, 18, '2025-08-27 13:37:17', 'pending'),
(14, 1, 5, '2025-08-30 22:00:03', 'pending'),
(15, 2, 5, '2025-08-30 22:00:13', 'pending'),
(16, 2, 7, '2025-08-30 22:00:54', 'pending'),
(17, 5, 5, '2025-08-31 17:31:52', 'pending'),
(18, 3, 6, '2025-09-03 20:11:30', 'pending'),
(19, 6, 6, '2025-09-03 20:18:54', 'pending'),
(20, 8, 6, '0000-00-00 00:00:00', 'pending'),
(21, 9, 6, '2025-09-03 20:43:39', 'pending'),
(22, 11, 6, '2025-09-03 20:44:18', 'pending'),
(23, 12, 6, '2025-09-03 20:48:35', 'pending'),
(24, 15, 48, '2025-09-25 15:54:14', 'pending'),
(25, 13, 48, '2025-09-25 15:55:50', 'pending'),
(26, 12, 48, '2025-09-25 15:59:49', 'pending');
INSERT INTO `messages` (`id`, `sender_id`, `sender_role`, `receiver_id`, `receiver_role`, `message`, `attachment_url`, `attachment_type`, `type`, `created_at`) VALUES
(1, 49, 'recruiter', 3, '', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:03:01'),
(2, 49, 'recruiter', 4, '', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:15:12'),
(3, 49, 'recruiter', 4, 'student', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:20:09'),
(4, 49, 'recruiter', 5, 'student', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:20:16'),
(5, 6, 'admin', 50, 'student', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:34:09'),
(6, 6, 'admin', 50, 'institute', 'Hello, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:35:52'),
(7, 50, 'institute', 48, 'institute', 'Hello Student, this is a message from Institute!', NULL, NULL, 'text', '2025-09-27 11:40:59'),
(8, 50, 'institute', 48, 'student', 'Hello Student, this is a message from Institute!', NULL, NULL, 'text', '2025-09-27 11:41:20'),
(9, 48, 'student', 7, 'institute', 'Hello Institute, this is a test message!', NULL, NULL, 'text', '2025-09-27 11:44:43');
INSERT INTO `notifications` (`id`, `user_id`, `receiver_id`, `message`, `type`, `is_read`, `created_at`, `received_role`) VALUES
(1, 49, NULL, 'System maintenance at 10 PM tonight', 'system', 0, '2025-09-30 16:39:11', NULL),
(2, 50, NULL, 'New college event: AI Workshop on 5th Oct!', 'general', 1, '2025-09-30 16:43:19', NULL),
(3, 49, NULL, 'New college event: AI Workshop on 5th Oct!', 'general', 0, '2025-10-01 17:26:56', NULL),
(4, 0, 49, 'Candidate Mike Johnson has been shortlisted for Frontend Developer role', 'general', 0, '2025-10-01 22:19:49', 'recruiter');
INSERT INTO `notifications_templates` (`id`, `name`, `type`, `subject`, `body`, `created_at`, `role`) VALUES
(1, 'Welcome Email', 'email', 'Welcome to JobSahi!', 'Welcome to JobSahi! We are excited to have you join our community.', '2025-08-26 14:36:09', NULL),
(2, 'Job Application SMS', 'sms', '', 'Your job application has been submitted successfully. We will keep you updated.', '2025-08-26 14:36:09', NULL),
(3, 'Interview Reminder', 'email', 'Interview Reminder', 'This is a reminder about your upcoming interview scheduled for 2024-08-20 at 14:30:00.', '2025-08-26 14:36:09', NULL),
(4, 'Course Enrollment', 'push', '', 'You have successfully enrolled in Full Stack Development. Classes start on 2024-09-01.', '2025-08-26 14:36:09', NULL),
(5, 'Updated Welcome Email', 'email', 'Your account has been updated', 'Hello {{name}}, your account was successfully updated.', '2025-09-05 01:25:38', NULL),
(6, 'Welcome Email', 'email', 'Welcome to JOBSAHI!', 'Hello {{user}}, thank you for joining JOBSAHI. We\'re glad to have you!', '2025-09-05 01:30:08', NULL),
(7, 'Updated Welcome to our College', 'email', 'Your account has been updated', 'Hello {{name}}, your account was successfully updated.', '2025-09-05 01:48:08', NULL),
(8, 'Updated Welcome to our placement cell', 'email', 'Your account has been updated', 'Hello {{name}}, your account was successfully updated.', '2025-09-05 01:49:11', NULL),
(9, 'Welcome Email', 'email', 'Welcome to our platform', 'Hello {{name}}, thank you for joining us!', '2025-09-05 01:51:27', NULL),
(10, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-09-30 16:58:21', NULL),
(11, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-09-30 17:11:31', NULL),
(12, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-10-01 17:35:45', NULL),
(13, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-10-01 21:43:07', 'institute'),
(14, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-10-01 21:49:01', 'recruiter'),
(15, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-10-01 22:02:10', 'recruiter'),
(16, 'Job Application Reminder', 'email', 'Reminder: Complete Your Application', 'Dear user, please complete your job application before the deadline.', '2025-10-01 22:16:52', 'recruiter');
INSERT INTO `otp_requests` (`id`, `user_id`, `otp_code`, `purpose`, `is_used`, `created_at`, `expires_at`) VALUES
(127, 51, '026297', 'forgot_password', 1, '2025-10-05 18:16:22', '2025-10-05 12:51:22');
INSERT INTO `plans` (`id`, `title`, `type`, `price`, `duration_days`, `features_json`, `created_at`, `modified_at`) VALUES
(1, 'Basic Employer', 'employer', 5000, 30, '{\"job_posts\": 5, \"resume_views\": 50, \"priority_support\": false}', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(2, 'Premium Employer', 'employer', 12000, 30, '{\"job_posts\": 20, \"resume_views\": 200, \"priority_support\": true, \"featured_jobs\": 3}', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(3, 'Enterprise Employer', 'employer', 25000, 30, '{\"job_posts\": \"unlimited\", \"resume_views\": \"unlimited\", \"priority_support\": true, \"featured_jobs\": 10, \"dedicated_manager\": true}', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(4, 'Basic Institute', 'institute', 8000, 30, '{\"course_listings\": 10, \"student_enrollments\": 100, \"certificates\": true}', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(5, 'Premium Institute', 'institute', 15000, 30, '{\"course_listings\": \"unlimited\", \"student_enrollments\": 500, \"certificates\": true, \"analytics\": true}', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(6, 'Premium Plan', 'institute', 200, 30, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:36:24'),
(7, 'Premium Plan', 'employer', 200, 30, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:50:46'),
(8, 'Premium Plan', 'employer', 400, 30, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:53:21'),
(9, 'Premium Plan', 'employer', 1500, 30, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:53:37'),
(10, 'Premium Plan', 'institute', 13000, 30, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:54:06'),
(11, 'Premium Plan', 'institute', 200, 60, '[\"Unlimited access\",\"Priority support\",\"Free trials\"]', '2025-10-01 15:36:24', '2025-10-01 15:36:24');
INSERT INTO `recruiter_profiles` (`id`, `user_id`, `company_name`, `company_logo`, `industry`, `website`, `location`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 2, 'New Company Name', 'logo.png', 'IT Services', 'https://example.com', 'Mumbai', '2025-08-11 14:10:23', '2025-09-03 17:02:28', NULL, ''),
(2, 16, 'Updated Company Name', '/uploads/logos/techcorp_logo.png', 'Technology', 'https://techcorp.com', 'New York, NY', '2024-05-15 10:00:00', '2025-09-25 17:31:06', NULL, 'approved'),
(3, 17, 'InnovateLabs', '/uploads/logos/innovate_logo.png', 'AI & Machine Learning', 'https://innovatelabs.com', 'Hyderabad, India', '2024-05-20 11:00:00', '2025-09-25 17:31:12', NULL, 'approved'),
(4, 49, 'techcorp', 'techcorp_logo1.png', 'Technology', 'https://techcorp.com', 'New York, NY', '2025-09-25 15:20:08', '2025-09-25 18:05:37', NULL, 'approved');
INSERT INTO `referrals` (`id`, `referrer_id`, `referee_email`, `job_id`, `status`, `created_at`, `admin_action`) VALUES
(1, 7, 'friend1@gmail.com', 1, 'pending', '2025-08-26 13:54:01', 'pending'),
(3, 10, 'classmate@gmail.com', 6, 'pending', '2025-08-26 13:54:01', 'pending'),
(12, 5, 'classmate@gmail.com', 6, 'pending', '2025-08-29 17:12:57', 'pending'),
(13, 6, 'classmate@gmail.com', 6, 'pending', '2025-09-01 00:25:23', 'pending'),
(14, 7, 'classmate@gmail.com', 6, 'pending', '2025-09-01 00:27:15', 'pending'),
(15, 9, 'classmate@gmail.com', 6, 'pending', '2025-09-03 15:21:38', 'pending'),
(16, 11, 'classmate@gmail.com', 6, 'pending', '2025-09-03 15:22:27', 'pending'),
(17, 12, 'classmate@gmail.com', 6, 'pending', '2025-09-03 15:30:36', 'pending'),
(18, 13, 'classmate@gmail.com', 6, 'pending', '2025-09-03 15:30:54', 'pending'),
(19, 14, 'classmate@gmail.com', 6, 'pending', '2025-09-03 15:40:38', 'pending'),
(20, 6, 'poojadhameja19@gmail.com', 15, 'pending', '2025-10-01 12:08:11', 'pending');
INSERT INTO `reports` (`id`, `generated_by`, `report_type`, `filters_applied`, `download_url`, `generated_at`, `admin_action`) VALUES
(2, 6, 'revenue_report', '{\"status\":\"approved\",\"date_range\":\"2025-09-01\"}', 'http://localhost/reports/downloads/applicants_report.pdf', '2025-09-04 08:08:18', 'pending'),
(3, 6, 'job_summary', '{\"course\":\"BCA\",\"year\":\"2025\"}', 'http://localhost/reports/report123.pdf', '2025-09-04 08:14:26', 'pending'),
(4, 6, 'placement_funnel', '{\"course\":\"BCA\",\"year\":\"2025\"}', 'http://localhost/reports/report123.pdf', '2025-09-04 08:17:03', 'pending'),
(5, 6, 'placement_funnel', '{\"course\":\"BCA\",\"year\":\"2025\"}', 'http://localhost/reports/report123.pdf', '2025-09-04 08:18:34', 'pending'),
(6, 20, 'revenue_report', '{\"course\":\"BCA\",\"year\":\"2025\"}', 'http://localhost/reports/report123.pdf', '2025-09-04 08:20:46', 'pending'),
(7, 48, 'job_summary', '{\"course_id\":5,\"semester\":\"Fall 2025\"}', 'http://localhost/reports/student_progress_5.pdf', '2025-09-27 12:32:56', 'pending'),
(8, 48, 'job_summary', '{\"course_id\":5,\"semester\":\"Fall 2025\"}', 'http://localhost/reports/student_progress_5.pdf', '2025-09-27 12:34:08', 'pending'),
(9, 48, 'placement_funnel', '{\"course_id\":5,\"semester\":\"Fall 2025\"}', 'http://localhost/reports/student_progress_5.pdf', '2025-09-27 12:37:36', 'pending'),
(10, 48, 'revenue_report', '{\"course_id\":5,\"semester\":\"Fall 2025\"}', 'http://localhost/reports/student_progress_5.pdf', '2025-09-27 12:39:09', 'pending'),
(11, 48, 'job_summary', '{\"course_id\":5,\"semester\":\"Fall 2025\"}', 'http://localhost/reports/student_progress_5.pdf', '2025-09-27 12:39:34', 'pending'),
(12, 48, 'revenue_report', '{\"course\":\"BCA\",\"year\":\"2025\"}', 'http://localhost/reports/report123.pdf', '2025-09-27 12:41:56', 'pending');
INSERT INTO `resume_access_logs` (`id`, `recruiter_id`, `student_id`, `viewed_at`) VALUES
(1, 1, 1, '2024-08-15 10:15:00'),
(2, 1, 3, '2024-08-18 16:20:00'),
(4, 3, 5, '2024-08-17 09:30:00'),
(5, 2, 4, '2024-08-19 11:45:00');
INSERT INTO `saved_jobs` (`id`, `student_id`, `job_id`, `saved_at`, `created_at`, `modified_at`, `deleted_at`) VALUES
(26, 5, 3, '2025-08-28 20:35:00', '2025-08-28 20:35:00', '2025-08-28 20:35:00', NULL),
(28, 5, 6, '2025-08-31 21:13:51', '2025-08-31 21:13:51', '2025-08-31 21:13:51', NULL);
INSERT INTO `skill_tests` (`id`, `student_id`, `test_platform`, `test_name`, `score`, `max_score`, `completed_at`, `badge_awarded`, `passed`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 7, NULL, 'Java Basics', 85, 100, '2025-09-30 14:47:58', 1, 1, '2025-09-30 18:10:42', '2025-09-30 20:17:58', NULL, 'pending'),
(2, 7, 'Zoom', 'Java Basics', 85, 100, '0000-00-00 00:00:00', 1, 1, '2025-09-30 18:11:07', '2025-09-30 18:11:07', NULL, 'pending'),
(3, 7, 'Mettl', 'Java Basics', 85, 100, '0000-00-00 00:00:00', 1, 1, '2025-09-30 18:11:50', '2025-09-30 18:11:50', NULL, 'pending'),
(4, 7, 'Mettl', 'Java Basics', 85, 100, '2025-09-30 14:51:02', 1, 1, '2025-09-30 20:17:17', '2025-09-30 20:21:02', NULL, 'approved'),
(5, 7, 'Zoom', 'Java Advance', 85, 100, '2025-09-30 09:30:00', 1, 1, '2025-09-30 20:24:08', '2025-09-30 20:24:08', NULL, 'approved'),
(6, 7, 'Mettl', 'Java Advance', 85, 100, '2025-09-30 09:30:00', 1, 1, '2025-09-30 20:25:51', '2025-09-30 20:25:51', NULL, 'approved'),
(7, 7, 'Mettl', 'Java Advance', 85, 100, '2025-09-30 09:30:00', 1, 1, '2025-10-01 17:51:24', '2025-10-01 17:51:24', NULL, 'approved');
INSERT INTO `student_batches` (`id`, `student_id`, `batch_id`, `admin_action`) VALUES
(1, 1, 1, 'approved'),
(3, 3, 4, 'pending'),
(4, 4, 2, 'pending'),
(5, 5, 6, 'pending'),
(6, 6, 3, 'pending'),
(7, 1, 2, 'approved'),
(8, 3, 2, 'approved'),
(9, 4, 2, 'approved');
INSERT INTO `student_course_enrollments` (`id`, `student_id`, `course_id`, `enrollment_date`, `status`, `created_at`, `modified_at`, `deleted_at`, `admin_action`) VALUES
(1, 1, 1, '2024-01-15 10:00:00', 'enrolled', '2024-01-15 10:00:00', '2025-10-24 00:34:06', NULL, 'approved'),
(3, 3, 3, '2024-01-20 12:00:00', 'enrolled', '2024-01-20 12:00:00', '2025-10-24 00:34:22', NULL, 'pending'),
(4, 4, 1, '2024-03-01 13:00:00', 'completed', '2024-03-01 13:00:00', '2025-10-24 00:33:25', NULL, 'pending'),
(5, 5, 5, '2024-02-10 14:00:00', 'completed', '2024-02-10 14:00:00', '2025-08-26 13:31:08', NULL, 'pending'),
(6, 6, 2, '2024-02-01 15:00:00', 'enrolled', '2024-02-01 15:00:00', '2025-08-26 13:31:08', NULL, 'pending'),
(15, 6, 1, '2025-09-01 00:08:31', 'enrolled', '2025-09-01 00:08:31', '2025-09-01 00:08:31', NULL, 'pending'),
(16, 5, 1, '2025-09-03 17:14:14', 'enrolled', '2025-09-03 17:14:14', '2025-09-03 17:14:14', NULL, 'pending'),
(17, 6, 3, '2025-09-03 17:14:49', 'enrolled', '2025-09-03 17:14:49', '2025-09-03 17:14:49', NULL, 'pending'),
(18, 7, 10, '2025-09-29 12:44:10', 'enrolled', '2025-09-29 12:44:10', '2025-09-29 12:45:08', NULL, 'approved'),
(20, 7, 11, '2025-09-29 13:03:50', 'enrolled', '2025-09-29 13:03:50', '2025-09-29 13:03:50', NULL, 'approved'),
(21, 1, 5, '2025-10-24 00:28:34', 'enrolled', '2025-10-24 00:28:34', '2025-10-24 00:34:06', NULL, 'approved'),
(22, 3, 5, '2025-10-24 00:28:34', 'enrolled', '2025-10-24 00:28:34', '2025-10-24 00:34:22', NULL, 'approved'),
(23, 4, 5, '2025-10-24 00:28:34', 'completed', '2025-10-24 00:28:34', '2025-10-24 00:33:25', NULL, 'approved');
INSERT INTO `student_profiles` (`id`, `user_id`, `skills`, `education`, `resume`, `certificates`, `portfolio_link`, `linkedin_url`, `dob`, `gender`, `job_type`, `trade`, `created_at`, `modified_at`, `deleted_at`, `admin_action`, `bio`, `experience`, `projects`, `languages`, `aadhar_number`, `graduation_year`, `cgpa`, `latitude`, `longitude`, `location`) VALUES
(1, 5, 'PHP, SQL, JavaScript, HTML, CSS', 'Bachelor of Technology in Computer Science (B.Tech)', 'uploads/resumes/student_5_resume.pdf', 'uploads/certificates/student_5_certificates.zip', 'https://portfolio.example.com/student5', 'https://linkedin.com/in/student5', '2000-05-15', 'female', 'full_time', 'IT', '2024-06-01 10:00:00', '2025-10-24 12:05:27', NULL, 'approved', 'A passionate software developer focused on building scalable backend systems and modern web applications. Skilled in PHP, React, and SQL.', '{\"level\":\"Intermediate\",\"years\":\"2\",\"details\":[{\"company\":\"TechNova Pvt Ltd\",\"role\":\"Backend Developer\",\"duration\":\"Jan 2023 - May 2024\",\"description\":\"Developed and maintained PHP-based REST APIs for client applications.\"},{\"company\":\"Brightorial Tech Solutions\",\"role\":\"Intern\",\"duration\":\"Aug 2022 - Dec 2022\",\"description\":\"Assisted in developing a React-based admin panel with MySQL backend.\"}]}', '[{\"name\":\"JobSahi Platform\",\"link\":\"https:\\/\\/jobsahi.in\"}]', 'English, Hindi', '9876-5432-8989', 2023, 8.70, 22.71960000, 75.85770000, 'Indore, Madhya Pradesh'),
(3, 9, '', '', '', NULL, 'http://portfolio.com', 'http://linkedin.com/in/student', '2000-05-15', 'female', 'full_time', 'IT', '2024-06-03 12:00:00', '2025-09-03 14:01:24', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 10, 'React, Angular, TypeScript, CSS, UI/UX Design', 'B.Des - NIFT Delhi (2020-2024)', '/uploads/resumes/kavya_patel_resume.pdf', NULL, 'https://kavyapatel.design', 'https://linkedin.com/in/kavyapatel', '2002-01-14', 'female', 'full_time', 'Frontend Development', '2024-06-04 13:00:00', '2025-08-26 13:27:34', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 11, 'PHP, MySQL, React', 'B.Tech in Computer Science', '/uploads/resumes/student_resume.pdf', 'uploads/certificates/approved_certificate.pdf', 'https://myportfolio.com', 'https://linkedin.com/in/student', '2000-05-15', 'female', '', 'Software Development', '2024-06-05 14:00:00', '2025-09-03 15:19:13', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 12, 'Digital Marketing, SEO, Content Writing, Social Media', 'MBA Marketing - Symbiosis Pune (2022-2024)', '/uploads/resumes/neha_gupta_resume.pdf', NULL, 'https://nehagupta.marketing', 'https://linkedin.com/in/nehagupta', '1999-12-05', 'female', 'full_time', 'Digital Marketing', '2024-06-06 15:00:00', '2025-08-26 13:27:34', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 48, 'PHP, MySQL, React', 'B.Tech Computer Science', 'resume.pdf', 'AWS, Azure', 'http://myportfolio.com', 'http://linkedin.com/in/example', '1998-05-10', 'female', 'full_time', 'Software Development', '2025-09-25 15:18:23', '2025-10-01 21:00:24', NULL, 'approved', 'Passionate developer with 3 years experience', '3 years', NULL, NULL, NULL, 2020, 8.50, NULL, NULL, NULL),
(15, 51, ' R, Machine Learning, Deep Learning, TensorFlow, PyTorch, Pandas, NumPy, Scikit-learn, SQL, Power BI, Tableau, Statistics, NLP', 'M.Sc. Data Science - IIT Delhi | B.Sc. Mathematics - St. Stephen\'s College, Delhi', 'https://resume.io/arjun.pdf', 'Google Data Analytics Professional Certificate, IBM Data Science Professional Certificate, Deep Learning Specialization (Coursera), TensorFlow Developer Certificate', 'https://arjunreddy-ds.netlify.app', 'https://linkedin.com/in/arjun-reddy-datascience', '2000-09-05', 'male', '', 'Data Science & Analytics', '2025-10-19 18:46:41', '2025-10-22 08:38:05', NULL, 'pending', 'Data science enthusiast with strong mathematical foundation and hands-on experience in machine learning. Completed multiple projects in predictive analytics and NLP. Passionate about deriving insights from data and building intelligent systems. Actively contributing to open-source ML projects.', '{\"level\":\"intermediate\",\"years\":\"1.5\",\"details\":[{\"company\":\"Fractal Analytics\",\"position\":\"Data Science Intern\",\"duration\":\"Jun 2024 - Present\",\"description\":\"Working on customer churn prediction models for telecom client. Implemented ensemble learning techniques improving accuracy by 15%. Creating interactive dashboards using Power BI.\"},{\"type\":\"research\",\"company\":\"IIT Delhi Research Lab\",\"position\":\"Research Assistant\",\"duration\":\"Jan 2024 - May 2024\",\"description\":\"Conducted research on sentiment analysis for regional Indian languages. Published paper in national conference. Developed custom NLP pipeline using BERT.\"},{\"type\":\"project\",\"company\":\"Kaggle Competition\",\"position\":\"Individual Contributor\",\"duration\":\"Aug 2023 - Nov 2023\",\"description\":\"Participated in image classification competition. Achieved top 5% ranking using CNN and transfer learning techniques.\"}]}', NULL, NULL, NULL, 2025, 9.40, 22.71956870, 75.85772580, 'Martand Chowk, Indore City, Indore, Juni Indore Tahsil, Indore, Madhya Pradesh, 452001, India');
INSERT INTO `subscriptions` (`id`, `user_id`, `plan_name`, `type`, `start_date`, `expiry_date`, `credits_remaining`, `admin_action`) VALUES
(1, 5, 'Premium Employer', 'employer', '2024-08-01', '2024-08-31', 18, 'pending'),
(2, 11, 'Basic Employer', 'employer', '2024-08-15', '2024-09-14', 4, 'pending'),
(3, 12, 'Premium Institute', 'institute', '2024-07-01', '2024-07-31', 450, 'pending'),
(4, 13, 'Basic Institute', 'institute', '2024-08-10', '2024-09-09', 95, 'pending');
INSERT INTO `system_alerts` (`id`, `title`, `message`, `target_role`, `scheduled_at`, `created_at`) VALUES
(1, 'Maintenance Notice', 'System maintenance scheduled for tonight 2:00 AM - 4:00 AM IST', 'all', '2024-08-25 02:00:00', '2025-08-26 14:02:12'),
(2, 'New Feature Launch', 'Check out our new skill assessment feature!', 'student', '2024-08-20 10:00:00', '2025-08-26 14:02:12'),
(3, 'Premium Plan Discount', 'Get 20% off on premium plans this month', '', '2024-08-15 09:00:00', '2025-08-26 14:02:12'),
(4, 'Course Certification Update', 'New certificate templates available for institutes', 'institute', '2024-08-18 12:00:00', '2025-08-26 14:02:12');
INSERT INTO `transactions` (`id`, `user_id`, `amount`, `method`, `purpose`, `status`, `timestamp`, `admin_action`) VALUES
(1, 5, 12000.00, 'UPI', 'plan', 'success', '2024-08-01 04:30:00', 'pending'),
(2, 11, 5000.00, 'card', 'plan', 'success', '2024-08-15 09:00:00', 'pending'),
(3, 12, 15000.00, 'UPI', 'plan', 'success', '2024-07-01 03:45:00', 'pending'),
(6, 6, 1500.75, 'wallet', 'highlight', 'success', '2025-09-05 07:16:31', 'pending'),
(7, 20, 1500.75, 'card', 'resume_boost', 'failed', '2025-09-05 07:16:53', 'pending'),
(8, 6, 1500.75, 'UPI', 'plan', 'success', '2025-09-05 07:16:59', 'pending'),
(9, 48, 1500.75, 'wallet', 'plan', 'failed', '2025-10-01 10:50:18', 'pending'),
(10, 6, 1500.75, 'card', 'plan', 'success', '2025-10-01 13:11:14', 'pending'),
(11, 6, 1500.75, 'card', 'plan', 'success', '2025-10-01 13:13:29', 'pending');
INSERT INTO `users` (`id`, `user_name`, `email`, `password`, `role`, `phone_number`, `is_verified`, `status`, `last_activity`) VALUES
(5, 'Pooja Dhameja', 'poojadhameja15@gmail.com', '$2y$10$onmgA.FGdSjm8.a/ZJiuP.WgF56WPOJ3DvHNBJvBGOzce4P7rN5my', 'student', '9876543210', 1, 'active', '2025-10-15 07:05:19'),
(6, 'Pooj Dhameja', 'poojadhameja123@gmail.com', '$2y$10$Q.Onj8vMwCTpfLXPW9u5AOUWuPnTRn4smlRRyFA16Xc4QlZuKPqKy', 'admin', '9825426785', 1, 'active', '2025-10-15 07:05:19'),
(7, 'John Doe Updated', 'poojadhameja14@gmail.com', '$2y$10$kMU2gpuGX2dWEL9rgsE5hu7iHdcGV1oDMpWYINQLgaRmCGHwMuDeO', 'admin', '9648148151', 1, 'active', '2025-10-15 07:05:19'),
(9, 'Amit Sharma', 'amit.sharma@email.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'student', '9876543212', 1, 'active', '2025-10-15 07:05:19'),
(10, 'Kavya Patel', 'kavya.patel@email.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'student', '9876543213', 1, 'active', '2025-10-15 07:05:19'),
(11, 'Arjun Reddy', 'arjun.reddy@email.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'student', '9876543214', 1, 'active', '2025-10-15 07:05:19'),
(12, 'Neha Gupta', 'neha.gupta@email.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'student', '9876543215', 1, 'active', '2025-10-15 07:05:19'),
(13, 'Tech Institute Delhi', 'admin@techinstituteDelhi.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'institute', '9876543216', 1, 'active', '2025-10-15 07:05:19'),
(14, 'NIIT Mumbai', 'contact@niitmumbai.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'institute', '9876543217', 1, 'active', '2025-10-15 07:05:19'),
(15, 'Code Academy Bangalore', 'info@codeacademybangalore.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'institute', '9876543218', 1, 'active', '2025-10-15 07:05:19'),
(16, 'TechCorp Solutions', 'hr@techcorp.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'recruiter', '9876543219', 1, 'active', '2025-10-15 07:05:19'),
(17, 'InnovateLabs', 'careers@innovatelabs.com', '$2y$10$ENxbJV5OnInnbP7eW3LzpuCIRUTtwPI0R2XmcHMQA95...', 'recruiter', '9876543220', 1, 'active', '2025-10-15 07:05:19'),
(18, 'Pooj Dhameja', 'poojadhameja12@gmail.com', '$2y$10$lrCRP1e8FS8eRJzATfPQzeG3YEsSgLzGu.WqL4Bf8gvrAIU8nRQNC', 'institute', '9825426786', 1, 'active', '2025-10-15 07:05:19'),
(19, 'Bhumika Jain', 'jbhumika71@gmail.com', '$2y$10$qPigtHpy6Hso8788IjFi8e5gT8lZspLB2zZyddWpky0ftp6ddBBxy', 'recruiter', '987654321', 1, 'active', '2025-10-15 07:05:19'),
(20, 'Pooj Dhameja', 'poojadhameja11@gmail.com', '$2y$10$018zQMF3tgN.AlMuk58heu3FhdtpdqsANVzo.lXfkIgkmU6lQhgMW', 'institute', '9598632451', 1, 'active', '2025-10-15 07:05:19'),
(21, 'himanshu shrirang', 'himanshushrirang@gmail.com', '$2y$10$v2p8Nd66zVuUVe/..9UsxOp8gLxS/mdnvreu6sKFkuhCo10qPvUue', 'institute', '8818986352', 1, 'active', '2025-10-15 07:05:19'),
(22, 'himanshu shrirang', 'himanshushrirang1@gmail.com', '$2y$10$ECNNgYrtwja98cypQJovFeOgPvBoPk.2otQUAXHvqgcXuXQkkqTEe', 'institute', '8818986353', 1, 'active', '2025-10-15 07:05:19'),
(28, 'himanshu shrirang', 'himanshushrirang3@gmail.com', '$2y$10$ezn7/hePJxi7Vw7wb8PakOVfANuVOgESjB84GKJdC.yTT5ZH2RX3y', 'institute', '8818986355', 1, 'active', '2025-10-15 07:05:19'),
(48, 'pooja dhameja', 'poojadhameja19@gmail.com', '$2y$10$WiVt17oc1JMy29X4XrHbdO0HmZ.xhL.Ymzdy/C2xtOfDR5nBDQGhm', 'student', '7378862436', 1, 'active', '2025-10-15 07:05:19'),
(49, 'pooja dhameja', 'poojadhameja20@gmail.com', '$2y$10$3Rqs7eT4vHi/GCk4mLB09ube2RQF1cedrt1lgSA1az3nTEoNqjcvy', 'recruiter', '7378863436', 1, 'active', '2025-10-15 07:05:19'),
(50, 'Bhumika Jain', 'jbhumika45@gmail.com', '$2y$10$V4nP0VzzaIpmdgRoZUSs6uudn.vN1qjaUI7CnJMpXGEdo4sZwVxuu', 'institute', '7678864436', 1, 'active', '2025-10-15 07:05:19'),
(51, 'Himanshu Shrirang', 'Stu@gmail.com', '$2y$10$uREeWImAl1hKwu0Ao7lod.pxmxy4tqezndsWepiycFXcmoTJQXT6e', 'student', '2234567892', 1, 'active', '2025-10-15 10:30:00');
