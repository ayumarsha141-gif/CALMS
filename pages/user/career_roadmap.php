<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

// ── Profile ──
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];

$targetCareerName = $profile['target_career'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_career'])) {
    $newCareer = trim($_POST['target_career'] ?? '');
    if ($newCareer) {
        $db->prepare("UPDATE mahasiswa_profiles SET target_career = ? WHERE id = ?")->execute([$newCareer, $studentId]);
        header('Location: career_roadmap.php?career_updated=1');
        exit;
    }
}

// ── All roles [id => name] ──
$allRoles = $db->query("SELECT id, position_name FROM career_positions ORDER BY position_name ASC")->fetchAll(PDO::FETCH_KEY_PAIR);

// Normalize career
if (!in_array($targetCareerName, $allRoles, true) && !empty($allRoles)) {
    $targetCareerName = current($allRoles);
    $db->prepare("UPDATE mahasiswa_profiles SET target_career = ? WHERE id = ?")->execute([$targetCareerName, $studentId]);
}
$careerId = array_search($targetCareerName, $allRoles, true);

// ── Readiness dari student_skills ──
$readiness = 0;
try {
    $stmt = $db->prepare("
        SELECT AVG(LEAST(ss.student_level / NULLIF(sk.industry_level, 0), 1.0)) * 100 AS pct
        FROM career_skills cs
        JOIN skills sk ON sk.name = cs.skill_name
        LEFT JOIN student_skills ss ON ss.skill_id = sk.id AND ss.student_id = ?
        WHERE cs.career_id = ?
    ");
    $stmt->execute([$studentId, $careerId]);
    $r = $stmt->fetch();
    $readiness = $r ? (int)round((float)$r['pct']) : 0;
} catch (PDOException $e) { $readiness = 0; }

// ── Roadmap dari DB (roadmap_steps) ──
$dbRoadmapSteps = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT rs.*, c.course_name_id, c.course_code, sc.grade
        FROM roadmap_steps rs
        LEFT JOIN courses c ON c.id = rs.course_id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        WHERE rs.career_id = ?
        ORDER BY rs.id ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $dbRoadmapSteps = $stmt->fetchAll();
}

