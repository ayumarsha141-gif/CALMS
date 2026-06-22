<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('dosen');
$user = getCurrentUser();
$db   = getDB();

$simulations = $db->query("
SELECT
       u.fullname,
       mp.nim,
       mp.target_career,
       sim.skill_score,
       sim.cert_score,
       sim.portfolio_score,
       ROUND(sim.probability_score * 100) AS prob,
       sim.created_at
    FROM simulations sim
    JOIN mahasiswa_profiles mp ON mp.id = sim.student_id
    JOIN users u ON u.id = mp.user_id
    ORDER BY sim.created_at DESC
")->fetchAll();

$totalSim  = count($simulations);
$avgProb   = $totalSim > 0 ? round(array_sum(array_column($simulations, 'prob')) / $totalSim) : 0;
$highCount = count(array_filter($simulations, fn($s) => $s['prob'] >= 70));
$lowCount  = count(array_filter($simulations, fn($s) => $s['prob'] < 40));

$activePageDosen = 'simulation_report';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Simulasi — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .sim-table { width:100%; border-collapse:collapse; }
        .sim-table th { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600; text-align:left; padding:10px 14px; border-bottom:1px solid var(--border); }
        .sim-table td { font-size:13px; padding:11px 14px; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); }
        .sim-table tr:last-child td { border-bottom:none; }
        .sim-table tr:hover td { background:rgba(255,255,255,0.02); }
        .prob-pill { font-size:11px; font-family:var(--font-mono); font-weight:700; padding:3px 10px; border-radius:999px; }
        .p-high { background:rgba(16,185,129,0.12); color:#10b981; }
        .p-mid  { background:rgba(245,158,11,0.12);  color:#f59e0b; }
        .p-low  { background:rgba(239,68,68,0.12);   color:#ef4444; }
        .score-mini { font-size:11px; font-family:var(--font-mono); color:var(--text-muted); }
        .stat-grid-3 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        .filter-bar { display:flex; gap:12px; margin-bottom:16px; }
        .filter-input { background:var(--bg-card); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); width:220px; }
        .filter-select { background:var(--bg-card); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); }
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
                <h1 class="page-title">Hasil Simulasi Rekrutmen</h1>
                <p class="page-sub">Rekap hasil Monte Carlo seluruh mahasiswa</p>
            </div>
        </div>
    </div>

    <div class="stat-grid-3">
        <div class="stat-card">
            <div class="stat-icon stat-icon--purple">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Simulasi</span>
                <span class="stat-value value--purple"><?= $totalSim ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--cyan">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Rata-rata Peluang</span>
                <span class="stat-value <?= $avgProb >= 70 ? 'value--green' : ($avgProb >= 40 ? 'value--amber' : 'value--red') ?>"><?= $avgProb ?>%</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Peluang Tinggi (≥70%)</span>
                <span class="stat-value value--green"><?= $highCount ?></span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Peluang Rendah (<40%)</span>
                <span class="stat-value value--red"><?= $lowCount ?></span>
            </div>
        </div>
    </div>

    <div class="filter-bar">
        <input type="text" class="filter-input" id="searchInput" placeholder="Cari nama atau NIM...">
        <select class="filter-select" id="filterProb">
            <option value="">Semua Peluang</option>
            <option value="tinggi">Tinggi (≥70%)</option>
            <option value="sedang">Sedang (40–69%)</option>
            <option value="rendah">Rendah (<40%)</option>
        </select>
    </div>

    <?php if (empty($simulations)): ?>
    <div class="dash-panel" style="text-align:center;padding:40px">
        <p style="color:var(--text-muted);font-size:14px">Belum ada mahasiswa yang menjalankan simulasi.</p>
    </div>
    <?php else: ?>
    <div class="dash-panel" style="padding:0;overflow:hidden;">
        <table class="sim-table" id="simTable">
            <thead>
                <tr>
                    <th>Mahasiswa</th>
                    <th>Target Karir</th>
                    <th>Skill</th>
                    <th>Sertifikasi</th>
                    <th>Portofolio</th>
                    <th>Peluang</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($simulations as $sim):
                    $p = $sim['prob'];
                    $pc = $p >= 70 ? 'p-high' : ($p >= 40 ? 'p-mid' : 'p-low');
                ?>
                <tr data-prob="<?= $p ?>">
                    <td>
                        <div style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($sim['fullname']) ?></div>
                        <div style="font-size:11px;font-family:var(--font-mono);color:var(--text-muted)"><?= htmlspecialchars($sim['nim']) ?></div>
                    </td>
                    <td>
                        <?= htmlspecialchars($sim['target_career'] ?: '-') ?>
                    </td>

                    <td class="score-mini">
                        <?= round($sim['skill_score']) ?>%
                    </td>

                    <td class="score-mini">
                        <?= round($sim['cert_score']) ?>%
                    </td>

                    <td class="score-mini">
                        <?= round($sim['portfolio_score']) ?>%
                    </td>  
                    <td><span class="prob-pill <?= $pc ?>"><?= $p ?>%</span></td>
                    <td style="font-size:11px;color:var(--text-muted)"><?= date('d M Y', strtotime($sim['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</main>

<script>
const toggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const prob   = document.getElementById('filterProb').value;
    document.querySelectorAll('#simTable tbody tr').forEach(row => {
        const text = row.textContent.toLowerCase();
        const p    = parseInt(row.dataset.prob) || 0;
        let show = true;
        if (search && !text.includes(search)) show = false;
        if (prob === 'tinggi' && p < 70)          show = false;
        if (prob === 'sedang' && (p < 40||p >= 70)) show = false;
        if (prob === 'rendah' && p >= 40)          show = false;
        row.style.display = show ? '' : 'none';
    });
}
document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('filterProb').addEventListener('change', filterTable);
</script>
</body>
</html>
