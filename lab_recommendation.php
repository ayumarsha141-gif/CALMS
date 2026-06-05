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
$studentId = $profile['id'];

// Get student skills (tags)
$stmt = $db->prepare("
    SELECT s.skill_name FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    WHERE ss.student_id = ? AND ss.student_level >= 3
");
$stmt->execute([$studentId]);
$mySkillTags = array_column($stmt->fetchAll(), 'skill_name');

// All labs
$stmt = $db->query("SELECT * FROM labs ORDER BY id");
$labs = $stmt->fetchAll();

// Score each lab based on skill match
function scoreLabMatch(array $labTags, array $myTags): int {
    $labArr = array_map('trim', explode(',', $labTags));
    $count  = 0;
    foreach ($labArr as $tag) {
        foreach ($myTags as $myTag) {
            if (stripos($myTag, $tag) !== false || stripos($tag, $myTag) !== false) {
                $count++;
                break;
            }
        }
    }
    return $count;
}

foreach ($labs as &$lab) {
    $lab['match_score'] = scoreLabMatch($lab['skill_tags'], $mySkillTags);
    $labTagsArr = array_map('trim', explode(',', $lab['skill_tags']));
    $lab['match_pct'] = count($labTagsArr) > 0 ? min(100, round($lab['match_score'] / count($labTagsArr) * 100)) : 0;
}
unset($lab);
usort($labs, fn($a, $b) => $b['match_score'] - $a['match_score']);

$targetCareer = $profile['target_career'] ?? '';

$activePage = 'lab';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Recommendation — CALMS</title>
    <meta name="description" content="Rekomendasi lab penelitian berdasarkan skill dan target karirmu.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .lab-intro { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:28px; display:flex; align-items:center; gap:20px; }
        .lab-intro-icon { width:56px; height:56px; border-radius:var(--radius-md); background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.2); display:flex; align-items:center;  justify-content:center; flex-shrink:0; color:var(--cyan); }
        .lab-intro-text h2 { font-size:17px; font-weight:700; margin-bottom:4px; }
        .lab-intro-text p { font-size:13px; color:var(--text-secondary); }
        .lab-cards { display:grid; grid-template-columns:1fr; gap:20px; }
        .lab-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; transition:var(--transition); position:relative; overflow:hidden; }
        .lab-card:hover { border-color:var(--border-hover); transform:translateY(-2px); }
        .lab-card.top-match { border-color:rgba(34,211,238,0.35); }
        .lab-card.top-match::before { content:'#1 Match'; position:absolute; top:0; right:0; background:var(--cyan); color:#0a0f1a; font-size:10px; font-weight:800; padding:4px 12px; border-bottom-left-radius:var(--radius-sm); letter-spacing:0.5px; }
        .lab-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .lab-rank { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
        .lab-title { font-size:16px; font-weight:700; flex:1; }
        .lab-focus { font-size:12px; padding:3px 10px; border-radius:999px; background:rgba(167,139,250,0.1); border:1px solid rgba(167,139,250,0.2); color:#a78bfa; }
        .lab-desc { font-size:13px; color:var(--text-secondary); line-height:1.6; margin-bottom:16px; }
        .lab-tags { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
        .lab-tag { font-size:11px; padding:3px 10px; border-radius:999px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); }
        .lab-tag.matched { background:rgba(34,211,238,0.08); border-color:rgba(34,211,238,0.2); color:var(--cyan); }
        .lab-match { display:flex; align-items:center; gap:12px; }
        .lab-match-bar { flex:1; height:8px; background:var(--border); border-radius:999px; overflow:hidden; }
        .lab-match-fill { height:100%; border-radius:999px; background:var(--cyan); transition:width 1s ease; }
        .lab-match-pct { font-size:12px; font-family:var(--font-mono); color:var(--cyan); font-weight:600; min-width:36px; }
        .lab-match-label { font-size:11px; color:var(--text-muted); }
        .no-skills-note { background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.2); border-radius:var(--radius-md); padding:16px; font-size:13px; color:#f59e0b; margin-bottom:24px; }
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
                <h1 class="page-title">Lab Recommendation</h1>
                <p class="page-sub">Rekomendasi lab Tugas Akhir berdasarkan profilmu</p>
            </div>
        </div>
        <div class="topbar-right">
            <?php if ($targetCareer): ?>
            <span class="career-badge">🎯 <?= htmlspecialchars($targetCareer) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="lab-intro">
        <div class="lab-intro-icon">
            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
        </div>
        <div class="lab-intro-text">
            <h2>Lab Matching Otomatis</h2>
            <p>Diurutkan berdasarkan kesesuaian skill-mu dengan fokus riset setiap lab. <?= empty($mySkillTags) ? 'Isi skill di halaman Skill Gap untuk hasil yang lebih akurat.' : 'Skill yang cocok ditandai warna cyan.' ?></p>
        </div>
    </div>

    <?php if (empty($mySkillTags)): ?>
    <div class="no-skills-note">
        ⚠️ Kamu belum mengisi skill. <a href="skill_gap.php" style="color:#f59e0b;font-weight:600;">Isi skill di sini</a> untuk mendapatkan rekomendasi yang lebih personal.
    </div>
    <?php endif; ?>

    <div class="lab-cards">
        <?php foreach ($labs as $i => $lab):
            $labTagsArr = array_map('trim', explode(',', $lab['skill_tags']));
            $rankColors = ['#22d3ee','#a78bfa','#f59e0b','#10b981','#f43f5e'];
            $rankBgs    = ['rgba(34,211,238,0.12)','rgba(167,139,250,0.12)','rgba(245,158,11,0.12)','rgba(16,185,129,0.12)','rgba(244,63,94,0.12)'];
        ?>
        <div class="lab-card <?= $i === 0 ? 'top-match' : '' ?>">
            <div class="lab-header">
                <div class="lab-rank" style="background:<?= $rankBgs[$i] ?? 'rgba(255,255,255,0.05)' ?>;color:<?= $rankColors[$i] ?? '#94a3b8' ?>">
                    <?= $i+1 ?>
                </div>
                <span class="lab-title"><?= htmlspecialchars($lab['lab_name']) ?></span>
                <span class="lab-focus"><?= htmlspecialchars($lab['focus_area']) ?></span>
            </div>
            <p class="lab-desc"><?= htmlspecialchars($lab['description']) ?></p>
            <div class="lab-tags">
                <?php foreach ($labTagsArr as $tag):
                    $isMatch = false;
                    foreach ($mySkillTags as $myTag) {
                        if (stripos($myTag, trim($tag)) !== false || stripos(trim($tag), $myTag) !== false) { $isMatch = true; break; }
                    }
                ?>
                <span class="lab-tag <?= $isMatch ? 'matched' : '' ?>"><?= htmlspecialchars(trim($tag)) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="lab-match">
                <span class="lab-match-label">Kesesuaian:</span>
                <div class="lab-match-bar">
                    <div class="lab-match-fill" data-width="<?= $lab['match_pct'] ?>" style="background:<?= $rankColors[$i] ?? 'var(--cyan)' ?>"></div>
                </div>
                <span class="lab-match-pct"><?= $lab['match_pct'] ?>%</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<script src="main.js"></script>
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
