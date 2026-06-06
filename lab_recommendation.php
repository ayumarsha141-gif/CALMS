<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];

// Fetch scores and weights
$stmt = $db->prepare("
    SELECT c.course_code, c.course_name_id, sc.grade, sc.score, clw.bobot_rpl, clw.bobot_hpc, clw.bobot_ai
    FROM course_lab_weights clw
    JOIN courses c ON c.id = clw.course_id
    JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
    WHERE sc.score > 0
");
$stmt->execute([$studentId]);
$courses = $stmt->fetchAll();

$totalRPL = 0;
$totalHPC = 0;
$totalAI  = 0;

foreach ($courses as $c) {
    $score = (float)$c['score'];
    $totalRPL += $score * (float)$c['bobot_rpl'];
    $totalHPC += $score * (float)$c['bobot_hpc'];
    $totalAI  += $score * (float)$c['bobot_ai'];
}

// Prepare labs for ranking
$labs = [
    [
        'id' => 'rpl',
        'name' => 'Lab Rekayasa Perangkat Lunak (RPL)',
        'score' => $totalRPL,
        'color' => '#10b981',
        'desc' => 'Fokus pada pengembangan perangkat lunak, arsitektur sistem, dan rekayasa data.'
    ],
    [
        'id' => 'hpc',
        'name' => 'Lab High Performance Computing (HPC)',
        'score' => $totalHPC,
        'color' => '#f59e0b',
        'desc' => 'Fokus pada jaringan komputer, komputasi awan (cloud), dan keamanan sistem.'
    ],
    [
        'id' => 'ai',
        'name' => 'Lab Kecerdasan Buatan (AI)',
        'score' => $totalAI,
        'color' => '#22d3ee',
        'desc' => 'Fokus pada machine learning, deep learning, pengolahan citra, dan sistem pakar.'
    ]
];

// Sort descending by score
usort($labs, fn($a, $b) => $b['score'] <=> $a['score']);

$activePage = 'lab';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Recommendation (SAW) — CALMS</title>
    <meta name="description" content="Rekomendasi lab penelitian dengan metode SAW.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .lab-intro { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:28px; display:flex; align-items:center; gap:20px; }
        .lab-intro-icon { width:56px; height:56px; border-radius:var(--radius-md); background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.2); display:flex; align-items:center; justify-content:center; flex-shrink:0; color:var(--cyan); }
        .lab-intro-text h2 { font-size:17px; font-weight:700; margin-bottom:4px; }
        .lab-intro-text p { font-size:13px; color:var(--text-secondary); }
        
        .lab-cards { display:grid; grid-template-columns:1fr; gap:20px; margin-bottom:30px; }
        .lab-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; transition:var(--transition); position:relative; overflow:hidden; }
        .lab-card:hover { border-color:var(--border-hover); transform:translateY(-2px); }
        .lab-card.top-match { border-color:rgba(34,211,238,0.35); }
        .lab-card.top-match::before { content:'#1 REKOMENDASI UTAMA'; position:absolute; top:0; right:0; background:var(--cyan); color:#0a0f1a; font-size:10px; font-weight:800; padding:4px 12px; border-bottom-left-radius:var(--radius-sm); letter-spacing:0.5px; }
        .lab-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .lab-rank { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
        .lab-title { font-size:18px; font-weight:700; flex:1; }
        .lab-score { font-size:24px; font-weight:700; font-family:monospace; }
        
        .table-responsive { overflow-x:auto; margin-top: 10px;}
        .table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .table th, .table td { padding: 10px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: left; }
        .table th { color: var(--text-muted); font-weight: 500; }
        .table td { color: var(--text-secondary); }
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
                <p class="page-sub">Rekomendasi lab berbasis Simple Additive Weighting (SAW)</p>
            </div>
        </div>
    </div>

    <div class="lab-intro">
        <div class="lab-intro-icon">
            <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
        </div>
        <div class="lab-intro-text">
            <h2>Metode SAW: Penilaian Otomatis</h2>
            <p>Sistem ini otomatis mengambil nilai transkrip kamu dan mengalikannya dengan bobot LAB yang diatur admin. Skor di bawah ini adalah hasil perhitungan SAW secara real-time.</p>
        </div>
    </div>

    <?php if (empty($courses)): ?>
    <div style="background:rgba(245,158,11,0.1); border:1px solid #f59e0b; padding:20px; border-radius:8px; color:#f59e0b; text-align:center; margin-bottom:30px;">
        Belum ada data nilai akademik untuk perhitungan SAW LAB. Silakan pastikan matkul wajib telah diisi di halaman Skill Gap.
    </div>
    <?php endif; ?>

    <!-- Lab Rankings -->
    <div class="lab-cards">
        <?php foreach ($labs as $i => $lab): ?>
        <div class="lab-card <?= $i === 0 ? 'top-match' : '' ?>">
            <div class="lab-header">
                <div class="lab-rank" style="background:<?= $lab['color'] ?>22; color:<?= $lab['color'] ?>">
                    <?= $i+1 ?>
                </div>
                <div class="lab-title"><?= htmlspecialchars($lab['name']) ?></div>
                <div class="lab-score" style="color:<?= $lab['color'] ?>"><?= number_format($lab['score'], 2) ?></div>
            </div>
            <p style="color:var(--text-secondary); font-size:13px;"><?= $lab['desc'] ?></p>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Details / Matrix -->
    <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px;">
        <h3 style="color:#fff; font-size:16px; margin-bottom:15px;">Detail Perhitungan Matriks Keputusan</h3>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>Nilai Skor</th>
                        <th>Bobot RPL</th>
                        <th>Bobot HPC</th>
                        <th>Bobot AI</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['course_name_id']) ?> <br><span style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($c['course_code']) ?></span></td>
                        <td><strong style="color:var(--cyan)"><?= $c['score'] ?></strong></td>
                        <td><?= $c['bobot_rpl'] ?></td>
                        <td><?= $c['bobot_hpc'] ?></td>
                        <td><?= $c['bobot_ai'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($courses)): ?>
                    <tr><td colspan="5" style="text-align:center;">Tidak ada data matriks.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
