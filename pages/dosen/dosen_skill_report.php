<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('dosen');
$user = getCurrentUser();
$db   = getDB();

$skillReport = $db->query("
    SELECT
        s.skill_name,
        s.category,
        s.industry_level,

        ROUND(AVG(ss.student_level),1) AS avg_student,

        ROUND(
            AVG(
                s.industry_level - COALESCE(ss.student_level,0)
            ),1
        ) AS avg_gap,

        COUNT(ss.id) AS student_count,

        SUM(
            CASE
                WHEN ss.student_level >= s.industry_level
                THEN 1
                ELSE 0
            END
        ) AS meet_standard

    FROM skills s

    LEFT JOIN student_skills ss
        ON ss.skill_id = s.id

    GROUP BY s.id

    ORDER BY avg_gap DESC
")->fetchAll();

$byCategory = [];
foreach ($skillReport as $sk) {
    $byCategory[$sk['category']][] = $sk;
}

$activePageDosen = 'skill_report';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Skill Gap — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .cat-section { margin-bottom:28px; }
        .cat-header { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
        .cat-title { font-size:13px; font-weight:600; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px; }
        .cat-badge { font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(167,139,250,0.1); color:#a78bfa; }
        .skill-report-table { width:100%; border-collapse:collapse; }
        .skill-report-table th { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600; text-align:left; padding:8px 14px; border-bottom:1px solid var(--border); }
        .skill-report-table td { font-size:13px; padding:10px 14px; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); }
        .skill-report-table tr:last-child td { border-bottom:none; }
        .gap-bar-wrap { display:flex; align-items:center; gap:8px; }
        .gap-bar { flex:1; max-width:120px; height:6px; background:#1e293b; border-radius:999px; overflow:hidden; }
        .gap-bar-fill { height:100%; border-radius:999px; }
        .summary-top { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
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
                <h1 class="page-title">Laporan Skill Gap</h1>
                <p class="page-sub">Analisis kesenjangan skill mahasiswa vs standar industri</p>
            </div>
        </div>
    </div>

    <div class="summary-top">
        <div class="stat-card">
            <div class="stat-icon stat-icon--purple">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Skill Dikatalogkan</span>
                <span class="stat-value value--purple"><?= count($skillReport) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Skill Gap Rendah (≤2)</span>
                <span class="stat-value value--green"><?= count(array_filter($skillReport, fn($s) => $s['avg_gap'] <= 1)) ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Skill Gap Tinggi (>4)</span>
                <span class="stat-value value--red"><?= count(array_filter($skillReport, fn($s) => $s['avg_gap'] > 3)) ?></span>
            </div>
        </div>
    </div>

    <?php foreach ($byCategory as $cat => $skills): ?>
    <div class="cat-section">
        <div class="cat-header">
            <span class="cat-title"><?= htmlspecialchars($cat) ?></span>
            <span class="cat-badge"><?= count($skills) ?> skill</span>
        </div>
        <div class="dash-panel" style="padding:0;overflow:hidden;">
            <table class="skill-report-table">
                <thead>
                    <tr>
                        <th>Skill</th>
                        <th>Standar Industri</th>
                        <th>Rata-rata Mhs</th>
                        <th>Gap Rata-rata</th>
                        <th>Mhs Terdata</th>
                        <th>Memenuhi Standar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($skills as $sk):
                        $gap = $sk['avg_gap'] ?? 0;
                        $gc = $gap <= 2 ? '#10b981' : ($gap <= 4 ? '#f59e0b' : '#ef4444');
                        $pct = $sk['industry_level'] > 0 ? round(($sk['avg_student'] / $sk['industry_level']) * 100) : 0;
                        $meetPct = $sk['student_count'] > 0 ? round(($sk['meet_standard'] / $sk['student_count']) * 100) : 0;
                    ?>
                    <tr>
                        <td style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($sk['skill_name']) ?></td>
                        <td style="font-family:var(--font-mono);text-align:center"><?= $sk['industry_level'] ?>/10</td>
                        <td style="font-family:var(--font-mono);text-align:center"><?= $sk['avg_student'] ?? '-' ?>/10</td>
                        <td>
                            <div class="gap-bar-wrap">
                                <div class="gap-bar">
                                    <div class="gap-bar-fill" style="width:<?= min(100, ($gap/10)*100) ?>%;background:<?= $gc ?>"></div>
                                </div>
                                <span style="font-family:var(--font-mono);font-size:12px;color:<?= $gc ?>">-<?= $gap ?></span>
                            </div>
                        </td>
                        <td style="font-family:var(--font-mono);text-align:center"><?= $sk['student_count'] ?> mhs</td>
                        <td>
                            <span style="font-family:var(--font-mono);font-size:12px;color:<?= $meetPct >= 50 ? '#10b981' : '#ef4444' ?>"><?= $meetPct ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</main>

<script>
const toggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
