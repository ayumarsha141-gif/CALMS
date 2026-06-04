<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

// Profile
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// All skills
$stmt = $db->prepare("
    SELECT s.id, s.skill_name, s.category, s.industry_level,
           COALESCE(ss.student_level, 0) AS student_level,
           (s.industry_level - COALESCE(ss.student_level, 0)) AS gap
    FROM skills s
    LEFT JOIN student_skills ss ON ss.skill_id = s.id
        AND ss.student_id = (SELECT id FROM mahasiswa_profiles WHERE user_id = ?)
    ORDER BY s.category, gap DESC
");
$stmt->execute([$user['id']]);
$allSkills = $stmt->fetchAll();

// Categories
$categories = [];
foreach ($allSkills as $sk) {
    $categories[$sk['category']][] = $sk;
}

// Handle save skill levels
$saveMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills'])) {
    $studentId = $profile['id'];
    $levels    = $_POST['levels'] ?? [];
    foreach ($levels as $skillId => $level) {
        $level = max(0, min(10, (int)$level));
        $stmt  = $db->prepare("INSERT INTO student_skills (student_id, skill_id, student_level) VALUES (?,?,?) ON DUPLICATE KEY UPDATE student_level = ?");
        $stmt->execute([$studentId, $skillId, $level, $level]);
    }
    $saveMsg = 'success';
    header('Location: skill_gap.php?saved=1');
    exit;
}

