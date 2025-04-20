-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2025 at 01:18 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pioneer_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `instructor_id` int(11) DEFAULT NULL,
  `is_trending` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `title`, `description`, `price`, `instructor_id`, `is_trending`, `created_at`) VALUES
(1, 'Introduction to Python', 'Learn the basics of Python programming.', 49.99, 7, 1, '2025-02-13 08:37:05'),
(3, 'Introduction to PHP', 'Learn the basics of PHP programming.', 49.99, 2, 1, '2025-02-13 10:01:31'),
(4, 'Python Web Dev', 'How to Create a Website Using HTML, CSS, JavaScript, Python, and Django from Scratch', 1200.00, 2, 0, '2025-04-02 10:11:58'),
(5, 'Ramro COurse', 'iuhoiuh', 8687.00, 7, 0, '2025-04-10 10:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `course_registrations`
--

CREATE TABLE `course_registrations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `verified` int(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_registrations`
--

INSERT INTO `course_registrations` (`id`, `user_id`, `course_id`, `registered_at`, `verified`) VALUES
(1, 2, 1, '2025-02-13 11:21:45', 1),
(2, 2, 4, '2025-04-02 10:55:12', 1),
(4, 4, 5, '2025-04-14 17:07:31', 1),
(5, 3, 5, '2025-04-14 17:10:21', 0),
(6, 8, 1, '2025-04-14 17:36:01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `instructor_details`
--

CREATE TABLE `instructor_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `qualification` varchar(255) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `instructor_details`
--

INSERT INTO `instructor_details` (`id`, `user_id`, `specialization`, `bio`, `qualification`, `experience_years`, `created_at`, `updated_at`) VALUES
(0, 7, 'Dolore nihil est eve', 'Saepe ea harum eveni', 'Minima sunt eos corr', 1980, '2025-04-14 16:43:02', '2025-04-14 16:43:14'),
(0, 2, 'Et laudantium et am', 'Aut quia officiis fu', 'Minima maiores illo ', 1977, '2025-04-14 16:43:22', '2025-04-14 16:43:22');

-- --------------------------------------------------------

--
-- Table structure for table `internships`
--

CREATE TABLE `internships` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `internship_type` enum('paid','unpaid') DEFAULT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internships`
--

INSERT INTO `internships` (`id`, `title`, `description`, `company`, `location`, `internship_type`, `posted_by`, `created_at`) VALUES
(1, 'Software Development Intern', 'Looking for a software development intern to join our team.', 'Tech Corp', 'New York, NY', 'paid', 4, '2025-02-15 13:02:59'),
(2, 'Harum non ut quis qu', 'Laboriosam odio qua', 'Cash and Benjamin LLC', 'Ad cumque accusantiu', 'unpaid', 4, '2025-04-14 18:12:47'),
(3, 'Qui dolorem asperior', 'Laudantium magni vo', 'Lynch and Noble Plc', 'Sit qui quod quam s', 'paid', 4, '2025-04-14 18:12:52');

-- --------------------------------------------------------

--
-- Table structure for table `internship_applications`
--

CREATE TABLE `internship_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `internship_id` int(11) DEFAULT NULL,
  `cv` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','accepted','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `internship_applications`
--

INSERT INTO `internship_applications` (`id`, `user_id`, `internship_id`, `cv`, `status`, `applied_at`) VALUES
(3, 8, 1, NULL, 'accepted', '2025-04-14 18:12:28'),
(4, 8, 2, '../uploads/cv/8_1744658534_Applied Cryptography.pdf', 'pending', '2025-04-14 19:22:14'),
(6, 8, 3, '../uploads/cv/8_1744659261_COMP111077 Applied Cryptography Coursework 1 Final-2.pdf', 'pending', '2025-04-14 19:34:21');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `company` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `job_type` enum('full-time','part-time','remote','contract') DEFAULT NULL,
  `posted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `title`, `description`, `company`, `location`, `job_type`, `posted_by`, `created_at`) VALUES
(1, 'Field Officer ', 'VACANCY ANNOUNCEMENT\r\n\r\nParent Federation of Persons with Intellectual Disabilities-Nepal (PFPID) is a non-governmental organization established by the parents of Persons with Intellectual Disabilities (PWIDs) of Nepal. This organization is the nationally federation representing the PWIDs and registered in the District Administration Office, Kathmandu and working for the rights and services of the Persons with PWIDs. The Head Office of this organization is located in Gyaneshwor, Kathmandu, Ward- 30, Nepal. Currently, this organization works throughout Nepal with its member organizations in 62 districts. The main objective of this organization is to protect and promote the rights of PWIDs for their greater inclusion in the family, community and Society at large.\r\n\r\nAs the following staff is required in PFPID, applications are invited from interested and qualified Nepali citizens:\r\n\r\nPosition: Field Officer\r\n\r\nNumber: 2 (1 in Simara and 1 in Kailali)\r\n\r\nJob location: Simara and Kailali\r\n\r\nThematic Area: \r\n\r\nBara and Rautahat Districts: Capacity Building of Persons with Intellectual Disabilities and Parents, Employment of Persons with intellectual disabilities, Self-advocacy, Inclusive Education, Advocacy and Awareness, capacity development of member organization and board members.\r\n\r\nKailali and Kanchanpur Districts: Capacity Building of Persons with Intellectual Disabilities and Parents, Employment of Persons with intellectual disabilities, Self-advocacy, Advocacy and Awareness, capacity development of member organization and board members.\r\n\r\nMajor responsibilities to be fulfilled:\r\n\r\n    Work closely with the member organizations of PFPID-Nepal in the assigned districts to facilitate the implementation of project activities.\r\n    Maintain strong and effective communication between district-level member organizations and PFPID-Nepal central office.\r\n    Provide technical and administrative support in both programmatic and financial aspects of the project. \r\n    Ensure proper documentation and timely submission of all required reporting formats (narrative and financial) from the field.\r\n    Study, understand, and implement various components of the project effectively at the field level. \r\n    Conduct regular field visits to monitor progress, provide support, and ensure quality assurance of implemented activities.\r\n    Assist in organizing local-level training, orientations, meetings, and advocacy events in coordination with member organizations.\r\n    Collaborate with the central team to ensure alignment with PFPID-Nepal\'s policies and strategic priorities.\r\n\r\nRequired Qualifications:\r\n\r\n    Minimum Bachelor’s degree in Social Sciences or relevant discipline. (Master\'s degree is an advantage).\r\n    At least 2 years of experience in the development sector (experience in the disability sector is highly preferred).\r\n    Strong understanding of project implementation and financial management.\r\n    Excellent communication, coordination, and facilitation skills.\r\n    Familiarity with working with community-based organizations, preferably with marginalized or disability-focused groups.\r\n    Ability and willingness to travel frequently within the assigned districts.\r\n    Proficient in Microsoft Office (Word, Excel, PowerPoint) and digital communication tools.\r\n    Strong organizational skills and attention to detail, particularly in reporting and documentation.\r\n\r\nPreferred Attributes:\r\n\r\n    Experience or knowledge in the field of disability rights, inclusion, and advocacy.\r\n    Previous involvement with federations, networks, or parent-led organizations is an asset.\r\n    Local candidates or individuals familiar with the geographic area are encouraged to apply.\r\n\r\nIf you are willing to work in accordance with the core and values of PFPID of Nepal, and have passion to serve the Persons with intellectual disabilities, than clearly fill out the prescribed application form and email us at vacancy@pfpid.org.np by 24th April, 2025. Please mention “Application for Field Officer – [Your Preferred District Group]” in the subject line.\r\n\r\nApplication form Link\r\n\r\nCandidates selected for interview, written or both assessments will be provided information via email or telephone\r\n\r\n. Special consideration will be given to parents of persons with intellectual disabilities, persons with disabilities, women, janajatis and dalits in the selection process. In case of any undue pressure or telephone calls in favor of the candidate, the application of the concerned person shall lead to automatic disqualification.\r\n\r\nPFPID-Nepal reserves the right to accept or reject any application without providing any reason whatsoever.\r\n', 'Nepal Hamro', 'Pokhara', 'part-time', 4, '2025-04-02 10:13:31');

-- --------------------------------------------------------

--
-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `job_id` int(11) DEFAULT NULL,
  `cv` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','accepted','rejected') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cover_letter` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `type` enum('job_alert','internship_alert','course_update') DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `course_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed') DEFAULT 'pending',
  `payment_gateway` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `course_id`, `amount`, `payment_status`, `payment_gateway`, `transaction_id`, `created_at`) VALUES
