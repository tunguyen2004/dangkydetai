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
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'create_course','Tạo học phần HM001','2026-07-12 16:33:15'),(3,1,'login','Đăng nhập hệ thống','2026-07-12 17:09:00'),(4,1,'login','Đăng nhập hệ thống','2026-07-12 17:43:56'),(5,1,'add_class_students','Gán 3 sinh viên vào lớp #2','2026-07-12 17:44:02'),(6,1,'create_registration_period','Tạo đợt đăng ký Đợt đăng ký đề tài bài tập lớn','2026-07-12 17:44:45'),(7,1,'assign_period_class','Gán đợt #1 cho lớp #2','2026-07-12 17:45:03'),(8,10,'login','Đăng nhập hệ thống','2026-07-12 17:45:33'),(9,10,'create_group','Tạo nhóm Nguyễn Anh Tú','2026-07-12 17:45:44'),(10,2,'login','Đăng nhập hệ thống','2026-07-12 17:50:01'),(11,2,'create_topic','Tạo đề tài gốc 123','2026-07-12 17:50:28'),(12,2,'assign_topic_class','Gán đề tài #1 cho lớp #2','2026-07-12 17:50:33'),(13,3,'login','Đăng nhập hệ thống','2026-07-12 17:51:27'),(14,10,'invite_member','Mời sv01@k73.test vào nhóm','2026-07-12 17:51:52'),(15,10,'register_topic','Nhóm Nguyễn Anh Tú đăng ký đề tài 123','2026-07-12 17:52:23'),(16,2,'review_registration','Đã duyệt đăng ký #1','2026-07-12 17:52:39'),(17,1,'create_user','Tạo tài khoản tunguyenanh2122004@gmail.com với mã GV002','2026-07-12 17:56:23'),(18,1,'login','Đăng nhập hệ thống','2026-07-12 17:57:54'),(19,1,'assign_period_class','Gán lớp #1 vào đợt #1','2026-07-12 18:00:47'),(20,1,'assign_period_class','Gán lớp #2 vào đợt #1','2026-07-12 18:00:52'),(24,1,'create_user','Tạo tài khoản thunguyen2122004@gmail.com với mã GV003','2026-07-12 18:20:12'),(27,10,'login','Đăng nhập hệ thống','2026-07-12 20:19:44'),(28,1,'login','Đăng nhập hệ thống','2026-07-12 20:20:00'),(29,3,'login','Đăng nhập hệ thống','2026-07-12 20:20:37'),(30,3,'create_group','Tạo nhóm sdaa','2026-07-12 20:20:58'),(31,1,'login','Đăng nhập hệ thống','2026-07-12 21:10:11'),(33,1,'login','Đăng nhập hệ thống','2026-07-12 21:33:23'),(34,2,'login','Đăng nhập hệ thống','2026-07-12 21:50:37'),(35,2,'login','Đăng nhập hệ thống','2026-07-12 21:50:47'),(39,1,'login','Đăng nhập hệ thống','2026-07-13 12:58:24'),(40,1,'login','Đăng nhập hệ thống','2026-07-13 23:25:51'),(41,1,'login','Đăng nhập hệ thống','2026-07-14 08:04:59'),(42,1,'login','Đăng nhập hệ thống','2026-07-14 09:09:19'),(43,1,'login','Đăng nhập hệ thống','2026-07-14 09:40:25'),(44,3,'login','Đăng nhập hệ thống','2026-07-14 09:42:06'),(45,1,'login','Đăng nhập hệ thống','2026-07-16 14:10:26'),(46,1,'login','Đăng nhập hệ thống','2026-07-16 14:26:27'),(47,2,'login','Đăng nhập hệ thống','2026-07-16 14:26:39'),(48,1,'extend_registration_period','Cập nhật thời gian đợt #1','2026-07-16 14:28:28'),(49,2,'teacher_create_group','Giảng viên tạo nhóm test','2026-07-16 14:28:56'),(50,12,'login','Đăng nhập hệ thống','2026-07-16 14:31:41'),(51,1,'reset_password','Reset mật khẩu tài khoản #12','2026-07-16 14:31:47'),(52,1,'reset_password','Reset mật khẩu tài khoản #10','2026-07-16 14:33:32'),(53,10,'login','Đăng nhập hệ thống','2026-07-16 14:33:36'),(54,10,'login','Đăng nhập hệ thống','2026-07-16 14:33:57'),(55,10,'change_password','Đổi mật khẩu','2026-07-16 14:34:19'),(56,1,'create_registration_period','Tạo đợt đăng ký test đợt đăng ký ','2026-07-16 14:36:39'),(57,1,'assign_period_class','Gán lớp #1 vào đợt #2','2026-07-16 14:36:53'),(58,1,'set_period_status','Cập nhật trạng thái đợt #2','2026-07-16 14:36:59'),(59,2,'teacher_create_group','Giảng viên tạo nhóm test1','2026-07-16 14:38:06'),(60,1,'reset_password','Reset mật khẩu tài khoản #8','2026-07-16 14:38:24'),(61,8,'login','Đăng nhập hệ thống','2026-07-16 14:38:36'),(62,8,'change_password','Đổi mật khẩu','2026-07-16 14:38:48'),(63,1,'add_class_students','Gán 1 sinh viên vào lớp #1','2026-07-16 14:39:31'),(64,8,'invite_member','Mời anhtu105182@gmail.com vào nhóm','2026-07-16 14:39:37'),(65,8,'invite_member','Mời sv03@k73.test vào nhóm','2026-07-16 14:42:10'),(66,8,'invite_member','Mời sv04@k73.test vào nhóm','2026-07-16 14:42:26'),(67,1,'login','Đăng nhập hệ thống','2026-07-20 22:23:33'),(68,1,'login','Đăng nhập hệ thống','2026-07-20 22:23:53'),(69,1,'login','Đăng nhập hệ thống','2026-07-21 08:42:14'),(70,2,'login','Đăng nhập hệ thống','2026-07-21 08:43:14'),(71,3,'login','Đăng nhập hệ thống','2026-07-21 08:44:46'),(72,1,'reset_password','Reset mật khẩu tài khoản #10','2026-07-21 08:45:34'),(73,10,'login','Đăng nhập hệ thống','2026-07-21 08:46:41'),(74,10,'change_password','Đổi mật khẩu','2026-07-21 08:46:56'),(75,1,'set_period_status','Cập nhật trạng thái đợt #2','2026-07-21 08:54:40'),(76,1,'set_period_status','Cập nhật trạng thái đợt #1','2026-07-21 08:54:43'),(77,1,'set_period_status','Cập nhật trạng thái đợt #1','2026-07-21 08:54:46'),(79,1,'set_period_status','Cập nhật trạng thái đợt #1','2026-07-21 09:02:00'),(80,NULL,'auto_close_registration_period','Tự động đóng đợt đăng ký #1 - Đợt đăng ký đề tài bài tập lớn vì đã hết thời gian.','2026-07-21 09:02:00'),(81,1,'create_registration_period','Tạo đợt đăng ký test 2','2026-07-21 09:04:01'),(82,1,'assign_period_class','Gán lớp #2 vào đợt #4','2026-07-21 09:04:09'),(83,1,'assign_period_class','Gán lớp #2 vào đợt #4','2026-07-21 09:04:16'),(84,1,'assign_period_class','Gán lớp #2 vào đợt #4','2026-07-21 09:04:23'),(85,NULL,'auto_close_registration_period','Tự động đóng đợt đăng ký #4 - test 2 vì đã hết thời gian.','2026-07-21 09:08:47'),(86,1,'login','Đăng nhập hệ thống','2026-07-21 10:01:43'),(87,1,'login','Đăng nhập hệ thống','2026-07-21 10:52:17'),(88,1,'create_registration_period','Tạo đợt đăng ký Đợt đăng ký đề tài bài tập lớn','2026-07-21 10:54:40'),(89,2,'login','Đăng nhập hệ thống','2026-07-21 10:54:57'),(90,1,'extend_registration_period','Cập nhật thời gian đợt #5','2026-07-21 10:55:52'),(91,1,'assign_period_class','Gán lớp #2 vào đợt #5','2026-07-21 10:56:23'),(92,1,'set_period_status','Cập nhật trạng thái đợt #5','2026-07-21 10:56:27'),(93,1,'assign_period_class','Gán lớp #2 vào đợt #5','2026-07-21 10:56:51'),(94,1,'assign_period_class','Gán lớp #1 vào đợt #5','2026-07-21 10:56:55'),(95,2,'create_topic','Tạo đề tài gốc qeqwe','2026-07-21 10:58:45'),(96,2,'assign_topic_class','Gán đề tài #2 cho lớp #2','2026-07-21 10:59:06'),(97,3,'login','Đăng nhập hệ thống','2026-07-21 10:59:15'),(98,10,'login','Đăng nhập hệ thống','2026-07-21 10:59:38'),(99,1,'extend_registration_period','Cập nhật thời gian đợt #4','2026-07-21 11:00:36'),(100,1,'set_period_status','Cập nhật trạng thái đợt #4','2026-07-21 11:00:41'),(101,10,'create_group','Tạo nhóm ádasdad','2026-07-21 11:00:52'),(102,10,'invite_member','Mời sv01@k73.test vào nhóm','2026-07-21 11:01:42'),(103,10,'register_topic','Nhóm ádasdad đăng ký đề tài qeqwe','2026-07-21 11:02:06'),(104,2,'review_registration','Đã duyệt đăng ký #2','2026-07-21 11:02:16'),(105,NULL,'auto_close_registration_period','Tự động đóng đợt đăng ký #5 - Đợt đăng ký đề tài bài tập lớn vì đã hết thời gian.','2026-07-21 11:33:23');
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
INSERT INTO `class_students` VALUES (1,3,'2026-07-10 16:24:14'),(1,4,'2026-07-10 16:24:14'),(1,5,'2026-07-10 16:24:14'),(1,6,'2026-07-10 16:24:14'),(1,7,'2026-07-10 16:24:14'),(1,8,'2026-07-10 16:24:14'),(1,10,'2026-07-16 14:39:31'),(2,3,'2026-07-10 16:24:14'),(2,4,'2026-07-10 16:24:14'),(2,5,'2026-07-12 17:44:02'),(2,6,'2026-07-12 17:44:02'),(2,7,'2026-07-10 16:24:14'),(2,8,'2026-07-10 16:24:14'),(2,10,'2026-07-12 17:44:02');
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'WEB301','Công nghệ Web','Học phần xây dựng ứng dụng web bằng PHP, MySQL, HTML, CSS và JavaScript.','2026-07-10 16:24:14'),(2,'DBS201','Cơ sở dữ liệu','Học phần thiết kế và quản trị cơ sở dữ liệu quan hệ.','2026-07-10 16:24:14'),(3,'HM001','học máy','ádfasfasfasfasdfaf','2026-07-12 16:33:15');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `group_invitations`
--

