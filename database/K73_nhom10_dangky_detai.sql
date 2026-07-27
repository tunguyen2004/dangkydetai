CREATE DATABASE IF NOT EXISTS `K73_nhom10_dangky_detai` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `K73_nhom10_dangky_detai`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `topic_registrations`;
DROP TABLE IF EXISTS `group_invitations`;
DROP TABLE IF EXISTS `group_members`;
DROP TABLE IF EXISTS `student_groups`;
DROP TABLE IF EXISTS `topic_classes`;
DROP TABLE IF EXISTS `topics`;
DROP TABLE IF EXISTS `registration_period_classes`;
DROP TABLE IF EXISTS `class_students`;
DROP TABLE IF EXISTS `classes`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `registration_periods`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
  `user_code` VARCHAR(30) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_user_code_unique` (`user_code`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `requested_ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `password_reset_tokens_hash_unique` (`token_hash`),
  KEY `password_reset_tokens_user_active_idx` (`user_id`, `used_at`, `expires_at`),
  CONSTRAINT `password_reset_tokens_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `courses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courses_code_unique` (`code`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `registration_periods` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `group_start` DATETIME NOT NULL,
  `group_end` DATETIME NOT NULL,
  `register_start` DATETIME NOT NULL,
  `register_end` DATETIME NOT NULL,
  `status` ENUM('draft', 'open', 'closed') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` INT UNSIGNED NOT NULL,
  `teacher_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `min_members` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `max_members` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `max_groups` SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  `allow_self_group` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `classes_course_id_fk` (`course_id`),
  KEY `classes_teacher_id_fk` (`teacher_id`),
  CONSTRAINT `classes_course_id_fk` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `classes_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `class_students` (
  `class_id` INT UNSIGNED NOT NULL,
  `student_id` INT UNSIGNED NOT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`, `student_id`),
  KEY `class_students_student_id_fk` (`student_id`),
  CONSTRAINT `class_students_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_students_student_id_fk` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `registration_period_classes` (
  `registration_period_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`registration_period_id`, `class_id`),
  KEY `registration_period_classes_class_id_fk` (`class_id`),
  CONSTRAINT `registration_period_classes_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `registration_period_classes_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `topics` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(40) NOT NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NOT NULL,
  `min_members` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `max_members` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topics_teacher_code_unique` (`teacher_id`, `code`),
  CONSTRAINT `topics_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `topic_classes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `topic_id` INT UNSIGNED NOT NULL,
  `class_id` INT UNSIGNED NOT NULL,
  `registration_period_id` INT UNSIGNED NOT NULL,
  `max_groups` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_classes_unique` (`topic_id`, `class_id`, `registration_period_id`),
  KEY `topic_classes_class_period_fk` (`registration_period_id`, `class_id`),
  KEY `topic_classes_class_id_fk` (`class_id`),
  CONSTRAINT `topic_classes_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_period_class_fk` FOREIGN KEY (`registration_period_id`, `class_id`) REFERENCES `registration_period_classes` (`registration_period_id`, `class_id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `student_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `class_id` INT UNSIGNED NOT NULL,
  `registration_period_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `join_code` VARCHAR(20) NOT NULL,
  `status` ENUM('forming', 'registered', 'locked') NOT NULL DEFAULT 'forming',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_groups_join_code_unique` (`join_code`),
  KEY `student_groups_class_period_fk` (`registration_period_id`, `class_id`),
  KEY `student_groups_class_id_fk` (`class_id`),
  KEY `student_groups_created_by_fk` (`created_by`),
  CONSTRAINT `student_groups_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_period_class_fk` FOREIGN KEY (`registration_period_id`, `class_id`) REFERENCES `registration_period_classes` (`registration_period_id`, `class_id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `group_members` (
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `role` ENUM('leader', 'member') NOT NULL DEFAULT 'member',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `user_id`),
  KEY `group_members_user_id_fk` (`user_id`),
  CONSTRAINT `group_members_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `group_invitations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `invited_user_id` INT UNSIGNED NOT NULL,
  `invited_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'accepted', 'rejected', 'cancelled', 'expired') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_invitations_group_status_idx` (`group_id`, `status`),
  KEY `group_invitations_invited_user_id_fk` (`invited_user_id`),
  KEY `group_invitations_invited_by_fk` (`invited_by`),
  CONSTRAINT `group_invitations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_invitations_invited_by_fk` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `group_invitations_invited_user_id_fk` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `topic_registrations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` INT UNSIGNED NOT NULL,
  `topic_class_id` INT UNSIGNED NOT NULL,
  `requested_by` INT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'revoked') NOT NULL DEFAULT 'pending',
  `teacher_feedback` TEXT DEFAULT NULL,
  `reviewed_by` INT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active_group_id` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_registrations_one_active_group_unique` (`active_group_id`),
  KEY `topic_registrations_group_id_fk` (`group_id`),
  KEY `topic_registrations_topic_class_id_fk` (`topic_class_id`),
  KEY `topic_registrations_requested_by_fk` (`requested_by`),
  KEY `topic_registrations_reviewed_by_fk` (`reviewed_by`),
  CONSTRAINT `topic_registrations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_registrations_topic_class_id_fk` FOREIGN KEY (`topic_class_id`) REFERENCES `topic_classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_registrations_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `topic_registrations_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(80) NOT NULL,
  `detail` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_fk` (`user_id`),
  CONSTRAINT `activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

DELIMITER $$

CREATE TRIGGER `topic_registrations_active_bi`
BEFORE INSERT ON `topic_registrations`
FOR EACH ROW
BEGIN
  SET NEW.`active_group_id` = IF(NEW.`status` IN ('pending', 'approved'), NEW.`group_id`, NULL);
END$$

CREATE TRIGGER `topic_registrations_active_bu`
BEFORE UPDATE ON `topic_registrations`
FOR EACH ROW
BEGIN
  SET NEW.`active_group_id` = IF(NEW.`status` IN ('pending', 'approved'), NEW.`group_id`, NULL);
END$$

DELIMITER ;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `user_code`, `phone`) VALUES
(1, 'Quản trị hệ thống', 'admin@k73.test', '$2y$10$flpFqwn.QCcCmtwM7QYarOdGgyWana2T4zjMfCjuyjZ3lliQ35e.e', 'admin', 'AD001', '0900000000'),
(2, 'ThS. Nguyễn Thu Hà', 'giangvien@k73.test', '$2y$10$MfJhPLqTOpygvqOfNr4EPO1GRlJKXifkUqWbUy6DesEKngfR4dsAC', 'teacher', 'GV001', '0911000001'),
(3, 'Nguyễn Minh An', 'sv01@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV001', '0922000001'),
(4, 'Trần Hoàng Bảo', 'sv02@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV002', '0922000002'),
(5, 'Lê Quốc Cường', 'sv03@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV003', '0922000003'),
(6, 'Phạm Thảo Duyên', 'sv04@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV004', '0922000004'),
(7, 'Vũ Gia Huy', 'sv05@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV005', '0922000005'),
(8, 'Đỗ Khánh Linh', 'sv06@k73.test', '$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu', 'student', 'SV006', '0922000006');

INSERT INTO `courses` (`id`, `code`, `name`, `description`) VALUES
(1, 'WEB301', 'Công nghệ Web', 'Học phần xây dựng ứng dụng web bằng PHP, MySQL, HTML, CSS và JavaScript.'),
(2, 'DBS201', 'Cơ sở dữ liệu', 'Học phần thiết kế và quản trị cơ sở dữ liệu quan hệ.');

INSERT INTO `registration_periods` (`id`, `name`, `group_start`, `group_end`, `register_start`, `register_end`, `status`) VALUES
(1, 'Đợt đăng ký đề tài bài tập lớn HK2 2025-2026', '2026-06-01 00:00:00', '2026-07-20 23:59:59', '2026-06-10 00:00:00', '2026-08-01 23:59:59', 'open');

INSERT INTO `classes` (`id`, `course_id`, `teacher_id`, `name`, `min_members`, `max_members`, `max_groups`, `allow_self_group`) VALUES
(1, 1, 2, 'Công nghệ Web - K73.CNTT01', 2, 4, 12, 1),
(2, 1, 2, 'Công nghệ Web - K73.CNTT02', 2, 5, 14, 1);

INSERT INTO `class_students` (`class_id`, `student_id`) VALUES
(1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8),
(2, 3), (2, 4), (2, 7), (2, 8);

INSERT INTO `registration_period_classes` (`registration_period_id`, `class_id`) VALUES
(1, 1), (1, 2);

INSERT INTO `topics` (`id`, `teacher_id`, `code`, `title`, `description`, `min_members`, `max_members`) VALUES
(1, 2, 'DT01', 'Hệ thống quản lý đăng ký đề tài', 'Xây dựng website hỗ trợ sinh viên lập nhóm, đăng ký đề tài và giảng viên phê duyệt.', 2, 4),
(2, 2, 'DT02', 'Website quản lý lịch tư vấn học tập', 'Hỗ trợ sinh viên đăng ký lịch tư vấn, giảng viên xác nhận và theo dõi trạng thái.', 2, 3),
(3, 2, 'DT03', 'Quản lý phòng máy bằng mã QR', 'Sinh viên quét mã QR để đăng ký sử dụng phòng máy và cán bộ theo dõi lượt sử dụng.', 2, 4),
(4, 2, 'DT04', 'Hệ thống theo dõi tiến độ bài tập lớn', 'Theo dõi mốc nộp đề cương, báo cáo, source code và trạng thái hoàn thành của nhóm.', 3, 5);

INSERT INTO `topic_classes` (`id`, `topic_id`, `class_id`, `registration_period_id`, `max_groups`, `status`) VALUES
(1, 1, 1, 1, 1, 'open'),
(2, 2, 1, 1, 2, 'open'),
(3, 3, 1, 1, 1, 'open'),
(4, 4, 1, 1, 2, 'open'),
(5, 1, 2, 1, 3, 'open'),
(6, 2, 2, 1, 1, 'open');

INSERT INTO `student_groups` (`id`, `class_id`, `registration_period_id`, `name`, `join_code`, `status`, `created_by`) VALUES
(1, 1, 1, 'Nhóm Alpha', 'ALPHA73', 'registered', 3),
(2, 1, 1, 'Nhóm Beta', 'BETA73', 'locked', 5),
(3, 1, 1, 'Nhóm Gamma', 'GAMMA73', 'forming', 7);

INSERT INTO `group_members` (`group_id`, `user_id`, `role`) VALUES
(1, 3, 'leader'),
(1, 4, 'member'),
(2, 5, 'leader'),
(2, 6, 'member'),
(3, 7, 'leader');

INSERT INTO `group_invitations` (`id`, `group_id`, `invited_user_id`, `invited_by`, `status`) VALUES
(1, 3, 8, 7, 'pending');

INSERT INTO `topic_registrations` (`id`, `group_id`, `topic_class_id`, `requested_by`, `status`, `teacher_feedback`, `reviewed_by`, `reviewed_at`) VALUES
(1, 1, 1, 3, 'pending', NULL, NULL, NULL),
(2, 2, 2, 5, 'approved', 'Đồng ý đề tài. Nhóm cần bổ sung sơ đồ CSDL trong báo cáo.', 2, '2026-06-20 09:00:00');

INSERT INTO `activity_logs` (`user_id`, `action`, `detail`) VALUES
(1, 'seed', 'Khởi tạo dữ liệu mẫu'),
(2, 'approve_registration', 'Duyệt đề tài cho Nhóm Beta');