(1, 3, 5, 8687.00, 'completed', 'Ipsum aliquid porro ', 'Autem saepe obcaecat', '2025-04-14 17:10:21');

-- --------------------------------------------------------

--
-- Table structure for table `pioneerhub_info`
--

CREATE TABLE `pioneerhub_info` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pioneerhub_info`
--

INSERT INTO `pioneerhub_info` (`id`, `name`, `email`, `logo`, `address`, `phone`, `website`, `description`, `created_at`, `updated_at`) VALUES
(1, 'PioneerHub Updated', 'info@pioneerhub.com', 'logo_1743588864_DummySS.png', 'Pokhara, Nepal', '123-456-7890', 'https://www.pioneerhub.com', 'PioneerHub is a platform for connecting professionals. Updated description.', '2025-02-16 08:30:10', '2025-04-02 10:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `submitted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `description`, `submitted_by`, `created_at`) VALUES
(1, 'New Peoject', 'Hello  Project\r\n', 1, '2025-04-02 10:15:59'),
(2, 'Python Fac e recon', 'Kaam ramro xa', 8, '2025-04-14 18:44:13');

-- --------------------------------------------------------

--
-- Table structure for table `project_collaborations`
--

CREATE TABLE `project_collaborations` (
  `id` int(11) NOT NULL,
  `project_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_collaborations`
--

INSERT INTO `project_collaborations` (`id`, `project_id`, `user_id`, `status`, `requested_at`) VALUES
(2, 1, 4, 'approved', '2025-04-03 10:42:14'),
(3, 1, 5, 'pending', '2025-04-03 10:42:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `cv` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','instructor','employer') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `profile_pic`, `cv`, `role`, `created_at`) VALUES
(1, 'John Doe', 'john@example.com', '$2y$10$QEj6rdIfAPqZ6keW6RfK3eHq2aXUzpQBJnkE1I8G8AdLuWUWqHq.2', NULL, NULL, 'user', '2025-02-12 10:21:38'),
(2, 'John Doe', 'john.doe@example.com', '$2y$10$Egh.qGHP9UIx1iG4dLioyuCvn60bY7LNWvyQIi2AbCr2uH1QzGaGq', NULL, NULL, 'instructor', '2025-02-13 07:34:08'),
(3, 'Company Deer', 'reev.info@gmail.com', '$2y$10$YJP/Sl6A8yvCK4jzX9GyWOuJcXdh7ku6gDHQLRj1nhRGIYrm2Jr4e', NULL, 'cv_3_1744659407.pdf', 'user', '2025-02-13 07:38:46'),
(4, 'New Admin', 'reevsolo@gmail.com', '$2a$12$GkMNVVWlBJL53lH1ylwvf.DKOUB1tsXfH4CnLKpVOANyEgVXYq4oO', 'profile_1743677750_DummySS.png', NULL, 'admin', '2025-02-13 07:47:41'),
(5, 'New Instructor', 'neha@gmail.com', '$2a$12$GkMNVVWlBJL53lH1ylwvf.DKOUB1tsXfH4CnLKpVOANyEgVXYq4oO', NULL, 'cv_5_1744650330.pdf', 'user', '2025-02-13 07:51:52'),
(6, 'New Employer', 'emp@gmail.com', '$2y$10$yT1TW7KKEpLRIFGDwwjv2OtDhT1o84L42q2b.jQ77/xkXfFg12jhe', NULL, NULL, 'instructor', '2025-02-13 07:52:07'),
(7, 'Garrison Obrien', 'mucybi@mailinator.com', '$2y$10$KiPGeNVqLCU2zBh.jy.07OKrJvW1FC9zM8WQFAOrbzFf9VTfP5ioi', NULL, NULL, 'instructor', '2025-04-14 16:43:02'),
(8, 'NBew User', 'user@email.com', '$2y$10$mnAJHzLannYDclZqNSd3I.iMbVT4.VxCA9i3HNVz6Eb5UdW65LWbK', NULL, NULL, 'user', '2025-04-14 17:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `instructor_id` (`instructor_id`);

--
-- Indexes for table `course_registrations`
--
ALTER TABLE `course_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `internships`
--
ALTER TABLE `internships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `internship_applications`
--
ALTER TABLE `internship_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `internship_id` (`internship_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `posted_by` (`posted_by`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `pioneerhub_info`
--
ALTER TABLE `pioneerhub_info`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `submitted_by` (`submitted_by`);

--
-- Indexes for table `project_collaborations`
--
ALTER TABLE `project_collaborations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `course_registrations`
--
ALTER TABLE `course_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `internships`
--
ALTER TABLE `internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `internship_applications`
--
ALTER TABLE `internship_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `pioneerhub_info`
--
ALTER TABLE `pioneerhub_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `project_collaborations`
--
ALTER TABLE `project_collaborations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`instructor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `course_registrations`
--
ALTER TABLE `course_registrations`
  ADD CONSTRAINT `course_registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_registrations_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internships`
--
ALTER TABLE `internships`
  ADD CONSTRAINT `internships_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `internship_applications`
--
ALTER TABLE `internship_applications`
  ADD CONSTRAINT `internship_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `internship_applications_ibfk_2` FOREIGN KEY (`internship_id`) REFERENCES `internships` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD CONSTRAINT `job_applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_applications_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_collaborations`
--
ALTER TABLE `project_collaborations`
  ADD CONSTRAINT `project_collaborations_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_collaborations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
