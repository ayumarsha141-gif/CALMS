<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();

$db = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email 
    FROM mahasiswa_profiles mp 
    JOIN users u ON u.id = mp.user_id 
    WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$studentId = $profile['id'];

// Ambil skill sesuai target_career (sama persis dengan skill_gap.php)
$stmt = $db->prepare("
    SELECT
        s.skill_name,
        s.category,
        s.industry_level,
        COALESCE(ss.student_level, 0) AS student_level,
        (s.industry_level - COALESCE(ss.student_level, 0)) AS gap
    FROM skills s
    JOIN career_skills cs
        ON UPPER(TRIM(cs.skill_name)) = UPPER(TRIM(s.skill_name))
    JOIN career_positions cp
        ON cp.id = cs.career_id
    LEFT JOIN student_skills ss
        ON ss.skill_id = s.id
        AND ss.student_id = ?
    WHERE cp.position_name = ?
    ORDER BY gap DESC
    LIMIT 5
");
$stmt->execute([$studentId, $profile['target_career']]);
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

// Jika belum ada target career atau skill belum diisi, $skills tetap array kosong

// Peluang Rekrutmen = dari probability_score simulasi (termasuk porto & sertifikasi)
$probScore = $simulation ? round($simulation['probability_score'] * 100) : 0;

// Career Readiness = 60% skill readiness + 40% avg nilai matkul (sama dengan skill_gap.php)
$stmt = $db->prepare("
    SELECT
        SUM(COALESCE(ss.student_level, 0) / GREATEST(s.industry_level, 1) * 100) / COUNT(*) AS skill_part
    FROM skills s
    JOIN career_skills cs
        ON UPPER(TRIM(cs.skill_name)) = UPPER(TRIM(s.skill_name))
    JOIN career_positions cp
        ON cp.id = cs.career_id
    LEFT JOIN student_skills ss
        ON ss.skill_id = s.id
        AND ss.student_id = ?
    WHERE cp.position_name = ?
");
$stmt->execute([$studentId, $profile['target_career']]);
$skillPart = (float)($stmt->fetchColumn() ?? 0);

$stmt = $db->prepare("
    SELECT AVG(score)
    FROM student_courses
    WHERE student_id = ?
");
$stmt->execute([$studentId]);
$coursePart = (float)($stmt->fetchColumn() ?? 0);

$readinessScore = (int) round(($skillPart * 0.6) + ($coursePart * 0.4));

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

$stmt = $db->prepare("
SELECT COUNT(*)
FROM student_skills ss
JOIN mahasiswa_profiles mp
ON mp.id = ss.student_id
WHERE mp.user_id = ?
");

$stmt->execute([$user['id']]);
$totalSkills = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link rel="stylesheet" href="../../styles/style_patch.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        <style>
            @media (max-width: 900px) {
                #sidebar {
                    transform: translateX(-100%) !important;
                    position: fixed !important;
                }
                #sidebar.open {
                    transform: translateX(0) !important;
                }
                .main-content {
                    margin-left: 0 !important;
                }
            }
        </style>
    </head>
<body class="dashboard-body">

<?php
$activePage = 'dashboard';
include '../../includes/sidebar.php';
?>

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
                <span class="stat-value value--green"><?= $totalSkills ?></span>
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
                
                        <?php
                        $indPct = max(0,min(100,$indPct));
                        $pct    = max(0,min(100,$pct));
                        ?>
                    <div class="skill-gap-bars">

                        <div class="bar-track">
                            <div class="bar-industry"
                                style="width:<?= $indPct ?>%"></div>
                        </div>

                        <div class="bar-track">
                            <div class="bar-student <?= barStudentClass($gap) ?>"
                                style="width:<?= $pct ?>%"></div>
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
                        <p><strong><?= htmlspecialchars($simulation['target_role'] ?? '-') ?></strong></p>
                        <p class="muted-txt"><?= htmlspecialchars($simulation['target_company'] ?? '-') ?></p>
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