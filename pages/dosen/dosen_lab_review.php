<?php
session_start();
require_once '../../includes/auth_guard_dosen.php';
require_once '../../config/database.php';

requireDosen();
$dosenUser = getDosenUser();
$db = getDB();

// Semua lab
$labs = $db->query("SELECT * FROM labs ORDER BY id")->fetchAll();

// Per lab: hitung berapa mahasiswa yang cocok (match_score > 0)
function scoreMatch(string $labTags, array $studentSkills): int {
    $tags = array_map('trim', explode(',', $labTags));
    $count = 0;
    foreach ($tags as $tag) {
        foreach ($studentSkills as $sk) {
            if (stripos($sk, $tag) !== false || stripos($tag, $sk) !== false) { $count++; break; }
        }
    }
    return $count;
}

// Ambil semua mahasiswa beserta skillnya
$allStudents = $db->query("
    SELECT mp.id, u.fullname, mp.nim, mp.semester, mp.target_career,
           GROUP_CONCAT(s.skill_name SEPARATOR ',') AS skills
    FROM mahasiswa_profiles mp
    JOIN users u ON u.id = mp.user_id
    LEFT JOIN student_skills ss ON ss.student_id = mp.id AND ss.student_level >= 3
    LEFT JOIN skills s ON s.id = ss.skill_id
    GROUP BY mp.id
")->fetchAll();

// Hitung distribusi mahasiswa per lab
$labData = [];
foreach ($labs as $lab) {
    $matched = [];
    $labTagsArr = array_map('trim', explode(',', $lab['skill_tags']));
    foreach ($allStudents as $s) {
        $studentSkills = $s['skills'] ? explode(',', $s['skills']) : [];
        $score = scoreMatch($lab['skill_tags'], $studentSkills);
        $pct = count($labTagsArr) > 0 ? min(100, round($score / count($labTagsArr) * 100)) : 0;
        if ($pct > 0) {
            $matched[] = ['name' => $s['fullname'], 'nim' => $s['nim'], 'pct' => $pct, 'target' => $s['target_career']];
        }
    }
    usort($matched, fn($a,$b) => $b['pct'] - $a['pct']);
    $labData[$lab['id']] = ['lab' => $lab, 'matched' => $matched];
}

$activePageDosen = 'lab_review';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kompatibilitas Lab — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .lab-grid { display:grid; grid-template-columns:1fr; gap:20px; }
        .lab-review-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; }
        .lab-card-head { padding:18px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .lab-card-title { font-size:15px; font-weight:700; }
        .lab-focus-badge { font-size:11px; padding:3px 10px; border-radius:999px; background:rgba(167,139,250,0.1); border:1px solid rgba(167,139,250,0.2); color:#a78bfa; }
        .lab-card-body { padding:18px 22px; }
        .lab-tags-wrap { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px; }
        .lab-tag { font-size:11px; padding:3px 10px; border-radius:999px; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text-muted); }
        .lab-students-title { font-size:12px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:.8px; margin-bottom:10px; }
        .student-match-row { display:flex; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid rgba(255,255,255,0.04); }
        .student-match-row:last-child { border-bottom:none; }
        .match-name { flex:1; font-size:13px; font-weight:500; color:var(--text-primary); }
        .match-nim  { font-family:var(--font-mono); font-size:11px; color:var(--text-muted); }
        .match-bar-wrap { display:flex; align-items:center; gap:8px; }
        .match-bar { width:80px; height:5px; background:#1e293b; border-radius:999px; overflow:hidden; }
        .match-bar-fill { height:100%; border-radius:999px; background:#a78bfa; }
        .match-pct { font-family:var(--font-mono); font-size:12px; color:#a78bfa; min-width:36px; }
        .no-match { font-size:13px; color:var(--text-muted); font-style:italic; padding:8px 0; }
        .lab-count-badge { font-size:12px; padding:4px 12px; border-radius:999px; background:rgba(34,211,238,0.08); border:1px solid rgba(34,211,238,0.2); color:var(--cyan); }
    </style>
</head>
<body class="dashboard-body">
<?php include '../../includes/sidebar_dosen.php'; ?>
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Kompatibilitas Lab</h1>
                <p class="page-sub">Kesesuaian mahasiswa dengan setiap laboratorium TA</p>
            </div>
        </div>
    </div>

    <div class="lab-grid">
        <?php foreach ($labData as $ld):
            $lab = $ld['lab'];
            $matched = $ld['matched'];
            $colors = ['#22d3ee','#a78bfa','#f59e0b','#10b981','#f43f5e'];
            $labColors = ['#22d3ee','#a78bfa','#f59e0b','#10b981','#f43f5e'];
            $ci = ($lab['id'] - 1) % count($colors);
        ?>
        <div class="lab-review-card">
            <div class="lab-card-head">
                <div style="display:flex;align-items:center;gap:12px">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $labColors[$ci] ?>22;border:1px solid <?= $labColors[$ci] ?>;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:<?= $labColors[$ci] ?>"><?= $lab['id'] ?></div>
                    <div>
                        <div class="lab-card-title"><?= htmlspecialchars($lab['lab_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($lab['description']) ?></div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                    <span class="lab-focus-badge"><?= htmlspecialchars($lab['focus_area']) ?></span>
                    <span class="lab-count-badge"><?= count($matched) ?> mhs cocok</span>
                </div>
            </div>
            <div class="lab-card-body">
                <div class="lab-tags-wrap">
                    <?php foreach (array_map('trim', explode(',', $lab['skill_tags'])) as $tag): ?>
                    <span class="lab-tag"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="lab-students-title">Mahasiswa yang Cocok (Top 5)</div>
                <?php if (empty($matched)): ?>
                    <p class="no-match">Belum ada mahasiswa dengan skill yang sesuai.</p>
                <?php else: ?>
                    <?php foreach (array_slice($matched, 0, 5) as $s): ?>
                    <div class="student-match-row">
                        <div style="flex:1">
                            <div class="match-name"><?= htmlspecialchars($s['name']) ?></div>
                            <div class="match-nim"><?= htmlspecialchars($s['nim']) ?> <?= $s['target'] ? '· '.$s['target'] : '' ?></div>
                        </div>
                        <div class="match-bar-wrap">
                            <div class="match-bar"><div class="match-bar-fill" style="width:<?= $s['pct'] ?>%;background:<?= $labColors[$ci] ?>"></div></div>
                            <span class="match-pct" style="color:<?= $labColors[$ci] ?>"><?= $s['pct'] ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($matched) > 5): ?>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:10px;text-align:center">+<?= count($matched) - 5 ?> mahasiswa lainnya cocok</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<script src="../../script/main.js"></script>
<script>
const toggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
