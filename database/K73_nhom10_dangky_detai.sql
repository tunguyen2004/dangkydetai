CREATE DATABASE IF NOT EXISTS `K73_nhom10_dangky_detai`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `K73_nhom10_dangky_detai`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `topic_registrations`;
DROP TABLE IF EXISTS `group_invitations`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `student_groups`;
DROP TABLE IF EXISTS `topics`;
DROP TABLE IF EXISTS `class_students`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
  `student_code` VARCHAR(30) DEFAULT NULL,
  `teacher_code` VARCHAR(30) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_student_code_unique` (`student_code`),
  UNIQUE KEY `users_teacher_code_unique` (`teacher_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `semesters` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `group_start` DATE NOT NULL,
  `group_end` DATE NOT NULL,
  `topic_start` DATE NOT NULL,
  `topic_end` DATE NOT NULL,
  `status` ENUM('draft','open','closed') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semester_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `course_code` VARCHAR(40) NOT NULL,
  `min_members` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `max_members` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `max_groups` SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `allow_self_group` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `classes_semester_id_fk` (`semester_id`),
  KEY `classes_teacher_id_fk` (`teacher_id`),
  CONSTRAINT `classes_semester_id_fk` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `classes_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `class_students` (
  `class_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`, `student_id`),
  KEY `class_students_student_id_fk` (`student_id`),
  CONSTRAINT `class_students_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_students_student_id_fk` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `technology` VARCHAR(180) DEFAULT NULL,
  `max_groups` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topics_class_code_unique` (`class_id`, `code`),
  KEY `topics_teacher_id_fk` (`teacher_id`),
  CONSTRAINT `topics_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topics_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `student_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `join_code` VARCHAR(20) NOT NULL,
  `status` ENUM('forming','registered','approved','locked') NOT NULL DEFAULT 'forming',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_groups_join_code_unique` (`join_code`),
  KEY `student_groups_class_id_fk` (`class_id`),
  KEY `student_groups_created_by_fk` (`created_by`),
  CONSTRAINT `student_groups_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('leader','member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `user_id`),
  UNIQUE KEY `group_members_user_unique` (`user_id`),
  CONSTRAINT `group_members_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_invitations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `invited_user_id` INT UNSIGNED NOT NULL,
  `invited_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','accepted','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_invitation_unique` (`group_id`, `invited_user_id`, `status`),
  KEY `group_invitations_invited_user_id_fk` (`invited_user_id`),
  CONSTRAINT `group_invitations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_invitations_invited_by_fk` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `group_invitations_invited_user_id_fk` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `topic_registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `topic_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','approved','rejected','revision') NOT NULL DEFAULT 'pending',
  `note` TEXT DEFAULT NULL,
  `teacher_feedback` TEXT DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `topic_registrations_group_id_fk` (`group_id`),
  KEY `topic_registrations_topic_id_fk` (`topic_id`),
  CONSTRAINT `topic_registrations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_registrations_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_registrations_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `topic_registrations_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `detail` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_fk` (`user_id`),
  CONSTRAINT `activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `student_code`, `teacher_code`, `phone`) VALUES
(1, 'Quản trị hệ thống', 'admin@k73.test', '$2y$10$flpFqwn.QCcCmtwM7QYarOdGgyWana2T4zjMfCjuyjZ3lliQ35e.e', 'admin', NULL, NULL, '0900000000'),
(2, 'ThS. Nguyễn Thu Hà', 'giangvien@k73.test', '$2y$10$MfJhPLqTOpygvqOfNr4EPO1GRlJKXifkUqWbUy6DesEKngfR4dsAC', 'teacher', NULL, 'GV001', '0911000001'),
(3, 'Nguyễn Minh An', 'sv01@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV001', NULL, '0922000001'),
(4, 'Trần Hoàng Bảo', 'sv02@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV002', NULL, '0922000002'),
(5, 'Lê Quốc Cường', 'sv03@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV003', NULL, '0922000003'),
(6, 'Phạm Thảo Duyên', 'sv04@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV004', NULL, '0922000004'),
(7, 'Vũ Gia Huy', 'sv05@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV005', NULL, '0922000005'),
(8, 'Đỗ Khánh Linh', 'sv06@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV006', NULL, '0922000006');

INSERT INTO `semesters` (`id`, `name`, `group_start`, `group_end`, `topic_start`, `topic_end`, `status`) VALUES
(1, 'Học kỳ 2 năm học 2025-2026', '2026-06-01', '2026-07-15', '2026-06-10', '2026-08-01', 'open');

INSERT INTO `classes` (`id`, `semester_id`, `teacher_id`, `name`, `course_code`, `min_members`, `max_members`, `max_groups`, `allow_self_group`) VALUES
(1, 1, 2, 'Công nghệ web - K73.CNTT01', 'WEB301', 2, 4, 12, 1);

INSERT INTO `class_students` (`class_id`, `student_id`) VALUES
(1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8);

INSERT INTO `topics` (`id`, `class_id`, `teacher_id`, `code`, `title`, `description`, `technology`, `max_groups`, `status`) VALUES
(1, 1, 2, 'DT01', 'Hệ thống quản lý đăng ký đề tài', 'Xây dựng website hỗ trợ sinh viên lập nhóm, đăng ký đề tài và giảng viên phê duyệt.', 'PHP, MySQL, HTML, CSS', 1, 'open'),
(2, 1, 2, 'DT02', 'Website quản lý lịch tư vấn học tập', 'Hỗ trợ sinh viên đăng ký lịch tư vấn, giảng viên xác nhận và theo dõi trạng thái.', 'PHP, MySQL', 2, 'open'),
(3, 1, 2, 'DT03', 'Quản lý phòng máy bằng mã QR', 'Sinh viên quét mã QR để đăng ký sử dụng phòng máy và cán bộ theo dõi lượt sử dụng.', 'PHP, MySQL, QR Code', 1, 'open'),
(4, 1, 2, 'DT04', 'Hệ thống theo dõi tiến độ bài tập lớn', 'Theo dõi mốc nộp đề cương, báo cáo, source code và trạng thái hoàn thành của nhóm.', 'PHP, MySQL, Bootstrap', 2, 'open');

INSERT INTO `student_groups` (`id`, `class_id`, `name`, `join_code`, `status`, `created_by`) VALUES
(1, 1, 'Nhóm Alpha', 'ALPHA73', 'registered', 3),
(2, 1, 'Nhóm Beta', 'BETA73', 'approved', 5),
(3, 1, 'Nhóm Gamma', 'GAMMA73', 'forming', 7);

INSERT INTO `group_members` (`group_id`, `user_id`, `role`) VALUES
(1, 3, 'leader'), (1, 4, 'member'),
(2, 5, 'leader'), (2, 6, 'member'),
(3, 7, 'leader');

INSERT INTO `group_invitations` (`id`, `group_id`, `invited_user_id`, `invited_by`, `status`) VALUES
(1, 3, 8, 7, 'pending');

INSERT INTO `topic_registrations` (`id`, `group_id`, `topic_id`, `requested_by`, `status`, `note`, `teacher_feedback`, `reviewed_by`, `reviewed_at`) VALUES
(1, 1, 1, 3, 'pending', 'Nhóm muốn xây dựng hệ thống đúng với yêu cầu môn Công nghệ web.', NULL, NULL, NULL),
(2, 2, 2, 5, 'approved', 'Nhóm có kinh nghiệm làm chức năng lịch hẹn.', 'Đồng ý đề tài. Nhóm cần bổ sung sơ đồ CSDL trong báo cáo.', 2, '2026-06-20 09:00:00');

INSERT INTO `activity_logs` (`user_id`, `action`, `detail`) VALUES
(1, 'seed', 'Khởi tạo dữ liệu mẫu'),
(2, 'approve_registration', 'Duyệt đề tài cho Nhóm Beta');
