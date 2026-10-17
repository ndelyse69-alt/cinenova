-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: posterg
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.3

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
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'Drama','drama','Character-driven, emotionally rich stories.','2026-08-28 23:55:11','2026-08-28 23:55:11'),(2,'Action','action','High-stakes thrills, stunts, and set pieces.','2026-08-28 23:55:11','2026-08-28 23:55:11'),(3,'Horror','horror','Scares, suspense, and the supernatural.','2026-08-28 23:55:11','2026-08-28 23:55:11'),(4,'Indian Cinema','indian-cinema','Bollywood and regional Indian releases.','2026-08-28 23:55:11','2026-08-28 23:55:11'),(5,'Animation','animation','Animated features for all ages.','2026-08-28 23:55:11','2026-08-28 23:55:11'),(6,'Science Fiction','science-fiction','Futuristic worlds and speculative tech.','2026-08-28 23:55:11','2026-08-28 23:55:11');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` char(2) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_countries_name` (`name`),
  UNIQUE KEY `uq_countries_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (1,'United States','US','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(2,'United Kingdom','GB','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(3,'South Korea','KR','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(4,'India','IN','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(5,'Spain','ES','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(6,'Rwanda','RW','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(7,'Nigeria','NG','active','2026-08-28 23:55:11','2026-08-28 23:55:11'),(8,'Japan','JP','inactive','2026-08-28 23:55:11','2026-08-28 23:55:11');
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movie_sources`
--