// ── Static roadmap fallback (kalau DB belum diisi admin) ──
$staticRoadmaps = [
    'Data Scientist' => [
        ['phase'=>'Fondasi','label'=>'Kuasai Dasar','months'=>'1-2','tasks'=>['Python dasar & OOP','SQL & manipulasi data','Statistik deskriptif & probabilitas','Linear algebra & kalkulus dasar'],'color'=>'#22d3ee'],
        ['phase'=>'Core Skills','label'=>'Data Science Core','months'=>'3-5','tasks'=>['Pandas, NumPy, Matplotlib','Machine Learning (Scikit-learn)','Feature engineering','Model evaluation & validation'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Deep Learning & BI','months'=>'6-9','tasks'=>['TensorFlow / PyTorch','Deep Learning & Neural Networks','Data visualization (Tableau/Power BI)','NLP & Computer Vision intro'],'color'=>'#f59e0b'],
        ['phase'=>'Portfolio','label'=>'Proyek & Sertifikasi','months'=>'10-12','tasks'=>['Kaggle competitions','Google Data Analytics Cert','Portfolio di GitHub','Kontribusi open-source'],'color'=>'#10b981'],
    ],
    'Full Stack Developer' => [
        ['phase'=>'Fondasi','label'=>'HTML/CSS/JS','months'=>'1-2','tasks'=>['HTML5 semantik','CSS3 & Flexbox/Grid','JavaScript ES6+','Git & version control'],'color'=>'#22d3ee'],
        ['phase'=>'Frontend','label'=>'Framework Frontend','months'=>'3-5','tasks'=>['React.js / Vue.js','State management','REST API integration','Responsive design'],'color'=>'#a78bfa'],
        ['phase'=>'Backend','label'=>'Server & Database','months'=>'6-8','tasks'=>['Node.js / Laravel / Django','RESTful API development','SQL & NoSQL database','Authentication & security'],'color'=>'#f59e0b'],
        ['phase'=>'Deployment','label'=>'DevOps & Launch','months'=>'9-12','tasks'=>['Docker & containers','CI/CD pipeline','Cloud deployment (AWS/GCP)','Portfolio & interview prep'],'color'=>'#10b981'],
    ],
    'Cybersecurity Analyst' => [
        ['phase'=>'Fondasi','label'=>'Networking Basics','months'=>'1-2','tasks'=>['TCP/IP & networking fundamentals','Linux command line','Kriptografi dasar','Security concepts & CIA triad'],'color'=>'#22d3ee'],
        ['phase'=>'Security Core','label'=>'Security Tools','months'=>'3-5','tasks'=>['Penetration testing basics','Wireshark & network analysis','Vulnerability scanning','OWASP Top 10'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Ethical Hacking','months'=>'6-9','tasks'=>['Metasploit framework','Web app security testing','Incident response','Security operations (SOC)'],'color'=>'#f59e0b'],
        ['phase'=>'Sertifikasi','label'=>'Cert & Career','months'=>'10-12','tasks'=>['CompTIA Security+','CEH certification','SIEM tools (Splunk)','Portfolio CTF writeups'],'color'=>'#10b981'],
    ],
    'Backend Developer' => [
        ['phase'=>'Fondasi','label'=>'Dasar Backend','months'=>'1-2','tasks'=>['PHP / Python / Node.js dasar','SQL & relational database','Git & version control','HTTP & REST basics'],'color'=>'#22d3ee'],
        ['phase'=>'Framework','label'=>'Framework & API','months'=>'3-5','tasks'=>['Laravel / Express.js / Django','RESTful API development','Authentication (JWT, OAuth)','Database ORM & migrations'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Scalability & Security','months'=>'6-9','tasks'=>['Caching (Redis)','Message queues','API security best practices','Unit & integration testing'],'color'=>'#f59e0b'],
        ['phase'=>'Deployment','label'=>'DevOps & Launch','months'=>'10-12','tasks'=>['Docker & containerization','CI/CD pipeline','Cloud deployment (AWS/GCP)','Portfolio & interview prep'],'color'=>'#10b981'],
    ],
    'Frontend Developer' => [
        ['phase'=>'Fondasi','label'=>'HTML/CSS/JS','months'=>'1-2','tasks'=>['HTML5 semantik','CSS3 & Flexbox/Grid','JavaScript ES6+','Responsive design'],'color'=>'#22d3ee'],
        ['phase'=>'Framework','label'=>'React / Vue','months'=>'3-5','tasks'=>['React.js / Vue.js','State management (Redux/Pinia)','REST API integration','Component architecture'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Performance & UX','months'=>'6-9','tasks'=>['TypeScript','Web performance optimization','Testing (Jest, Cypress)','Accessibility (a11y)'],'color'=>'#f59e0b'],
        ['phase'=>'Portfolio','label'=>'Build & Deploy','months'=>'10-12','tasks'=>['SSR/SSG (Next.js/Nuxt)','CI/CD & Vercel/Netlify','Portfolio projects','Meta Front-End Certificate'],'color'=>'#10b981'],
    ],
    'Data Analyst' => [
        ['phase'=>'Fondasi','label'=>'Data Basics','months'=>'1-2','tasks'=>['Excel & Google Sheets','SQL dasar & query','Statistik deskriptif','Python untuk data (Pandas)'],'color'=>'#22d3ee'],
        ['phase'=>'Visualization','label'=>'Data Viz & BI','months'=>'3-5','tasks'=>['Tableau / Power BI','Matplotlib & Seaborn','Dashboard design','Storytelling dengan data'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Analytics Lanjut','months'=>'6-9','tasks'=>['A/B testing & statistik inferensial','Cohort & funnel analysis','SQL advanced (window functions)','Google Analytics / Looker'],'color'=>'#f59e0b'],
        ['phase'=>'Sertifikasi','label'=>'Cert & Portfolio','months'=>'10-12','tasks'=>['Google Data Analytics Cert','Kaggle competitions','Portfolio dashboard','Internship / freelance project'],'color'=>'#10b981'],
    ],
    'ML Engineer' => [
        ['phase'=>'Fondasi','label'=>'Math & Python','months'=>'1-2','tasks'=>['Python & NumPy/Pandas','Linear algebra & probabilitas','Statistik inferensial','Git & Jupyter Notebook'],'color'=>'#22d3ee'],
        ['phase'=>'ML Core','label'=>'Machine Learning','months'=>'3-6','tasks'=>['Scikit-learn & ML algorithms','Feature engineering','Model evaluation & tuning','Supervised & unsupervised learning'],'color'=>'#a78bfa'],
        ['phase'=>'Deep Learning','label'=>'Neural Networks','months'=>'7-9','tasks'=>['TensorFlow / PyTorch','CNN, RNN, Transformer','Transfer learning','MLOps basics'],'color'=>'#f59e0b'],
        ['phase'=>'Deployment','label'=>'Production ML','months'=>'10-12','tasks'=>['Model serving (FastAPI/Flask)','Docker & cloud ML (Vertex AI/SageMaker)','TensorFlow Developer Cert','Kaggle & portfolio'],'color'=>'#10b981'],
    ],
    'DevOps Engineer' => [
        ['phase'=>'Fondasi','label'=>'Linux & Scripting','months'=>'1-2','tasks'=>['Linux administration','Bash & Python scripting','Networking fundamentals','Git & version control'],'color'=>'#22d3ee'],
        ['phase'=>'CI/CD','label'=>'Pipeline & Automation','months'=>'3-5','tasks'=>['Docker & containerization','GitHub Actions / Jenkins','Infrastructure as Code (Terraform)','Ansible & configuration management'],'color'=>'#a78bfa'],
        ['phase'=>'Cloud','label'=>'Cloud & Kubernetes','months'=>'6-9','tasks'=>['AWS / GCP / Azure fundamentals','Kubernetes & Helm','Monitoring (Prometheus, Grafana)','Service mesh & microservices'],'color'=>'#f59e0b'],
        ['phase'=>'Sertifikasi','label'=>'Cert & Specialization','months'=>'10-12','tasks'=>['AWS DevOps Engineer Cert','Google Cloud Professional','SRE practices','Portfolio & open-source contrib'],'color'=>'#10b981'],
    ],
    'UI/UX Designer' => [
        ['phase'=>'Fondasi','label'=>'Design Basics','months'=>'1-2','tasks'=>['Prinsip desain (kontras, hierarki, spasi)','Tipografi & color theory','Figma dasar','User research methods'],'color'=>'#22d3ee'],
        ['phase'=>'UX Process','label'=>'Research & Wireframe','months'=>'3-5','tasks'=>['User persona & journey map','Wireframing & prototyping','Usability testing','Information architecture'],'color'=>'#a78bfa'],
        ['phase'=>'UI Design','label'=>'Visual & Interaction','months'=>'6-9','tasks'=>['Design system & component library','Micro-interactions & animation','Responsive & mobile design','Handoff ke developer (Zeplin)'],'color'=>'#f59e0b'],
        ['phase'=>'Portfolio','label'=>'Case Study & Cert','months'=>'10-12','tasks'=>['3+ case study portfolio','Google UX Design Certificate','Dribbble / Behance presence','Internship / freelance project'],'color'=>'#10b981'],
    ],
    'Cloud Engineer' => [
        ['phase'=>'Fondasi','label'=>'Linux & Networking','months'=>'1-2','tasks'=>['Linux administration','Networking & protocols','Bash scripting','Git & version control'],'color'=>'#22d3ee'],
        ['phase'=>'Cloud Core','label'=>'AWS / GCP Basics','months'=>'3-5','tasks'=>['Cloud fundamentals','AWS EC2, S3, RDS','IAM & security policies','Serverless functions (Lambda)'],'color'=>'#a78bfa'],
        ['phase'=>'DevOps','label'=>'Containerization','months'=>'6-9','tasks'=>['Docker & container orchestration','Kubernetes','Terraform & IaC','CI/CD pipelines'],'color'=>'#f59e0b'],
        ['phase'=>'Sertifikasi','label'=>'AWS/GCP Certified','months'=>'10-12','tasks'=>['AWS Solutions Architect Cert','Google ACE Cert','Multi-cloud architecture','Cost optimization'],'color'=>'#10b981'],
    ],
    'Mobile Developer' => [
        ['phase'=>'Fondasi','label'=>'Programming Basics','months'=>'1-2','tasks'=>['Dart / Kotlin / Swift dasar','OOP principles','Git & version control','UI/UX fundamentals'],'color'=>'#22d3ee'],
        ['phase'=>'Flutter/Native','label'=>'Mobile Framework','months'=>'3-6','tasks'=>['Flutter widgets & state','Navigation & routing','API integration','Local storage (SQLite/Hive)'],'color'=>'#a78bfa'],
        ['phase'=>'Advanced','label'=>'Advanced Features','months'=>'7-9','tasks'=>['Push notifications','Firebase integration','Maps & geolocation','Performance optimization'],'color'=>'#f59e0b'],
        ['phase'=>'Launch','label'=>'Deploy & Portfolio','months'=>'10-12','tasks'=>['App Store / Play Store deployment','Flutter certification (Udemy)','Portfolio apps','Freelance / internship'],'color'=>'#10b981'],
    ],
];

// ── Helper function ──
function convertGradeToScore($grade) {
    $map = ['A'=>100,'A+'=>100,'A-'=>95,'B+'=>90,'B'=>80,'B-'=>75,'C+'=>70,'C'=>60,'C-'=>55,'D'=>40,'E'=>0];
    return $map[strtoupper($grade ?? '')] ?? 0;
}

// Cek apakah pakai mode DB atau static
$useDBMode    = !empty($dbRoadmapSteps);
$staticPhases = $staticRoadmaps[$targetCareerName] ?? $staticRoadmaps['Data Scientist'];
$activePage   = 'roadmap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Roadmap — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Career Selector */
        .career-selector{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 22px;margin-bottom:24px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
        .career-selector label{font-size:13px;color:var(--text-secondary);font-weight:500;flex-shrink:0}
        .career-select{background:var(--bg-secondary);border:1px solid var(--border);color:var(--text-primary);padding:8px 14px;border-radius:var(--radius-sm);font-size:13px;cursor:pointer;font-family:var(--font-sans);flex:1;min-width:200px}
        .career-select:focus{outline:none;border-color:var(--cyan)}
        .career-change-btn{padding:8px 18px;background:var(--cyan);color:#0a0f1a;border:none;border-radius:var(--radius-sm);font-size:13px;font-weight:700;cursor:pointer}
        .career-change-btn:hover{opacity:.85}

        /* Hero */
        .roadmap-hero{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:28px;margin-bottom:28px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
        .roadmap-hero-left h2{font-size:20px;font-weight:700;margin-bottom:4px}
        .roadmap-hero-left p{font-size:13px;color:var(--text-secondary)}
        .readiness-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:999px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.25);color:var(--cyan);font-weight:700;font-size:14px}

        /* ── Static / Phase mode ── */
        .roadmap-timeline{position:relative}
        .roadmap-timeline::before{content:'';position:absolute;left:28px;top:0;bottom:0;width:2px;background:linear-gradient(to bottom,#22d3ee,#a78bfa,#f59e0b,#10b981);opacity:.3}
        .roadmap-phase{display:flex;gap:24px;margin-bottom:32px;position:relative}
        .phase-indicator{flex-shrink:0;display:flex;flex-direction:column;align-items:center}
        .phase-dot{width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:700;z-index:1;border:2px solid}
        .phase-body{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:22px 24px;flex:1;transition:var(--transition)}
        .phase-body:hover{border-color:var(--border-hover);transform:translateX(4px)}
        .phase-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
        .phase-label{font-size:16px;font-weight:700}
        .phase-months{font-size:11px;padding:3px 10px;border-radius:999px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:var(--text-muted)}
        .phase-tag{font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px}
        .task-list{list-style:none;display:flex;flex-direction:column;gap:8px}
        .task-list li{display:flex;align-items:flex-start;gap:8px;font-size:13px;color:var(--text-secondary)}
        .task-list li::before{content:'▸';flex-shrink:0;margin-top:1px}

        /* ── DB Step mode ── */
        .step-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.1);border-radius:8px;padding:20px;margin-bottom:15px}
        .step-header{display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding-bottom:10px;margin-bottom:10px}
        .step-title{font-size:16px;font-weight:700;color:var(--cyan)}
        .step-course{font-size:14px;font-weight:600;color:#fff}
        .status-badge{padding:4px 10px;border-radius:4px;font-size:12px;font-weight:bold}
        .status-review{background:rgba(239,68,68,.2);color:#ef4444;border:1px solid #ef4444}
        .status-plan{background:rgba(245,158,11,.2);color:#f59e0b;border:1px solid #f59e0b}
        .status-good{background:rgba(16,185,129,.2);color:#10b981;border:1px solid #10b981}
        .saran-box{background:rgba(34,211,238,.05);border-left:3px solid var(--cyan);padding:10px;font-size:13px;color:var(--text-secondary);margin-top:5px}
        .saran-box a{color:var(--cyan);text-decoration:underline}

        .timeline-footer{background:var(--bg-card);border:1px solid rgba(34,211,238,.2);border-radius:var(--radius-md);padding:20px;text-align:center;margin-top:8px}
        .timeline-footer p{font-size:13px;color:var(--text-secondary)}
        .timeline-footer strong{color:var(--cyan)}
        .alert-success-sm{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#10b981;padding:10px 16px;border-radius:var(--radius-sm);margin-bottom:18px;font-size:13px}
        .mode-badge{display:inline-block;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;margin-bottom:16px}
        .mode-db{background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.25);color:var(--cyan)}
        .mode-static{background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.25);color:#a78bfa}
    </style>
</head>
<body class="dashboard-body">
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Career Roadmap</h1>
                <p class="page-sub">Peta jalan karir personal — step by step</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">🎯 <?= htmlspecialchars($targetCareerName) ?></span>
        </div>
    </div>

    <?php if (isset($_GET['career_updated'])): ?>
    <div class="alert-success-sm">✅ Target karir berhasil diperbarui!</div>
    <?php endif; ?>

    <!-- Career Selector -->
    <form method="POST" class="career-selector">
        <label>🎯 Target Karir:</label>
        <select name="target_career" class="career-select">
            <?php foreach ($allRoles as $rid => $rname): ?>
            <option value="<?= htmlspecialchars($rname) ?>" <?= $targetCareerName === $rname ? 'selected' : '' ?>><?= htmlspecialchars($rname) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="change_career" class="career-change-btn">Ubah Roadmap</button>
    </form>

    <!-- Hero -->
    <div class="roadmap-hero">
        <div class="roadmap-hero-left">
            <h2>Roadmap: <?= htmlspecialchars($targetCareerName) ?></h2>
            <p><?= $useDBMode ? 'Roadmap dikonfigurasi oleh admin berdasarkan mata kuliah aktual.' : 'Program 12 bulan untuk mencapai kesiapan industri.' ?></p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px">
            <div class="readiness-pill">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <?= $readiness ?>% Readiness
            </div>
            <span style="font-size:11px;color:var(--text-muted)">Berdasarkan skill mandiri yang terdata</span>
        </div>
    </div>

    <div class="mode-badge <?= $useDBMode ? 'mode-db' : 'mode-static' ?>">
        <?= $useDBMode ? '📊 Mode DB: Data roadmap dari admin' : '📋 Mode Template: Admin belum mengisi roadmap spesifik' ?>
    </div>

    <?php if ($useDBMode): ?>
    <!-- ══ DB Mode: roadmap_steps dari database ══ -->
    <div style="display:flex;flex-direction:column;gap:0">
        <?php foreach ($dbRoadmapSteps as $step):
            $statusClass = ''; $statusText = ''; $showSaran = false;
            if ($step['type_matkul'] === 'Wajib') {
                $score = convertGradeToScore($step['grade']);
                if (!$step['grade'] || $score < 60) {
                    $statusClass = 'status-review'; $statusText = 'Perlu Ditinjau'; $showSaran = true;
                } else {
                    $statusClass = 'status-good'; $statusText = 'Dikuasai (Nilai '.$step['grade'].')';
                }
            } else {
                if (!$step['grade']) {
                    $statusClass = 'status-plan'; $statusText = 'Rencana Masa Depan'; $showSaran = true;
                } else {
                    $statusClass = 'status-good'; $statusText = 'Telah Diambil (Nilai '.$step['grade'].')';
                }
            }
        ?>
        <div class="step-card">
            <div class="step-header">
                <span class="step-title"><?= htmlspecialchars($step['step_name']) ?></span>
                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
            </div>
            <div class="step-course">
                📖 <?= htmlspecialchars($step['course_name_id'] ?? 'N/A') ?>
                <span style="font-size:12px;color:var(--text-muted);font-weight:normal">(<?= $step['type_matkul'] ?>)</span>
            </div>
            <?php if ($showSaran): ?>
            <div class="saran-box">
                <?php if ($step['saran_matkul']): ?>
                <strong>💡 Saran:</strong> <?= nl2br(htmlspecialchars($step['saran_matkul'])) ?><br><br>
                <?php endif; ?>
                <?php if ($step['saran_kursus']): ?>
                <strong>🌐 Kursus Eksternal:</strong>
                <?php if ($step['saran_kursus_url']): ?>
                    <a href="<?= htmlspecialchars($step['saran_kursus_url']) ?>" target="_blank"><?= htmlspecialchars($step['saran_kursus']) ?></a>
                <?php else: ?>
                    <?= htmlspecialchars($step['saran_kursus']) ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- ══ Static Mode: template per karir ══ -->
    <div class="roadmap-timeline">
        <?php foreach ($staticPhases as $i => $phase): ?>
        <div class="roadmap-phase">
            <div class="phase-indicator">
                <div class="phase-dot" style="background:<?= $phase['color'] ?>22;border-color:<?= $phase['color'] ?>;color:<?= $phase['color'] ?>">
                    <?= $i + 1 ?>
                </div>
            </div>
            <div class="phase-body">
                <div class="phase-header">
                    <span class="phase-label"><?= htmlspecialchars($phase['label']) ?></span>
                    <span class="phase-months">Bulan <?= $phase['months'] ?></span>
                </div>
                <div class="phase-tag" style="color:<?= $phase['color'] ?>"><?= htmlspecialchars($phase['phase']) ?></div>
                <ul class="task-list">
                    <?php foreach ($phase['tasks'] as $task): ?>
                    <li><?= htmlspecialchars($task) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="timeline-footer">
        <p>Roadmap untuk <strong><?= htmlspecialchars($targetCareerName) ?></strong>.
        <?= $useDBMode ? 'Data dikonfigurasi oleh admin berdasarkan kurikulum.' : 'Konsisten 1-2 jam/hari selama 12 bulan = siap industri! 🚀' ?></p>
    </div>
</main>

<script src="../../script/main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
</script>
</body>
</html>