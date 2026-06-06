<?php
session_start();
require_once '../includes/auth_guard.php';
require_once '../config/database.php';

requireRole('admin');
$user = getCurrentUser();
$db   = getDB();

// Stat: Total Users
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$totalUsers = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa' AND is_active = 1");
$totalMahasiswa = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'dosen' AND is_active = 1");
$totalDosen = $stmt->fetchColumn();

// Stat: Simulasi & Rata-rata Readiness 
$stmt = $db->query("SELECT COUNT(*) FROM simulations");
$totalSimulations = $stmt->fetchColumn();

$stmt = $db->query("SELECT AVG(probability_score) FROM simulations");
$avgProb = round(($stmt->fetchColumn() ?? 0) * 100);

// Stat: Skill gap rata-rata sistem
$stmt = $db->query("
    SELECT AVG(s.industry_level - ss.student_level) AS avg_gap
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
");
$avgGap = round($stmt->fetchColumn() ?? 0, 1);

//  Tabel: 5 mahasiswa terbaru 
$stmt = $db->query("
    SELECT u.fullname, u.email, mp.nim, mp.semester, mp.target_career,
           u.created_at
    FROM users u
    JOIN mahasiswa_profiles mp ON mp.user_id = u.id
    WHERE u.role = 'mahasiswa' AND u.is_active = 1
    ORDER BY u.created_at DESC
    LIMIT 5
");
$recentStudents = $stmt->fetchAll();

//  Top 5 mahasiswa by readiness 
$stmt = $db->query("
    SELECT u.fullname, mp.nim, mp.target_career,
           ROUND(AVG((ss.student_level / s.industry_level) * 100)) AS readiness
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    JOIN users u ON u.id = mp.user_id
    GROUP BY mp.id
    ORDER BY readiness DESC
    LIMIT 5
");
$topStudents = $stmt->fetchAll();

//  Distribusi target karir 
$stmt = $db->query("
    SELECT target_career, COUNT(*) AS total
    FROM mahasiswa_profiles
    WHERE target_career IS NOT NULL AND target_career != ''
    GROUP BY target_career
    ORDER BY total DESC
    LIMIT 6
");
$careerDist = $stmt->fetchAll();

//  Simulasi terbaru (5 data) 
$stmt = $db->query("
    SELECT u.fullname, mp.nim, si.target_role, si.target_company,
           si.probability_score, si.created_at
    FROM simulations si
    JOIN mahasiswa_profiles mp ON mp.id = si.student_id
    JOIN users u ON u.id = mp.user_id
    ORDER BY si.created_at DESC
    LIMIT 5
");
$recentSims = $stmt->fetchAll();

//  Industry trends terbaru 
$stmt = $db->query("
    SELECT title, category, source, trend_date
    FROM industry_trends
    ORDER BY trend_date DESC
    LIMIT 4
");
$trends = $stmt->fetchAll();

// Helper functions
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
function probLabel(int $score): string {
    if ($score >= 70) return 'Tinggi';
    if ($score >= 40) return 'Sedang';
    return 'Rendah';
}
function gapBadgeClass(float $gap): string {
    if ($gap <= 1) return 'gap-low';
    if ($gap <= 3) return 'gap-mid';
    return 'gap-high';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — CALMS</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Tambahan khusus admin ── */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .admin-table th {
            text-align: left;
            padding: 8px 12px;
            font-size: 11px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .admin-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: rgba(255,255,255,0.02); }
        .td-name { color: var(--text-primary) !important; font-weight: 600; }
        .td-mono { font-family: var(--font-mono); font-size: 12px; }

        .career-pill {
            font-size: 11px;
            padding: 3px 10px;
            border-radius: 999px;
            background: rgba(34,211,238,0.08);
            border: 1px solid rgba(34,211,238,0.15);
            color: var(--cyan);
            white-space: nowrap;
        }

        .dist-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 12px;
        }
        .dist-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        .dist-meta span:first-child { color: var(--text-primary); font-weight: 500; }
        .dist-meta span:last-child  { color: var(--text-muted); font-family: var(--font-mono); }
        .dist-bar {
            height: 6px;
            background: var(--border);
            border-radius: 999px;
            overflow: hidden;
        }
        .dist-bar-fill {
            height: 100%;
            background: #22d3ee;
            border-radius: 999px;
            transition: width 1s ease;
        }

        .trend-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .trend-item:last-child { border-bottom: none; padding-bottom: 0; }
        .trend-cat {
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(167,139,250,0.1);
            color: #a78bfa;
            border: 1px solid rgba(167,139,250,0.2);
            white-space: nowrap;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .trend-info strong {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.4;
        }
        .trend-info span {
            font-size: 11px;
            color: var(--text-muted);
        }

        .stat-grid-5 {
            grid-template-columns: repeat(5, 1fr);
        }
        @media (max-width: 1400px) { .stat-grid-5 { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 900px)  { .stat-grid-5 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .stat-grid-5 { grid-template-columns: 1fr; } }

        .admin-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .admin-grid-3 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        @media (max-width: 1100px) {
            .admin-grid, .admin-grid-3 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="dashboard-body">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['fullname'], 0, 2)) ?></div>
        <div class="user-info">
            <strong><?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?></strong>
            <span>Administrator</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Menu Admin</div>
        <a href="dashboardAdmin.php" class="active">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="admin_master.php?tab=career">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Kelola Posisi & Skill
        </a>
        <a href="admin_master.php?tab=roadmap">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
            Setup Roadmap
        </a>
        <a href="admin_master.php?tab=lab">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
            Matriks Bobot LAB
        </a>
        <a href="admin_master.php?tab=saw">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            Pengaturan Konstanta SAW
        </a>
        <a href="users.php">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            User Management
        </a>

        <div class="nav-group-label nav-group-label--mt">Laporan</div>
        <a href="simulations.php">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            Simulation Logs
        </a>

        <div class="nav-group-label nav-group-label--mt">Akun</div>
        <a href="../logout.php" class="nav-logout">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </nav>
</aside>

<!-- ══════════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════════ -->
<main class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <div class="page-title">Admin Dashboard</div>
                <div class="page-sub">Selamat datang, <?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?> — pantau seluruh sistem CALMS</div>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">
                <?= date('d M Y') ?>
            </span>
            <span class="career-badge">&#128274; Admin Access</span>
        </div>
    </div>

    <!-- ── Stat Cards ── -->
    <div class="stat-grid stat-grid-5">

        <div class="stat-card">
            <div class="stat-icon stat-icon--cyan">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Pengguna</span>
                <span class="stat-value value--cyan"><?= $totalUsers ?></span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill stat-bar-fill--cyan" data-width="100"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--blue">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Mahasiswa Aktif</span>
                <span class="stat-value value--blue"><?= $totalMahasiswa ?></span>
            </div>
            <div class="stat-bar">
                <?php $mPct = $totalUsers > 0 ? round(($totalMahasiswa / $totalUsers) * 100) : 0; ?>
                <div class="stat-bar-fill stat-bar-fill--blue" data-width="<?= $mPct ?>"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.45 2 2 0 0 1 3.58 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Dosen Terdaftar</span>
                <span class="stat-value value--green"><?= $totalDosen ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--purple">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Simulasi</span>
                <span class="stat-value value--purple"><?= $totalSimulations ?></span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill" style="background:#a78bfa" data-width="<?= min($totalSimulations, 100) ?>"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,0.1);color:#f59e0b;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Avg Prob. Rekrutmen</span>
                <span class="stat-value <?= readinessClass($avgProb) ?>"><?= $avgProb ?>%</span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill" style="background:#f59e0b" data-width="<?= $avgProb ?>"></div>
            </div>
        </div>

    </div>

    <!--  Row 1: Mahasiswa Terbaru + Top Readiness  -->
    <div class="admin-grid">

        <!-- Mahasiswa Terbaru -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Mahasiswa Terbaru</h2>
                    <p class="panel-sub">5 akun mahasiswa yang baru mendaftar</p>
                </div>
                <a href="users.php" class="panel-link">Semua User →</a>
            </div>
            <?php if (!empty($recentStudents)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Semester</th>
                        <th>Target Karir</th>
                        <th>Bergabung</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentStudents as $s): ?>
                    <tr>
                        <td class="td-name"><?= htmlspecialchars($s['fullname']) ?></td>
                        <td class="td-mono"><?= htmlspecialchars($s['nim']) ?></td>
                        <td style="text-align:center"><?= $s['semester'] ?></td>
                        <td>
                            <?php if ($s['target_career']): ?>
                                <span class="career-pill"><?= htmlspecialchars($s['target_career']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--text-muted);font-size:12px">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--text-muted);font-size:12px">
                            <?= date('d M Y', strtotime($s['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>Belum ada mahasiswa terdaftar.</p></div>
            <?php endif; ?>
        </div>

        <!-- Top Readiness -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Top Career Readiness</h2>
                    <p class="panel-sub">Mahasiswa dengan readiness tertinggi</p>
                </div>
            </div>
            <?php if (!empty($topStudents)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama</th>
                        <th>NIM</th>
                        <th>Readiness</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($topStudents as $i => $ts): ?>
                    <tr>
                        <td>
                            <div class="cert-rank <?= $i < 3 ? 'rank-'.($i+1) : '' ?>" style="<?= $i >= 3 ? 'background:rgba(255,255,255,0.05);color:var(--text-muted)' : '' ?>">
                                <?= $i+1 ?>
                            </div>
                        </td>
                        <td class="td-name"><?= htmlspecialchars(explode(' ', $ts['fullname'])[0]) ?></td>
                        <td class="td-mono"><?= htmlspecialchars($ts['nim']) ?></td>
                        <td>
                            <span class="stat-value <?= readinessClass((int)$ts['readiness']) ?>" style="font-size:16px">
                                <?= $ts['readiness'] ?>%
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>Data skill belum tersedia.</p></div>
            <?php endif; ?>
        </div>

    </div>

    <!--  Row 2: Simulasi Terbaru + Distribusi Karir + Trends  -->
    <div class="admin-grid">

        <!-- Simulasi Terbaru -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Simulasi Rekrutmen Terbaru</h2>
                    <p class="panel-sub">5 simulasi Monte Carlo terakhir</p>
                </div>
                <a href="simulations.php" class="panel-link">Semua →</a>
            </div>
            <?php if (!empty($recentSims)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Target Role</th>
                        <th>Perusahaan</th>
                        <th>Probabilitas</th>
                        <th>Tanggal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentSims as $sim):
                    $prob = round($sim['probability_score'] * 100);
                ?>
                    <tr>
                        <td>
                            <div class="td-name"><?= htmlspecialchars(explode(' ', $sim['fullname'])[0]) ?></div>
                            <div class="td-mono" style="color:var(--text-muted)"><?= htmlspecialchars($sim['nim']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($sim['target_role'] ?? '—') ?></td>
                        <td style="color:var(--text-muted)"><?= htmlspecialchars($sim['target_company'] ?? '—') ?></td>
                        <td>
                            <span class="<?= probClass($prob) ?>" style="font-weight:700;font-family:var(--font-mono)">
                                <?= $prob ?>%
                            </span>
                        </td>
                        <td style="color:var(--text-muted);font-size:12px">
                            <?= date('d M Y', strtotime($sim['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state"><p>Belum ada simulasi berjalan.</p></div>
            <?php endif; ?>
        </div>

        <!-- Distribusi Karir -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Distribusi Target Karir</h2>
                    <p class="panel-sub">Pilihan karir terbanyak mahasiswa</p>
                </div>
            </div>
            <?php if (!empty($careerDist)):
                $maxCareer = max(array_column($careerDist, 'total'));
            ?>
                <?php foreach ($careerDist as $cd):
                    $pct = $maxCareer > 0 ? round(($cd['total'] / $maxCareer) * 100) : 0;
                ?>
                <div class="dist-row">
                    <div class="dist-meta">
                        <span><?= htmlspecialchars($cd['target_career']) ?></span>
                        <span><?= $cd['total'] ?> mhs</span>
                    </div>
                    <div class="dist-bar">
                        <div class="dist-bar-fill" data-width="<?= $pct ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state"><p>Belum ada data target karir.</p></div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ── Row 3: Industry Trends + Skill Gap System ── -->
    <div class="admin-grid-3">

        <!-- Recent Trends -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Industry Trends Terbaru</h2>
                    <p class="panel-sub">Tren teknologi terkini yang diinput ke sistem</p>
                </div>
                <a href="industry_trends.php" class="panel-link">Kelola →</a>
            </div>
            <?php if (!empty($trends)): ?>
                <?php foreach ($trends as $tr): ?>
                <div class="trend-item">
                    <span class="trend-cat"><?= htmlspecialchars($tr['category']) ?></span>
                    <div class="trend-info">
                        <strong><?= htmlspecialchars($tr['title']) ?></strong>
                        <span>
                            <?= htmlspecialchars($tr['source']) ?>
                            &middot; <?= date('d M Y', strtotime($tr['trend_date'])) ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state"><p>Belum ada data trend.</p></div>
            <?php endif; ?>
        </div>

        <!-- Skill Gap Sistem -->
        <div class="dash-panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Rata-rata Skill Gap</h2>
                    <p class="panel-sub">Selisih skill sistem secara global</p>
                </div>
            </div>
            <div style="text-align:center;padding:20px 0;">
                <div class="sim-prob <?= $avgGap <= 1 ? 'sim-prob--green' : ($avgGap <= 3 ? 'sim-prob--amber' : 'sim-prob--red') ?>"
                     style="font-size:48px;line-height:1;">
                    <?= $avgGap ?>
                </div>
                <p style="font-size:13px;color:var(--text-muted);margin-top:8px">rata-rata gap poin (skala 1-10)</p>
                <span class="gap-tag <?= gapBadgeClass($avgGap) ?>" style="margin-top:12px;display:inline-block">
                    Gap <?= $avgGap <= 1 ? 'Rendah' : ($avgGap <= 3 ? 'Sedang' : 'Tinggi') ?>
                </span>
            </div>
            <div style="padding-top:16px;border-top:1px solid var(--border);text-align:center">
                <a href="reports.php" class="btn-sm-cyan">Lihat Laporan Lengkap →</a>
            </div>
        </div>

    </div>

</main>

<script>
// Sidebar toggle
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

// Animasi bar width
document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = '0%';
    setTimeout(() => {
        el.style.width = el.dataset.width + '%';
    }, 200);
});
</script>

</body>
</html>