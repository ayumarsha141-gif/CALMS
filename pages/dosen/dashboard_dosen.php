<?php
session_start();
require_once '../../includes/auth_guard_dosen.php';
require_once '../../config/database.php';

requireDosen();
$dosenUser = getDosenUser();
$db = getDB();

// Total mahasiswa
$totalMhs = $db->query("SELECT COUNT(*) FROM users WHERE role = 'mahasiswa' AND is_active = 1")->fetchColumn();

// Rata-rata readiness semua mahasiswa
$avgReady = $db->query("
    SELECT AVG(sub.avg_level) FROM (
        SELECT AVG(ss.student_level / s.industry_level * 100) AS avg_level
        FROM student_skills ss
        JOIN skills s ON s.id = ss.skill_id
        GROUP BY ss.student_id
    ) sub
")->fetchColumn();
$avgReady = $avgReady ? round($avgReady) : 0;

$topStudents = $db->query("
    SELECT
        u.fullname,
        mp.nim,
        mp.target_career,
        mp.semester,
        ROUND(
            AVG(
                LEAST(
                    (ss.student_level / s.industry_level) * 100,
                    100
                )
            )
        ) AS readiness
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    JOIN users u ON u.id = mp.user_id
    GROUP BY ss.student_id
    ORDER BY readiness DESC
    LIMIT 5
")->fetchAll();

// Mahasiswa dengan readiness terendah (perlu perhatian)
$lowStudents = $db->query("
    SELECT
        u.fullname,
        mp.nim,
        mp.target_career,
        mp.semester,
        ROUND(
            AVG(
                LEAST(
                    (ss.student_level / s.industry_level) * 100,
                    100
                )
            )
        ) AS readiness
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    JOIN users u ON u.id = mp.user_id
    GROUP BY ss.student_id
    ORDER BY readiness ASC
    LIMIT 5
")->fetchAll();

// Distribusi target career
$careerDist = $db->query("
    SELECT target_career, COUNT(*) AS total
    FROM mahasiswa_profiles
    WHERE target_career IS NOT NULL AND target_career != ''
    GROUP BY target_career
    ORDER BY total DESC
    LIMIT 6
")->fetchAll();

// Skill paling lemah (gap tertinggi rata-rata)
$weakSkills = $db->query("
    SELECT s.skill_name, s.category,
           ROUND(AVG(s.industry_level - ss.student_level), 1) AS avg_gap,
           COUNT(ss.id) AS student_count
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    GROUP BY ss.skill_id
    ORDER BY avg_gap DESC
    LIMIT 5
")->fetchAll();

// Simulasi terakhir per mahasiswa (ringkasan)
$simSummary = $db->query("
    SELECT
        u.fullname,
        mp.nim,
        mp.target_career,
        ROUND(sim.probability_score * 100) AS prob,
        sim.created_at
    FROM simulations sim
    JOIN mahasiswa_profiles mp ON mp.id = sim.student_id
    JOIN users u ON u.id = mp.user_id
    ORDER BY sim.created_at DESC
    LIMIT 8
")->fetchAll();

// Mahasiswa belum isi skill
$noSkillMhs = $db->query("
    SELECT COUNT(*) FROM mahasiswa_profiles mp
    WHERE mp.id NOT IN (SELECT DISTINCT student_id FROM student_skills)
")->fetchColumn();

$activePageDosen = 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Dosen theme — purple accent */
        .dosen-accent { color: #a78bfa; }
        .sidebar-nav a.active { background:rgba(167,139,250,0.1); color:#a78bfa; }
        .sidebar-nav a:hover  { background:rgba(167,139,250,0.07); color:var(--text-primary); }

        .dosen-header-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:4px 12px; border-radius:999px;
            background:rgba(167,139,250,0.1); border:1px solid rgba(167,139,250,0.25);
            color:#a78bfa; font-size:11px; font-weight:700; letter-spacing:.5px;
        }

        /* Overview cards */
        .overview-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        @media(max-width:1100px){ .overview-grid{ grid-template-columns:repeat(2,1fr); } }
        @media(max-width:600px) { .overview-grid{ grid-template-columns:1fr 1fr; } }

        /* Two-col layout */
        .dosen-grid { display:grid; grid-template-columns:1fr 360px; gap:20px; align-items:start; }
        @media(max-width:1100px){ .dosen-grid{ grid-template-columns:1fr; } }

        /* Student table */
        .student-table { width:100%; border-collapse:collapse; }
        .student-table th {
            font-size:11px; text-transform:uppercase; letter-spacing:1px;
            color:var(--text-muted); font-weight:600; text-align:left;
            padding:8px 12px; border-bottom:1px solid var(--border);
        }
        .student-table td {
            font-size:13px; padding:10px 12px;
            border-bottom:1px solid rgba(255,255,255,.04);
            color:var(--text-secondary);
        }
        .student-table tr:last-child td { border-bottom:none; }
        .student-table tr:hover td { background:rgba(255,255,255,.02); }

        .readiness-mini { display:inline-flex; align-items:center; gap:6px; }
        .readiness-mini-bar { width:60px; height:5px; background:#1e293b; border-radius:999px; overflow:hidden; flex-shrink:0; }
        .readiness-mini-fill { height:100%; border-radius:999px; }

        /* Alert box */
        .alert-dosen {
            background:rgba(245,158,11,.08); border:1px solid rgba(245,158,11,.2);
            border-radius:var(--radius-md); padding:14px 18px;
            font-size:13px; color:#f59e0b; margin-bottom:20px;
            display:flex; align-items:center; gap:10px;
        }

        /* Career dist */
        .career-dist-row { display:flex; flex-direction:column; gap:8px; }
        .career-dist-item { display:flex; flex-direction:column; gap:4px; }
        .career-dist-meta { display:flex; justify-content:space-between; font-size:12px; }
        .career-dist-meta span:first-child { color:var(--text-secondary); }
        .career-dist-meta span:last-child  { color:var(--text-muted); font-family:var(--font-mono); }
        .career-bar-track { height:6px; background:#1e293b; border-radius:999px; overflow:hidden; }
        .career-bar-fill  { height:100%; border-radius:999px; background:#a78bfa; }

        /* Weak skill row */
        .weak-skill-row { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.04); }
        .weak-skill-row:last-child { border-bottom:none; }
        .weak-skill-name { font-size:13px; font-weight:500; }
        .weak-skill-right { display:flex; align-items:center; gap:8px; }
        .gap-num { font-family:var(--font-mono); font-size:12px; color:#ef4444; }

        /* Sim table */
        .prob-pill {
            font-size:11px; font-family:var(--font-mono); font-weight:700;
            padding:2px 8px; border-radius:999px;
        }
        .prob-high { background:rgba(16,185,129,.12); color:#10b981; }
        .prob-mid  { background:rgba(245,158,11,.12); color:#f59e0b; }
        .prob-low  { background:rgba(239,68,68,.12);  color:#ef4444; }
    </style>
</head>
<body class="dashboard-body">

<?php include '../../includes/sidebar_dosen.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Dashboard Dosen Wali</h1>
                <p class="page-sub">Monitoring karir mahasiswa Informatika Unram</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="dosen-header-badge">👨‍🏫 <?= htmlspecialchars(explode(' ', $dosenUser['fullname'])[0]) ?></span>
        </div>
    </div>

    <?php if ($noSkillMhs > 0): ?>
    <div class="alert-dosen">
        ⚠️ <span><strong><?= $noSkillMhs ?> mahasiswa</strong> belum mengisi data skill. Ingatkan mereka untuk mengisi di halaman Skill Gap Analysis.</span>
    </div>
    <?php endif; ?>

    <!-- Overview Stats -->
    <div class="overview-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon--purple">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Mahasiswa</span>
                <span class="stat-value value--purple"><?= $totalMhs ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--cyan">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Avg Career Readiness</span>
                <span class="stat-value <?= $avgReady >= 70 ? 'value--green' : ($avgReady >= 40 ? 'value--amber' : 'value--red') ?>"><?= $avgReady ?>%</span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill stat-bar-fill--cyan" data-width="<?= $avgReady ?>"></div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Sudah Isi Skill</span>
                <span class="stat-value value--green"><?= $totalMhs - $noSkillMhs ?></span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Belum Isi Skill</span>
                <span class="stat-value value--red"><?= $noSkillMhs ?></span>
            </div>
        </div>
    </div>

    <div class="dosen-grid">
        <!-- LEFT col -->
        <div style="display:flex;flex-direction:column;gap:20px;">

            <!-- Top students -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">🏆 Readiness Tertinggi</h2>
                        <p class="panel-sub">Mahasiswa dengan kesiapan karir terbaik</p>
                    </div>
                </div>
                <?php if (empty($topStudents)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">Belum ada data skill mahasiswa.</p>
                <?php else: ?>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th>Target Karir</th>
                            <th>Readiness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topStudents as $i => $s): ?>
                        <tr>
                            <td style="color:<?= $i===0?'#facc15':($i===1?'#94a3b8':'#b47850') ?>;font-weight:700;"><?= $i+1 ?></td>
                            <td style="color:var(--text-primary);font-weight:500;"><?= htmlspecialchars($s['fullname']) ?></td>
                            <td style="font-family:var(--font-mono);font-size:12px;"><?= htmlspecialchars($s['nim']) ?></td>
                            <td><?= htmlspecialchars($s['target_career'] ?: '-') ?></td>
                            <td>
                                <div class="readiness-mini">
                                    <div class="readiness-mini-bar">
                                        <div class="readiness-mini-fill" style="width:<?= $s['readiness'] ?>%;background:<?= $s['readiness']>=70?'#10b981':($s['readiness']>=40?'#f59e0b':'#ef4444') ?>;"></div>
                                    </div>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:<?= $s['readiness']>=70?'#10b981':($s['readiness']>=40?'#f59e0b':'#ef4444') ?>;"><?= $s['readiness'] ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Low students - perlu perhatian -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">⚠️ Perlu Perhatian</h2>
                        <p class="panel-sub">Mahasiswa dengan readiness terendah</p>
                    </div>
                </div>
                <?php if (empty($lowStudents)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">Belum ada data skill mahasiswa.</p>
                <?php else: ?>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NIM</th>
                            <th>Semester</th>
                            <th>Target Karir</th>
                            <th>Readiness</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lowStudents as $s): ?>
                        <tr>
                            <td style="color:var(--text-primary);font-weight:500;"><?= htmlspecialchars($s['fullname']) ?></td>
                            <td style="font-family:var(--font-mono);font-size:12px;"><?= htmlspecialchars($s['nim']) ?></td>
                            <td>Sem <?= $s['semester'] ?></td>
                            <td><?= htmlspecialchars($s['target_career'] ?: '-') ?></td>
                            <td>
                                <div class="readiness-mini">
                                    <div class="readiness-mini-bar">
                                        <div class="readiness-mini-fill" style="width:<?= $s['readiness'] ?>%;background:#ef4444;"></div>
                                    </div>
                                    <span style="font-family:var(--font-mono);font-size:12px;color:#ef4444;"><?= $s['readiness'] ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Simulation results -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">🎲 Simulasi Rekrutmen Terbaru</h2>
                        <p class="panel-sub">Hasil Monte Carlo mahasiswa terkini</p>
                    </div>
                </div>
                <?php if (empty($simSummary)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">Belum ada simulasi yang dijalankan.</p>
                <?php else: ?>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Target Karir</th>
                            <th>Peluang</th>
                            <th>Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($simSummary as $sim): ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;color:var(--text-primary);"><?= htmlspecialchars($sim['fullname']) ?></div>
                                <div style="font-size:11px;font-family:var(--font-mono);color:var(--text-muted);"><?= htmlspecialchars($sim['nim']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($sim['target_career'] ?: '-') ?></td>
                            <td>
                                <?php
                                $p = $sim['prob'];
                                $pc = $p >= 70 ? 'prob-high' : ($p >= 40 ? 'prob-mid' : 'prob-low');
                                ?>
                                <span class="prob-pill <?= $pc ?>"><?= $p ?>%</span>
                            </td>
                            <td style="font-size:11px;color:var(--text-muted);"><?= date('d M Y', strtotime($sim['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div>

        <!-- RIGHT col -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Career distribution -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">🎯 Distribusi Target Karir</h2>
                        <p class="panel-sub">Pilihan karir mahasiswa</p>
                    </div>
                </div>
                <?php if (empty($careerDist)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">Belum ada data target karir.</p>
                <?php else:
                    $maxTotal = max(array_column($careerDist, 'total'));
                ?>
                <div class="career-dist-row">
                    <?php foreach ($careerDist as $cd): ?>
                    <div class="career-dist-item">
                        <div class="career-dist-meta">
                            <span><?= htmlspecialchars($cd['target_career']) ?></span>
                            <span><?= $cd['total'] ?> mhs</span>
                        </div>
                        <div class="career-bar-track">
                            <div class="career-bar-fill" style="width:<?= round($cd['total'] / $maxTotal * 100) ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Weak skills -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">📉 Skill Paling Lemah</h2>
                        <p class="panel-sub">Gap rata-rata tertinggi semua mahasiswa</p>
                    </div>
                </div>
                <?php if (empty($weakSkills)): ?>
                    <p style="font-size:13px;color:var(--text-muted);">Belum ada data skill.</p>
                <?php else: ?>
                <?php foreach ($weakSkills as $i => $ws): ?>
                <div class="weak-skill-row">
                    <div>
                        <div class="weak-skill-name"><?= htmlspecialchars($ws['skill_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($ws['category']) ?> · <?= $ws['student_count'] ?> mahasiswa</div>
                    </div>
                    <div class="weak-skill-right">
                        <span class="gap-tag gap-high">Gap avg</span>
                        <span class="gap-num">-<?= $ws['avg_gap'] ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick info -->
            <div class="dash-panel" style="background:rgba(167,139,250,0.05);border-color:rgba(167,139,250,0.2);">
                <h2 class="panel-title" style="margin-bottom:14px;">ℹ️ Akun Dosen</h2>
                <div style="font-size:13px;color:var(--text-secondary);line-height:1.7;">
                    <div><strong style="color:var(--text-primary);">Nama:</strong> <?= htmlspecialchars($dosenUser['fullname']) ?></div>
                    <div><strong style="color:var(--text-primary);">Email:</strong> <?= htmlspecialchars($dosenUser['email']) ?></div>
                    <div style="margin-top:12px;font-size:12px;color:var(--text-muted);">
                        Dashboard ini menampilkan data seluruh mahasiswa yang terdaftar di CALMS. Gunakan menu di sidebar untuk laporan lebih detail.
                    </div>
                </div>
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