LOCK TABLES `group_invitations` WRITE;
/*!40000 ALTER TABLE `group_invitations` DISABLE KEYS */;
INSERT INTO `group_invitations` VALUES (1,1,3,10,'accepted','2026-07-12 17:51:52','2026-07-12 17:52:13'),(2,4,10,8,'accepted','2026-07-16 14:39:37','2026-07-16 14:39:46'),(3,4,5,8,'pending','2026-07-16 14:42:10',NULL),(4,4,6,8,'pending','2026-07-16 14:42:26',NULL),(5,5,3,10,'accepted','2026-07-21 11:01:42','2026-07-21 11:01:47');
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
INSERT INTO `group_members` VALUES (1,3,'member','2026-07-12 17:52:13'),(1,10,'leader','2026-07-12 17:45:44'),(3,5,'leader','2026-07-16 14:28:56'),(4,8,'leader','2026-07-16 14:38:06'),(4,10,'member','2026-07-16 14:39:46'),(5,3,'member','2026-07-21 11:01:47'),(5,10,'leader','2026-07-21 11:00:52');
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
INSERT INTO `registration_period_classes` VALUES (1,1,'2026-07-12 18:00:47'),(1,2,'2026-07-12 17:45:03'),(2,1,'2026-07-16 14:36:53'),(4,2,'2026-07-21 09:04:09'),(5,1,'2026-07-21 10:56:55'),(5,2,'2026-07-21 10:56:23');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `registration_periods`
--

