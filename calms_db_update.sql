-- ═══════════════════════════════════════════════
-- CALMS DB UPDATE — Curriculum Courses & Mapping
-- Run this AFTER the main calms_db.sql
-- ═══════════════════════════════════════════════
USE calms_db;

-- ── 1. Courses table (full BIE/PSTI curriculum) ──
CREATE TABLE IF NOT EXISTS courses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    course_code     VARCHAR(20),
    course_name     VARCHAR(200) NOT NULL,
    course_name_id  VARCHAR(200),
    semester        INT DEFAULT 1,
    credits         INT DEFAULT 3
) ENGINE=InnoDB;

-- ── 2. Course → Skill mapping table ──
CREATE TABLE IF NOT EXISTS course_skill_mapping (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    course_id   INT NOT NULL,
    skill_id    INT NOT NULL,
    UNIQUE KEY uq_csm (course_id, skill_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id)  REFERENCES skills(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. Student taken courses ──
CREATE TABLE IF NOT EXISTS student_courses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    course_id   INT,
    course_name VARCHAR(200),
    grade       VARCHAR(5),
    score       INT DEFAULT 0,
    source      ENUM('transcript','manual') DEFAULT 'manual',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id)  REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ═══════════════════════════════════════════════
-- INSERT ALL COURSES (BIE Curriculum Map Fig 3.2)
-- ═══════════════════════════════════════════════
INSERT IGNORE INTO courses (course_code, course_name, course_name_id, semester, credits) VALUES
-- Semester 1
('BIE101', 'Pancasila',                        'Pancasila',                               1, 2),
('BIE102', 'Interpersonal Skill',              'Interpersonal Skill',                     1, 2),
('BIE103', 'Information Technology Introduction','Pengantar Teknologi Informasi',          1, 3),
('BIE104', 'Technopreneurship',                'Technopreneurship',                       1, 2),
('BIE105', 'Informatic Logic',                 'Logika Informatika',                      1, 3),
('BIE106', 'Calculus',                         'Kalkulus',                                1, 3),
('BIE107', 'Digital System',                   'Sistem Digital',                          1, 3),
('BIE108', 'Religion Education',               'Pendidikan Agama',                        1, 2),
-- Semester 2
('BIE201', 'Computer Architecture and Organization','Arsitektur dan Organisasi Komputer', 2, 3),
('BIE202', 'Computer and Society',             'Komputer dan Masyarakat',                 2, 2),
('BIE203', 'Discrete Mathematics',             'Matematika Diskrit',                      2, 3),
('BIE204', 'Linear Algebra',                   'Aljabar Linier',                          2, 3),
('BIE205', 'Algorithm and Programming',        'Algoritma dan Pemrograman',               2, 4),
('BIE206', 'Probability and Statistics',       'Probabilitas dan Statistika',             2, 3),
('BIE207', 'English',                          'Bahasa Inggris',                          2, 2),
('BIE208', 'Citizenship',                      'Kewarganegaraan',                         2, 2),
-- Semester 3
('BIE301', 'Algorithm and Data Structure',     'Algoritma dan Struktur Data',             3, 4),
('BIE302', 'Information System',               'Sistem Informasi',                        3, 3),
('BIE303', 'Operating System',                 'Sistem Operasi',                          3, 3),
('BIE304', 'Numerical Method',                 'Metode Numerik',                          3, 3),
('BIE305', 'Human Computer Interaction',       'Interaksi Manusia dan Komputer',          3, 3),
('BIE306', 'Database System',                  'Sistem Basis Data',                       3, 4),
('BIE307', 'Computer Network',                 'Jaringan Komputer',                       3, 3),
-- Semester 4
('BIE401', 'File System',                      'Sistem Berkas',                           4, 3),
('BIE402', 'Object Oriented Programming and Analysis','Pemrograman Berorientasi Objek dan Analisis', 4, 4),
('BIE403', 'Software Engineering',             'Rekayasa Perangkat Lunak',                4, 3),
('BIE404', 'Digital Image Processing',         'Pengolahan Citra Digital',                4, 3),
('BIE405', 'Web Programming',                  'Pemrograman Web',                         4, 3),
('BIE406', 'Scientific Paper Writing',         'Penulisan Karya Ilmiah',                  4, 2),
('BIE407', 'Parallel Processing',              'Pemrosesan Paralel',                      4, 3),
-- Semester 5
('BIE501', 'Professional Ethics',              'Etika Profesi',                           5, 2),
('BIE502', 'Artificial Intelligence',          'Kecerdasan Buatan',                       5, 3),
('BIE503', 'Object Oriented Programming',      'Pemrograman Berorientasi Objek',          5, 3),
('BIE504', 'Information Technology Security',  'Keamanan Teknologi Informasi',            5, 3),
('BIE505', 'Research on Information Technology','Penelitian Teknologi Informasi',         5, 2),
('BIE506', 'Operational Research',             'Penelitian Operasional',                  5, 3),
('BIE507', 'Automate and Formal Language',     'Otomata dan Bahasa Formal',               5, 3),
('BIE508', 'Big Data',                         'Big Data',                                5, 3),
-- Semester 6
('BIE601', 'Internet of Things',               'Internet of Things (IoT)',                6, 3),
('BIE602', 'Fuzzy Logic',                      'Logika Fuzzy',                            6, 3),
('BIE603', 'Modeling and Simulation',          'Pemodelan dan Simulasi',                  6, 3),
('BIE604', 'Visual Programming',               'Pemrograman Visual',                      6, 3),
('BIE605', 'Practical Work',                   'Kerja Praktik',                           6, 2),
('BIE606', 'Distributed System',               'Sistem Terdistribusi',                    6, 3),
('BIE607', 'Mobile Programming',               'Pemrograman Mobile',                      6, 3),
-- Semester 7
('BIE701', 'Field Study Service',              'Kuliah Kerja Nyata (KKN)',                7, 3),
('BIE702', 'Internet Programming',             'Pemrograman Internet',                    7, 3),
('BIE703', 'Software Development Project',     'Proyek Pengembangan Perangkat Lunak',     7, 4),
('BIE704', 'Artificial Neural Network',        'Jaringan Syaraf Tiruan',                  7, 3),
('BIE705', 'Final Project I',                  'Skripsi I',                               7, 3),
-- Semester 8
('BIE801', 'Final Project II',                 'Skripsi II',                              8, 4);

-- ═══════════════════════════════════════════════
-- COURSE → SKILL MAPPINGS
-- skill_id reference:
--  1=Python  2=JavaScript  3=PHP  4=Java  5=TypeScript  6=Go
--  7=SQL  8=MySQL  9=PostgreSQL  10=MongoDB
-- 11=React.js  12=Vue.js  13=HTML/CSS  14=Node.js  15=Laravel  16=Express.js
-- 17=Docker  18=Kubernetes  19=Git/GitHub  20=Linux
-- 21=Machine Learning  22=Deep Learning  23=TensorFlow
-- 24=AWS  25=Google Cloud  26=Azure
-- 27=Flutter  28=React Native  29=Figma/UI Design  30=Cybersecurity
-- ═══════════════════════════════════════════════

-- Python (1): Logika, Kalkulus, Algo&Prog, Mat.Diskrit, AlJabar, Prob&Stat,
--             Algo&DS, Metode Numerik, Pengolahan Citra, Parallel, AI, Op.Research,
--             Otomata, Big Data, IoT, Fuzzy, Modeling
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 1 FROM courses c WHERE c.course_code IN
('BIE105','BIE106','BIE205','BIE203','BIE204','BIE206',
 'BIE301','BIE304','BIE404','BIE407','BIE502','BIE506',
 'BIE507','BIE508','BIE601','BIE602','BIE603');

-- JavaScript (2): Web Programming, Visual Programming, Internet Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 2 FROM courses c WHERE c.course_code IN
('BIE405','BIE604','BIE702');

-- PHP (3): Information System, Web Programming, Internet Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 3 FROM courses c WHERE c.course_code IN
('BIE302','BIE405','BIE702');

-- Java (4): Algo&Prog, Algo&DS, OOP&Analysis, OOP
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 4 FROM courses c WHERE c.course_code IN
('BIE205','BIE301','BIE402','BIE503');

-- SQL (7): Information System, Database System, Big Data
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 7 FROM courses c WHERE c.course_code IN
('BIE302','BIE306','BIE508');

-- MySQL (8): Database System
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 8 FROM courses c WHERE c.course_code IN
('BIE306');

-- MongoDB (10): Big Data
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 10 FROM courses c WHERE c.course_code IN
('BIE508');

-- React.js (11): Internet Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 11 FROM courses c WHERE c.course_code IN
('BIE702');

-- HTML/CSS (13): Web Programming, Visual Programming, Internet Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 13 FROM courses c WHERE c.course_code IN
('BIE405','BIE604','BIE702');

-- Node.js (14): IoT, Internet Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 14 FROM courses c WHERE c.course_code IN
('BIE601','BIE702');

-- Laravel (15): Software Dev Project
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 15 FROM courses c WHERE c.course_code IN
('BIE703');

-- Docker (17): Distributed System
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 17 FROM courses c WHERE c.course_code IN
('BIE606');

-- Kubernetes (18): Distributed System
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 18 FROM courses c WHERE c.course_code IN
('BIE606');

-- Git/GitHub (19): Software Engineering, Software Dev Project, Final Project I & II
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 19 FROM courses c WHERE c.course_code IN
('BIE403','BIE703','BIE705','BIE801');

-- Linux (20): Operating System, Computer Network, IoT
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 20 FROM courses c WHERE c.course_code IN
('BIE303','BIE307','BIE601');

-- Machine Learning (21): Linear Algebra, Prob&Stat, Pengolahan Citra, AI, Fuzzy Logic, ANN
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 21 FROM courses c WHERE c.course_code IN
('BIE204','BIE206','BIE404','BIE502','BIE602','BIE704');

-- Deep Learning (22): Artificial Neural Network
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 22 FROM courses c WHERE c.course_code IN
('BIE704');

-- TensorFlow (23): Artificial Neural Network
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 23 FROM courses c WHERE c.course_code IN
('BIE704');

-- AWS/Cloud (24): Distributed System
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 24 FROM courses c WHERE c.course_code IN
('BIE606');

-- Flutter (27): Mobile Programming
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 27 FROM courses c WHERE c.course_code IN
('BIE607');

-- Figma/UI Design (29): Human Computer Interaction
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 29 FROM courses c WHERE c.course_code IN
('BIE305');

-- Cybersecurity (30): Computer Network, IT Security
INSERT IGNORE INTO course_skill_mapping (course_id, skill_id)
SELECT c.id, 30 FROM courses c WHERE c.course_code IN
('BIE307','BIE504');