// Stats
$gapHigh = $gapMid = $gapLow = 0;
$totalReadiness = 0;
$tracked = 0;
foreach ($allSkills as $sk) {
    if ($sk['student_level'] > 0) {
        $tracked++;
        $totalReadiness += ($sk['student_level'] / $sk['industry_level']) * 100;
        if ($sk['gap'] <= 1)      $gapLow++;
        elseif ($sk['gap'] <= 3)  $gapMid++;
        else                       $gapHigh++;
    }
}
$avgReadiness = $tracked > 0 ? round($totalReadiness / $tracked) : 0;
$activePage = 'skill_gap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Gap Analysis — CALMS</title>
    <meta name="description" content="Analisis gap antara kemampuanmu dengan standar industri.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .sg-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .sg-stat { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:20px; text-align:center; }
        .sg-stat-num { font-size:36px; font-weight:700; font-family:var(--font-mono); }
        .sg-stat-label { font-size:12px; color:var(--text-muted); margin-top:4px; }
        .category-section { margin-bottom:28px; }
        .category-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
        .category-title { font-size:13px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px; }
        .category-badge { font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(34,211,238,0.1); color:var(--cyan); }
        .skill-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:18px 20px; margin-bottom:10px; transition:var(--transition); }
        .skill-card:hover { border-color:var(--border-hover); }
        .skill-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
        .skill-name { font-size:14px; font-weight:600; }
        .skill-meta { display:flex; align-items:center; gap:10px; }
        .skill-score { font-family:var(--font-mono); font-size:13px; color:var(--text-muted); }
        .skill-bars { position:relative; height:22px; }
        .skill-bar-bg { position:absolute; top:0; left:0; width:100%; height:9px; background:#1e293b; border-radius:999px; margin-top:2px; }
        .skill-bar-fill { position:absolute; bottom:0; left:0; height:9px; border-radius:999px; transition:width 1s ease; }
        .skill-input-wrap { display:flex; align-items:center; gap:8px; margin-top:10px; }
        .skill-input-wrap label { font-size:11px; color:var(--text-muted); flex-shrink:0; }
        .skill-range { flex:1; accent-color:var(--cyan); cursor:pointer; }
        .skill-val-display { font-size:12px; font-family:var(--font-mono); color:var(--cyan); min-width:18px; text-align:center; }
        .legend-bar { display:flex; gap:20px; margin-bottom:20px; }
        .legend-bar-item { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--text-muted); }
        .legend-dot-bar { width:24px; height:8px; border-radius:4px; }
        .save-btn { display:inline-flex; align-items:center; gap:8px; padding:10px 24px; background:var(--cyan); color:#0a0f1a; border:none; border-radius:999px; font-size:13px; font-weight:700; cursor:pointer; transition:var(--transition); }
        .save-btn:hover { opacity:0.85; transform:translateY(-1px); }
        .alert-success { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); color:#10b981; padding:12px 18px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:13px; }
        @media(max-width:900px){ .sg-stats{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:480px){ .sg-stats{grid-template-columns:1fr 1fr;} }
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
                <h1 class="page-title">Skill Gap Analysis</h1>
                <p class="page-sub">Perbandingan kemampuanmu vs standar industri</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">Semester <?= $profile['semester'] ?? '-' ?></span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Skill level berhasil disimpan!</div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="sg-stats">
        <div class="sg-stat">
            <div class="sg-stat-num" style="color:var(--cyan)"><?= $avgReadiness ?>%</div>
            <div class="sg-stat-label">Avg Readiness</div>
        </div>
        <div class="sg-stat">
            <div class="sg-stat-num" style="color:#10b981"><?= $gapLow ?></div>
            <div class="sg-stat-label">Gap Rendah ≤1</div>
        </div>
        <div class="sg-stat">
            <div class="sg-stat-num" style="color:#f59e0b"><?= $gapMid ?></div>
            <div class="sg-stat-label">Gap Sedang 2-3</div>
        </div>
        <div class="sg-stat">
            <div class="sg-stat-num" style="color:#ef4444"><?= $gapHigh ?></div>
            <div class="sg-stat-label">Gap Tinggi ≥4</div>
        </div>
    </div>

    <div class="legend-bar">
        <div class="legend-bar-item"><div class="legend-dot-bar" style="background:#1e293b"></div>Standar Industri</div>
        <div class="legend-bar-item"><div class="legend-dot-bar" style="background:var(--cyan)"></div>Kemampuanmu</div>
    </div>

    <form method="POST">
    <?php foreach ($categories as $cat => $skills): ?>
    <div class="category-section">
        <div class="category-header">
            <span class="category-title"><?= htmlspecialchars($cat) ?></span>
            <span class="category-badge"><?= count($skills) ?> skills</span>
        </div>
        <?php foreach ($skills as $sk):
            $pct    = round(($sk['student_level'] / 10) * 100);
            $indPct = round(($sk['industry_level'] / 10) * 100);
            $gap    = (int)$sk['gap'];
            $gLabel = $gap <= 1 ? 'Rendah' : ($gap <= 3 ? 'Sedang' : 'Tinggi');
            $gClass = $gap <= 1 ? 'gap-low' : ($gap <= 3 ? 'gap-mid' : 'gap-high');
            $barColor = $gap <= 1 ? '#10b981' : ($gap <= 3 ? '#f59e0b' : '#ef4444');
        ?>
        <div class="skill-card">
            <div class="skill-header">
                <span class="skill-name"><?= htmlspecialchars($sk['skill_name']) ?></span>
                <div class="skill-meta">
                    <span class="gap-tag <?= $gClass ?>">Gap: <?= $gLabel ?></span>
                    <span class="skill-score"><?= $sk['student_level'] ?>/<?= $sk['industry_level'] ?></span>
                </div>
            </div>
            <div class="skill-bars">
                <div class="skill-bar-bg"></div>
                <div class="skill-bar-fill" id="fill-<?= $sk['id'] ?>" data-width="<?= $pct ?>" style="background:<?= $barColor ?>"></div>
            </div>
            <div class="skill-input-wrap">
                <label for="range-<?= $sk['id'] ?>">Level kamu:</label>
                <input class="skill-range" type="range" id="range-<?= $sk['id'] ?>" name="levels[<?= $sk['id'] ?>]"
                    min="0" max="10" value="<?= $sk['student_level'] ?>"
                    oninput="updateSkill(<?= $sk['id'] ?>,<?= $sk['industry_level'] ?>,this.value)">
                <span class="skill-val-display" id="val-<?= $sk['id'] ?>"><?= $sk['student_level'] ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:8px; padding-bottom:32px;">
        <button type="submit" name="save_skills" class="save-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Simpan Semua Skill
        </button>
    </div>
    </form>
</main>

<script src="main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = el.dataset.width + '%';
});

function updateSkill(id, industryLevel, val) {
    val = parseInt(val);
    document.getElementById('val-' + id).textContent = val;
    const fill = document.getElementById('fill-' + id);
    fill.style.width = (val / 10 * 100) + '%';
    const gap = industryLevel - val;
    fill.style.background = gap <= 1 ? '#10b981' : (gap <= 3 ? '#f59e0b' : '#ef4444');
}
</script>
</body>
</html>
