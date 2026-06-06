<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('dosen');
$user = getCurrentUser();
$db   = getDB();

// Dosen profile
$stmt = $db->prepare("SELECT * FROM dosen_profiles WHERE user_id = ?");
$stmt->execute([$user['id']]);
$dosenProfile = $stmt->fetch() ?: ['nidn' => '-', 'prodi' => 'Informatika'];

// Config thresholds
$cfg = [];
try {
    $cfgRows = $db->query("SELECT config_key, config_val FROM system_config")->fetchAll();
    foreach ($cfgRows as $r) $cfg[$r['config_key']] = $r['config_val'];
} catch (Exception $e) {}
$gapThreshold = (int)($cfg['at_risk_gap_threshold'] ?? 5);
$ipkThreshold = (float)($cfg['at_risk_ipk_threshold'] ?? 2.5);

// ── Stats Overview ──
$totalMahasiswa = $db->query("SELECT COUNT(*) FROM mahasiswa_profiles")->fetchColumn();
$avgIPK = $db->query("SELECT AVG(ipk) FROM mahasiswa_profiles WHERE ipk > 0")->fetchColumn();
$avgReadiness = 0;

// Avg readiness across all students
try {
    $stmtR = $db->query("
        SELECT AVG(skill_pct) as avg_r FROM (
            SELECT mp.id,
                COALESCE(
                    (SELECT AVG(ss.student_level / s.industry_level * 100)
                     FROM student_skills ss JOIN skills s ON s.id = ss.skill_id
                     WHERE ss.student_id = mp.id AND ss.student_level > 0),
                0) AS skill_pct
            FROM mahasiswa_profiles mp
        ) t
    ");
    $avgReadiness = round((float)$stmtR->fetchColumn());
} catch (Exception $e) {}

// At-risk students
try {
    $stmtAtRisk = $db->prepare("
        SELECT mp.id, u.fullname, mp.nim, mp.semester, mp.ipk, mp.target_career,
            (SELECT COUNT(*)
             FROM student_skills ss JOIN skills s ON s.id = ss.skill_id
             WHERE ss.student_id = mp.id
               AND (s.industry_level - ss.student_level) >= 4
            ) AS high_gap_count
        FROM mahasiswa_profiles mp
        JOIN users u ON u.id = mp.user_id
        HAVING high_gap_count >= ? OR mp.ipk < ?
        ORDER BY high_gap_count DESC, mp.ipk ASC
        LIMIT 20
    ");
    $stmtAtRisk->execute([$gapThreshold, $ipkThreshold]);
    $atRiskList = $stmtAtRisk->fetchAll();
} catch (Exception $e) {
    $atRiskList = [];
}

// Semester distribution
try {
    $semDist = $db->query("SELECT semester, COUNT(*) as cnt FROM mahasiswa_profiles GROUP BY semester ORDER BY semester")->fetchAll();
} catch (Exception $e) {
    $semDist = [];
}

// Career distribution
try {
    $careerDist = $db->query("
        SELECT target_career, COUNT(*) as cnt FROM mahasiswa_profiles
        WHERE target_career IS NOT NULL AND target_career != ''
        GROUP BY target_career ORDER BY cnt DESC LIMIT 8
    ")->fetchAll();
} catch (Exception $e) {
    $careerDist = [];
}

// Recent simulations avg
try {
    $avgProb = $db->query("SELECT AVG(probability_score)*100 FROM simulations")->fetchColumn();
    $avgProb = round((float)$avgProb, 1);
} catch (Exception $e) { $avgProb = 0; }

$activePage = 'dosen_dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen — CALMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .stat-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:20px; }
        .stat-card-num { font-size:32px; font-weight:700; font-family:var(--font-mono); margin-bottom:4px; }
        .stat-card-label { font-size:12px; color:var(--text-muted); }
        .at-risk-table { width:100%; border-collapse:collapse; font-size:13px; }
        .at-risk-table th { font-size:11px; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); padding:8px 12px; text-align:left; border-bottom:1px solid var(--border); }
        .at-risk-table td { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.04); }
        .at-risk-table tr:hover td { background:rgba(255,255,255,.02); }
        .risk-badge { font-size:10px; padding:2px 8px; border-radius:999px; font-weight:700; }
        .risk-gap { background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.25); }
        .risk-ipk { background:rgba(245,158,11,.12); color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
        .risk-both { background:rgba(239,68,68,.18); color:#ef4444; border:1px solid rgba(239,68,68,.3); }
        .career-bar-row { display:flex; align-items:center; gap:10px; margin-bottom:8px; font-size:12px; }
        .career-bar-label { width:160px; flex-shrink:0; color:var(--text-secondary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .career-bar-track { flex:1; height:8px; background:rgba(255,255,255,.06); border-radius:999px; overflow:hidden; }
        .career-bar-fill { height:100%; background:linear-gradient(90deg,#22d3ee,#a78bfa); border-radius:999px; transition:width .8s ease; }
        .career-bar-cnt { font-size:11px; font-family:var(--font-mono); color:var(--text-muted); min-width:24px; text-align:right; }
        .sem-pills { display:flex; flex-wrap:wrap; gap:8px; }
        .sem-pill-item { display:flex; flex-direction:column; align-items:center; padding:10px 16px; background:rgba(34,211,238,.06); border:1px solid rgba(34,211,238,.15); border-radius:var(--radius-sm); }
        .sem-pill-num { font-size:20px; font-weight:700; font-family:var(--font-mono); color:var(--cyan); }
        .sem-pill-label { font-size:10px; color:var(--text-muted); margin-top:2px; }
        .section-title { font-size:14px; font-weight:700; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        @media(max-width:900px){ .stats-grid{grid-template-columns:repeat(2,1fr)} }
    </style>
</head>
<body class="dashboard-body">
<?php include 'includes/sidebar_dosen.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Dashboard Dosen</h1>
                <p class="page-sub">Monitoring progress & skill gap mahasiswa</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">👨‍🏫 <?= htmlspecialchars($dosenProfile['prodi'] ?? 'Informatika') ?></span>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-card-num" style="color:var(--cyan)"><?= $totalMahasiswa ?></div>
            <div class="stat-card-label">Total Mahasiswa</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num" style="color:#10b981"><?= number_format((float)$avgIPK, 2) ?></div>
            <div class="stat-card-label">Rata-rata IPK</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num" style="color:#a78bfa"><?= $avgReadiness ?>%</div>
            <div class="stat-card-label">Avg Skill Readiness</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num" style="color:#ef4444"><?= count($atRiskList) ?></div>
            <div class="stat-card-label">Mahasiswa At-Risk</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px;">
        <!-- Career Distribution -->
        <div class="dash-panel">
            <div class="section-title">
                <svg width="16" height="16" fill="none" stroke="var(--cyan)" stroke-width="2" viewBox="0 0 24 24"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                Distribusi Target Karir
            </div>
            <?php
            $maxCnt = $careerDist ? max(array_column($careerDist,'cnt')) : 1;
            foreach ($careerDist as $cd):
            ?>
            <div class="career-bar-row">
                <span class="career-bar-label" title="<?= htmlspecialchars($cd['target_career']) ?>"><?= htmlspecialchars($cd['target_career']) ?></span>
                <div class="career-bar-track">
                    <div class="career-bar-fill" data-width="<?= round($cd['cnt']/$maxCnt*100) ?>"></div>
                </div>
                <span class="career-bar-cnt"><?= $cd['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
            <?php if (empty($careerDist)): ?>
            <p style="font-size:13px;color:var(--text-muted);text-align:center;padding:20px;">Belum ada data target karir.</p>
            <?php endif; ?>
        </div>

        <!-- Semester Distribution -->
        <div class="dash-panel">
            <div class="section-title">
                <svg width="16" height="16" fill="none" stroke="var(--cyan)" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Distribusi Semester
            </div>
            <div class="sem-pills">
                <?php foreach ($semDist as $sd): ?>
                <div class="sem-pill-item">
                    <div class="sem-pill-num"><?= $sd['cnt'] ?></div>
                    <div class="sem-pill-label">Sem <?= $sd['semester'] ?></div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($semDist)): ?>
                <p style="font-size:13px;color:var(--text-muted);">Belum ada data.</p>
                <?php endif; ?>
            </div>
            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                <div style="font-size:12px;color:var(--text-muted);">Rata-rata Probabilitas Rekrutmen (simulasi)</div>
                <div style="font-size:24px;font-weight:700;font-family:var(--font-mono);color:<?= $avgProb >= 70 ? '#10b981' : ($avgProb >= 40 ? '#f59e0b' : '#ef4444') ?>;margin-top:4px;"><?= $avgProb ?>%</div>
            </div>
        </div>
    </div>

    <!-- At-Risk Mahasiswa -->
    <div class="dash-panel">
        <div class="section-title" style="justify-content:space-between;">
            <span style="display:flex;align-items:center;gap:8px;">
                <svg width="16" height="16" fill="none" stroke="#ef4444" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                Mahasiswa At-Risk
            </span>
            <a href="dosen_notifications.php" style="font-size:12px;color:var(--cyan);">Lihat semua →</a>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin-bottom:14px;">
            Threshold: ≥ <?= $gapThreshold ?> skill gap tinggi ATAU IPK &lt; <?= $ipkThreshold ?>
        </p>

        <?php if (empty($atRiskList)): ?>
        <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:13px;">
            ✅ Tidak ada mahasiswa yang at-risk saat ini.
        </div>
        <?php else: ?>
        <table class="at-risk-table">
            <thead>
                <tr>
                    <th>Nama</th><th>NIM</th><th>Semester</th><th>IPK</th>
                    <th>Gap Tinggi</th><th>Target Karir</th><th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($atRiskList as $ar):
                    $isGapRisk = $ar['high_gap_count'] >= $gapThreshold;
                    $isIPKRisk = (float)$ar['ipk'] < $ipkThreshold && $ar['ipk'] > 0;
                    $badgeCls  = ($isGapRisk && $isIPKRisk) ? 'risk-both' : ($isIPKRisk ? 'risk-ipk' : 'risk-gap');
                    $badgeTxt  = ($isGapRisk && $isIPKRisk) ? 'Gap + IPK' : ($isIPKRisk ? 'IPK Rendah' : 'Banyak Gap');
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($ar['fullname']) ?></strong></td>
                    <td style="font-family:var(--font-mono);font-size:12px;"><?= htmlspecialchars($ar['nim']) ?></td>
                    <td>Sem <?= $ar['semester'] ?></td>
                    <td style="font-family:var(--font-mono);color:<?= (float)$ar['ipk'] < $ipkThreshold ? '#ef4444' : 'var(--text-primary)' ?>"><?= number_format((float)$ar['ipk'],2) ?></td>
                    <td style="color:<?= $ar['high_gap_count'] >= $gapThreshold ? '#ef4444' : 'var(--text-primary)' ?>;font-family:var(--font-mono);"><?= $ar['high_gap_count'] ?></td>
                    <td style="font-size:12px;color:var(--text-secondary);"><?= htmlspecialchars($ar['target_career'] ?? '-') ?></td>
                    <td><span class="risk-badge <?= $badgeCls ?>"><?= $badgeTxt ?></span></td>
                    <td><a href="dosen_mahasiswa.php?nim=<?= urlencode($ar['nim']) ?>" style="font-size:12px;color:var(--cyan);">Detail →</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</main>

<script src="main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.toggle('open'));
document.querySelectorAll('[data-width]').forEach(el => el.style.width = el.dataset.width + '%');
</script>
</body>
</html>
