<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('admin');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
$totalUsers = $stmt->fetchColumn();

$stmt = $db->query("SELECT COUNT(*) FROM simulations");
$totalSimulations = $stmt->fetchColumn();

$stmt = $db->query("SELECT AVG(probability_score) FROM simulations");
$avgProb = round(($stmt->fetchColumn() ?? 0) * 100);

$stmt = $db->query("
    SELECT AVG(s.industry_level - ss.student_level) AS avg_gap
    FROM student_skills ss JOIN skills s ON s.id = ss.skill_id
");
$avgGap = round($stmt->fetchColumn() ?? 0, 1);

$stmt = $db->query("
    SELECT u.fullname, u.email, mp.nim, mp.semester, mp.target_career, u.created_at
    FROM users u JOIN mahasiswa_profiles mp ON mp.user_id = u.id
    WHERE u.role = 'mahasiswa' AND u.is_active = 1
    ORDER BY u.created_at DESC LIMIT 5
");
$recentStudents = $stmt->fetchAll();

$stmt = $db->query("
    SELECT u.fullname, mp.nim,
           ROUND(AVG((ss.student_level / s.industry_level) * 100)) AS readiness
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    JOIN users u ON u.id = mp.user_id
    GROUP BY mp.id ORDER BY readiness DESC LIMIT 5
");
$topStudents = $stmt->fetchAll();

$stmt = $db->query("
    SELECT target_career, COUNT(*) AS total FROM mahasiswa_profiles
    WHERE target_career IS NOT NULL AND target_career != ''
    GROUP BY target_career ORDER BY total DESC LIMIT 6
");
$careerDist = $stmt->fetchAll();

$stmt = $db->query("
    SELECT u.fullname, mp.nim, si.target_role, si.probability_score, si.created_at
    FROM simulations si
    JOIN mahasiswa_profiles mp ON mp.id = si.student_id
    JOIN users u ON u.id = mp.user_id
    ORDER BY si.created_at DESC LIMIT 5
");
$recentSims = $stmt->fetchAll();

$trends = [];
try {
    $stmt = $db->query("SELECT title, category, source, trend_date FROM industry_trends ORDER BY trend_date DESC LIMIT 4");
    $trends = $stmt->fetchAll();
} catch (PDOException $e) {}

$activePage = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link rel="stylesheet" href="../../styles/style_patch.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 32px 32px 48px;
            min-height: 100vh;
            box-sizing: border-box;
            width: calc(100% - 260px);
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 20px 16px 40px; }
            .sidebar { transform: translateX(-100%); transition: .25s ease; }
            .sidebar.open { transform: translateX(0); }
        }

        .stat-grid-admin {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width: 1100px) { .stat-grid-admin { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 560px)  { .stat-grid-admin { grid-template-columns: 1fr; } }

        .stat-card-adm {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px 18px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: border-color .2s, transform .2s;
        }
        .stat-card-adm:hover { border-color: var(--border-hover); transform: translateY(-2px); }
        .stat-card-adm .icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .stat-card-adm .lbl { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .04em; }
        .stat-card-adm .val { font-size: 30px; font-weight: 700; font-family: var(--font-mono); line-height: 1; }
        .stat-card-adm .bar-track { height: 4px; background: rgba(255,255,255,.06); border-radius: 999px; overflow: hidden; }
        .stat-card-adm .bar-fill  { height: 100%; border-radius: 999px; transition: width 1s ease; }

        .admin-grid   { display: grid; grid-template-columns: 3fr 2fr; gap: 20px; margin-bottom: 20px; }
        .admin-grid-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px; }
        @media (max-width: 1100px) { .admin-grid, .admin-grid-3 { grid-template-columns: 1fr; } }

        .admin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .admin-table th { text-align: left; padding: 8px 12px; font-size: 11px; letter-spacing: .8px; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
        .admin-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--text-secondary); vertical-align: middle; }
        .admin-table tr:last-child td { border-bottom: none; }
        .admin-table tr:hover td { background: rgba(255,255,255,.02); }
        .td-name { color: var(--text-primary) !important; font-weight: 600; }
        .td-mono { font-family: var(--font-mono); font-size: 12px; }

        .career-pill { font-size: 11px; padding: 3px 10px; border-radius: 999px; background: rgba(34,211,238,.08); border: 1px solid rgba(34,211,238,.15); color: var(--cyan); white-space: nowrap; display: inline-block; max-width: 160px; overflow: hidden; text-overflow: ellipsis; }

        .dist-row { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
        .dist-meta { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; }
        .dist-meta span:first-child { color: var(--text-primary); font-weight: 500; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dist-meta span:last-child  { color: var(--text-muted); font-family: var(--font-mono); flex-shrink: 0; }
        .dist-bar { height: 6px; background: var(--border); border-radius: 999px; overflow: hidden; }
        .dist-bar-fill { height: 100%; background: #22d3ee; border-radius: 999px; transition: width 1s ease; }

        .trend-item { display: flex; align-items: flex-start; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--border); }
        .trend-item:last-child { border-bottom: none; padding-bottom: 0; }
        .trend-cat { font-size: 10px; padding: 3px 8px; border-radius: 999px; background: rgba(167,139,250,.1); color: #a78bfa; border: 1px solid rgba(167,139,250,.2); white-space: nowrap; flex-shrink: 0; margin-top: 2px; }
        .trend-info strong { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); line-height: 1.4; }
        .trend-info span   { font-size: 11px; color: var(--text-muted); }

        .empty-box { text-align: center; padding: 32px; color: var(--text-muted); font-size: 13px; }
    
        @media (max-width: 900px) {
            .sg-content { grid-template-columns: 1fr; }
            .sg-stats   { grid-template-columns: repeat(2, 1fr); }
            #sidebar {
                transform: translateX(-100%) !important;
                position: fixed !important;
                top: 0 !important; left: 0 !important; bottom: 0 !important;
                z-index: 999 !important;
                transition: transform 0.25s ease !important;
            }
            #sidebar.open { transform: translateX(0) !important; }
            .main-content { margin-left: 0 !important; width: 100% !important; }
            .sidebar-toggle {
                display: flex !important;
                position: relative !important;
                z-index: 9999 !important;
                pointer-events: all !important;
            }
            #sidebar-overlay { display: none; !important; }
            #sidebar-overlay.show { display: block; }
        }
        @media (max-width: 560px) {
            .sg-stats { grid-template-columns: 1fr 1fr; }
            .level-row { grid-template-columns: 1fr 1fr; }
        }

        .sidebar-toggle{
            position: relative !important;
            z-index: 1001 !important;
        }

        .topbar{
            position: relative !important;
            z-index: 1 !important;
        }

        .sg-stats,
        .sg-content,
        .skill-card{
            position: static !important;
            z-index: auto !important;
        }

        .sidebar{
            z-index: 1000 !important;
        }

        .main-content{
            position: relative;
            z-index: 1;
        }
    </style>
</head>
<body class="dashboard-body admin-body">

<?php include '../../includes/sidebar_admin.php'; ?>

<main class="main-content">

    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Admin Dashboard</h1>
                <p class="page-sub">Selamat datang, <?= htmlspecialchars(explode(' ', $user['fullname'])[0]) ?> — pantau seluruh sistem CALMS</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge"><?= date('d M Y') ?></span>
            <span class="career-badge">🔒 Admin</span>
        </div>
    </div>

    <div class="stat-grid-admin">

        <div class="stat-card-adm">
            <div class="icon" style="background:rgba(34,211,238,.1)">
                <svg width="18" height="18" fill="none" stroke="#22d3ee" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div><div class="lbl">Total Pengguna</div><div class="val" style="color:#22d3ee"><?= $totalUsers ?></div></div>
            <div class="bar-track"><div class="bar-fill" style="background:#22d3ee" data-width="100"></div></div>
        </div>

        <div class="stat-card-adm">
            <div class="icon" style="background:rgba(167,139,250,.1)">
                <svg width="18" height="18" fill="none" stroke="#a78bfa" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div><div class="lbl">Total Simulasi</div><div class="val" style="color:#a78bfa"><?= $totalSimulations ?></div></div>
            <div class="bar-track"><div class="bar-fill" style="background:#a78bfa" data-width="<?= min($totalSimulations*5,100) ?>"></div></div>
        </div>

        <div class="stat-card-adm">
            <div class="icon" style="background:rgba(245,158,11,.1)">
                <svg width="18" height="18" fill="none" stroke="#f59e0b" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div>
                <div class="lbl">Avg Prob. Rekrutmen</div>
                <div class="val" style="color:<?= $avgProb>=70?'#10b981':($avgProb>=40?'#f59e0b':'#ef4444') ?>"><?= $avgProb ?>%</div>
            </div>
            <div class="bar-track"><div class="bar-fill" style="background:#f59e0b" data-width="<?= $avgProb ?>"></div></div>
        </div>

    </div>

    <div class="admin-grid">

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Mahasiswa Terbaru</h2><p class="panel-sub"><?= count($recentStudents) ?> akun mahasiswa yang baru mendaftar</p></div>
                <a href="admin_master.php?tab=career" class="panel-link">Semua →</a>
            </div>
            <?php if (!empty($recentStudents)): ?>
            <table class="admin-table">
                <thead><tr><th>Nama</th><th>NIM</th><th>Sem</th><th>Target Karir</th><th>Bergabung</th></tr></thead>
                <tbody>
                <?php foreach ($recentStudents as $s): ?>
                <tr>
                    <td class="td-name"><?= htmlspecialchars($s['fullname']) ?></td>
                    <td class="td-mono"><?= htmlspecialchars($s['nim']) ?></td>
                    <td style="text-align:center"><?= $s['semester'] ?></td>
                    <td><?= $s['target_career'] ? '<span class="career-pill">'.htmlspecialchars($s['target_career']).'</span>' : '<span style="color:var(--text-muted);font-size:12px">—</span>' ?></td>
                    <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= date('d M Y', strtotime($s['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty-box">Belum ada mahasiswa terdaftar.</div><?php endif; ?>
        </div>

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Top Career Readiness</h2><p class="panel-sub">Mahasiswa dengan readiness tertinggi</p></div>
            </div>
            <?php if (!empty($topStudents)): ?>
            <table class="admin-table">
                <thead><tr><th>#</th><th>Nama</th><th>NIM</th><th>Readiness</th></tr></thead>
                <tbody>
                <?php foreach ($topStudents as $i => $ts): ?>
                <tr>
                    <td style="font-weight:700;color:<?= $i===0?'#facc15':($i===1?'#94a3b8':($i===2?'#b47850':'var(--text-muted)')) ?>"><?= $i+1 ?></td>
                    <td class="td-name"><?= htmlspecialchars($ts['fullname']) ?></td>
                    <td class="td-mono"><?= htmlspecialchars($ts['nim']) ?></td>
                    <td style="font-weight:700;font-family:var(--font-mono);color:<?= (int)$ts['readiness']>=70?'#10b981':((int)$ts['readiness']>=40?'#f59e0b':'#ef4444') ?>"><?= $ts['readiness'] ?>%</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty-box">Data skill belum tersedia.</div><?php endif; ?>
        </div>

    </div>

    <div class="admin-grid">

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Simulasi Rekrutmen Terbaru</h2><p class="panel-sub"><?= count($recentSims) ?> simulasi terakhir yang dijalankan</p></div>
            </div>
            <?php if (!empty($recentSims)): ?>
            <table class="admin-table">
                <thead><tr><th>Mahasiswa</th><th>Target Role</th><th>Probabilitas</th><th>Tanggal</th></tr></thead>
                <tbody>
                <?php foreach ($recentSims as $sim):
                    $prob = round($sim['probability_score'] * 100);
                ?>
                <tr>
                    <td>
                        <div class="td-name"><?= htmlspecialchars(explode(' ',$sim['fullname'])[0]) ?></div>
                        <div class="td-mono" style="color:var(--text-muted)"><?= htmlspecialchars($sim['nim']) ?></div>
                    </td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($sim['target_role'] ?? '—') ?></td>
                    <td style="font-weight:700;font-family:var(--font-mono);color:<?= $prob>=70?'#10b981':($prob>=40?'#f59e0b':'#ef4444') ?>"><?= $prob ?>%</td>
                    <td style="color:var(--text-muted);font-size:12px;white-space:nowrap"><?= date('d M Y', strtotime($sim['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty-box">Belum ada simulasi berjalan.</div><?php endif; ?>
        </div>

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Distribusi Target Karir</h2><p class="panel-sub">Pilihan karir terbanyak mahasiswa</p></div>
            </div>
            <?php if (!empty($careerDist)):
                $maxCareer = max(array_column($careerDist, 'total'));
                foreach ($careerDist as $cd):
                    $pct = $maxCareer > 0 ? round(($cd['total']/$maxCareer)*100) : 0;
            ?>
            <div class="dist-row">
                <div class="dist-meta">
                    <span><?= htmlspecialchars($cd['target_career']) ?></span>
                    <span><?= $cd['total'] ?> mhs</span>
                </div>
                <div class="dist-bar"><div class="dist-bar-fill" data-width="<?= $pct ?>"></div></div>
            </div>
            <?php endforeach; else: ?>
            <div class="empty-box">Belum ada data target karir.</div>
            <?php endif; ?>
        </div>

    </div>

    <div class="admin-grid-3">

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Industry Trends Terbaru</h2><p class="panel-sub">Tren teknologi terkini yang diinput ke sistem</p></div>
                
            </div>
            <?php if (!empty($trends)): ?>
            <?php foreach ($trends as $tr): ?>
            <div class="trend-item">
                <span class="trend-cat"><?= htmlspecialchars($tr['category']) ?></span>
                <div class="trend-info">
                    <strong><?= htmlspecialchars($tr['title']) ?></strong>
                    <span><?= htmlspecialchars($tr['source']) ?> · <?= date('d M Y', strtotime($tr['trend_date'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?><div class="empty-box">Belum ada data trend.</div><?php endif; ?>
        </div>

        <div class="dash-panel">
            <div class="panel-header">
                <div><h2 class="panel-title">Rata-rata Skill Gap</h2><p class="panel-sub">Selisih skill sistem secara global</p></div>
            </div>
            <?php
            $gapColor = $avgGap <= 1 ? '#10b981' : ($avgGap <= 3 ? '#f59e0b' : '#ef4444');
            $gapLabel = $avgGap <= 1 ? 'Rendah' : ($avgGap <= 3 ? 'Sedang' : 'Tinggi');
            $gapBg    = $avgGap <= 1 ? 'rgba(16,185,129,.15)' : ($avgGap <= 3 ? 'rgba(245,158,11,.15)' : 'rgba(239,68,68,.15)');
            ?>
            <div style="text-align:center;padding:28px 0 20px">
                <div style="font-size:56px;line-height:1;font-family:var(--font-mono);font-weight:700;color:<?= $gapColor ?>"><?= $avgGap ?></div>
                <p style="font-size:13px;color:var(--text-muted);margin-top:10px">rata-rata gap poin (skala 1–10)</p>
                <span style="display:inline-block;margin-top:14px;font-size:12px;padding:4px 14px;border-radius:999px;font-weight:600;background:<?= $gapBg ?>;color:<?= $gapColor ?>">
                    Gap <?= $gapLabel ?>
                </span>
            </div>
            <div style="padding-top:16px;border-top:1px solid var(--border);text-align:center">
                <a href="admin_master.php" style="color:var(--cyan);font-size:13px;font-weight:600">Lihat Master Data →</a>
            </div>
        </div>

    </div>

</main>

<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar')?.classList.toggle('open');
});
document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = '0%';
    setTimeout(() => { el.style.width = (el.dataset.width ?? 0) + '%'; }, 300);
});
</script>
</body>
</html>