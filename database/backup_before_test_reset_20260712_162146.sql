-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: K73_nhom10_dangky_detai
-- ------------------------------------------------------
-- Server version	8.0.45

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_fk` (`user_id`),
  CONSTRAINT `activity_logs_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'seed','Khởi tạo dữ liệu mẫu','2026-07-10 16:24:14'),(2,2,'approve_registration','Duyệt đề tài cho Nhóm Beta','2026-07-10 16:24:14'),(3,1,'login','Đăng nhập hệ thống','2026-07-10 16:33:34'),(4,1,'login','Đăng nhập hệ thống','2026-07-10 16:37:39'),(5,1,'login','Đăng nhập hệ thống','2026-07-10 17:12:13'),(6,3,'login','Đăng nhập hệ thống','2026-07-10 17:13:30'),(7,2,'login','Đăng nhập hệ thống','2026-07-10 17:14:48'),(8,2,'review_registration','Đã duyệt đăng ký #1','2026-07-10 17:14:50'),(9,3,'login','Đăng nhập hệ thống','2026-07-10 17:15:14'),(10,2,'create_topic','Tạo đề tài gốc adsfasdf','2026-07-10 17:15:56'),(11,2,'assign_topic_class','Gán đề tài #5 cho lớp #1','2026-07-10 17:16:08'),(12,1,'login','Đăng nhập hệ thống','2026-07-10 17:50:51'),(13,2,'login','Đăng nhập hệ thống','2026-07-10 17:52:43'),(14,1,'login','Đăng nhập hệ thống','2026-07-10 18:04:18'),(15,3,'login','Đăng nhập hệ thống','2026-07-10 18:09:04'),(16,1,'login','Đăng nhập hệ thống','2026-07-10 19:12:30'),(17,1,'login','Đăng nhập hệ thống','2026-07-10 22:43:46'),(18,1,'extend_registration_period','Cập nhật thời gian đợt #1','2026-07-10 22:44:34'),(19,1,'login','Đăng nhập hệ thống','2026-07-10 22:55:49'),(20,1,'create_user','Tạo tài khoản svtest_auto@k73.test với mã SV007','2026-07-10 22:55:49'),(21,1,'create_user','Tạo tài khoản anhtu105182@gmail.com với mã SV007','2026-07-10 22:56:56'),(22,1,'toggle_user_lock','Khóa/mở tài khoản #10','2026-07-10 22:57:20'),(23,1,'toggle_user_lock','Khóa/mở tài khoản #10','2026-07-10 22:57:23'),(24,1,'login','Đăng nhập hệ thống','2026-07-11 23:59:03'),(25,1,'login','Đăng nhập hệ thống','2026-07-12 13:31:27'),(26,1,'login','Đăng nhập hệ thống','2026-07-12 14:11:33'),(27,1,'login','Đăng nhập hệ thống','2026-07-12 14:13:56'),(28,1,'reset_password','Reset mật khẩu tài khoản #10','2026-07-12 14:14:07'),(29,10,'login','Đăng nhập hệ thống','2026-07-12 14:14:35'),(30,10,'change_password','Đổi mật khẩu','2026-07-12 14:14:46'),(31,3,'login','Đăng nhập hệ thống','2026-07-12 14:15:10'),(32,3,'create_group','Tạo nhóm tuna','2026-07-12 14:15:20'),(33,1,'login','Đăng nhập hệ thống','2026-07-12 16:19:09');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_students`
--

DROP TABLE IF EXISTS `class_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_students` (
  `class_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`,`student_id`),
  KEY `class_students_student_id_fk` (`student_id`),
  CONSTRAINT `class_students_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `class_students_student_id_fk` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_students`
--

LOCK TABLES `class_students` WRITE;
/*!40000 ALTER TABLE `class_students` DISABLE KEYS */;
INSERT INTO `class_students` VALUES (1,3,'2026-07-10 16:24:14'),(1,4,'2026-07-10 16:24:14'),(1,5,'2026-07-10 16:24:14'),(1,6,'2026-07-10 16:24:14'),(1,7,'2026-07-10 16:24:14'),(1,8,'2026-07-10 16:24:14'),(2,3,'2026-07-10 16:24:14'),(2,4,'2026-07-10 16:24:14'),(2,7,'2026-07-10 16:24:14'),(2,8,'2026-07-10 16:24:14');
/*!40000 ALTER TABLE `class_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `course_id` int unsigned NOT NULL,
  `teacher_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_members` tinyint unsigned NOT NULL DEFAULT '2',
  `max_members` tinyint unsigned NOT NULL DEFAULT '4',
  `max_groups` smallint unsigned NOT NULL DEFAULT '20',
  `allow_self_group` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `classes_course_id_fk` (`course_id`),
  KEY `classes_teacher_id_fk` (`teacher_id`),
  CONSTRAINT `classes_course_id_fk` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `classes_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,1,2,'Công nghệ Web - K73.CNTT01',2,4,12,1,'2026-07-10 16:24:14'),(2,1,2,'Công nghệ Web - K73.CNTT02',2,5,14,1,'2026-07-10 16:24:14');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `courses_code_unique` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'WEB301','Công nghệ Web','Học phần xây dựng ứng dụng web bằng PHP, MySQL, HTML, CSS và JavaScript.','2026-07-10 16:24:14'),(2,'DBS201','Cơ sở dữ liệu','Học phần thiết kế và quản trị cơ sở dữ liệu quan hệ.','2026-07-10 16:24:14');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_invitations`
