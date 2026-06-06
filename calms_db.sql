CREATE DATABASE IF NOT EXISTS calms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE calms_db;

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: calms_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

<<<<<<< HEAD
--
-- Table structure for table `career_courses`
--
=======
CREATE TABLE IF NOT EXISTS skills (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    skill_name      VARCHAR(100) NOT NULL,
    category        VARCHAR(50),
    industry_level  INT DEFAULT 8  
) ENGINE=InnoDB;
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87

DROP TABLE IF EXISTS `career_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `career_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_career_course` (`career_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `career_courses_ibfk_1` FOREIGN KEY (`career_id`) REFERENCES `career_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `career_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

<<<<<<< HEAD
--
-- Dumping data for table `career_courses`
--
=======
CREATE TABLE IF NOT EXISTS student_skills (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    skill_id      INT NOT NULL,
    student_level INT DEFAULT 0,  
    UNIQUE KEY uq_student_skill (student_id, skill_id),
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id)   REFERENCES skills(id)
) ENGINE=InnoDB;
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87

LOCK TABLES `career_courses` WRITE;
/*!40000 ALTER TABLE `career_courses` DISABLE KEYS */;
INSERT INTO `career_courses` VALUES (1,1,22),(2,1,32),(3,1,38),(4,2,21);
/*!40000 ALTER TABLE `career_courses` ENABLE KEYS */;
UNLOCK TABLES;

<<<<<<< HEAD
--
-- Table structure for table `career_positions`
--
=======
INSERT INTO certifications (cert_name, provider, tier, score, category, career_relevance) VALUES
('AWS Certified Cloud Practitioner',        'Amazon Web Services', 1, 100, 'Cloud',           'Cloud Engineer, DevOps'),
('AWS Certified Solutions Architect',       'Amazon Web Services', 1, 100, 'Cloud',           'Cloud Engineer, Backend'),
('Google Associate Cloud Engineer',         'Google Cloud',        1, 100, 'Cloud',           'Cloud Engineer, DevOps'),
('Google Professional Data Engineer',       'Google Cloud',        1, 100, 'Data',            'Data Engineer, ML Engineer'),
('Microsoft Azure Fundamentals (AZ-900)',   'Microsoft',           1, 100, 'Cloud',           'Cloud Engineer, DevOps'),
('Cisco CCNA',                             'Cisco',               1, 100, 'Networking',      'Network Engineer, Cybersecurity'),
('Oracle Java SE Programmer',              'Oracle',              1, 100, 'Programming',     'Backend Developer'),
('Google Data Analytics Certificate',       'Google / Coursera',   1, 100, 'Data',            'Data Analyst, Data Scientist'),
('Meta Front-End Developer Certificate',    'Meta / Coursera',     1, 100, 'Frontend',        'Frontend Developer'),
('TensorFlow Developer Certificate',        'Google',              1, 100, 'AI/ML',           'ML Engineer, Data Scientist'),
('CompTIA Security+',                       'CompTIA',             1, 100, 'Security',        'Cybersecurity Analyst'),
('Professional Scrum Master (PSM I)',       'Scrum.org',           1, 100, 'Management',      'Project Manager, Scrum Master'),

('Sertifikat Kompetensi Programmer BNSP',   'BNSP',                2,  75, 'Programming',     'Backend, Frontend Developer'),
('Sertifikat Kompetensi Junior Network Administrator', 'BNSP',     2,  75, 'Networking',      'Network Engineer'),
('Sertifikat Kompetensi Database Administrator', 'BNSP',           2,  75, 'Database',        'Data Engineer, DBA'),
('Sertifikat Kompetensi Web Developer',     'BNSP',                2,  75, 'Programming',     'Full Stack Developer'),
('Sertifikat Kompetensi Keamanan Informasi','BNSP',                2,  75, 'Security',        'Cybersecurity Analyst'),

('Python for Everybody',                    'Coursera / UMich',    3,  50, 'Programming',     'Backend, Data Science'),
('Machine Learning Specialization',         'Coursera / DeepLearning.AI', 3, 50, 'AI/ML',    'ML Engineer'),
('The Web Developer Bootcamp',              'Udemy',               3,  50, 'Programming',     'Full Stack Developer'),
('JavaScript Algorithms and Data Structures','freeCodeCamp',       3,  50, 'Programming',     'Frontend, Backend Developer'),
('Responsive Web Design',                   'freeCodeCamp',        3,  50, 'Frontend',        'Frontend Developer'),
('Data Science: R Basics',                  'edX / HarvardX',      3,  50, 'Data',            'Data Analyst'),
('Introduction to Cybersecurity',           'Cisco NetAcad',       3,  50, 'Security',        'Cybersecurity Analyst'),
('Flutter & Dart Development',              'Udemy',               3,  50, 'Mobile',          'Mobile Developer'),
('SQL for Data Science',                    'Coursera / UC Davis', 3,  50, 'Database',        'Data Analyst, DBA');
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87

