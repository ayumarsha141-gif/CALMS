<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: dashboard.php');
elseif ($role === 'dosen') header('Location: dashboard.php');
else header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CALMS — Career Adaptive Learning Management System</title>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body>

    <header class="navbar" id="navbar">
        <div class="nav-brand">
            <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
        </div>
        <nav class="nav-links">
            <a href="#features">Features</a>
            <a href="#dashboard-preview">Dashboard</a>
            <a href="#labs">Lab Match</a>
            <a href="#about">About</a>
        </nav>
        <div class="nav-actions">
            <a href="register.php" class="btn-outline">Daftar</a>
            <a href="login.php" class="btn-primary">Masuk →</a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
    </header>

    <section class="hero">
        <div class="hero-bg-grid"></div>
        <div class="hero-content">
            <div class="hero-text">
                <div class="hero-badge">
                    <span class="badge-dot"></span>
                    GPS Karir Adaptif &middot; Universitas Mataram
                </div>
                <h1 class="hero-title">
                    Bangun <span class="highlight">Career Roadmap</span><br>Sebelum Lulus
                </h1>
                <p class="hero-desc">
                    CALMS membantu mahasiswa Informatika Unram memahami skill gap,
                    memprediksi peluang rekrutmen, dan merencanakan karir sejak semester awal
                    melalui simulasi Monte Carlo dan analisis industri.
                </p>
                <div class="hero-actions">
                    <a href="register.php" class="btn-primary btn-lg">Mulai Sekarang →</a>
                    <a href="#features" class="btn-ghost">Lihat Fitur ↓</a>
                </div>
                <div class="hero-stats">
                    <div class="stat-item">
                        <span class="stat-num">10+</span>
                        <span class="stat-label">Fitur Karir</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-num">5</span>
                        <span class="stat-label">Lab Rekomendasi</span>
                    </div>
                    <div class="stat-divider"></div>
                    <div class="stat-item">
                        <span class="stat-num">3</span>
                        <span class="stat-label">Aktor Sistem</span>
                    </div>
                </div>
            </div>

            <div class="hero-card-wrap">
                <div class="hero-card">
                    <div class="card-header">
                        <span class="card-title">Recruitment Probability</span>
                        <span class="card-badge-live">&#9679; Live</span>
                    </div>
                    <div class="probability-display">
                        <div class="prob-ring">
                            <svg viewBox="0 0 100 100" class="ring-svg">
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#1e293b" stroke-width="8"/>
                                <circle cx="50" cy="50" r="42" fill="none" stroke="#22d3ee" stroke-width="8"
                                    stroke-dasharray="264" stroke-dashoffset="57"
                                    stroke-linecap="round" transform="rotate(-90 50 50)" class="ring-progress"/>
                            </svg>
                            <span class="prob-number">78%</span>
                        </div>
                        <div class="prob-label">Monte Carlo Simulation<br><small>10.000 iterasi</small></div>
                    </div>
                    <div class="skill-bars">
                        <div class="skill-row">
                            <span class="skill-name">Frontend Skill</span>
                            <div class="bar-wrap"><div class="bar-fill bar-fill--cyan bar-fill--85"></div></div>
                            <span class="skill-pct">85%</span>
                        </div>
                        <div class="skill-row">
                            <span class="skill-name">Portfolio Quality</span>
                            <div class="bar-wrap"><div class="bar-fill bar-fill--blue bar-fill--70"></div></div>
                            <span class="skill-pct">70%</span>
                        </div>
                        <div class="skill-row">
                            <span class="skill-name">Industry Readiness</span>
                            <div class="bar-wrap"><div class="bar-fill bar-fill--green bar-fill--62"></div></div>
                            <span class="skill-pct">62%</span>
                        </div>
                    </div>
                    <div class="card-footer-info">
                        <span class="target-label">Target:</span>
                        <span class="target-value">Backend Dev @ Tokopedia</span>
                    </div>
                </div>
                <div class="float-badge float-top">&#127919; Lab AI Match: <strong>89%</strong></div>
                <div class="float-badge float-bottom">&#128220; AWS Cert Recommended</div>
            </div>
        </div>
    </section>

    <section id="features" class="section features-section">
        <div class="section-label">FITUR UTAMA</div>
        <h2 class="section-title">Semua yang Kamu Butuhkan<br>untuk Persiapan Karir</h2>
        <div class="feature-grid">
            <div class="feature-card feature-card--cyan">
                <div class="feature-icon">&#128506;</div>
                <h3>Adaptive Roadmap</h3>
                <p>Roadmap karir personal berdasarkan target dan skill yang sudah kamu miliki.</p>
            </div>
            <div class="feature-card feature-card--blue">
                <div class="feature-icon">&#128202;</div>
                <h3>Skill Gap Analysis</h3>
                <p>Bandingkan kemampuanmu dengan standar industri secara visual dan interaktif.</p>
            </div>
            <div class="feature-card feature-card--green">
                <div class="feature-icon">&#127922;</div>
                <h3>Monte Carlo Simulation</h3>
                <p>Prediksi peluang lolos rekrutmen menggunakan simulasi 10.000 iterasi adaptif.</p>
            </div>
            <div class="feature-card feature-card--purple">
                <div class="feature-icon">&#128300;</div>
                <h3>Lab Recommendation</h3>
                <p>Rekomendasi Lab TA berdasarkan skill, nilai akademik, dan target karirmu.</p>
            </div>
            <div class="feature-card feature-card--amber">
                <div class="feature-icon">&#128200;</div>
                <h3>Industry Insight</h3>
                <p>Tren skill dan profesi yang paling dicari industri tech Indonesia saat ini.</p>
            </div>
            <div class="feature-card feature-card--red">
                <div class="feature-icon">&#127885;</div>
                <h3>Certification Guide</h3>
                <p>Rekomendasi sertifikasi industri yang relevan dengan jalur karir pilihanmu.</p>
            </div>
        </div>
    </section>

    <section id="dashboard-preview" class="section dashboard-preview-section">
        <div class="section-label">PREVIEW DASHBOARD</div>
        <h2 class="section-title">Pantau Semua Progress<br>dalam Satu Layar</h2>
        <div class="preview-panels">
            <div class="panel">
                <div class="panel-head">
                    <span>Skill Gap Dashboard</span>
                    <span class="panel-tag">Mahasiswa</span>
                </div>
                <div class="skill-list">
                    <div class="skill-item">
                        <div class="skill-meta"><span>Python</span><span class="gap gap-low">Gap Rendah</span></div>
                        <div class="bar-wrap"><div class="bar-fill bar-fill--cyan bar-fill--82"></div></div>
                    </div>
                    <div class="skill-item">
                        <div class="skill-meta"><span>JavaScript</span><span class="gap gap-med">Gap Sedang</span></div>
                        <div class="bar-wrap"><div class="bar-fill bar-fill--blue bar-fill--65"></div></div>
                    </div>
                    <div class="skill-item">
                        <div class="skill-meta"><span>Cloud Computing</span><span class="gap gap-high">Gap Tinggi</span></div>
                        <div class="bar-wrap"><div class="bar-fill bar-fill--amber bar-fill--38"></div></div>
                    </div>
                    <div class="skill-item">
                        <div class="skill-meta"><span>Machine Learning</span><span class="gap gap-high">Gap Tinggi</span></div>
                        <div class="bar-wrap"><div class="bar-fill bar-fill--red bar-fill--25"></div></div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-head">
                    <span>Rekomendasi Sertifikasi</span>
                    <span class="panel-tag">Top 3</span>
                </div>
                <div class="cert-list">
                    <div class="cert-item">
                        <div class="cert-rank r1">1</div>
                        <div class="cert-info">
                            <strong>Google Data Analytics</strong>
                            <span>Beginner &middot; Google</span>
                        </div>
                        <span class="cert-pct">94%</span>
                    </div>
                    <div class="cert-item">
                        <div class="cert-rank r2">2</div>
                        <div class="cert-info">
                            <strong>Meta Front-End Developer</strong>
                            <span>Intermediate &middot; Meta</span>
                        </div>
                        <span class="cert-pct">87%</span>
                    </div>
                    <div class="cert-item">
                        <div class="cert-rank r3">3</div>
                        <div class="cert-info">
                            <strong>AWS Cloud Practitioner</strong>
                            <span>Beginner &middot; Amazon</span>
                        </div>
                        <span class="cert-pct">79%</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="labs" class="section labs-section">
        <div class="section-label">LAB RECOMMENDATION</div>
        <h2 class="section-title">Temukan Lab TA<br>yang Paling Sesuai</h2>
        <div class="lab-grid">
            <div class="lab-card">
                <div class="lab-pct pct-high">89%</div>
                <h3>Lab Kecerdasan Buatan</h3>
                <p>AI, Machine Learning, Deep Learning, Computer Vision</p>
                <div class="lab-tags"><span>Python</span><span>TensorFlow</span><span>Data Science</span></div>
            </div>
            <div class="lab-card">
                <div class="lab-pct pct-mid">74%</div>
                <h3>Lab Sistem Informasi</h3>
                <p>Enterprise Systems, Database, Business Intelligence</p>
                <div class="lab-tags"><span>SQL</span><span>PHP</span><span>ERP</span></div>
            </div>
            <div class="lab-card">
                <div class="lab-pct pct-low">61%</div>
                <h3>Lab Rekayasa Perangkat Lunak</h3>
                <p>Software Engineering, Mobile Dev, Web Application</p>
                <div class="lab-tags"><span>Java</span><span>React</span><span>Flutter</span></div>
            </div>
        </div>
    </section>

    <section id="about" class="section about-section">
        <div class="cta-box">
            <div class="cta-left">
                <div class="section-label">TENTANG CALMS</div>
                <h2>Dibuat oleh Mahasiswa<br>untuk Mahasiswa Informatika Unram</h2>
                <p>CALMS dikembangkan sebagai project akhir mata kuliah Pemrograman Web oleh mahasiswa Informatika Universitas Mataram angkatan 2024.</p>
                <div class="team-list">
                    <div class="team-member">
                        <div class="member-avatar">GM</div>
                        <div class="member-info">
                            <strong>Gusti Ayu Marsha W.</strong>
                            <span>Frontend &amp; System Analyst</span>
                        </div>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">WA</div>
                        <div class="member-info">
                            <strong>Winona Andien J.</strong>
                            <span>Backend Developer</span>
                        </div>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">NM</div>
                        <div class="member-info">
                            <strong>Nadin Mufida</strong>
                            <span>Database &amp; Documentation</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="cta-right">
                <h3>Siap Mulai?</h3>
                <p>Daftar sekarang dan temukan roadmap karirmu sejak semester awal.</p>
                <a href="register.php" class="btn-primary btn-lg btn-block">Daftar Gratis →</a>
                <a href="login.php" class="btn-outline btn-block btn-block--mt">Sudah punya akun? Masuk</a>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="footer-brand">
            <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
        </div>
        <p class="footer-sub">Career Adaptive Learning Management System</p>
        <p class="footer-copy">&copy; 2025 Informatika Universitas Mataram &middot; Project Akhir Pemrograman Web</p>
    </footer>

    <script src="main.js"></script>
</body>
</html>
