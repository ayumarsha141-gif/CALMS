<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();

$db = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email 
    FROM mahasiswa_profiles mp 
    JOIN users u ON u.id = mp.user_id 
    WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$stmt = $db->prepare("
    SELECT s.skill_name, s.category, s.industry_level, ss.student_level,
           (s.industry_level - ss.student_level) AS gap
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    WHERE mp.user_id = ?
    ORDER BY gap DESC
    LIMIT 5
");
$stmt->execute([$user['id']]);
$skills = $stmt->fetchAll();

$stmt = $db->prepare("
    SELECT s.probability_score, s.target_company, s.target_role, s.created_at
    FROM simulations s
    JOIN mahasiswa_profiles mp ON mp.id = s.student_id
    WHERE mp.user_id = ?
    ORDER BY s.created_at DESC
    LIMIT 1
");
$stmt->execute([$user['id']]);
$simulation = $stmt->fetch();

$stmt = $db->prepare("
    SELECT sc.cert_name, sc.provider, sc.tier, sc.score, sc.status
    FROM student_certifications sc
    JOIN mahasiswa_profiles mp ON mp.id = sc.student_id
    WHERE mp.user_id = ? AND sc.status = 'recommended'
    ORDER BY sc.tier ASC, sc.score DESC
    LIMIT 3
");
    
$stmt->execute([$user['id']]);
$certs = $stmt->fetchAll();

// $skills tetap array kosong jika belum ada data, tampilan empty state sudah ditangani di view

$totalReadiness = 0;
foreach ($skills as $sk) {
    $totalReadiness += ($sk['student_level'] / $sk['industry_level']) * 100;
}
$readinessScore = count($skills) > 0 ? round($totalReadiness / count($skills)) : 0;
$probScore = $simulation ? round($simulation['probability_score'] * 100) : 0;

function readinessClass(int $score): string {
    if ($score >= 70) return 'value--green';
    if ($score >= 40) return 'value--amber';
    return 'value--red';
}

function probClass(int $score): string {
    if ($score >= 70) return 'sim-prob--green';
    if ($score >= 40) return 'sim-prob--amber';
    return 'sim-prob--red';
}

function ringClass(int $score): string {
    if ($score >= 70) return 'ring--green';
    if ($score >= 40) return 'ring--amber';
    return 'ring--red';
}

function gapClass(int $gap): string {
    if ($gap <= 1) return 'gap-low';
    if ($gap <= 3) return 'gap-mid';
    return 'gap-high';
}

function barStudentClass(int $gap): string {
    if ($gap <= 1) return 'bar-student--green';
    if ($gap <= 3) return 'bar-student--amber';
    return 'bar-student--red';
}

$circumference = 2 * M_PI * 42;
$offset = $circumference - ($readinessScore / 100) * $circumference;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CALMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
</head>
<body class="dashboard-body">

<?php $activePage = 'dashboard'; include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Dashboard</h1>
                <p class="page-sub">Selamat datang, <?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?> 👋</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">Semester <?= $profile['semester'] ?? '-' ?></span>
            <?php if ($profile['target_career']): ?>
                <span class="career-badge">🎯 <?= htmlspecialchars($profile['target_career']) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon--cyan">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Career Readiness</span>
                <span class="stat-value <?= readinessClass($readinessScore) ?>"><?= $readinessScore ?>%</span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill stat-bar-fill--cyan" data-width="<?= $readinessScore ?>"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--blue">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Peluang Rekrutmen</span>
                <span class="stat-value <?= probClass($probScore) === 'sim-prob--green' ? 'value--green' : (probClass($probScore) === 'sim-prob--amber' ? 'value--amber' : 'value--red') ?>"><?= $probScore ?>%</span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill stat-bar-fill--blue" data-width="<?= $probScore ?>"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Skills Terdaftar</span>
                <span class="stat-value value--green"><?= count($skills) ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--purple">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M9 12l2 2 4-4"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Sertifikasi</span>
                <span class="stat-value value--purple"><?= count($certs) ?></span>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">

        <div class="dash-panel panel-wide">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Skill Gap Analysis</h2>
                    <p class="panel-sub">Perbandingan kemampuanmu vs standar industri</p>
                </div>
                <a href="skill_gap.php" class="panel-link">Lihat detail →</a>
            </div>

            <div class="skill-gap-list">
                <?php foreach ($skills as $sk):
                    $pct    = round(($sk['student_level'] / 10) * 100);
                    $indPct = round(($sk['industry_level'] / 10) * 100);
                    $gap    = $sk['gap'];
                    $gLabel = $gap <= 1 ? 'Rendah' : ($gap <= 3 ? 'Sedang' : 'Tinggi');
                ?>
                <div class="skill-gap-row">
                    <div class="skill-gap-meta">
                        <span class="skill-gap-name"><?= htmlspecialchars($sk['skill_name']) ?></span>
                        <div class="skill-gap-right">
                            <span class="gap-tag <?= gapClass($gap) ?>">Gap: <?= $gLabel ?></span>
                            <span class="skill-score-txt"><?= $sk['student_level'] ?>/<?= $sk['industry_level'] ?></span>
                        </div>
                    </div>
                    <div class="skill-gap-bars">
                        <div class="double-bar">
                            <div class="bar-industry" data-width="<?= $indPct ?>"></div>
                            <div class="bar-student <?= barStudentClass($gap) ?>" data-width="<?= $pct ?>"></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="legend">
                <span class="legend-item"><span class="legend-dot legend-dot--industry"></span> Standar Industri</span>
                <span class="legend-item"><span class="legend-dot legend-dot--student"></span> Kemampuanmu</span>
            </div>
        </div>

        <div class="dash-right">

            <div class="dash-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Career Readiness</h2>
                </div>
                <div class="readiness-wrap">
                    <div class="readiness-ring">
                        <svg viewBox="0 0 100 100" width="110" height="110">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="#1e293b" stroke-width="8"/>
                            <circle cx="50" cy="50" r="42" fill="none"
                                class="ring-progress <?= ringClass($readinessScore) ?>"
                                stroke-width="8"
                                stroke-dasharray="<?= round($circumference, 2) ?>"
                                stroke-dashoffset="<?= round($offset, 2) ?>"
                                stroke-linecap="round"
                                transform="rotate(-90 50 50)"/>
                        </svg>
                        <div class="ring-center">
                            <span class="<?= readinessClass($readinessScore) ?>"><?= $readinessScore ?>%</span>
                        </div>
                    </div>
                    <div class="readiness-info">
                        <?php
                        $rlabel = $readinessScore >= 70 ? 'Siap Kerja' : ($readinessScore >= 40 ? 'Perlu Peningkatan' : 'Masih Berkembang');
                        $rdesc  = $readinessScore >= 70 ? 'Kamu sudah cukup siap bersaing di industri!' : ($readinessScore >= 40 ? 'Tingkatkan beberapa skill kuncimu.' : 'Fokus dulu pada skill dasar yang dibutuhkan.');
                        ?>
                        <strong><?= $rlabel ?></strong>
                        <p><?= $rdesc ?></p>
                        <a href="simulation.php" class="btn-sm-cyan">Coba Simulasi →</a>
                    </div>
                </div>
            </div>

            <div class="dash-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Simulasi Terakhir</h2>
                    <a href="simulation.php" class="panel-link">Jalankan →</a>
                </div>
                <?php if ($simulation): ?>
                <div class="sim-result">
                    <div class="sim-prob <?= probClass($probScore) ?>">
                        <?= $probScore ?>%
                    </div>
                    <div class="sim-detail">
                        <p><strong><?= htmlspecialchars($simulation['target_role'] ?? 'N/A') ?></strong></p>
                        <p class="muted-txt"><?= htmlspecialchars($simulation['target_company'] ?? 'N/A') ?></p>
                        <p class="muted-txt sim-date">
                            <?= date('d M Y', strtotime($simulation['created_at'])) ?>
                        </p>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <p>Belum ada simulasi.</p>
                    <a href="simulation.php" class="btn-sm-cyan">Mulai Simulasi →</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="dash-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Sertifikasi Direkomendasikan</h2>
                    <a href="certifications.php" class="panel-link">Semua →</a>
                </div>
                <?php if (!empty($certs)): ?>
                    <?php foreach ($certs as $i => $cert): ?>
                    <div class="cert-row">
                        <div class="cert-rank rank-<?= $i+1 ?>"><?= $i+1 ?></div>
                        <div class="cert-info">
                            <strong><?= htmlspecialchars($cert['cert_name']) ?></strong>
                            <?php $tierLabel = $cert['tier'] == 1 ? 'Internasional' : ($cert['tier'] == 2 ? 'BNSP' : 'Kursus'); ?>
                            <span><?= htmlspecialchars($cert['provider']) ?> · Tier <?= $cert['tier'] ?> — <?= $tierLabel ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>Lengkapi profil skill untuk mendapatkan rekomendasi.</p>
                        <a href="skill_gap.php" class="btn-sm-cyan">Isi Skill →</a>
                    </div>
                <?php endif; ?>
            </div>

        </div>
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
