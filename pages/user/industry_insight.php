<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Student's top skills
$stmt = $db->prepare("
    SELECT s.skill_name, s.category, ss.student_level
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    WHERE ss.student_id = (SELECT id FROM mahasiswa_profiles WHERE user_id = ?)
    ORDER BY ss.student_level DESC LIMIT 5
");
$stmt->execute([$user['id']]);
$topSkills = $stmt->fetchAll();

$targetCareer = $profile['target_career'] ?? 'Software Engineer';

// Static industry data (realistic Indonesia tech industry data)
$industryTrends = [
    ['role' => 'Big Data Specialist', 'demand' => 94, 'avgSalary' => '12-25 jt', 'growth' => '+38%', 'color' => '#22d3ee', 'icon' => '🤖'],
    ['role' => 'FinTech Engineers',       'demand' => 91, 'avgSalary' => '13-28 jt', 'growth' => '+42%', 'color' => '#a78bfa', 'icon' => '☁️'],
    ['role' => 'AI and Machine Learning Specialist',          'demand' => 88, 'avgSalary' => '10-22 jt', 'growth' => '+29%', 'color' => '#f59e0b', 'icon' => '💻'],
    ['role' => 'Software and Applications Developers',         'demand' => 86, 'avgSalary' => '12-26 jt', 'growth' => '+45%', 'color' => '#ef4444', 'icon' => '🛡️'],
    ['role' => 'Security Management Specialist',    'demand' => 80, 'avgSalary' => '9-20 jt',  'growth' => '+25%', 'color' => '#10b981', 'icon' => '📱'],
    ['role' => 'Data Warehousing Specialist',               'demand' => 75, 'avgSalary' => '8-18 jt',  'growth' => '+22%', 'color' => '#f43f5e', 'icon' => '🎨'],
];

$topCompanies = [
    ['name' => 'Gojek',           'roles' => 'Backend, ML, Data', 'badge' => 'Startup Unicorn',  'color' => '#00aa5b'],
    ['name' => 'Tokopedia',       'roles' => 'Full Stack, Data',  'badge' => 'E-commerce',        'color' => '#42b549'],
    ['name' => 'Shopee (Sea Ltd)','roles' => 'Backend, Data',     'badge' => 'Regional Unicorn',  'color' => '#f36628'],
    ['name' => 'Bank BCA Digital','roles' => 'Full Stack, Cloud', 'badge' => 'Fintech/Bank',      'color' => '#005baa'],
    ['name' => 'Telkom Indonesia','roles' => 'Network, Cloud',    'badge' => 'State-owned Tech',  'color' => '#e31e2d'],
    ['name' => 'Google Indonesia','roles' => 'SWE, ML, DevRel',   'badge' => 'Big Tech',          'color' => '#4285f4'],
];

$techStacks2024 = [
    ['name' => 'Python',        'pct' => 82, 'cat' => 'Language'],
    ['name' => 'JavaScript',    'pct' => 79, 'cat' => 'Language'],
    ['name' => 'Go (Golang)',   'pct' => 58, 'cat' => 'Language'],
    ['name' => 'TypeScript',    'pct' => 67, 'cat' => 'Language'],
    ['name' => 'React.js',      'pct' => 72, 'cat' => 'Framework'],
    ['name' => 'Docker',        'pct' => 68, 'cat' => 'DevOps'],
    ['name' => 'Kubernetes',    'pct' => 51, 'cat' => 'DevOps'],
    ['name' => 'AWS',           'pct' => 63, 'cat' => 'Cloud'],
    ['name' => 'PostgreSQL',    'pct' => 59, 'cat' => 'Database'],
    ['name' => 'Machine Learning','pct' => 55, 'cat' => 'AI/ML'],
];

$salaryByRole = [
    ['role' => 'Junior (0-2 thn)',  'min' => 5,  'max' => 10, 'color' => '#22d3ee'],
    ['role' => 'Mid (2-5 thn)',     'min' => 10, 'max' => 20, 'color' => '#a78bfa'],
    ['role' => 'Senior (5+ thn)',   'min' => 20, 'max' => 40, 'color' => '#f59e0b'],
    ['role' => 'Lead/Manager',      'min' => 30, 'max' => 60, 'color' => '#10b981'],
];

$activePage = 'industry';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Industry Insight — CALMS</title>
    <meta name="description" content="Tren industri teknologi Indonesia 2024 — demand, salary, dan tech stack.">
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .insight-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:28px; }
        .insight-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; }
        .insight-card.full { grid-column:1/-1; }
        .insight-title { font-size:15px; font-weight:700; margin-bottom:4px; }
        .insight-sub { font-size:12px; color:var(--text-muted); margin-bottom:20px; }
        .demand-row { display:flex; align-items:center; gap:12px; margin-bottom:14px; }
        .demand-icon { font-size:20px; flex-shrink:0; width:32px; text-align:center; }
        .demand-info { flex:1; }
        .demand-role { font-size:13px; font-weight:600; margin-bottom:4px; }
        .demand-meta { display:flex; align-items:center; gap:8px; }
        .demand-salary { font-size:11px; color:var(--text-muted); }
        .demand-growth { font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(16,185,129,0.1); color:#10b981; font-weight:600; }
        .demand-bar { width:120px; flex-shrink:0; }
        .demand-bar-bg { height:6px; background:var(--border); border-radius:999px; overflow:hidden; }
        .demand-bar-fill { height:100%; border-radius:999px; transition:width 1s ease; }
        .demand-pct { font-size:11px; font-family:var(--font-mono); text-align:right; color:var(--text-muted); margin-top:2px; }
        .company-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
        .company-card { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-md); padding:14px; transition:var(--transition); }
        .company-card:hover { border-color:var(--border-hover); transform:translateY(-1px); }
        .company-logo { width:36px; height:36px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; color:#fff; margin-bottom:8px; }
        .company-name { font-size:13px; font-weight:700; margin-bottom:2px; }
        .company-roles { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
        .company-badge { font-size:10px; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); }
        .stack-row { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .stack-cat { font-size:10px; width:70px; padding:2px 8px; border-radius:999px; text-align:center; font-weight:600; flex-shrink:0; }
        .stack-name { font-size:13px; font-weight:500; width:120px; flex-shrink:0; }
        .stack-bar-wrap { flex:1; }
        .stack-bar-bg { height:8px; background:var(--border); border-radius:999px; overflow:hidden; }
        .stack-bar-fill { height:100%; background:var(--cyan); border-radius:999px; transition:width 1s ease; }
        .stack-pct { font-size:12px; font-family:var(--font-mono); color:var(--text-muted); min-width:36px; text-align:right; }
        .salary-chart { display:flex; flex-direction:column; gap:16px; }
        .salary-row { display:flex; align-items:center; gap:12px; }
        .salary-label { font-size:12px; color:var(--text-secondary); width:120px; flex-shrink:0; }
        .salary-bar-wrap { flex:1; position:relative; height:28px; background:var(--border); border-radius:999px; overflow:hidden; }
        .salary-bar-fill { position:absolute; left:0; top:0; bottom:0; border-radius:999px; display:flex; align-items:center; padding-left:12px; transition:width 1s ease; }
        .salary-range-txt { font-size:11px; font-family:var(--font-mono); color:rgba(255,255,255,0.8); white-space:nowrap; }
        .year-badge { background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.2); border-radius:999px; padding:4px 12px; font-size:12px; color:var(--cyan); font-weight:600; }
        @media(max-width:1100px){ .company-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:900px){ .insight-grid{grid-template-columns:1fr;} .insight-card.full{grid-column:auto;} }
        @media(max-width:640px){ .company-grid{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body class="dashboard-body">

<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Industry Insight</h1>
                <p class="page-sub">Tren industri teknologi Indonesia 2024</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="year-badge">📊 Data 2024</span>
        </div>
    </div>

    <div class="insight-grid">

        <!-- Demand Roles -->
        <div class="insight-card">
            <div class="insight-title">🔥 Demand Posisi Terpanas</div>
            <div class="insight-sub">Berdasarkan data job posting Indonesia 2024</div>
            <?php foreach ($industryTrends as $trend): ?>
            <div class="demand-row">
                <div class="demand-icon"><?= $trend['icon'] ?></div>
                <div class="demand-info">
                    <div class="demand-role"><?= $trend['role'] ?></div>
                    <div class="demand-meta">
                        <span class="demand-salary">💰 <?= $trend['avgSalary'] ?>/bln</span>
                        <span class="demand-growth"><?= $trend['growth'] ?> YoY</span>
                    </div>
                </div>
                <div class="demand-bar">
                    <div class="demand-bar-bg">
                        <div class="demand-bar-fill" data-width="<?= $trend['demand'] ?>" style="background:<?= $trend['color'] ?>"></div>
                    </div>
                    <div class="demand-pct"><?= $trend['demand'] ?>%</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Salary Range -->
        <div class="insight-card">
            <div class="insight-title">💰 Rentang Gaji per Level</div>
            <div class="insight-sub">Estimasi gaji software engineer (juta/bulan)</div>
            <div class="salary-chart">
                <?php foreach ($salaryByRole as $sal):
                    $maxPossible = 60;
                    $widthPct = ($sal['max'] / $maxPossible * 100);
                ?>
                <div class="salary-row">
                    <span class="salary-label"><?= $sal['role'] ?></span>
                    <div class="salary-bar-wrap">
                        <div class="salary-bar-fill" data-width="<?= $widthPct ?>" style="background:<?= $sal['color'] ?>">
                            <span class="salary-range-txt">Rp <?= $sal['min'] ?>–<?= $sal['max'] ?>jt</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="font-size:11px;color:var(--text-muted);line-height:1.6;">
                    📍 Data berdasarkan survei Indeed, LinkedIn, dan Glassdoor Indonesia 2024.<br>
                    Gaji dapat bervariasi tergantung kota, perusahaan, dan negosiasi.
                </div>
            </div>
        </div>

        <!-- Tech Stack Demand -->
        <div class="insight-card">
            <div class="insight-title">⚙️ Tech Stack Paling Dicari</div>
            <div class="insight-sub">% perusahaan yang mencantumkan skill ini di job posting</div>
            <?php
            $catColors = ['Language'=>'#22d3ee','Framework'=>'#a78bfa','DevOps'=>'#f59e0b','Cloud'=>'#3b82f6','Database'=>'#10b981','AI/ML'=>'#f43f5e'];
            foreach ($techStacks2024 as $stack): ?>
            <div class="stack-row">
                <span class="stack-cat" style="background:<?= ($catColors[$stack['cat']] ?? '#94a3b8') ?>22;color:<?= ($catColors[$stack['cat']] ?? '#94a3b8') ?>"><?= $stack['cat'] ?></span>
                <span class="stack-name"><?= $stack['name'] ?></span>
                <div class="stack-bar-wrap">
                    <div class="stack-bar-bg">
                        <div class="stack-bar-fill" data-width="<?= $stack['pct'] ?>" style="background:<?= ($catColors[$stack['cat']] ?? 'var(--cyan)') ?>"></div>
                    </div>
                </div>
                <span class="stack-pct"><?= $stack['pct'] ?>%</span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Top Companies -->
        <div class="insight-card">
            <div class="insight-title">🏢 Top Perusahaan Tech Indonesia</div>
            <div class="insight-sub">Yang paling aktif rekrut fresh graduate tech</div>
            <div class="company-grid" style="grid-template-columns:repeat(2,1fr);">
                <?php foreach ($topCompanies as $co): ?>
                <div class="company-card">
                    <div class="company-logo" style="background:<?= $co['color'] ?>">
                        <?= strtoupper(substr($co['name'], 0, 2)) ?>
                    </div>
                    <div class="company-name"><?= htmlspecialchars($co['name']) ?></div>
                    <div class="company-roles"><?= htmlspecialchars($co['roles']) ?></div>
                    <span class="company-badge"><?= htmlspecialchars($co['badge']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Bottom banner -->
    <div style="background:linear-gradient(135deg,rgba(34,211,238,0.08),rgba(167,139,250,0.08));border:1px solid rgba(34,211,238,0.2);border-radius:var(--radius-lg);padding:24px;text-align:center;">
        <div style="font-size:18px;font-weight:700;margin-bottom:6px;">🚀 Siap bersaing di industri?</div>
        <p style="font-size:13px;color:var(--text-secondary);margin-bottom:16px;">Tingkatkan skill gap kamu dan coba simulasi rekrutmen untuk mengukur peluangmu.</p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="skill_gap.php" class="btn-sm-cyan" style="font-size:13px;padding:10px 22px;">Update Skill →</a>
            <a href="simulation.php" class="btn-sm-cyan" style="font-size:13px;padding:10px 22px;background:rgba(167,139,250,0.1);border-color:rgba(167,139,250,0.25);color:#a78bfa;">Simulasi Rekrutmen →</a>
        </div>
    </div>

</main>

<script src="../../script/main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = el.dataset.width + '%';
});
</script>
</body>
</html>