LOCK TABLES `registration_periods` WRITE;
/*!40000 ALTER TABLE `registration_periods` DISABLE KEYS */;
INSERT INTO `registration_periods` VALUES (1,'Đợt đăng ký đề tài bài tập lớn','2026-07-12 17:44:00','2026-07-16 20:47:00','2026-07-12 17:44:00','2026-07-16 20:44:00','closed','2026-07-12 17:44:45'),(2,'test đợt đăng ký','2026-07-16 14:36:00','2026-07-17 14:36:00','2026-07-16 14:36:00','2026-07-16 14:36:00','closed','2026-07-16 14:36:39'),(4,'test 2','2026-07-21 09:03:00','2026-07-21 11:08:00','2026-07-21 09:03:00','2026-07-22 11:08:00','open','2026-07-21 09:04:01'),(5,'Đợt đăng ký đề tài bài tập lớn','2026-07-21 10:54:00','2026-07-21 11:08:00','2026-07-21 10:54:00','2026-07-21 11:20:00','closed','2026-07-21 10:54:40');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_groups`
--

LOCK TABLES `student_groups` WRITE;
/*!40000 ALTER TABLE `student_groups` DISABLE KEYS */;
INSERT INTO `student_groups` VALUES (1,2,1,'Nguyễn Anh Tú','1AD4CEA8','locked',10,'2026-07-12 17:45:44'),(3,2,1,'test','B889CD3B','forming',2,'2026-07-16 14:28:56'),(4,1,2,'test1','F1674327','forming',2,'2026-07-16 14:38:06'),(5,2,4,'ádasdad','F5990E78','locked',10,'2026-07-21 11:00:52');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topic_classes`
--

