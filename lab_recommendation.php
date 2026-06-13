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

$stmt = $db->prepare("

SELECT

l.id,
l.lab_name,
l.focus_area,
l.description,

COALESCE(SUM(

CASE

WHEN sc.grade='A'  THEN lcm.weight*1.00
WHEN sc.grade='A-' THEN lcm.weight*0.90
WHEN sc.grade='B+' THEN lcm.weight*0.85
WHEN sc.grade='B'  THEN lcm.weight*0.80
WHEN sc.grade='B-' THEN lcm.weight*0.75
WHEN sc.grade='C+' THEN lcm.weight*0.70
WHEN sc.grade='C'  THEN lcm.weight*0.65
ELSE 0

END

),0) AS score

FROM labs l

LEFT JOIN lab_course_mapping lcm
ON l.id=lcm.lab_id

LEFT JOIN student_courses sc
ON sc.course_id=lcm.course_id
AND sc.student_id=?

GROUP BY
l.id,
l.lab_name,
l.focus_area,
l.description

ORDER BY score DESC

");

$stmt->execute([$studentId]);

$labs = $stmt->fetchAll();

unset($lab);

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
            <p>
                Rekomendasi laboratorium berdasarkan nilai mata kuliah yang telah ditempuh mahasiswa.
            </p>
        </div>
    </div>
    <div class="lab-cards">

    <?php foreach($labs as $i => $lab): ?>

    <div class="lab-card <?= $i==0 ? 'top-match' : '' ?>">

        <div class="lab-header">

            <div class="lab-rank">
                <?= $i+1 ?>
            </div>

            <span class="lab-title">
                <?= htmlspecialchars($lab['lab_name']) ?>
            </span>

            <span class="lab-focus">
                <?= htmlspecialchars($lab['focus_area']) ?>
            </span>

        </div>

        <p class="lab-desc">
            <?= htmlspecialchars($lab['description']) ?>
        </p>

        <div class="lab-match">

            <span class="lab-match-label">
                Skor Kesesuaian:
            </span>

            <div class="lab-match-bar">

                <div
                class="lab-match-fill"
                data-width="<?= min(100, round($lab['score'])) ?>">
                </div>

            </div>

            <span class="lab-match-pct">
                <?= round($lab['score']) ?>
                Pts
            </span>

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