--

DROP TABLE IF EXISTS `group_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_invitations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL,
  `invited_user_id` int unsigned NOT NULL,
  `invited_by` int unsigned NOT NULL,
  `status` enum('pending','accepted','rejected','cancelled','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_invitations_group_status_idx` (`group_id`,`status`),
  KEY `group_invitations_invited_user_id_fk` (`invited_user_id`),
  KEY `group_invitations_invited_by_fk` (`invited_by`),
  CONSTRAINT `group_invitations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_invitations_invited_by_fk` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `group_invitations_invited_user_id_fk` FOREIGN KEY (`invited_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_invitations`
--

LOCK TABLES `group_invitations` WRITE;
/*!40000 ALTER TABLE `group_invitations` DISABLE KEYS */;
INSERT INTO `group_invitations` VALUES (1,3,8,7,'pending','2026-07-10 16:24:14',NULL);
/*!40000 ALTER TABLE `group_invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `group_members`
--

DROP TABLE IF EXISTS `group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_members` (
  `group_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `role` enum('leader','member') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`,`user_id`),
  KEY `group_members_user_id_fk` (`user_id`),
  CONSTRAINT `group_members_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_members_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_members`
--

LOCK TABLES `group_members` WRITE;
/*!40000 ALTER TABLE `group_members` DISABLE KEYS */;
INSERT INTO `group_members` VALUES (1,3,'leader','2026-07-10 16:24:14'),(1,4,'member','2026-07-10 16:24:14'),(2,5,'leader','2026-07-10 16:24:14'),(2,6,'member','2026-07-10 16:24:14'),(3,7,'leader','2026-07-10 16:24:14');
/*!40000 ALTER TABLE `group_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registration_period_classes`
--

DROP TABLE IF EXISTS `registration_period_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_period_classes` (
  `registration_period_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`registration_period_id`,`class_id`),
  KEY `registration_period_classes_class_id_fk` (`class_id`),
  CONSTRAINT `registration_period_classes_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `registration_period_classes_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_period_classes`
--

LOCK TABLES `registration_period_classes` WRITE;
/*!40000 ALTER TABLE `registration_period_classes` DISABLE KEYS */;
INSERT INTO `registration_period_classes` VALUES (1,1,'2026-07-10 16:24:14'),(1,2,'2026-07-10 16:24:14');
/*!40000 ALTER TABLE `registration_period_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `registration_periods`
--

DROP TABLE IF EXISTS `registration_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_periods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `group_start` datetime NOT NULL,
  `group_end` datetime NOT NULL,
  `register_start` datetime NOT NULL,
  `register_end` datetime NOT NULL,
  `status` enum('draft','open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_periods`
--

LOCK TABLES `registration_periods` WRITE;
/*!40000 ALTER TABLE `registration_periods` DISABLE KEYS */;
INSERT INTO `registration_periods` VALUES (1,'Đợt đăng ký đề tài bài tập lớn HK2 2025-2026','2026-06-01 00:00:00','2026-07-20 23:59:00','2026-06-10 00:00:00','2026-08-01 23:59:00','open','2026-07-10 16:24:14');
/*!40000 ALTER TABLE `registration_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_groups`
--

DROP TABLE IF EXISTS `student_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned NOT NULL,
  `registration_period_id` int unsigned NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `join_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('forming','registered','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'forming',
  `created_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_groups_join_code_unique` (`join_code`),
  KEY `student_groups_class_period_fk` (`registration_period_id`,`class_id`),
  KEY `student_groups_class_id_fk` (`class_id`),
  KEY `student_groups_created_by_fk` (`created_by`),
  CONSTRAINT `student_groups_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `student_groups_period_class_fk` FOREIGN KEY (`registration_period_id`, `class_id`) REFERENCES `registration_period_classes` (`registration_period_id`, `class_id`) ON DELETE CASCADE,
  CONSTRAINT `student_groups_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_groups`
--

LOCK TABLES `student_groups` WRITE;
/*!40000 ALTER TABLE `student_groups` DISABLE KEYS */;
INSERT INTO `student_groups` VALUES (1,1,1,'Nhóm Alpha','ALPHA73','locked',3,'2026-07-10 16:24:14'),(2,1,1,'Nhóm Beta','BETA73','locked',5,'2026-07-10 16:24:14'),(3,1,1,'Nhóm Gamma','GAMMA73','forming',7,'2026-07-10 16:24:14');
/*!40000 ALTER TABLE `student_groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_classes`
--

DROP TABLE IF EXISTS `topic_classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `topic_id` int unsigned NOT NULL,
  `class_id` int unsigned NOT NULL,
  `registration_period_id` int unsigned NOT NULL,
  `max_groups` tinyint unsigned NOT NULL DEFAULT '1',
  `status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_classes_unique` (`topic_id`,`class_id`,`registration_period_id`),
  KEY `topic_classes_class_period_fk` (`registration_period_id`,`class_id`),
  KEY `topic_classes_class_id_fk` (`class_id`),
  CONSTRAINT `topic_classes_class_id_fk` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_period_class_fk` FOREIGN KEY (`registration_period_id`, `class_id`) REFERENCES `registration_period_classes` (`registration_period_id`, `class_id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_period_id_fk` FOREIGN KEY (`registration_period_id`) REFERENCES `registration_periods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_classes_topic_id_fk` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_classes`
--

LOCK TABLES `topic_classes` WRITE;
/*!40000 ALTER TABLE `topic_classes` DISABLE KEYS */;
INSERT INTO `topic_classes` VALUES (1,1,1,1,1,'open','2026-07-10 16:24:14'),(2,2,1,1,2,'open','2026-07-10 16:24:14'),(3,3,1,1,1,'open','2026-07-10 16:24:14'),(4,4,1,1,2,'open','2026-07-10 16:24:14'),(5,1,2,1,3,'open','2026-07-10 16:24:14'),(6,2,2,1,1,'open','2026-07-10 16:24:14'),(7,5,1,1,3,'open','2026-07-10 17:16:08');
/*!40000 ALTER TABLE `topic_classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `topic_registrations`
--

DROP TABLE IF EXISTS `topic_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topic_registrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int unsigned NOT NULL,
  `topic_class_id` int unsigned NOT NULL,
  `requested_by` int unsigned NOT NULL,
  `status` enum('pending','approved','rejected','cancelled','revoked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `teacher_feedback` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active_group_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topic_registrations_one_active_group_unique` (`active_group_id`),
  KEY `topic_registrations_group_id_fk` (`group_id`),
  KEY `topic_registrations_topic_class_id_fk` (`topic_class_id`),
  KEY `topic_registrations_requested_by_fk` (`requested_by`),
  KEY `topic_registrations_reviewed_by_fk` (`reviewed_by`),
  CONSTRAINT `topic_registrations_group_id_fk` FOREIGN KEY (`group_id`) REFERENCES `student_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `topic_registrations_requested_by_fk` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `topic_registrations_reviewed_by_fk` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `topic_registrations_topic_class_id_fk` FOREIGN KEY (`topic_class_id`) REFERENCES `topic_classes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_registrations`
--

LOCK TABLES `topic_registrations` WRITE;
/*!40000 ALTER TABLE `topic_registrations` DISABLE KEYS */;
INSERT INTO `topic_registrations` VALUES (1,1,1,3,'approved','',2,'2026-07-10 17:14:50','2026-07-10 16:24:14',1),(2,2,2,5,'approved','Đồng ý đề tài. Nhóm cần bổ sung sơ đồ CSDL trong báo cáo.',2,'2026-06-20 09:00:00','2026-07-10 16:24:14',2);
/*!40000 ALTER TABLE `topic_registrations` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `topic_registrations_active_bi` BEFORE INSERT ON `topic_registrations` FOR EACH ROW BEGIN
  SET NEW.`active_group_id` = IF(NEW.`status` IN ('pending', 'approved'), NEW.`group_id`, NULL);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `topic_registrations_active_bu` BEFORE UPDATE ON `topic_registrations` FOR EACH ROW BEGIN
  SET NEW.`active_group_id` = IF(NEW.`status` IN ('pending', 'approved'), NEW.`group_id`, NULL);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `topics`
--

DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topics` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(220) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_members` tinyint unsigned NOT NULL DEFAULT '2',
  `max_members` tinyint unsigned NOT NULL DEFAULT '4',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `topics_teacher_code_unique` (`teacher_id`,`code`),
  CONSTRAINT `topics_teacher_id_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topics`
--

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
INSERT INTO `topics` VALUES (1,2,'DT01','Hệ thống quản lý đăng ký đề tài','Xây dựng website hỗ trợ sinh viên lập nhóm, đăng ký đề tài và giảng viên phê duyệt.',2,4,'2026-07-10 16:24:14'),(2,2,'DT02','Website quản lý lịch tư vấn học tập','Hỗ trợ sinh viên đăng ký lịch tư vấn, giảng viên xác nhận và theo dõi trạng thái.',2,3,'2026-07-10 16:24:14'),(3,2,'DT03','Quản lý phòng máy bằng mã QR','Sinh viên quét mã QR để đăng ký sử dụng phòng máy và cán bộ theo dõi lượt sử dụng.',2,4,'2026-07-10 16:24:14'),(4,2,'DT04','Hệ thống theo dõi tiến độ bài tập lớn','Theo dõi mốc nộp đề cương, báo cáo, source code và trạng thái hoàn thành của nhóm.',3,5,'2026-07-10 16:24:14'),(5,2,'adsfasdf','ádfasdfsafasd','ádfasdfasdasdfasdfasdfasdf',2,4,'2026-07-10 17:15:56');
/*!40000 ALTER TABLE `topics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','teacher','student') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student',
  `user_code` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_locked` tinyint(1) NOT NULL DEFAULT '0',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_user_code_unique` (`user_code`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị hệ thống','admin@k73.test','$2y$10$flpFqwn.QCcCmtwM7QYarOdGgyWana2T4zjMfCjuyjZ3lliQ35e.e','admin','AD001','0900000000',0,0,'2026-07-10 16:24:14'),(2,'ThS. Nguyễn Thu Hà','giangvien@k73.test','$2y$10$MfJhPLqTOpygvqOfNr4EPO1GRlJKXifkUqWbUy6DesEKngfR4dsAC','teacher','GV001','0911000001',0,0,'2026-07-10 16:24:14'),(3,'Nguyễn Minh An','sv01@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV001','0922000001',0,0,'2026-07-10 16:24:14'),(4,'Trần Hoàng Bảo','sv02@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV002','0922000002',0,0,'2026-07-10 16:24:14'),(5,'Lê Quốc Cường','sv03@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV003','0922000003',0,0,'2026-07-10 16:24:14'),(6,'Phạm Thảo Duyên','sv04@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV004','0922000004',0,0,'2026-07-10 16:24:14'),(7,'Vũ Gia Huy','sv05@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV005','0922000005',0,0,'2026-07-10 16:24:14'),(8,'Đỗ Khánh Linh','sv06@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV006','0922000006',0,0,'2026-07-10 16:24:14'),(10,'Nguyễn Anh Tú','anhtu105182@gmail.com','$2y$10$mPFK8m7tY1uIbHB/E1kytOkGFWcjx68XjkZtKRt96pP06ZJHdqB2e','student','SV007','0383137092',0,0,'2026-07-10 22:56:56');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'K73_nhom10_dangky_detai'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-12 16:21:46