LOCK TABLES `topic_classes` WRITE;
/*!40000 ALTER TABLE `topic_classes` DISABLE KEYS */;
INSERT INTO `topic_classes` VALUES (1,1,2,1,1,'open','2026-07-12 17:50:33'),(2,2,2,4,2,'open','2026-07-21 10:59:06');
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
INSERT INTO `topic_registrations` VALUES (1,1,1,10,'approved','',2,'2026-07-12 17:52:39','2026-07-12 17:52:23',1),(2,5,2,10,'approved','',2,'2026-07-21 11:02:16','2026-07-21 11:02:06',5);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `topics`
--

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
INSERT INTO `topics` VALUES (1,2,'123','123123','123123131',2,4,'2026-07-12 17:50:28'),(2,2,'qeqwe','123123','123123',2,4,'2026-07-21 10:58:45');
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Quản trị hệ thống','admin@k73.test','$2y$10$flpFqwn.QCcCmtwM7QYarOdGgyWana2T4zjMfCjuyjZ3lliQ35e.e','admin','AD001','0900000000',0,0,'2026-07-10 16:24:14'),(2,'ThS. Nguyễn Thu Hà','giangvien@k73.test','$2y$10$MfJhPLqTOpygvqOfNr4EPO1GRlJKXifkUqWbUy6DesEKngfR4dsAC','teacher','GV001','0911000001',0,0,'2026-07-10 16:24:14'),(3,'Nguyễn Minh An','sv01@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV001','0922000001',0,0,'2026-07-10 16:24:14'),(4,'Trần Hoàng Bảo','sv02@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV002','0922000002',0,0,'2026-07-10 16:24:14'),(5,'Lê Quốc Cường','sv03@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV003','0922000003',0,0,'2026-07-10 16:24:14'),(6,'Phạm Thảo Duyên','sv04@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV004','0922000004',0,0,'2026-07-10 16:24:14'),(7,'Vũ Gia Huy','sv05@k73.test','$2y$10$VxEVab.//0YFKu/fqxBx6ON8/68stmCEpyS/tgpgY7Sf0rZGaWhHu','student','SV005','0922000005',0,0,'2026-07-10 16:24:14'),(8,'Đỗ Khánh Linh','sv06@k73.test','$2y$10$/L2TZ21o9VH55vYIslGJXu0TNkf.MixDPC0zwesSFMQVOqcEsIMgK','student','SV006','0922000006',0,0,'2026-07-10 16:24:14'),(10,'Nguyễn Anh Tú','anhtu105182@gmail.com','$2y$10$.YzXAd8EaexaE0QnpMCc2ORLPEVYG/HLsrR8whvoGaHIHPQT7eBkK','student','SV007','0383137092',0,0,'2026-07-10 22:56:56'),(12,'tuna','tunguyenanh2122004@gmail.com','$2y$10$BVFX9F7SpOnpz7L.ZOy0hexOJA/TUYT.G4UnBC0FELuKM6fFR3./u','teacher','GV002','0383137092',0,1,'2026-07-12 17:56:23'),(13,'Nguyễn Thư','thunguyen2122004@gmail.com','$2y$10$GXfzxgDoD4cQWsd6HAWjbehLNSSaZr33.smDaJOyRua0SkjCud/LW','teacher','GV003','0987654123',0,0,'2026-07-12 18:20:12');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'K73_nhom10_dangky_detai'
--

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

-- Dump completed on 2026-07-21 16:34:13