DROP TABLE IF EXISTS `movie_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movie_sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `movie_id` int unsigned NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_type` enum('embed','upload','vidsrc') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'embed',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sources_movie` (`movie_id`),
  CONSTRAINT `fk_sources_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movie_sources`
--

LOCK TABLES `movie_sources` WRITE;
/*!40000 ALTER TABLE `movie_sources` DISABLE KEYS */;
INSERT INTO `movie_sources` VALUES (1,1,'Episode 1','https://hgcloud.to/e/rkcsuqn0s1ro','embed',0,'2026-08-28 23:55:11'),(2,2,'Episode 1','https://hgcloud.to/e/c5394fg4y09c','embed',0,'2026-08-28 23:55:11'),(3,2,'Episode 2','https://hgcloud.to/e/0fla05m2s7w0','embed',1,'2026-08-28 23:55:11'),(4,2,'Episode 3','https://hgcloud.to/e/eopr0bv3huth','embed',2,'2026-08-28 23:55:11'),(5,2,'Episode 4','https://hgcloud.to/e/cidldzly1x7u','embed',3,'2026-08-28 23:55:11'),(6,2,'Episode 5','https://hgcloud.to/e/4pspq7s70dfx','embed',4,'2026-08-28 23:55:11'),(7,2,'Episode 6','https://hgcloud.to/e/pmecd09ghjqi','embed',5,'2026-08-28 23:55:11'),(8,3,'S2 Ep1','https://hgcloud.to/e/nhseilafyghs','embed',0,'2026-08-28 23:55:11'),(9,3,'S2 Ep2','https://hgcloud.to/e/boixaav1b1b8','embed',1,'2026-08-28 23:55:11'),(10,3,'S2 Ep3','https://hgcloud.to/e/8ojpwzi4t78d','embed',2,'2026-08-28 23:55:11'),(11,3,'S2 Ep4','https://hgcloud.to/e/vmhp92imog6r','embed',3,'2026-08-28 23:55:11'),(12,3,'S2 Ep5','https://hgcloud.to/e/sy9sq3fxobxm','embed',4,'2026-08-28 23:55:11'),(13,4,'Ep1','https://hgcloud.to/e/yw9fa38tep2v','embed',0,'2026-08-28 23:55:11'),(14,4,'Ep2','https://hgcloud.to/e/oojzggi5tfor','embed',1,'2026-08-28 23:55:11'),(15,4,'Ep3','https://hgcloud.to/e/i9s66r2pklq5','embed',2,'2026-08-28 23:55:11'),(16,4,'Ep4','https://hgcloud.to/e/tw5jc81kbspb','embed',3,'2026-08-28 23:55:11'),(17,4,'Ep5','https://hgcloud.to/e/4fzl5gbmctp6','embed',4,'2026-08-28 23:55:11'),(18,4,'Ep6','https://hgcloud.to/e/y4q38q9ro3g7','embed',5,'2026-08-28 23:55:11'),(19,4,'Ep7','https://hgcloud.to/e/flvcmpo415fx','embed',6,'2026-08-28 23:55:11'),(20,4,'Ep8','https://hgcloud.to/e/06nqlh7k9iby','embed',7,'2026-08-28 23:55:11'),(21,4,'Ep9','https://hgcloud.to/e/v6j8g7p13ywr','embed',8,'2026-08-28 23:55:11'),(22,4,'Ep10','https://hgcloud.to/e/qykvd7bmqgtz','embed',9,'2026-08-28 23:55:11'),(23,5,'Full Movie','https://hgcloud.to/e/rcymgknylohx','embed',0,'2026-08-28 23:55:11'),(24,6,'Full Movie','https://hgcloud.to/e/g39s7v9jy7gw','embed',0,'2026-08-28 23:55:11'),(25,7,'Full Movie','https://hgcloud.to/e/hk8obr37pmgk','embed',0,'2026-08-28 23:55:11'),(26,8,'Full Movie','https://hgcloud.to/e/d67be82jk6d6','embed',0,'2026-08-28 23:55:11'),(27,9,'Full Movie','https://hgcloud.to/e/mojfz67ovev0','embed',0,'2026-08-28 23:55:11'),(28,10,'Full Movie','https://hgcloud.to/e/2sz1spi1ommy','embed',0,'2026-08-28 23:55:11'),(29,11,'Full Movie','https://hgcloud.to/e/20y0nv6bok2q','embed',0,'2026-08-28 23:55:11'),(30,12,'Full Movie','https://hgcloud.to/e/dkteljvxyuib','embed',0,'2026-08-28 23:55:11'),(31,13,'Full Movie','https://hgcloud.to/e/nb69zafkk7z2','embed',0,'2026-08-28 23:55:11'),(32,14,'Part 1','https://hgcloud.to/e/nvj2j20cil59','embed',0,'2026-08-28 23:55:11'),(33,14,'Part 2','https://hgcloud.to/e/y815bhd3lxpc','embed',1,'2026-08-28 23:55:11'),(34,15,'Part 1','https://hgcloud.to/e/xngslo3hic28','embed',0,'2026-08-28 23:55:11'),(35,15,'Part 2','https://hgcloud.to/e/r3t0v279l4yy','embed',1,'2026-08-28 23:55:11'),(36,16,'Full Movie','https://hgcloud.to/e/2ydym48qbn6g','embed',0,'2026-08-28 23:55:11'),(37,17,'Part 1','https://hgcloud.to/e/ebyrfwlunpfv','embed',0,'2026-08-28 23:55:11'),(38,17,'Part 2','https://hgcloud.to/e/cbcjbylkhdt1','embed',1,'2026-08-28 23:55:11'),(39,18,'Full Movie','https://hgcloud.to/e/frdkg36og7co','embed',0,'2026-08-28 23:55:11'),(40,19,'Part 1','https://hgcloud.to/e/g81xwiojlrhd','embed',0,'2026-08-28 23:55:11'),(41,19,'Part 2','https://hgcloud.to/e/k2lx4acapfo9','embed',1,'2026-08-28 23:55:11'),(42,19,'Part 3','https://hgcloud.to/e/87wymel1fega','embed',2,'2026-08-28 23:55:11'),(43,20,'Full Movie','https://hgcloud.to/e/0w4jm6k8ugpa','embed',0,'2026-08-28 23:55:11'),(44,21,'Full Movie','https://hgcloud.to/e/dg3911z33jih','embed',0,'2026-08-28 23:55:11'),(45,22,'Full Movie','https://hgcloud.to/e/nw81ze4j1t32','embed',0,'2026-08-28 23:55:11');
/*!40000 ALTER TABLE `movie_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movies`
--

DROP TABLE IF EXISTS `movies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tmdb_id` bigint unsigned DEFAULT NULL,
  `source` enum('tmdb','local') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tmdb',
  `media_type` enum('movie','tv') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'movie',
  `category_id` int unsigned DEFAULT NULL,
  `country_id` int unsigned DEFAULT NULL,
  `year` year DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `poster_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` decimal(3,1) NOT NULL DEFAULT '0.0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_movies_slug` (`slug`),
  KEY `idx_movies_tmdb` (`tmdb_id`,`media_type`),
  KEY `idx_movies_category` (`category_id`),
  KEY `idx_movies_country` (`country_id`),
  KEY `idx_movies_title` (`title`),
  CONSTRAINT `fk_movies_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_movies_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movies`
--

