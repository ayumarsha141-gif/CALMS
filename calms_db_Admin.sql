USE calms_db;

CREATE TABLE IF NOT EXISTS industry_trends (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    category    VARCHAR(100),
    source      VARCHAR(100),
    trend_date  DATE,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO industry_trends (title, category, source, trend_date, description) VALUES
('AI & Machine Learning Mendominasi Rekrutmen Tech 2025',  'AI/ML',       'LinkedIn Indonesia', '2025-05-01', 'Permintaan engineer AI naik 40% dibanding tahun lalu.'),
('Cloud Native & Kubernetes Jadi Skill Wajib DevOps',      'Cloud/DevOps', 'JobStreet',          '2025-04-20', 'Perusahaan besar mulai mensyaratkan Kubernetes di JD DevOps.'),
('Full Stack JavaScript (React + Node) Masih Teratas',     'Frontend',     'Glassdoor',          '2025-04-10', 'React.js bertahan di posisi #1 framework frontend Indonesia.'),
('Cybersecurity Analyst: Shortage Talenta di Indonesia',   'Security',     'IDN Times Tech',     '2025-03-28', 'Kebutuhan analis keamanan siber meningkat pasca insiden data 2024.'),
('Data Engineering Geser Data Analyst di Prioritas Hiring','Data',         'Dicoding Insight',   '2025-03-15', 'Skill pipeline data & Spark lebih dicari dari sekadar SQL analyst.'),
('Flutter Jadi Pilihan Utama Mobile Dev Startup Indonesia', 'Mobile',      'Tech in Asia',       '2025-02-20', 'Startup tahap awal dominan pilih Flutter karena efisiensi tim.');