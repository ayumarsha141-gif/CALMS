<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Skills with gap
$stmt = $db->prepare("
    SELECT s.skill_name, s.category, s.industry_level,
           COALESCE(ss.student_level, 0) AS student_level,
           (s.industry_level - COALESCE(ss.student_level, 0)) AS gap
    FROM skills s
    LEFT JOIN student_skills ss ON ss.skill_id = s.id
        AND ss.student_id = (SELECT id FROM mahasiswa_profiles WHERE user_id = ?)
    ORDER BY gap DESC
");
$stmt->execute([$user['id']]);
$allSkills = $stmt->fetchAll();

$readiness = 0; $tracked = 0;
foreach ($allSkills as $sk) {
    if ($sk['student_level'] > 0) {
        $tracked++;
        $readiness += ($sk['student_level'] / $sk['industry_level']) * 100;
    }
}
$readiness = $tracked > 0 ? round($readiness / $tracked) : 0;
$targetCareer = $profile['target_career'] ?? 'Software Engineer';

// Roadmap stages berdasarkan target career
$roadmaps = [
    'Data Scientist'     => [
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
    'Cybersecurity Analyst' => [
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

// Default roadmap jika tidak ada yang cocok
$roadmap = $roadmaps[$targetCareer] ?? $roadmaps['Data Scientist'];

$activePage = 'roadmap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Roadmap — CALMS</title>
    <meta name="description" content="Peta jalan karir personalmu berdasarkan target posisi.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .roadmap-hero { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
        .roadmap-hero-left h2 { font-size:20px; font-weight:700; margin-bottom:4px; }
        .roadmap-hero-left p { font-size:13px; color:var(--text-secondary); }
        .readiness-pill { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:999px; background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.25); color:var(--cyan); font-weight:700; font-size:14px; }
        .roadmap-timeline { position:relative; }
        .roadmap-timeline::before { content:''; position:absolute; left:28px; top:0; bottom:0; width:2px; background:linear-gradient(to bottom, #22d3ee, #a78bfa, #f59e0b, #10b981); opacity:0.3; }
        .roadmap-phase { display:flex; gap:24px; margin-bottom:32px; position:relative; }
        .phase-indicator { flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:0; }
        .phase-dot { width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; text-align:center; line-height:1.2; z-index:1; border:2px solid; }
        .phase-body { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:22px 24px; flex:1; transition:var(--transition); }
        .phase-body:hover { border-color:var(--border-hover); transform:translateX(4px); }
        .phase-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .phase-label { font-size:16px; font-weight:700; }
        .phase-months { font-size:11px; padding:3px 10px; border-radius:999px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); }
        .phase-tag { font-size:10px; font-weight:600; letter-spacing:1px; text-transform:uppercase; margin-bottom:10px; }
        .task-list { list-style:none; display:flex; flex-direction:column; gap:8px; }
        .task-list li { display:flex; align-items:flex-start; gap:8px; font-size:13px; color:var(--text-secondary); }
        .task-list li::before { content:'▸'; flex-shrink:0; margin-top:1px; }
        .career-select-wrap { display:flex; align-items:center; gap:12px; }
        .career-select { background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; cursor:pointer; font-family:var(--font-sans); }
        .timeline-footer { background:var(--bg-card); border:1px solid rgba(34,211,238,0.2); border-radius:var(--radius-md); padding:20px; text-align:center; margin-top:8px; }
        .timeline-footer p { font-size:13px; color:var(--text-secondary); }
        .timeline-footer strong { color:var(--cyan); }
    </style>
</head>
<body class="dashboard-body">

<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Career Roadmap</h1>
                <p class="page-sub">Peta jalan karir personalmu — step by step</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="career-badge">🎯 <?= htmlspecialchars($targetCareer) ?></span>
        </div>
    </div>

    <!-- Hero -->
    <div class="roadmap-hero">
        <div class="roadmap-hero-left">
            <h2>Roadmap: <?= htmlspecialchars($targetCareer) ?></h2>
            <p>Program 12 bulan untuk mencapai kesiapan industri. Update target karir di halaman Profil.</p>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
            <div class="readiness-pill">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <?= $readiness ?>% Readiness
            </div>
            <span style="font-size:11px;color:var(--text-muted);">Berdasarkan skill yang terdata</span>
        </div>
    </div>

    <!-- Timeline -->
    <div class="roadmap-timeline">
        <?php foreach ($roadmap as $i => $phase): ?>
        <div class="roadmap-phase">
            <div class="phase-indicator">
                <div class="phase-dot" style="background:<?= $phase['color'] ?>22; border-color:<?= $phase['color'] ?>; color:<?= $phase['color'] ?>;">
                    <?= $i+1 ?>
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
                    <li style="--c:<?= $phase['color'] ?>"><?= htmlspecialchars($task) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="timeline-footer">
        <p>Roadmap ini dirancang untuk <strong><?= htmlspecialchars($targetCareer) ?></strong>. Konsisten 1-2 jam/hari selama 12 bulan = siap industri! 🚀</p>
    </div>

</main>

<script src="main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