DROP TABLE IF EXISTS `career_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_name` (`position_name`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `career_positions`
--

LOCK TABLES `career_positions` WRITE;
/*!40000 ALTER TABLE `career_positions` DISABLE KEYS */;
INSERT INTO `career_positions` VALUES (3,'Backend Developer'),(1,'Data Analyst'),(4,'Frontend Developer'),(5,'Machine Learning Engineer'),(2,'UI/UX Designer');
/*!40000 ALTER TABLE `career_positions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `career_skills`
--

<<<<<<< HEAD
DROP TABLE IF EXISTS `career_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `career_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `career_id` int(11) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_career_skill` (`career_id`,`skill_name`),
  CONSTRAINT `career_skills_ibfk_1` FOREIGN KEY (`career_id`) REFERENCES `career_positions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
=======
INSERT INTO labs (lab_name, description, focus_area, skill_tags) VALUES
('Lab Kecerdasan Buatan',        'Riset AI, Machine Learning, Deep Learning, Computer Vision, NLP', 'AI/ML',          'Python,TensorFlow,Machine Learning,Deep Learning,Data Science'),
('Lab Sistem Informasi',          'Enterprise Systems, Database, Business Intelligence, ERP',        'Data/Enterprise', 'SQL,PHP,Java,Database,ERP,Business Intelligence'),
('Lab Rekayasa Perangkat Lunak', 'Software Engineering, Mobile Dev, Web Application, Agile',        'Software Eng',   'Java,React,Flutter,Git,Agile,Testing');
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87

--
-- Dumping data for table `career_skills`
--

LOCK TABLES `career_skills` WRITE;
/*!40000 ALTER TABLE `career_skills` DISABLE KEYS */;
INSERT INTO `career_skills` VALUES (1,1,'Python'),(2,1,'Tableau/Power BI'),(3,2,'Figma'),(5,2,'User Research'),(4,2,'Wireframing');
/*!40000 ALTER TABLE `career_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certifications`
--

DROP TABLE IF EXISTS `certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cert_name` varchar(255) NOT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `tier` int(11) DEFAULT 3,
  `score` int(11) DEFAULT 50,
  `category` varchar(50) DEFAULT NULL,
  `career_relevance` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certifications`
--

LOCK TABLES `certifications` WRITE;
/*!40000 ALTER TABLE `certifications` DISABLE KEYS */;
INSERT INTO `certifications` VALUES (1,'AWS Certified Cloud Practitioner','Amazon Web Services',1,100,'Cloud','Cloud Engineer, DevOps'),(2,'AWS Certified Solutions Architect','Amazon Web Services',1,100,'Cloud','Cloud Engineer, Backend'),(3,'Google Associate Cloud Engineer','Google Cloud',1,100,'Cloud','Cloud Engineer, DevOps'),(4,'Google Professional Data Engineer','Google Cloud',1,100,'Data','Data Engineer, ML Engineer'),(5,'Microsoft Azure Fundamentals (AZ-900)','Microsoft',1,100,'Cloud','Cloud Engineer, DevOps'),(6,'Cisco CCNA','Cisco',1,100,'Networking','Network Engineer, Cybersecurity'),(7,'Oracle Java SE Programmer','Oracle',1,100,'Programming','Backend Developer'),(8,'Google Data Analytics Certificate','Google / Coursera',1,100,'Data','Data Analyst, Data Scientist'),(9,'Meta Front-End Developer Certificate','Meta / Coursera',1,100,'Frontend','Frontend Developer'),(10,'TensorFlow Developer Certificate','Google',1,100,'AI/ML','ML Engineer, Data Scientist'),(11,'CompTIA Security+','CompTIA',1,100,'Security','Cybersecurity Analyst'),(12,'Professional Scrum Master (PSM I)','Scrum.org',1,100,'Management','Project Manager, Scrum Master'),(13,'Sertifikat Kompetensi Programmer BNSP','BNSP',2,75,'Programming','Backend, Frontend Developer'),(14,'Sertifikat Kompetensi Junior Network Administrator','BNSP',2,75,'Networking','Network Engineer'),(15,'Sertifikat Kompetensi Database Administrator','BNSP',2,75,'Database','Data Engineer, DBA'),(16,'Sertifikat Kompetensi Web Developer','BNSP',2,75,'Programming','Full Stack Developer'),(17,'Sertifikat Kompetensi Keamanan Informasi','BNSP',2,75,'Security','Cybersecurity Analyst'),(18,'Python for Everybody','Coursera / UMich',3,50,'Programming','Backend, Data Science'),(19,'Machine Learning Specialization','Coursera / DeepLearning.AI',3,50,'AI/ML','ML Engineer'),(20,'The Web Developer Bootcamp','Udemy',3,50,'Programming','Full Stack Developer'),(21,'JavaScript Algorithms and Data Structures','freeCodeCamp',3,50,'Programming','Frontend, Backend Developer'),(22,'Responsive Web Design','freeCodeCamp',3,50,'Frontend','Frontend Developer'),(23,'Data Science: R Basics','edX / HarvardX',3,50,'Data','Data Analyst'),(24,'Introduction to Cybersecurity','Cisco NetAcad',3,50,'Security','Cybersecurity Analyst'),(25,'Flutter & Dart Development','Udemy',3,50,'Mobile','Mobile Developer'),(26,'SQL for Data Science','Coursera / UC Davis',3,50,'Database','Data Analyst, DBA'),(27,'AWS Certified Cloud Practitioner','Amazon Web Services',1,100,'Cloud','Cloud Engineer, DevOps'),(28,'AWS Certified Solutions Architect','Amazon Web Services',1,100,'Cloud','Cloud Engineer, Backend'),(29,'Google Associate Cloud Engineer','Google Cloud',1,100,'Cloud','Cloud Engineer, DevOps'),(30,'Google Professional Data Engineer','Google Cloud',1,100,'Data','Data Engineer, ML Engineer'),(31,'Microsoft Azure Fundamentals (AZ-900)','Microsoft',1,100,'Cloud','Cloud Engineer, DevOps'),(32,'Cisco CCNA','Cisco',1,100,'Networking','Network Engineer, Cybersecurity'),(33,'Oracle Java SE Programmer','Oracle',1,100,'Programming','Backend Developer'),(34,'Google Data Analytics Certificate','Google / Coursera',1,100,'Data','Data Analyst, Data Scientist'),(35,'Meta Front-End Developer Certificate','Meta / Coursera',1,100,'Frontend','Frontend Developer'),(36,'TensorFlow Developer Certificate','Google',1,100,'AI/ML','ML Engineer, Data Scientist'),(37,'CompTIA Security+','CompTIA',1,100,'Security','Cybersecurity Analyst'),(38,'Professional Scrum Master (PSM I)','Scrum.org',1,100,'Management','Project Manager, Scrum Master'),(39,'Sertifikat Kompetensi Programmer BNSP','BNSP',2,75,'Programming','Backend, Frontend Developer'),(40,'Sertifikat Kompetensi Junior Network Administrator','BNSP',2,75,'Networking','Network Engineer'),(41,'Sertifikat Kompetensi Database Administrator','BNSP',2,75,'Database','Data Engineer, DBA'),(42,'Sertifikat Kompetensi Web Developer','BNSP',2,75,'Programming','Full Stack Developer'),(43,'Sertifikat Kompetensi Keamanan Informasi','BNSP',2,75,'Security','Cybersecurity Analyst'),(44,'Python for Everybody','Coursera / UMich',3,50,'Programming','Backend, Data Science'),(45,'Machine Learning Specialization','Coursera / DeepLearning.AI',3,50,'AI/ML','ML Engineer'),(46,'The Web Developer Bootcamp','Udemy',3,50,'Programming','Full Stack Developer'),(47,'JavaScript Algorithms and Data Structures','freeCodeCamp',3,50,'Programming','Frontend, Backend Developer'),(48,'Responsive Web Design','freeCodeCamp',3,50,'Frontend','Frontend Developer'),(49,'Data Science: R Basics','edX / HarvardX',3,50,'Data','Data Analyst'),(50,'Introduction to Cybersecurity','Cisco NetAcad',3,50,'Security','Cybersecurity Analyst'),(51,'Flutter & Dart Development','Udemy',3,50,'Mobile','Mobile Developer'),(52,'SQL for Data Science','Coursera / UC Davis',3,50,'Database','Data Analyst, DBA');
/*!40000 ALTER TABLE `certifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_lab_weights`
--

DROP TABLE IF EXISTS `course_lab_weights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_lab_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `bobot_rpl` decimal(3,2) DEFAULT 0.00,
  `bobot_hpc` decimal(3,2) DEFAULT 0.00,
  `bobot_ai` decimal(3,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_id` (`course_id`),
  CONSTRAINT `course_lab_weights_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_lab_weights`
--

LOCK TABLES `course_lab_weights` WRITE;
/*!40000 ALTER TABLE `course_lab_weights` DISABLE KEYS */;
INSERT INTO `course_lab_weights` VALUES (1,13,0.50,0.30,0.20),(2,22,0.40,0.20,0.40),(3,17,0.30,0.40,0.30);
/*!40000 ALTER TABLE `course_lab_weights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_skill_mapping`
--

DROP TABLE IF EXISTS `course_skill_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `course_skill_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_csm` (`course_id`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `course_skill_mapping_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_skill_mapping_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_skill_mapping`
--

LOCK TABLES `course_skill_mapping` WRITE;
/*!40000 ALTER TABLE `course_skill_mapping` DISABLE KEYS */;
INSERT INTO `course_skill_mapping` VALUES (1,5,1),(2,6,1),(3,11,1),(4,12,1),(70,12,21),(5,13,1),(38,13,4),(6,14,1),(71,14,21),(7,17,1),(39,17,4),(35,18,3),(45,18,7),(67,19,20),(8,20,1),(81,21,29),(46,22,7),(48,22,8),(68,23,20),(82,23,30),(40,25,4),(60,26,19),(9,27,1),(72,27,21),(32,28,2),(36,28,3),(51,28,13),(10,30,1),(11,32,1),(73,32,21),(41,33,4),(83,34,30),(12,36,1),(13,37,1),(14,38,1),(47,38,7),(49,38,10),(15,39,1),(54,39,14),(69,39,20),(16,40,1),(74,40,21),(17,41,1),(33,42,2),(52,42,13),(58,44,17),(59,44,18),(79,44,24),(80,45,27),(34,47,2),(37,47,3),(50,47,11),(53,47,13),(55,47,14),(57,48,15),(61,48,19),(75,49,21),(77,49,22),(78,49,23),(62,50,19),(63,51,19);
/*!40000 ALTER TABLE `course_skill_mapping` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) DEFAULT NULL,
  `course_name` varchar(200) NOT NULL,
  `course_name_id` varchar(200) DEFAULT NULL,
  `semester` int(11) DEFAULT 1,
  `credits` int(11) DEFAULT 3,
  `is_wajib` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'BIE101','Pancasila','Pancasila',1,2,1),(2,'BIE102','Interpersonal Skill','Interpersonal Skill',1,2,1),(3,'BIE103','Information Technology Introduction','Pengantar Teknologi Informasi',1,3,1),(4,'BIE104','Technopreneurship','Technopreneurship',1,2,1),(5,'BIE105','Informatic Logic','Logika Informatika',1,3,1),(6,'BIE106','Calculus','Kalkulus',1,3,1),(7,'BIE107','Digital System','Sistem Digital',1,3,1),(8,'BIE108','Religion Education','Pendidikan Agama',1,2,1),(9,'BIE201','Computer Architecture and Organization','Arsitektur dan Organisasi Komputer',2,3,1),(10,'BIE202','Computer and Society','Komputer dan Masyarakat',2,2,1),(11,'BIE203','Discrete Mathematics','Matematika Diskrit',2,3,1),(12,'BIE204','Linear Algebra','Aljabar Linier',2,3,1),(13,'BIE205','Algorithm and Programming','Algoritma dan Pemrograman',2,4,1),(14,'BIE206','Probability and Statistics','Probabilitas dan Statistika',2,3,1),(15,'BIE207','English','Bahasa Inggris',2,2,1),(16,'BIE208','Citizenship','Kewarganegaraan',2,2,1),(17,'BIE301','Algorithm and Data Structure','Algoritma dan Struktur Data',3,4,1),(18,'BIE302','Information System','Sistem Informasi',3,3,1),(19,'BIE303','Operating System','Sistem Operasi',3,3,1),(20,'BIE304','Numerical Method','Metode Numerik',3,3,1),(21,'BIE305','Human Computer Interaction','Interaksi Manusia dan Komputer',3,3,1),(22,'BIE306','Database System','Sistem Basis Data',3,4,1),(23,'BIE307','Computer Network','Jaringan Komputer',3,3,1),(24,'BIE401','File System','Sistem Berkas',4,3,1),(25,'BIE402','Object Oriented Programming and Analysis','Pemrograman Berorientasi Objek dan Analisis',4,4,1),(26,'BIE403','Software Engineering','Rekayasa Perangkat Lunak',4,3,1),(27,'BIE404','Digital Image Processing','Pengolahan Citra Digital',4,3,1),(28,'BIE405','Web Programming','Pemrograman Web',4,3,1),(29,'BIE406','Scientific Paper Writing','Penulisan Karya Ilmiah',4,2,1),(30,'BIE407','Parallel Processing','Pemrosesan Paralel',4,3,1),(31,'BIE501','Professional Ethics','Etika Profesi',5,2,1),(32,'BIE502','Artificial Intelligence','Kecerdasan Buatan',5,3,1),(33,'BIE503','Object Oriented Programming','Pemrograman Berorientasi Objek',5,3,1),(34,'BIE504','Information Technology Security','Keamanan Teknologi Informasi',5,3,1),(35,'BIE505','Research on Information Technology','Penelitian Teknologi Informasi',5,2,1),(36,'BIE506','Operational Research','Penelitian Operasional',5,3,1),(37,'BIE507','Automate and Formal Language','Otomata dan Bahasa Formal',5,3,1),(38,'BIE508','Big Data','Big Data',5,3,1),(39,'BIE601','Internet of Things','Internet of Things (IoT)',6,3,1),(40,'BIE602','Fuzzy Logic','Logika Fuzzy',6,3,1),(41,'BIE603','Modeling and Simulation','Pemodelan dan Simulasi',6,3,1),(42,'BIE604','Visual Programming','Pemrograman Visual',6,3,1),(43,'BIE605','Practical Work','Kerja Praktik',6,2,1),(44,'BIE606','Distributed System','Sistem Terdistribusi',6,3,1),(45,'BIE607','Mobile Programming','Pemrograman Mobile',6,3,1),(46,'BIE701','Field Study Service','Kuliah Kerja Nyata (KKN)',7,3,1),(47,'BIE702','Internet Programming','Pemrograman Internet',7,3,1),(48,'BIE703','Software Development Project','Proyek Pengembangan Perangkat Lunak',7,4,1),(49,'BIE704','Artificial Neural Network','Jaringan Syaraf Tiruan',7,3,1),(50,'BIE705','Final Project I','Skripsi I',7,3,1),(51,'BIE801','Final Project II','Skripsi II',8,4,1);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dosen_notifications`
--

DROP TABLE IF EXISTS `dosen_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dosen_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dosen_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `type` enum('at_risk_gap','at_risk_ipk','general') DEFAULT 'at_risk_gap',
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dosen_id` (`dosen_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `dosen_notifications_ibfk_1` FOREIGN KEY (`dosen_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dosen_notifications_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dosen_notifications`
--

LOCK TABLES `dosen_notifications` WRITE;
/*!40000 ALTER TABLE `dosen_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `dosen_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dosen_profiles`
--

DROP TABLE IF EXISTS `dosen_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dosen_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nidn` varchar(20) DEFAULT NULL,
  `prodi` varchar(100) DEFAULT 'Informatika',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `dosen_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dosen_profiles`
--

LOCK TABLES `dosen_profiles` WRITE;
/*!40000 ALTER TABLE `dosen_profiles` DISABLE KEYS */;
INSERT INTO `dosen_profiles` VALUES (1,5,'D001','Informatika','2026-06-06 13:08:55');
/*!40000 ALTER TABLE `dosen_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `industry_trends`
--

DROP TABLE IF EXISTS `industry_trends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industry_trends` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `trend_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industry_trends`
--

LOCK TABLES `industry_trends` WRITE;
/*!40000 ALTER TABLE `industry_trends` DISABLE KEYS */;
INSERT INTO `industry_trends` VALUES (1,'AI & Machine Learning Mendominasi Rekrutmen Tech 2025','AI/ML','LinkedIn Indonesia','2025-05-01','Permintaan engineer AI naik 40% dibanding tahun lalu.','2026-06-06 13:08:55'),(2,'Cloud Native & Kubernetes Jadi Skill Wajib DevOps','Cloud/DevOps','JobStreet','2025-04-20','Perusahaan besar mulai mensyaratkan Kubernetes di JD DevOps.','2026-06-06 13:08:55'),(3,'Full Stack JavaScript (React + Node) Masih Teratas','Frontend','Glassdoor','2025-04-10','React.js bertahan di posisi #1 framework frontend Indonesia.','2026-06-06 13:08:55'),(4,'Cybersecurity Analyst: Shortage Talenta di Indonesia','Security','IDN Times Tech','2025-03-28','Kebutuhan analis keamanan siber meningkat pasca insiden data 2024.','2026-06-06 13:08:55'),(5,'Data Engineering Geser Data Analyst di Prioritas Hiring','Data','Dicoding Insight','2025-03-15','Skill pipeline data & Spark lebih dicari dari sekadar SQL analyst.','2026-06-06 13:08:55'),(6,'Flutter Jadi Pilihan Utama Mobile Dev Startup Indonesia','Mobile','Tech in Asia','2025-02-20','Startup tahap awal dominan pilih Flutter karena efisiensi tim.','2026-06-06 13:08:55');
/*!40000 ALTER TABLE `industry_trends` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labs`
--

DROP TABLE IF EXISTS `labs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `focus_area` varchar(255) DEFAULT NULL,
  `skill_tags` varchar(500) DEFAULT NULL,
  `career_focus` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labs`
--

LOCK TABLES `labs` WRITE;
/*!40000 ALTER TABLE `labs` DISABLE KEYS */;
INSERT INTO `labs` VALUES (1,'Lab Kecerdasan Buatan','Riset AI, Machine Learning, Deep Learning, Computer Vision, NLP','AI/ML','Python,TensorFlow,Machine Learning,Deep Learning,Data Science','ML Engineer,Data Scientist,Data Analyst'),(2,'Lab Sistem Informasi','Enterprise Systems, Database, Business Intelligence, ERP','Data/Enterprise','SQL,PHP,Java,Database,ERP,Business Intelligence','Backend Developer,Database Administrator,Full Stack Developer'),(3,'Lab Rekayasa Perangkat Lunak','Software Engineering, Mobile Dev, Web Application, Agile','Software Eng','Java,React,Flutter,Git,Agile,Testing','Full Stack Developer,Mobile Developer,Software Architect,QA Engineer'),(4,'Lab Jaringan Komputer','Networking, Cybersecurity, Cloud Infrastructure','Network/Cloud','Linux,Docker,AWS,Kubernetes,Networking,Cybersecurity','Network Engineer,Cloud Engineer,DevOps Engineer,Cybersecurity Analyst'),(5,'Lab Multimedia & Desain','UI/UX Design, Multimedia, Grafik Komputer','Design','Figma,CSS,JavaScript,UI/UX,Adobe','UI/UX Designer,Frontend Developer,Full Stack Developer'),(6,'Lab Kecerdasan Buatan','Riset AI, Machine Learning, Deep Learning, Computer Vision, NLP','AI/ML','Python,TensorFlow,Machine Learning,Deep Learning,Data Science','ML Engineer,Data Scientist,Data Analyst'),(7,'Lab Sistem Informasi','Enterprise Systems, Database, Business Intelligence, ERP','Data/Enterprise','SQL,PHP,Java,Database,ERP,Business Intelligence','Backend Developer,Database Administrator,Full Stack Developer'),(8,'Lab Rekayasa Perangkat Lunak','Software Engineering, Mobile Dev, Web Application, Agile','Software Eng','Java,React,Flutter,Git,Agile,Testing','Full Stack Developer,Mobile Developer,Software Architect,QA Engineer'),(9,'Lab Jaringan Komputer','Networking, Cybersecurity, Cloud Infrastructure','Network/Cloud','Linux,Docker,AWS,Kubernetes,Networking,Cybersecurity','Network Engineer,Cloud Engineer,DevOps Engineer,Cybersecurity Analyst'),(10,'Lab Multimedia & Desain','UI/UX Design, Multimedia, Grafik Komputer','Design','Figma,CSS,JavaScript,UI/UX,Adobe','UI/UX Designer,Frontend Developer,Full Stack Developer');
/*!40000 ALTER TABLE `labs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mahasiswa_profiles`
--

DROP TABLE IF EXISTS `mahasiswa_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mahasiswa_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `nim` varchar(20) NOT NULL,
  `semester` int(11) DEFAULT 1,
  `ipk` decimal(3,2) DEFAULT 0.00,
  `target_career` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `github_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `avatar_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `nim` (`nim`),
  CONSTRAINT `mahasiswa_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mahasiswa_profiles`
--

LOCK TABLES `mahasiswa_profiles` WRITE;
/*!40000 ALTER TABLE `mahasiswa_profiles` DISABLE KEYS */;
INSERT INTO `mahasiswa_profiles` VALUES (1,1,'F1D024001',5,3.45,'Data Scientist',NULL,NULL,NULL,'2026-06-04 03:00:49','2026-06-04 03:00:49',NULL),(2,2,'F1D02410007',1,3.00,'Backend Developer','','','','2026-06-04 03:07:03','2026-06-06 13:19:20',NULL);
/*!40000 ALTER TABLE `mahasiswa_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roadmap_steps`
--

DROP TABLE IF EXISTS `roadmap_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roadmap_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `career_id` int(11) NOT NULL,
  `step_name` varchar(255) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `type_matkul` enum('Wajib','Pilihan') DEFAULT 'Wajib',
  `saran_matkul` text DEFAULT NULL,
  `saran_kursus` text DEFAULT NULL,
  `saran_kursus_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `career_id` (`career_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `roadmap_steps_ibfk_1` FOREIGN KEY (`career_id`) REFERENCES `career_positions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `roadmap_steps_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roadmap_steps`
--

LOCK TABLES `roadmap_steps` WRITE;
/*!40000 ALTER TABLE `roadmap_steps` DISABLE KEYS */;
INSERT INTO `roadmap_steps` VALUES (1,1,'Step 1: Dasar Basis Data',22,'Wajib','Silakan review kembali materi praktikum matkul ini.','Google Data Analytics di Coursera','https://www.coursera.org/');
/*!40000 ALTER TABLE `roadmap_steps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `simulations`
--

DROP TABLE IF EXISTS `simulations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `simulations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `target_role` varchar(100) DEFAULT NULL,
  `target_company` varchar(100) DEFAULT NULL,
  `ipk_score` decimal(5,2) DEFAULT 0.00,
  `skill_score` decimal(5,2) DEFAULT 0.00,
  `cert_score` decimal(5,2) DEFAULT 0.00,
  `portfolio_score` decimal(5,2) DEFAULT 0.00,
  `probability_score` decimal(5,4) DEFAULT 0.0000,
  `iterations` int(11) DEFAULT 10000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `simulations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `simulations`
--

LOCK TABLES `simulations` WRITE;
/*!40000 ALTER TABLE `simulations` DISABLE KEYS */;
INSERT INTO `simulations` VALUES (1,2,'UI/UX Designer',NULL,75.00,24.00,0.00,0.00,0.0000,10000,'2026-06-04 16:32:25'),(2,2,'UI/UX Designer',NULL,75.00,24.00,33.33,20.00,0.0000,10000,'2026-06-04 16:33:32');
/*!40000 ALTER TABLE `simulations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `skills`
--

DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `skill_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `industry_level` int(11) DEFAULT 8,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `skills`
--

LOCK TABLES `skills` WRITE;
/*!40000 ALTER TABLE `skills` DISABLE KEYS */;
INSERT INTO `skills` VALUES (1,'Python','Programming',8),(2,'JavaScript','Programming',9),(3,'PHP','Programming',7),(4,'Java','Programming',7),(5,'TypeScript','Programming',8),(6,'Go / Golang','Programming',7),(7,'SQL','Database',8),(8,'MySQL','Database',8),(9,'PostgreSQL','Database',7),(10,'MongoDB','Database',7),(11,'React.js','Frontend',9),(12,'Vue.js','Frontend',7),(13,'HTML/CSS','Frontend',9),(14,'Node.js','Backend',8),(15,'Laravel','Backend',8),(16,'Express.js','Backend',7),(17,'Docker','DevOps',8),(18,'Kubernetes','DevOps',7),(19,'Git / GitHub','Tools',9),(20,'Linux','Tools',8),(21,'Machine Learning','AI/ML',8),(22,'Deep Learning','AI/ML',7),(23,'TensorFlow','AI/ML',7),(24,'AWS','Cloud',8),(25,'Google Cloud','Cloud',7),(26,'Azure','Cloud',7),(27,'Flutter','Mobile',8),(28,'React Native','Mobile',7),(29,'Figma / UI Design','Design',7),(30,'Cybersecurity','Security',8),(31,'Python','Programming',8),(32,'JavaScript','Programming',9),(33,'PHP','Programming',7),(34,'Java','Programming',7),(35,'TypeScript','Programming',8),(36,'Go / Golang','Programming',7),(37,'SQL','Database',8),(38,'MySQL','Database',8),(39,'PostgreSQL','Database',7),(40,'MongoDB','Database',7),(41,'React.js','Frontend',9),(42,'Vue.js','Frontend',7),(43,'HTML/CSS','Frontend',9),(44,'Node.js','Backend',8),(45,'Laravel','Backend',8),(46,'Express.js','Backend',7),(47,'Docker','DevOps',8),(48,'Kubernetes','DevOps',7),(49,'Git / GitHub','Tools',9),(50,'Linux','Tools',8),(51,'Machine Learning','AI/ML',8),(52,'Deep Learning','AI/ML',7),(53,'TensorFlow','AI/ML',7),(54,'AWS','Cloud',8),(55,'Google Cloud','Cloud',7),(56,'Azure','Cloud',7),(57,'Flutter','Mobile',8),(58,'React Native','Mobile',7),(59,'Figma / UI Design','Design',7),(60,'Cybersecurity','Security',8);
/*!40000 ALTER TABLE `skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_certifications`
--

DROP TABLE IF EXISTS `student_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `cert_name` varchar(255) NOT NULL,
  `provider` varchar(100) DEFAULT NULL,
  `tier` int(11) DEFAULT 3,
  `score` int(11) DEFAULT 50,
  `status` enum('owned','recommended') DEFAULT 'owned',
  `obtained_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_certifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_certifications`
--

LOCK TABLES `student_certifications` WRITE;
/*!40000 ALTER TABLE `student_certifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_certifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_courses`
--

DROP TABLE IF EXISTS `student_courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `course_name` varchar(200) DEFAULT NULL,
  `grade` varchar(5) DEFAULT NULL,
  `score` int(11) DEFAULT 0,
  `source` enum('transcript','manual') DEFAULT 'manual',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_course` (`student_id`,`course_id`,`source`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `student_courses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_courses_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_courses`
--

LOCK TABLES `student_courses` WRITE;
/*!40000 ALTER TABLE `student_courses` DISABLE KEYS */;
INSERT INTO `student_courses` VALUES (1,2,5,'Informatic Logic','A',10,'transcript','2026-06-06 13:19:47'),(2,2,3,'Information Technology Introduction','A',10,'transcript','2026-06-06 13:19:47'),(3,2,7,'Digital System','A',10,'transcript','2026-06-06 13:19:47'),(4,2,6,'Calculus','A',10,'transcript','2026-06-06 13:19:47'),(5,2,1,'Pancasila','A',10,'transcript','2026-06-06 13:19:47'),(6,2,8,'Religion Education','A',10,'transcript','2026-06-06 13:19:47'),(7,2,15,'English','A',10,'transcript','2026-06-06 13:19:47'),(8,2,12,'Linear Algebra','A',10,'transcript','2026-06-06 13:19:47'),(9,2,13,'Algorithm and Programming','B',7,'transcript','2026-06-06 13:19:47'),(10,2,10,'Computer and Society','A',10,'transcript','2026-06-06 13:19:47'),(11,2,16,'Citizenship','A',10,'transcript','2026-06-06 13:19:47'),(12,2,11,'Discrete Mathematics','B',7,'transcript','2026-06-06 13:19:47'),(13,2,14,'Probability and Statistics','B',7,'transcript','2026-06-06 13:19:47'),(14,2,9,'Computer Architecture and Organization','A',10,'transcript','2026-06-06 13:19:47'),(15,2,21,'Human Computer Interaction','A',10,'transcript','2026-06-06 13:19:47'),(16,2,17,'Algorithm and Data Structure','A',10,'transcript','2026-06-06 13:19:47'),(17,2,23,'Computer Network','A',10,'transcript','2026-06-06 13:19:47'),(18,2,22,'Database System','A',10,'transcript','2026-06-06 13:19:47'),(19,2,19,'Operating System','A',10,'transcript','2026-06-06 13:19:47'),(20,2,20,'Numerical Method','A',10,'transcript','2026-06-06 13:19:47'),(21,2,18,'Information System','A',10,'transcript','2026-06-06 13:19:47'),(22,2,31,'Professional Ethics','A',10,'transcript','2026-06-06 13:19:47');
/*!40000 ALTER TABLE `student_courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_projects`
--

DROP TABLE IF EXISTS `student_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `project_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `scale` enum('besar','kecil') DEFAULT 'kecil',
  `score` int(11) DEFAULT 20,
  `tech_stack` varchar(255) DEFAULT NULL,
  `project_url` varchar(500) DEFAULT NULL,
  `created_year` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `student_projects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_projects`
--

LOCK TABLES `student_projects` WRITE;
/*!40000 ALTER TABLE `student_projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_skills`
--

DROP TABLE IF EXISTS `student_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `student_skills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `skill_id` int(11) NOT NULL,
  `student_level` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_skill` (`student_id`,`skill_id`),
  KEY `skill_id` (`skill_id`),
  CONSTRAINT `student_skills_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `mahasiswa_profiles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_skills_ibfk_2` FOREIGN KEY (`skill_id`) REFERENCES `skills` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_skills`
--

LOCK TABLES `student_skills` WRITE;
/*!40000 ALTER TABLE `student_skills` DISABLE KEYS */;
INSERT INTO `student_skills` VALUES (1,2,21,10),(2,2,23,10),(3,2,22,2),(4,2,15,0),(5,2,14,5),(6,2,16,0),(7,2,24,0),(8,2,25,0),(9,2,26,0),(10,2,8,0),(11,2,7,0),(12,2,10,0),(13,2,9,0),(14,2,29,0),(15,2,17,0),(16,2,18,0),(17,2,13,0),(18,2,11,0),(19,2,12,0),(20,2,27,0),(21,2,28,0),(22,2,2,0),(23,2,5,0),(24,2,1,0),(25,2,6,0),(26,2,4,0),(27,2,3,0),(28,2,30,10),(29,2,19,10),(30,2,20,10);
/*!40000 ALTER TABLE `student_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_config`
--

DROP TABLE IF EXISTS `system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_val` varchar(500) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_config`
--

LOCK TABLES `system_config` WRITE;
/*!40000 ALTER TABLE `system_config` DISABLE KEYS */;
INSERT INTO `system_config` VALUES (1,'at_risk_gap_threshold','5','Jumlah minimum skill gap TINGGI yang dianggap at-risk','2026-06-06 13:08:54'),(2,'at_risk_ipk_threshold','2.5','Nilai IPK minimum Ã”Ã‡Ã¶ di bawah ini dianggap at-risk','2026-06-06 13:08:54'),(3,'monte_carlo_weight_ipk','0.30','Bobot IPK dalam simulasi Monte Carlo','2026-06-06 13:08:54'),(4,'monte_carlo_weight_skill','0.30','Bobot Hard Skill dalam simulasi Monte Carlo','2026-06-06 13:08:54'),(5,'monte_carlo_weight_cert','0.25','Bobot Sertifikasi dalam simulasi Monte Carlo','2026-06-06 13:08:54'),(6,'monte_carlo_weight_port','0.15','Bobot Portofolio dalam simulasi Monte Carlo','2026-06-06 13:08:54'),(7,'saw_weight_academic','0.40','Bobot Skill Akademik (W1)','2026-06-06 13:08:55'),(8,'saw_weight_practical','0.30','Bobot Skill Praktis (W2)','2026-06-06 13:08:55'),(9,'saw_weight_portfolio','0.20','Bobot Portofolio (W3)','2026-06-06 13:08:55'),(10,'saw_weight_certification','0.10','Bobot Sertifikasi (W4)','2026-06-06 13:08:55'),(11,'saw_sub_weight_course','0.70','Bobot Rata-Rata Matkul Spesifik (C1)','2026-06-06 13:08:55'),(12,'saw_sub_weight_ipk','0.30','Bobot Skor Konversi IPK (C1)','2026-06-06 13:08:55'),(13,'saw_tier1_min','85','Nilai Minimal Tier 1 (Internasional)','2026-06-06 13:08:55'),(14,'saw_tier2_min','70','Nilai Minimal Tier 2 (Nasional)','2026-06-06 13:08:55'),(15,'saw_tier3_min','55','Nilai Minimal Tier 3 (Lokal)','2026-06-06 13:08:55');
/*!40000 ALTER TABLE `system_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fullname` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('mahasiswa','dosen','admin') DEFAULT 'mahasiswa',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Gusti Ayu Marsha W.','marsha@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','mahasiswa',1,'2026-06-04 03:00:49'),(2,'Gusti Ayu Marsha Widyaswari','ayumarsha141@gmail.com','$2y$10$enlHZkwhGlDuB4itbUgjF.LXKvYnuc07txb0/jomzb1AEWY/OkYge','mahasiswa',1,'2026-06-04 03:07:03'),(3,'nadin marsha','marshanadin@gmail.com','$2y$10$Va6Yc6QLop/mlSWLTag/2uxTXkjwrSTIW5k/G45vtL35Xo/xOlFNq','mahasiswa',1,'2026-06-04 03:08:40'),(5,'Dr. Ahmad Fauzi','dosen@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','dosen',1,'2026-06-06 13:08:54'),(6,'Administrator','admin@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',1,'2026-06-06 13:08:55');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-06 21:32:57
