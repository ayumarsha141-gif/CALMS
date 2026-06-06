<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('dosen');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT * FROM dosen_profiles WHERE user_id = ?");
$stmt->execute([$user['id']]);
$dosenProfile = $stmt->fetch() ?: ['nidn' => '-', 'prodi' => 'Informatika'];

$searchNIM = trim($_GET['nim'] ?? '');
$student   = null;
$skills    = [];
$courses   = [];
$certs     = [];
$projects  = [];
$labRecs   = [];
$lastSim   = null;
$error     = '';

if ($searchNIM !== '') {
    $stmt = $db->prepare("
        SELECT mp.*, u.fullname, u.email
        FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id
        WHERE mp.nim = ?
    ");
    $stmt->execute([$searchNIM]);
    $student = $stmt->fetch();

    if (!$student) {
        $error = "Mahasiswa dengan NIM \"$searchNIM\" tidak ditemukan.";
    } else {
        $sid = $student['id'];

        // Skills with gap
        $skills = $db->prepare("
            SELECT s.skill_name, s.category, s.industry_level,
                   COALESCE(ss.student_level, 0) AS student_level,
                   (s.industry_level - COALESCE(ss.student_level, 0)) AS gap
            FROM skills s
            LEFT JOIN student_skills ss ON ss.skill_id = s.id AND ss.student_id = ?
            ORDER BY gap DESC, s.category
        ");
        $skills->execute([$sid]);
        $skills = $skills->fetchAll();

        // Taken courses
        try {
            $cStmt = $db->prepare("
                SELECT sc.course_name, sc.grade, sc.score, sc.source, c.semester, c.course_code
                FROM student_courses sc
                LEFT JOIN courses c ON c.id = sc.course_id
                WHERE sc.student_id = ?
                ORDER BY c.semester, sc.course_name
            ");
            $cStmt->execute([$sid]);
            $courses = $cStmt->fetchAll();
        } catch (Exception $e) { $courses = []; }

        // Certifications
        $certs = $db->prepare("SELECT * FROM student_certifications WHERE student_id = ? AND status = 'owned' ORDER BY tier, cert_name");
        $certs->execute([$sid]);
        $certs = $certs->fetchAll();

        // Projects
        $projects = $db->prepare("SELECT * FROM student_projects WHERE student_id = ? ORDER BY created_year DESC");
        $projects->execute([$sid]);
        $projects = $projects->fetchAll();

        // Last simulation
        $lastSim = $db->prepare("SELECT * FROM simulations WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
        $lastSim->execute([$sid]);
        $lastSim = $lastSim->fetch();

        // Gap stats
        $gapHigh = count(array_filter($skills, fn($s) => (int)$s['gap'] >= 4));
        $gapMid  = count(array_filter($skills, fn($s) => (int)$s['gap'] >= 2 && (int)$s['gap'] <= 3));
        $gapLow  = count(array_filter($skills, fn($s) => (int)$s['gap'] <= 1 && (int)$s['student_level'] > 0));
    }
}

$activePage = 'dosen_mahasiswa';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Mahasiswa — CALMS Dosen</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .search-bar { display:flex; gap:12px; align-items:center; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px 24px; margin-bottom:28px; }
        .search-input { flex:1; background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-primary); padding:10px 16px; font-size:14px; font-family:var(--font-mono); transition:var(--transition); }
        .search-input:focus { outline:none; border-color:var(--cyan); }
        .search-btn { padding:10px 24px; background:var(--cyan); color:#0a0f1a; border:none; border-radius:var(--radius-sm); font-size:13px; font-weight:700; cursor:pointer; transition:var(--transition); }
        .search-btn:hover { opacity:.85; }
        .student-card { background:linear-gradient(135deg,rgba(34,211,238,.06),rgba(167,139,250,.06)); border:1px solid rgba(34,211,238,.2); border-radius:var(--radius-lg); padding:24px; margin-bottom:24px; display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap; }
        .student-avatar { width:64px; height:64px; border-radius:50%; background:rgba(34,211,238,.12); border:2px solid rgba(34,211,238,.3); color:var(--cyan); font-size:22px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .student-info { flex:1; }
        .student-name { font-size:18px; font-weight:700; margin-bottom:4px; }
        .student-nim { font-size:12px; font-family:var(--font-mono); color:var(--text-muted); }
        .info-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-top:14px; }
        .info-item { background:rgba(255,255,255,.04); border-radius:8px; padding:10px 12px; }
        .info-item-label { font-size:10px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; }
        .info-item-val { font-size:16px; font-weight:700; font-family:var(--font-mono); margin-top:2px; }
        .tab-bar { display:flex; gap:4px; border-bottom:1px solid var(--border); margin-bottom:24px; flex-wrap:wrap; }
        .tab-btn { padding:8px 16px; font-size:13px; font-weight:500; color:var(--text-secondary); background:transparent; border:none; border-bottom:2px solid transparent; cursor:pointer; transition:var(--transition); margin-bottom:-1px; }
        .tab-btn:hover { color:var(--text-primary); }
        .tab-btn.active { color:var(--cyan); border-bottom-color:var(--cyan); }
        .tab-panel { display:none; }
        .tab-panel.active { display:block; }
        .skill-row { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.04); font-size:13px; }
        .skill-row:last-child { border:none; }
        .skill-name-col { width:180px; flex-shrink:0; font-weight:500; }
        .gap-bar { flex:1; height:8px; background:rgba(255,255,255,.06); border-radius:999px; overflow:hidden; }
        .gap-bar-fill { height:100%; border-radius:999px; }
        .gap-tag-sm { font-size:10px; padding:2px 8px; border-radius:999px; font-weight:700; flex-shrink:0; }
        .gap-low { background:rgba(16,185,129,.12); color:#10b981; border:1px solid rgba(16,185,129,.25); }
        .gap-mid { background:rgba(245,158,11,.12); color:#f59e0b; border:1px solid rgba(245,158,11,.25); }
        .gap-high { background:rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.25); }
        .course-tbl { width:100%; border-collapse:collapse; font-size:12px; }
        .course-tbl th { font-size:10px; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); padding:6px 10px; border-bottom:1px solid var(--border); text-align:left; }
        .course-tbl td { padding:8px 10px; border-bottom:1px solid rgba(255,255,255,.03); }
        .grade-pill { font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; }
        .gr-a { background:rgba(16,185,129,.15); color:#10b981; }
        .gr-b { background:rgba(34,211,238,.12); color:var(--cyan); }
        .gr-c { background:rgba(245,158,11,.12); color:#f59e0b; }
        .gr-d { background:rgba(239,68,68,.12); color:#ef4444; }
        .cert-row { display:flex; align-items:center; gap:10px; padding:10px; background:var(--bg-secondary); border:1px solid var(--border); border-radius:8px; margin-bottom:8px; font-size:12px; }
        .tier-badge { font-size:10px; font-weight:700; padding:2px 8px; border-radius:999px; flex-shrink:0; }
        .tier-1 { background:rgba(34,211,238,.15); color:#22d3ee; }
        .tier-2 { background:rgba(96,165,250,.15); color:#60a5fa; }
        .tier-3 { background:rgba(148,163,184,.12); color:#94a3b8; }
        .alert-error { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.25); color:#ef4444; padding:12px 18px; border-radius:var(--radius-sm); margin-bottom:16px; font-size:13px; }
        .sim-result-box { background:rgba(34,211,238,.05); border:1px solid rgba(34,211,238,.2); border-radius:var(--radius-md); padding:20px; display:flex; align-items:center; gap:20px; flex-wrap:wrap; }
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
                <h1 class="page-title">Monitor Mahasiswa</h1>
                <p class="page-sub">Cari mahasiswa berdasarkan NIM untuk melihat detail profil</p>
            </div>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" class="search-bar">
        <svg width="20" height="20" fill="none" stroke="var(--cyan)" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input class="search-input" type="text" name="nim" placeholder="Masukkan NIM mahasiswa (cth: F1D024001)" value="<?= htmlspecialchars($searchNIM) ?>" autofocus>
        <button type="submit" class="search-btn">Cari Mahasiswa</button>
    </form>

    <?php if ($error): ?>
    <div class="alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($student): ?>
    <!-- Student Card -->
    <div class="student-card">
        <div class="student-avatar"><?= strtoupper(substr($student['fullname'], 0, 2)) ?></div>
        <div class="student-info">
            <div class="student-name"><?= htmlspecialchars($student['fullname']) ?></div>
            <div class="student-nim"><?= htmlspecialchars($student['nim']) ?> · <?= htmlspecialchars($student['email']) ?></div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-item-label">Semester</div>
                    <div class="info-item-val" style="color:var(--cyan)"><?= $student['semester'] ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">IPK</div>
                    <div class="info-item-val" style="color:<?= (float)$student['ipk'] < 2.5 ? '#ef4444' : '#10b981' ?>"><?= number_format((float)$student['ipk'],2) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Target Karir</div>
                    <div class="info-item-val" style="font-size:12px;color:#a78bfa"><?= htmlspecialchars($student['target_career'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-item-label">Gap Tinggi</div>
                    <div class="info-item-val" style="color:<?= $gapHigh >= 5 ? '#ef4444' : 'var(--text-primary)' ?>"><?= $gapHigh ?? 0 ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Last Simulation Result -->
    <?php if ($lastSim): ?>
    <div class="sim-result-box" style="margin-bottom:24px;">
        <div>
            <div style="font-size:11px;color:var(--text-muted);margin-bottom:4px;">SIMULASI TERAKHIR</div>
            <div style="font-size:32px;font-weight:700;font-family:var(--font-mono);color:<?= $lastSim['probability_score'] >= .7 ? '#10b981' : ($lastSim['probability_score'] >= .4 ? '#f59e0b' : '#ef4444') ?>"><?= round($lastSim['probability_score']*100,1) ?>%</div>
        </div>
        <div style="flex:1;font-size:13px;color:var(--text-secondary);">
            Target: <strong><?= htmlspecialchars($lastSim['target_role'] ?? '-') ?></strong><br>
            Tanggal: <?= date('d M Y H:i', strtotime($lastSim['created_at'])) ?><br>
            IPK <?= round($lastSim['ipk_score'],0) ?>% · Skill <?= round($lastSim['skill_score'],0) ?>% · Sertif <?= round($lastSim['cert_score'],0) ?>% · Porto <?= round($lastSim['portfolio_score'],0) ?>%
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="dash-panel">
        <div class="tab-bar">
            <button class="tab-btn active" data-tab="sgap">Skill Gap (<?= count($skills) ?>)</button>
            <button class="tab-btn" data-tab="courses">Matkul (<?= count($courses) ?>)</button>
            <button class="tab-btn" data-tab="certs">Sertifikasi (<?= count($certs) ?>)</button>
            <button class="tab-btn" data-tab="projects">Portofolio (<?= count($projects) ?>)</button>
        </div>

        <!-- Skill Gap -->
        <div class="tab-panel active" id="tab-sgap">
            <div style="display:flex;gap:14px;margin-bottom:16px;font-size:12px;">
                <span style="color:#10b981;">● <?= $gapLow ?> Rendah</span>
                <span style="color:#f59e0b;">● <?= $gapMid ?> Sedang</span>
                <span style="color:#ef4444;">● <?= $gapHigh ?> Tinggi</span>
            </div>
            <?php foreach ($skills as $sk):
                $gap = (int)$sk['gap'];
                $lvl = (int)$sk['student_level'];
                $pct = $sk['industry_level'] > 0 ? round($lvl / $sk['industry_level'] * 100) : 0;
                $barColor = $gap <= 1 ? '#10b981' : ($gap <= 3 ? '#f59e0b' : '#ef4444');
                $gClass = $gap <= 1 ? 'gap-low' : ($gap <= 3 ? 'gap-mid' : 'gap-high');
                $gLabel = $gap <= 1 ? 'Rendah' : ($gap <= 3 ? 'Sedang' : 'Tinggi');
            ?>
            <div class="skill-row">
                <span class="skill-name-col"><?= htmlspecialchars($sk['skill_name']) ?></span>
                <div class="gap-bar"><div class="gap-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>"></div></div>
                <span style="font-size:12px;font-family:var(--font-mono);min-width:40px;text-align:right;"><?= $lvl ?>/<?= $sk['industry_level'] ?></span>
                <span class="gap-tag-sm <?= $gClass ?>">Gap: <?= $gLabel ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Courses -->
        <div class="tab-panel" id="tab-courses">
            <?php if (empty($courses)): ?>
            <p style="font-size:13px;color:var(--text-muted);">Belum ada data matkul. Mahasiswa belum upload transkrip.</p>
            <?php else: ?>
            <table class="course-tbl">
                <thead><tr><th>Kode</th><th>Nama Matkul</th><th>Semester</th><th>Nilai</th><th>Skor</th><th>Sumber</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $c):
                    $gradeUp = strtoupper($c['grade'] ?? '');
                    $grCls = str_starts_with($gradeUp,'A') ? 'gr-a' : (str_starts_with($gradeUp,'B') ? 'gr-b' : (str_starts_with($gradeUp,'C') ? 'gr-c' : 'gr-d'));
                ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['course_code'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($c['course_name']) ?></td>
                    <td><?= $c['semester'] ? 'Sem '.$c['semester'] : '-' ?></td>
                    <td><span class="grade-pill <?= $grCls ?>"><?= htmlspecialchars($c['grade'] ?? '-') ?></span></td>
                    <td style="font-family:var(--font-mono);font-size:12px;"><?= $c['score'] ?>/10</td>
                    <td style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($c['source']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Certifications -->
        <div class="tab-panel" id="tab-certs">
            <?php if (empty($certs)): ?>
            <p style="font-size:13px;color:var(--text-muted);">Belum ada sertifikasi yang tercatat.</p>
            <?php else: ?>
            <?php foreach ($certs as $cert): ?>
            <div class="cert-row">
                <span class="tier-badge tier-<?= $cert['tier'] ?>">Tier <?= $cert['tier'] ?></span>
                <strong><?= htmlspecialchars($cert['cert_name']) ?></strong>
                <span style="color:var(--text-muted)">— <?= htmlspecialchars($cert['provider'] ?? '-') ?></span>
                <span style="margin-left:auto;font-size:11px;color:var(--text-muted);"><?= $cert['obtained_date'] ? date('M Y', strtotime($cert['obtained_date'])) : '' ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Projects -->
        <div class="tab-panel" id="tab-projects">
            <?php if (empty($projects)): ?>
            <p style="font-size:13px;color:var(--text-muted);">Belum ada proyek yang tercatat.</p>
            <?php else: ?>
            <?php foreach ($projects as $proj): ?>
            <div class="cert-row" style="flex-direction:column;align-items:flex-start;gap:6px;">
                <div style="display:flex;align-items:center;gap:10px;width:100%;">
                    <span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:999px;background:<?= $proj['scale']==='besar'?'rgba(251,191,36,.15)':'rgba(148,163,184,.12)' ?>;color:<?= $proj['scale']==='besar'?'#fbbf24':'#94a3b8' ?>;"><?= $proj['scale'] === 'besar' ? 'Besar' : 'Kecil' ?></span>
                    <strong><?= htmlspecialchars($proj['project_name']) ?></strong>
                    <span style="margin-left:auto;font-size:11px;color:var(--text-muted);"><?= $proj['created_year'] ?></span>
                </div>
                <?php if ($proj['tech_stack']): ?>
                <div style="font-size:11px;color:var(--text-muted);">Stack: <?= htmlspecialchars($proj['tech_stack']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.toggle('open'));
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('tab-' + btn.dataset.tab)?.classList.add('active');
    });
});
</script>
</body>
</html>