LOCK TABLES `movies` WRITE;
/*!40000 ALTER TABLE `movies` DISABLE KEYS */;
INSERT INTO `movies` VALUES (1,'teach-you-a-lesson','Teach You a Lesson',276161,'tmdb','tv',1,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(2,'memorist','Memorist',99478,'tmdb','tv',1,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(3,'berlin-season-2','Berlin — Season 2',146176,'tmdb','tv',1,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(4,'agent-kim-reactivated','Agent Kim Reactivated',296206,'tmdb','tv',2,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(5,'xxx-return-of-xander-cage','XXX: Return of Xander Cage',47971,'tmdb','movie',2,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(6,'indemnity','Indemnity',854467,'tmdb','movie',2,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(7,'in-the-grey','In the Grey',1122573,'tmdb','movie',2,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(8,'from-paris-with-love','From Paris with Love',26389,'tmdb','movie',2,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(9,'evil-dead-burn','Evil Dead Burn',1212763,'tmdb','movie',3,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(10,'ready-or-not-2-here-i-come','Ready or Not 2: Here I Come',1266127,'tmdb','movie',3,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(11,'fall','Fall',985939,'tmdb','movie',3,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(12,'apex','Apex',763164,'tmdb','movie',3,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(13,'send-help','Send Help',1198994,'tmdb','movie',3,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(14,'saiyaara','Saiyaara',1241634,'tmdb','movie',4,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(15,'blast','Blast',1515729,'tmdb','movie',4,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(16,'mom','Mom',404604,'tmdb','movie',4,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(17,'goat','GOAT',1297842,'tmdb','movie',5,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(18,'the-croods','The Croods',49519,'tmdb','movie',5,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(19,'avatar-fire-and-ash','Avatar: Fire and Ash',83533,'tmdb','movie',6,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(20,'spider-man-far-from-home','Spider-Man: Far From Home',429617,'tmdb','movie',6,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(21,'supergirl','Supergirl',1081003,'tmdb','movie',6,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11'),(22,'spider-man-no-way-home','Spider-Man: No Way Home',634649,'tmdb','movie',6,NULL,NULL,NULL,NULL,0.0,'2026-08-28 23:55:11','2026-08-28 23:55:11');
/*!40000 ALTER TABLE `movies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` tinyint unsigned NOT NULL DEFAULT '1',
  `site_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CineNova',
  `site_tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_description` text COLLATE utf8mb4_unicode_ci,
  `support_email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT '0',
  `social_twitter` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_facebook` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `social_instagram` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_settings_singleton` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'CineNova','Stream the Future of Entertainment','CineNova is a premium streaming destination for movies and series — trending titles, curated picks, and a library that keeps growing.','support@cinenova.example',0,'https://twitter.com/cinenova','https://facebook.com/cinenova','https://instagram.com/cinenova','2026-08-28 23:55:11');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `plan` enum('Free','Standard','Premium') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Free',
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joined_at` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'elyse','Elyse','elyse@cinenova.example','$2y$10$A33gS3anterc.p2lQ/NRmeI3Bz1RPdpFqa9oPIDKl91POykrOvGZO','admin','Premium','active','2026-08-16','2026-08-28 23:55:11','2026-08-28 23:55:11'),(2,'amarauwase','Amara Uwase','amara.uwase@example.com','$2y$10$lvL7/gpElKWRj9u6UnA2Y.oDTSz/KEpRcYtgdKq8bZgZs1kFt3EGW','user','Premium','active','2025-11-02','2026-08-28 23:55:11','2026-08-28 23:55:11'),(3,'jordanblake','Jordan Blake','jordan.blake@example.com','$2y$10$PGKNgtTxOL6SM0MWpPK7rO3x8RMOpIHTwvt85IN4xHsKX6.Mx6Vme','user','Standard','active','2025-12-14','2026-08-28 23:55:11','2026-08-28 23:55:11'),(4,'priyanair','Priya Nair','priya.nair@example.com','$2y$10$sH7TxVSc/Tk20cob.SRI4.E9GkPeT/7Ni/R4Y0SILtJwpa0IX4UvO','user','Premium','suspended','2026-01-05','2026-08-28 23:55:11','2026-08-28 23:55:11'),(5,'liamchen','Liam Chen','liam.chen@example.com','$2y$10$wWmRMpRpC4H5vR8UKz9/RedmnD4b7zxnYoM66NRXaTuodAIE5A6Fi','user','Free','active','2026-02-20','2026-08-28 23:55:11','2026-08-28 23:55:11'),(6,'sofiatorres','Sofia Torres','sofia.torres@example.com','$2y$10$dFDNFA5ut9OxnlAbZkaTc.4Lp.4dtI3DWkLo5AoN6SQK9Pm0rxkEG','user','Standard','active','2026-03-11','2026-08-28 23:55:11','2026-08-28 23:55:11'),(7,'kwamemensah','Kwame Mensah','kwame.mensah@example.com','$2y$10$3tTvoSi4Rxg4x6ttoeA/4eagYjJdySOT.jrTgSHpNw08wm6DcZCu6','user','Premium','inactive','2026-04-30','2026-08-28 23:55:11','2026-08-28 23:55:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visits`
--

DROP TABLE IF EXISTS `visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `visits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `page` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `query_string` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_hash` char(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `referrer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `visited_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visits_page` (`page`(50)),
  KEY `idx_visits_visited_at` (`visited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visits`
--

LOCK TABLES `visits` WRITE;
/*!40000 ALTER TABLE `visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `visits` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-29  6:14:18
