CREATE DATABASE IF NOT EXISTS calms_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE calms_db;

-- ============================================================
-- 1. USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fullname   VARCHAR(255) NOT NULL,
    email      VARCHAR(255) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('mahasiswa','dosen','admin') DEFAULT 'mahasiswa',
    is_active  TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. MAHASISWA PROFILES
-- ============================================================
CREATE TABLE IF NOT EXISTS mahasiswa_profiles (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNIQUE NOT NULL,
    nim            VARCHAR(20) UNIQUE NOT NULL,
    semester       INT DEFAULT 1,
    ipk            DECIMAL(3,2) DEFAULT 0.00,
    target_career  VARCHAR(100),
    bio            TEXT,
    linkedin_url   VARCHAR(500),
    github_url     VARCHAR(500),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. SKILLS (Katalog standar industri)
-- ============================================================
CREATE TABLE IF NOT EXISTS skills (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    skill_name      VARCHAR(100) NOT NULL,
    category        VARCHAR(50),
    industry_level  INT DEFAULT 8  -- /10, standar industri
) ENGINE=InnoDB;

INSERT INTO skills (skill_name, category, industry_level) VALUES
('Python',           'Programming',    8),
('JavaScript',       'Programming',    9),
('PHP',              'Programming',    7),
('Java',             'Programming',    7),
('TypeScript',       'Programming',    8),
('Go / Golang',      'Programming',    7),
('SQL',              'Database',       8),
('MySQL',            'Database',       8),
('PostgreSQL',       'Database',       7),
('MongoDB',          'Database',       7),
('React.js',         'Frontend',       9),
('Vue.js',           'Frontend',       7),
('HTML/CSS',         'Frontend',       9),
('Node.js',          'Backend',        8),
('Laravel',          'Backend',        8),
('Express.js',       'Backend',        7),
('Docker',           'DevOps',         8),
('Kubernetes',       'DevOps',         7),
('Git / GitHub',     'Tools',          9),
('Linux',            'Tools',          8),
('Machine Learning', 'AI/ML',          8),
('Deep Learning',    'AI/ML',          7),
('TensorFlow',       'AI/ML',          7),
('AWS',              'Cloud',          8),
('Google Cloud',     'Cloud',          7),
('Azure',            'Cloud',          7),
('Flutter',          'Mobile',         8),
('React Native',     'Mobile',         7),
('Figma / UI Design','Design',         7),
('Cybersecurity',    'Security',       8);

-- ============================================================
-- 4. STUDENT SKILLS
-- ============================================================
CREATE TABLE IF NOT EXISTS student_skills (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    skill_id      INT NOT NULL,
    student_level INT DEFAULT 0,  -- /10
    UNIQUE KEY uq_student_skill (student_id, skill_id),
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id)   REFERENCES skills(id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. CERTIFICATIONS CATALOG (dengan sistem Tiering)
-- ============================================================
-- Tier 1 (score=100): Sertifikasi Vendor Internasional
-- Tier 2 (score=75):  Sertifikasi Nasional BNSP
-- Tier 3 (score=50):  Certificate Kursus Biasa
-- ============================================================
CREATE TABLE IF NOT EXISTS certifications (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    cert_name        VARCHAR(255) NOT NULL,
    provider         VARCHAR(100),
    tier             INT DEFAULT 3,
    score            INT DEFAULT 50,
    category         VARCHAR(50),
    career_relevance VARCHAR(255)
) ENGINE=InnoDB;

INSERT INTO certifications (cert_name, provider, tier, score, category, career_relevance) VALUES
-- Tier 1 — Internasional
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
-- Tier 2 — Nasional BNSP
('Sertifikat Kompetensi Programmer BNSP',   'BNSP',                2,  75, 'Programming',     'Backend, Frontend Developer'),
('Sertifikat Kompetensi Junior Network Administrator', 'BNSP',     2,  75, 'Networking',      'Network Engineer'),
('Sertifikat Kompetensi Database Administrator', 'BNSP',           2,  75, 'Database',        'Data Engineer, DBA'),
('Sertifikat Kompetensi Web Developer',     'BNSP',                2,  75, 'Programming',     'Full Stack Developer'),
('Sertifikat Kompetensi Keamanan Informasi','BNSP',                2,  75, 'Security',        'Cybersecurity Analyst'),
-- Tier 3 — Kursus
('Python for Everybody',                    'Coursera / UMich',    3,  50, 'Programming',     'Backend, Data Science'),
('Machine Learning Specialization',         'Coursera / DeepLearning.AI', 3, 50, 'AI/ML',    'ML Engineer'),
('The Web Developer Bootcamp',              'Udemy',               3,  50, 'Programming',     'Full Stack Developer'),
('JavaScript Algorithms and Data Structures','freeCodeCamp',       3,  50, 'Programming',     'Frontend, Backend Developer'),
('Responsive Web Design',                   'freeCodeCamp',        3,  50, 'Frontend',        'Frontend Developer'),
('Data Science: R Basics',                  'edX / HarvardX',      3,  50, 'Data',            'Data Analyst'),
('Introduction to Cybersecurity',           'Cisco NetAcad',       3,  50, 'Security',        'Cybersecurity Analyst'),
('Flutter & Dart Development',              'Udemy',               3,  50, 'Mobile',          'Mobile Developer'),
('SQL for Data Science',                    'Coursera / UC Davis', 3,  50, 'Database',        'Data Analyst, DBA');

-- ============================================================
-- 6. STUDENT CERTIFICATIONS (yang dimiliki mahasiswa)
-- ============================================================
CREATE TABLE IF NOT EXISTS student_certifications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    cert_name     VARCHAR(255) NOT NULL,
    provider      VARCHAR(100),
    tier          INT DEFAULT 3,
    score         INT DEFAULT 50,
    status        ENUM('owned','recommended') DEFAULT 'owned',
    obtained_date DATE,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. STUDENT PROJECTS (Portofolio)
-- ============================================================
-- scale 'besar': Tugas Akhir/Client/Teamwork  → score 40
-- scale 'kecil': Tugas harian/Individual      → score 20
-- ============================================================
CREATE TABLE IF NOT EXISTS student_projects (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_id    INT NOT NULL,
    project_name  VARCHAR(255) NOT NULL,
    description   TEXT,
    scale         ENUM('besar','kecil') DEFAULT 'kecil',
    score         INT DEFAULT 20,
    tech_stack    VARCHAR(255),
    project_url   VARCHAR(500),
    created_year  INT,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 8. SIMULATIONS (Hasil Monte Carlo)
-- ============================================================
CREATE TABLE IF NOT EXISTS simulations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    student_id        INT NOT NULL,
    target_role       VARCHAR(100),
    target_company    VARCHAR(100),
    ipk_score         DECIMAL(5,2) DEFAULT 0,
    skill_score       DECIMAL(5,2) DEFAULT 0,
    cert_score        DECIMAL(5,2) DEFAULT 0,
    portfolio_score   DECIMAL(5,2) DEFAULT 0,
    probability_score DECIMAL(5,4) DEFAULT 0,
    iterations        INT DEFAULT 10000,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES mahasiswa_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. LABS (Rekomendasi Lab TA)
-- ============================================================
CREATE TABLE IF NOT EXISTS labs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    lab_name    VARCHAR(100) NOT NULL,
    description TEXT,
    focus_area  VARCHAR(255),
    skill_tags  VARCHAR(500)
) ENGINE=InnoDB;

INSERT INTO labs (lab_name, description, focus_area, skill_tags) VALUES
('Lab Kecerdasan Buatan',        'Riset AI, Machine Learning, Deep Learning, Computer Vision, NLP', 'AI/ML',          'Python,TensorFlow,Machine Learning,Deep Learning,Data Science'),
('Lab Sistem Informasi',          'Enterprise Systems, Database, Business Intelligence, ERP',        'Data/Enterprise', 'SQL,PHP,Java,Database,ERP,Business Intelligence'),
('Lab Rekayasa Perangkat Lunak', 'Software Engineering, Mobile Dev, Web Application, Agile',        'Software Eng',   'Java,React,Flutter,Git,Agile,Testing'),
('Lab Jaringan Komputer',         'Networking, Cybersecurity, Cloud Infrastructure',                  'Network/Cloud',  'Linux,Docker,AWS,Kubernetes,Networking,Cybersecurity'),
('Lab Multimedia & Desain',       'UI/UX Design, Multimedia, Grafik Komputer',                       'Design',         'Figma,CSS,JavaScript,UI/UX,Adobe');

-- ============================================================
-- DEMO USER (untuk testing — password: password123)
-- ============================================================
INSERT IGNORE INTO users (fullname, email, password, role) VALUES
('Gusti Ayu Marsha W.', 'marsha@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'mahasiswa');

INSERT IGNORE INTO mahasiswa_profiles (user_id, nim, semester, ipk, target_career) VALUES
(1, 'F1D024001', 5, 3.45, 'Data Scientist');
