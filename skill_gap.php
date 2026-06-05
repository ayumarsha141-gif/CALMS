<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

// Profile
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];

// All skills
$stmt = $db->prepare("
    SELECT s.id, s.skill_name, s.category, s.industry_level,
           COALESCE(ss.student_level, 0) AS student_level,
           (s.industry_level - COALESCE(ss.student_level, 0)) AS gap
    FROM skills s
    LEFT JOIN student_skills ss ON ss.skill_id = s.id
        AND ss.student_id = ?
    ORDER BY s.category, gap DESC
");
$stmt->execute([$studentId]);
$allSkills = $stmt->fetchAll();

$categories = [];
foreach ($allSkills as $sk) {
    $categories[$sk['category']][] = $sk;
}

// ── Courses per skill (with student's taken status) ──
$coursesBySkill = [];
$allCoursesForJS = [];  // Full list for JS PDF matching
try {
    $stmt = $db->prepare("
        SELECT c.id AS course_id, c.course_code, c.course_name, c.course_name_id,
               c.semester, c.credits, csm.skill_id,
               sc.grade, sc.score AS course_score, sc.source
        FROM courses c
        JOIN course_skill_mapping csm ON csm.course_id = c.id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        ORDER BY csm.skill_id, c.semester, c.course_name
    ");
    $stmt->execute([$studentId]);
    foreach ($stmt->fetchAll() as $row) {
        $coursesBySkill[$row['skill_id']][] = $row;
    }

    // Full course list for JS matching
    $stmt2 = $db->query("SELECT id, course_name, course_name_id, course_code, semester FROM courses ORDER BY semester");
    $allCoursesForJS = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables not yet created — run calms_db_update.sql first
    $coursesBySkill  = [];
    $allCoursesForJS = [];
}

// Handle save skill levels (form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills'])) {
    $levels = $_POST['levels'] ?? [];
    foreach ($levels as $skillId => $level) {
        $level = max(0, min(10, (int)$level));
        $stmt  = $db->prepare("INSERT INTO student_skills (student_id, skill_id, student_level) VALUES (?,?,?) ON DUPLICATE KEY UPDATE student_level = ?");
        $stmt->execute([$studentId, $skillId, $level, $level]);
    }
    // Also save IPK if posted
    if (isset($_POST['ipk_value']) && $_POST['ipk_value'] !== '') {
        $newIpk = round(max(0, min(4.0, (float)$_POST['ipk_value'])), 2);
        $db->prepare("UPDATE mahasiswa_profiles SET ipk = ? WHERE id = ?")->execute([$newIpk, $studentId]);
    }
    header('Location: skill_gap.php?saved=1');
    exit;
}

// Stats
$gapHigh = $gapMid = $gapLow = 0;
$totalReadiness = 0; $tracked = 0;
foreach ($allSkills as $sk) {
    if ($sk['student_level'] > 0) {
        $tracked++;
        $totalReadiness += ($sk['student_level'] / $sk['industry_level']) * 100;
        if ($sk['gap'] <= 1) $gapLow++;
        elseif ($sk['gap'] <= 3) $gapMid++;
        else $gapHigh++;
    }
}
$avgReadiness = $tracked > 0 ? round($totalReadiness / $tracked) : 0;

// Grade → colour helper
function gradeClass(string $g): string {
    if (in_array($g, ['A','A+'])) return 'gr-a';
    if ($g === 'A-') return 'gr-a';
    if (in_array($g, ['B+','B'])) return 'gr-b';
    if ($g === 'B-') return 'gr-b';
    if (in_array($g, ['C+','C'])) return 'gr-c';
    return 'gr-d';
}

$activePage = 'skill_gap';
$coursesJson = json_encode($allCoursesForJS, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Gap Analysis — CALMS</title>
    <meta name="description" content="Analisis gap kemampuanmu vs standar industri. Upload transkrip untuk auto-fill.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* Stats */
        .sg-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px}
        .sg-stat{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;text-align:center}
        .sg-stat-num{font-size:36px;font-weight:700;font-family:var(--font-mono)}
        .sg-stat-label{font-size:12px;color:var(--text-muted);margin-top:4px}

        /* ── Upload card ── */
        .tc-card{background:linear-gradient(135deg,rgba(34,211,238,.06),rgba(167,139,250,.06));border:1px solid rgba(34,211,238,.25);border-radius:var(--radius-lg);padding:24px 28px;margin-bottom:28px;position:relative;overflow:hidden}
        .tc-card::before{content:'';position:absolute;top:-40px;right:-40px;width:160px;height:160px;background:radial-gradient(circle,rgba(34,211,238,.12),transparent 70%);pointer-events:none}
        .tc-hd{display:flex;align-items:center;gap:14px;margin-bottom:18px}
        .tc-icon{width:48px;height:48px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.25);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;color:var(--cyan);flex-shrink:0}
        .tc-title{font-size:16px;font-weight:700}
        .tc-sub{font-size:12px;color:var(--text-muted);margin-top:2px}
        .tc-body{display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap}

        /* IPK banner (shown after parse) */
        .ipk-banner{display:none;margin-top:16px;background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.25);border-radius:var(--radius-md);padding:14px 20px;align-items:center;gap:16px;flex-wrap:wrap}
        .ipk-banner.show{display:flex}
        .ipk-label-txt{font-size:12px;color:var(--text-muted);font-weight:500}
        .ipk-val-input{width:90px;height:44px;font-size:20px;font-weight:700;text-align:center;background:#0a0f1a;border:2px solid rgba(34,211,238,.35);border-radius:10px;color:var(--cyan);font-family:var(--font-mono)}
        .ipk-val-input:focus{outline:none;border-color:var(--cyan)}
        .ipk-sim-hint{font-size:11px;color:var(--text-muted);line-height:1.5}
        .ipk-sim-hint a{color:var(--cyan)}

        /* Upload zone */
        .upload-zone{flex:1;min-width:220px;border:2px dashed rgba(34,211,238,.35);border-radius:var(--radius-md);padding:20px;text-align:center;cursor:pointer;transition:.25s;background:rgba(34,211,238,.03);position:relative}
        .upload-zone:hover,.upload-zone.drag-over{border-color:var(--cyan);background:rgba(34,211,238,.07)}
        .upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
        .upload-fn{font-size:12px;color:var(--cyan);margin-top:8px;font-family:var(--font-mono);display:none}
        .parse-btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;background:var(--cyan);color:#0a0f1a;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;transition:var(--transition);white-space:nowrap}
        .parse-btn:hover{opacity:.85;transform:translateY(-1px)}
        .parse-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

        /* Progress */
        .pp-wrap{display:none;margin-top:16px;background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px 20px}
        .pp-wrap.show{display:block}
        .pp-hd{display:flex;align-items:center;gap:10px;margin-bottom:10px;font-size:13px;font-weight:600}
        .pp-track{height:6px;background:rgba(255,255,255,.07);border-radius:999px;overflow:hidden;margin-bottom:10px}
        .pp-bar{height:100%;background:linear-gradient(90deg,var(--cyan),#a78bfa);border-radius:999px;width:0;transition:width .4s ease}
        .pp-log{font-size:11px;color:var(--text-muted);font-family:var(--font-mono);max-height:72px;overflow-y:auto}
        .log-ok{color:#10b981}.log-warn{color:#f59e0b}

        /* Preview table */
        .pv-wrap{display:none;margin-top:14px;background:rgba(16,185,129,.05);border:1px solid rgba(16,185,129,.2);border-radius:var(--radius-md);padding:16px 20px}
        .pv-wrap.show{display:block}
        .pv-title{font-size:13px;font-weight:700;color:#10b981;margin-bottom:12px;display:flex;align-items:center;gap:8px}
        .pv-tbl{width:100%;border-collapse:collapse;font-size:12px}
        .pv-tbl th{text-align:left;color:var(--text-muted);padding:4px 8px;border-bottom:1px solid var(--border);font-weight:500}
        .pv-tbl td{padding:5px 8px;border-bottom:1px solid rgba(255,255,255,.04);color:var(--text-secondary)}
        .gr-a{color:#10b981;font-weight:700}.gr-b{color:var(--cyan);font-weight:700}.gr-c{color:#f59e0b;font-weight:700}.gr-d{color:#ef4444;font-weight:700}
        .apply-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;margin-top:12px;background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;border-radius:999px;font-size:12px;font-weight:700;cursor:pointer;transition:var(--transition);font-family:var(--font-sans)}
        .apply-btn:hover{background:rgba(16,185,129,.22)}

        /* Skill cards */
        .category-section{margin-bottom:28px}
        .category-header{display:flex;align-items:center;gap:10px;margin-bottom:14px}
        .category-title{font-size:13px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:1px}
        .category-badge{font-size:11px;padding:2px 8px;border-radius:999px;background:rgba(34,211,238,.1);color:var(--cyan)}
        .skill-card{background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 20px;margin-bottom:10px;transition:var(--transition);position:relative}
        .skill-card:hover{border-color:var(--border-hover)}
        .skill-card.auto-filled{border-color:rgba(34,211,238,.4);animation:pulse 1s ease}
        @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(34,211,238,.4)}70%{box-shadow:0 0 0 8px rgba(34,211,238,0)}100%{box-shadow:0 0 0 0 rgba(34,211,238,0)}}
        .auto-badge{position:absolute;top:10px;right:10px;font-size:9px;padding:2px 8px;border-radius:999px;background:rgba(34,211,238,.15);border:1px solid rgba(34,211,238,.3);color:var(--cyan);font-weight:700;display:none}
        .skill-card.auto-filled .auto-badge{display:inline-block}
        .skill-hd{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-right:60px}
        .skill-name{font-size:14px;font-weight:600}
        .skill-meta{display:flex;align-items:center;gap:10px}
        .skill-score-txt{font-family:var(--font-mono);font-size:13px;color:var(--text-muted)}
        .skill-bars{position:relative;height:22px}
        .bar-bg{position:absolute;top:2px;left:0;width:100%;height:9px;background:#1e293b;border-radius:999px}
        .bar-fill{position:absolute;bottom:0;left:0;height:9px;border-radius:999px;transition:width .8s ease}
        .skill-slider-row{display:flex;align-items:center;gap:8px;margin-top:10px}
        .skill-slider-row label{font-size:11px;color:var(--text-muted);flex-shrink:0}
        .skill-range{flex:1;accent-color:var(--cyan);cursor:pointer}
        .skill-val{font-size:12px;font-family:var(--font-mono);color:var(--cyan);min-width:18px;text-align:center}

        /* ── Course section under each skill card ── */
        .course-section{margin-top:12px;border-top:1px solid rgba(255,255,255,.05);padding-top:10px}
        .course-toggle{display:flex;align-items:center;gap:8px;width:100%;background:none;border:none;cursor:pointer;padding:4px 0;text-align:left;font-size:12px;color:var(--text-muted);font-family:var(--font-sans);transition:.2s}
        .course-toggle:hover{color:var(--text-secondary)}
        .course-toggle svg{transition:transform .25s;flex-shrink:0}
        .course-toggle.open svg{transform:rotate(180deg)}
        .course-list{display:none;flex-direction:column;gap:6px;margin-top:10px}
        .course-list.open{display:flex}
        .course-item{display:flex;align-items:center;gap:8px;padding:7px 10px;border-radius:8px;font-size:12px;transition:.15s}
        .course-item.taken{background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.15);color:var(--text-secondary)}
        .course-item.available{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);color:var(--text-muted)}
        .sem-pill{font-size:10px;padding:2px 7px;border-radius:999px;background:rgba(255,255,255,.07);color:var(--text-muted);white-space:nowrap;flex-shrink:0}
        .course-name-txt{flex:1}
        .grade-pill{font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-left:auto;flex-shrink:0}
        .grade-pill.gr-a{background:rgba(16,185,129,.15);color:#10b981}
        .grade-pill.gr-b{background:rgba(34,211,238,.12);color:var(--cyan)}
        .grade-pill.gr-c{background:rgba(245,158,11,.12);color:#f59e0b}
        .grade-pill.gr-d{background:rgba(239,68,68,.12);color:#ef4444}
        .not-yet-txt{font-size:10px;color:var(--text-muted);margin-left:auto;opacity:.6}

        /* Legend + bottom bar */
        .legend-bar{display:flex;gap:20px;margin-bottom:20px}
        .legend-item{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text-muted)}
        .legend-dot{width:24px;height:8px;border-radius:4px}
        .save-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:var(--cyan);color:#0a0f1a;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;transition:var(--transition)}
        .save-btn:hover{opacity:.85;transform:translateY(-1px)}
        .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#10b981;padding:12px 18px;border-radius:var(--radius-sm);margin-bottom:20px;font-size:13px}
        .alert-info{background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25);color:var(--cyan);padding:12px 18px;border-radius:var(--radius-sm);margin-bottom:20px;font-size:13px}
        .spinner{width:15px;height:15px;border:2px solid rgba(10,15,26,.3);border-top-color:#0a0f1a;border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
        @keyframes spin{to{transform:rotate(360deg)}}
        @media(max-width:900px){.sg-stats{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:640px){.tc-body{flex-direction:column}}
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
                <h1 class="page-title">Skill Gap Analysis</h1>
                <p class="page-sub">Perbandingan kemampuanmu vs standar industri</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">Semester <?= $profile['semester'] ?? '-' ?></span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Skill level berhasil disimpan! IPK tersambung ke Simulasi Rekrutmen.</div>
    <?php endif; ?>

    <!-- ══ Upload Transkrip ══ -->
    <div class="tc-card">
        <div class="tc-hd">
            <div class="tc-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div>
                <div class="tc-title">🎓 Upload Transkrip Nilai (PDF)</div>
                <div class="tc-sub">Sistem otomatis baca nilai matkul → isi skill level & IPK. Kamu tetap bisa edit setelahnya.</div>
            </div>
        </div>
        <div class="tc-body">
            <div class="upload-zone" id="dropZone">
                <input type="file" id="pdfInput" accept="application/pdf">
                <svg width="30" height="30" fill="none" stroke="var(--cyan)" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div style="font-size:13px;font-weight:600">Klik atau seret PDF transkrip</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:4px">PDF dari SIAKAD · Maks 10MB</div>
                <div class="upload-fn" id="uploadFn"></div>
            </div>
            <button class="parse-btn" id="parseBtn" disabled>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Baca Transkrip
            </button>
        </div>

        <!-- IPK Banner -->
        <div class="ipk-banner" id="ipkBanner">
            <div>
                <div class="ipk-label-txt">📊 IPK Terdeteksi dari Transkrip</div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px">Otomatis terisi ke Simulasi Rekrutmen</div>
            </div>
            <input type="number" class="ipk-val-input" id="ipkDisplay" step="0.01" min="0" max="4" placeholder="0.00">
            <div class="ipk-sim-hint">
                Nilai IPK ini tersambung ke<br>
                <a href="simulation.php">🎲 Simulasi Rekrutmen</a>.<br>
                Klik <strong>Simpan</strong> di bawah untuk menyimpan.
            </div>
        </div>

        <!-- Progress -->
        <div class="pp-wrap" id="ppWrap">
            <div class="pp-hd">
                <span class="spinner" id="ppSpinner"></span>
                <span id="ppStatus">Membaca PDF...</span>
            </div>
            <div class="pp-track"><div class="pp-bar" id="ppBar"></div></div>
            <div class="pp-log" id="ppLog"></div>
        </div>

        <!-- Results preview -->
        <div class="pv-wrap" id="pvWrap">
            <div class="pv-title">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Hasil Pembacaan — <span id="pvCount">0</span> skill terdeteksi
            </div>
            <table class="pv-tbl">
                <thead><tr><th>Mata Kuliah (Terdeteksi)</th><th>Nilai</th><th>Skill</th><th>Skor</th></tr></thead>
                <tbody id="pvBody"></tbody>
            </table>
            <button class="apply-btn" id="applyBtn">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Terapkan ke Skill + Simpan ke DB
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="sg-stats">
        <div class="sg-stat"><div class="sg-stat-num" style="color:var(--cyan)"><?= $avgReadiness ?>%</div><div class="sg-stat-label">Avg Readiness</div></div>
        <div class="sg-stat"><div class="sg-stat-num" style="color:#10b981"><?= $gapLow ?></div><div class="sg-stat-label">Gap Rendah ≤1</div></div>
        <div class="sg-stat"><div class="sg-stat-num" style="color:#f59e0b"><?= $gapMid ?></div><div class="sg-stat-label">Gap Sedang 2-3</div></div>
        <div class="sg-stat"><div class="sg-stat-num" style="color:#ef4444"><?= $gapHigh ?></div><div class="sg-stat-label">Gap Tinggi ≥4</div></div>
    </div>

    <div class="legend-bar">
        <div class="legend-item"><div class="legend-dot" style="background:#1e293b"></div>Standar Industri</div>
        <div class="legend-item"><div class="legend-dot" style="background:var(--cyan)"></div>Level Kamu</div>
        <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div>✓ Matkul Sudah Diambil</div>
        <div class="legend-item"><div class="legend-dot" style="background:rgba(255,255,255,.08)"></div>○ Belum Diambil</div>
    </div>

    <form method="POST" id="skillForm">
        <!-- Hidden IPK -->
        <input type="hidden" name="ipk_value" id="ipkHidden" value="<?= htmlspecialchars($profile['ipk'] ?? '') ?>">

    <?php foreach ($categories as $cat => $skills): ?>
    <div class="category-section">
        <div class="category-header">
            <span class="category-title"><?= htmlspecialchars($cat) ?></span>
            <span class="category-badge"><?= count($skills) ?> skills</span>
        </div>

        <?php foreach ($skills as $sk):
            $pct      = round(($sk['student_level'] / 10) * 100);
            $gap      = (int)$sk['gap'];
            $gLabel   = $gap <= 1 ? 'Rendah' : ($gap <= 3 ? 'Sedang' : 'Tinggi');
            $gClass   = $gap <= 1 ? 'gap-low' : ($gap <= 3 ? 'gap-mid' : 'gap-high');
            $barColor = $gap <= 1 ? '#10b981' : ($gap <= 3 ? '#f59e0b' : '#ef4444');

            $relCourses = $coursesBySkill[$sk['id']] ?? [];
            $takenCount = count(array_filter($relCourses, fn($c) => $c['grade'] !== null));
        ?>
        <div class="skill-card" id="scard-<?= $sk['id'] ?>">
            <span class="auto-badge">✦ AUTO</span>
            <div class="skill-hd">
                <span class="skill-name"><?= htmlspecialchars($sk['skill_name']) ?></span>
                <div class="skill-meta">
                    <span class="gap-tag <?= $gClass ?>" id="gtag-<?= $sk['id'] ?>">Gap: <?= $gLabel ?></span>
                    <span class="skill-score-txt" id="score-<?= $sk['id'] ?>"><?= $sk['student_level'] ?>/<?= $sk['industry_level'] ?></span>
                </div>
            </div>
            <div class="skill-bars">
                <div class="bar-bg"></div>
                <div class="bar-fill" id="fill-<?= $sk['id'] ?>" data-width="<?= $pct ?>" style="background:<?= $barColor ?>"></div>
            </div>
            <div class="skill-slider-row">
                <label for="rng-<?= $sk['id'] ?>">Level kamu:</label>
                <input class="skill-range" type="range" id="rng-<?= $sk['id'] ?>" name="levels[<?= $sk['id'] ?>]"
                    min="0" max="10" value="<?= $sk['student_level'] ?>"
                    data-industry="<?= $sk['industry_level'] ?>"
                    oninput="updateSkill(<?= $sk['id'] ?>,<?= $sk['industry_level'] ?>,this.value)">
                <span class="skill-val" id="val-<?= $sk['id'] ?>"><?= $sk['student_level'] ?></span>
            </div>

            <?php if (!empty($relCourses)): ?>
            <!-- ── Matkul Terkait ── -->
            <div class="course-section">
                <button type="button" class="course-toggle" id="ctoggle-<?= $sk['id'] ?>" onclick="toggleCourses(<?= $sk['id'] ?>)">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    📚 <?= count($relCourses) ?> Matkul Terkait
                    <?php if ($takenCount > 0): ?>
                    &nbsp;·&nbsp;<span style="color:#10b981"><?= $takenCount ?> sudah diambil</span>
                    <?php else: ?>
                    &nbsp;·&nbsp;<span style="color:var(--text-muted)">Belum ada dari transkrip</span>
                    <?php endif; ?>
                </button>
                <div class="course-list" id="clist-<?= $sk['id'] ?>">
                    <?php
                    // Show taken first, then not-taken
                    $taken    = array_filter($relCourses, fn($c) => $c['grade'] !== null);
                    $notTaken = array_filter($relCourses, fn($c) => $c['grade'] === null);
                    foreach (array_merge(array_values($taken), array_values($notTaken)) as $c):
                        $isTaken = $c['grade'] !== null;
                        $gc      = $isTaken ? gradeClass($c['grade']) : '';
                    ?>
                    <div class="course-item <?= $isTaken ? 'taken' : 'available' ?>">
                        <span class="sem-pill">Sem <?= $c['semester'] ?></span>
                        <span class="course-name-txt"><?= htmlspecialchars($c['course_name']) ?></span>
                        <?php if ($isTaken): ?>
                        <span class="grade-pill <?= $gc ?>"><?= htmlspecialchars($c['grade']) ?></span>
                        <?php else: ?>
                        <span class="not-yet-txt">Belum diambil</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:8px;padding-bottom:32px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <button type="submit" name="save_skills" class="save-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Simpan Semua Skill + IPK
        </button>
        <span style="font-size:12px;color:var(--text-muted)">IPK tersimpan juga ke Simulasi Rekrutmen</span>
    </div>
    </form>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// All courses from DB (for matching)
const DB_COURSES = <?= $coursesJson ?>;

// Grade → score (out of 10)
function gradeToScore(g) {
    const m = {'A':10,'A+':10,'A-':9,'B+':8,'B':7,'B-':6,'C+':5,'C':4,'C-':3,'D':2,'E':0,'K':0};
    return m[g.trim().toUpperCase()] ?? null;
}

// Normalize for fuzzy match
function norm(s){ return (s||'').toLowerCase().replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim(); }

// Match a text line to a DB course
function matchDBCourse(line) {
    const nl = norm(line);
    if (nl.length < 4) return null;
    let best = null, bestScore = 0;
    for (const c of DB_COURSES) {
        const names = [norm(c.course_name), norm(c.course_name_id||'')];
        for (const name of names) {
            if (!name) continue;
            const words = name.split(' ').filter(w => w.length > 3);
            let hits = 0;
            for (const w of words) { if (nl.includes(w)) hits++; }
            const score = words.length > 0 ? hits / words.length : 0;
            if (score > bestScore && score >= 0.5) {
                bestScore = score;
                best = c;
            }
        }
    }
    return best;
}

// Course → skill mapping (client-side, mirrors DB)
const COURSE_SKILL_MAP = {
    'BIE105':1,'BIE106':1,'BIE205':1,'BIE203':1,'BIE204':1,'BIE206':1,
    'BIE301':1,'BIE304':1,'BIE404':1,'BIE407':1,'BIE502':1,'BIE506':1,
    'BIE507':1,'BIE508':1,'BIE601':1,'BIE602':1,'BIE603':1,
    // JS
    'BIE405':2,'BIE604':2,'BIE702':2,
    // PHP
    'BIE302':3,'BIE405b':3,
    // Java
    'BIE301j':4,'BIE402':4,'BIE503':4,
    // SQL
    'BIE306':7,'BIE508s':7,
    // MySQL
    'BIE306m':8,
    // React
    'BIE702r':11,
    // HTML/CSS
    'BIE405h':13,'BIE604h':13,'BIE702h':13,
    // Node.js
    'BIE601n':14,'BIE702n':14,
    // Laravel
    'BIE703':15,
    // Docker/K8s
    'BIE606':17,
    // Linux
    'BIE303':20,'BIE307':20,'BIE601l':20,
    // ML
    'BIE204m':21,'BIE206m':21,'BIE404m':21,'BIE502m':21,'BIE602m':21,'BIE704m':21,
    // DL + TF
    'BIE704':22,'BIE704t':23,
    // Flutter
    'BIE607':27,
    // Figma
    'BIE305':29,
    // Cyber
    'BIE307c':30,'BIE504':30,
};

// Simpler: course_code → [skill_ids] mapping
const CODE_SKILLS = {
    'BIE105':[1],'BIE106':[1],'BIE203':[1],'BIE204':[1,21],'BIE205':[1,4],'BIE206':[1,21],
    'BIE301':[1,4],'BIE302':[3,7],'BIE303':[20],'BIE304':[1],'BIE305':[29],
    'BIE306':[7,8],'BIE307':[20,30],
    'BIE402':[4],'BIE403':[19],'BIE404':[1,21],'BIE405':[2,3,13],'BIE407':[1],
    'BIE502':[1,21],'BIE503':[4],'BIE504':[30],'BIE506':[1],'BIE507':[1],'BIE508':[1,7,10],
    'BIE601':[1,14,20],'BIE602':[1,21],'BIE603':[1],'BIE604':[2,13],'BIE606':[17,18,24],'BIE607':[27],
    'BIE702':[2,3,11,13,14],'BIE703':[15,19],'BIE704':[21,22,23],
};

function extractGrade(line) {
    const m = line.match(/\b(A[+\-]?|B[+\-]?|C[+\-]?|D|E|K)\b/);
    return m ? m[1] : null;
}

function extractIPK(lines) {
    for (const line of lines) {
        const m = line.match(/(?:IPK|IP\.?\s*Kum(?:ulatif)?|GPA|Indeks\s+Prestasi\s+Kumulatif)\s*[:\s=]\s*(\d[,.]?\d{1,2})/i);
        if (m) {
            const v = parseFloat(m[1].replace(',','.'));
            if (v > 0 && v <= 4.0) return v;
        }
    }
    return null;
}

// DOM
const pdfInput  = document.getElementById('pdfInput');
const dropZone  = document.getElementById('dropZone');
const parseBtn  = document.getElementById('parseBtn');
const uploadFn  = document.getElementById('uploadFn');
const ppWrap    = document.getElementById('ppWrap');
const ppBar     = document.getElementById('ppBar');
const ppStatus  = document.getElementById('ppStatus');
const ppLog     = document.getElementById('ppLog');
const ppSpinner = document.getElementById('ppSpinner');
const pvWrap    = document.getElementById('pvWrap');
const pvBody    = document.getElementById('pvBody');
const pvCount   = document.getElementById('pvCount');
const applyBtn  = document.getElementById('applyBtn');
const ipkBanner = document.getElementById('ipkBanner');
const ipkDisplay= document.getElementById('ipkDisplay');
const ipkHidden = document.getElementById('ipkHidden');

let parsedResults = [];
let detectedIPK   = null;

// Drag-drop
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f && f.type === 'application/pdf') onFile(f);
});
pdfInput.addEventListener('change', () => { if (pdfInput.files[0]) onFile(pdfInput.files[0]); });

function onFile(f) {
    uploadFn.textContent = '📄 ' + f.name;
    uploadFn.style.display = 'block';
    parseBtn.disabled = false;
    parseBtn._file = f;
}

function logLine(msg, cls='') {
    const d = document.createElement('div');
    d.className = cls;
    d.textContent = msg;
    ppLog.appendChild(d);
    ppLog.scrollTop = ppLog.scrollHeight;
}

parseBtn.addEventListener('click', async () => {
    const file = parseBtn._file;
    if (!file) return;
    parseBtn.disabled = true;
    ppWrap.classList.add('show');
    pvWrap.classList.remove('show');
    ipkBanner.classList.remove('show');
    ppLog.innerHTML = '';
    ppBar.style.width = '0%';
    ppStatus.textContent = 'Membaca PDF...';
    ppSpinner.style.display = '';
    parsedResults = [];

    try {
        const buf = await file.arrayBuffer();
        ppBar.style.width = '20%';
        logLine('✓ File dimuat');

        const pdf = await pdfjsLib.getDocument({data: buf}).promise;
        logLine(`✓ ${pdf.numPages} halaman ditemukan`, 'log-ok');

        let allLines = [];
        for (let p = 1; p <= pdf.numPages; p++) {
            const page    = await pdf.getPage(p);
            const content = await page.getTextContent();
            allLines = allLines.concat(content.items.map(i => i.str).filter(s => s.trim()));
        }
        ppBar.style.width = '60%';
        logLine(`✓ ${allLines.length} baris teks diekstrak`);

        // Extract IPK
        detectedIPK = extractIPK(allLines);
        if (detectedIPK) {
            logLine(`✓ IPK terdeteksi: ${detectedIPK}`, 'log-ok');
        }

        // Match courses
        const foundBySkill = {};   // skill_id → {course, grade, score}
        const foundCourses = [];   // for saving to DB

        for (let i = 0; i < allLines.length; i++) {
            const line  = allLines[i];
            const dbCourse = matchDBCourse(line);
            if (!dbCourse) continue;

            let grade = extractGrade(line);
            if (!grade) {
                for (let j = i+1; j <= Math.min(i+4, allLines.length-1); j++) {
                    grade = extractGrade(allLines[j]);
                    if (grade) break;
                }
            }
            if (!grade) continue;

            const score    = gradeToScore(grade);
            if (score === null) continue;

            const skillIds = CODE_SKILLS[dbCourse.course_code] || [];
            for (const sid of skillIds) {
                if (!foundBySkill[sid] || score > foundBySkill[sid].score) {
                    foundBySkill[sid] = { course: dbCourse, grade, score, skillId: sid };
                    logLine(`✓ ${dbCourse.course_name} → ${grade} (${score}/10)`, 'log-ok');
                }
            }
            // Record course for DB
            if (!foundCourses.find(c => c.course_id === dbCourse.id)) {
                foundCourses.push({ course_id: dbCourse.id, course_name: dbCourse.course_name, grade, score });
            }
        }

        ppBar.style.width = '100%';
        parsedResults = Object.values(foundBySkill);
        ppStatus.textContent = `Selesai! ${parsedResults.length} skill & ${foundCourses.length} matkul terdeteksi.`;
        ppSpinner.style.display = 'none';

        // Show IPK banner
        if (detectedIPK) {
            ipkDisplay.value = detectedIPK;
            ipkHidden.value  = detectedIPK;
            ipkBanner.classList.add('show');
        }

        // Show preview
        if (parsedResults.length > 0) {
            pvCount.textContent = parsedResults.length;
            pvBody.innerHTML = '';
            parsedResults.forEach(r => {
                const gc = r.grade.startsWith('A') ? 'gr-a' : r.grade.startsWith('B') ? 'gr-b' : r.grade.startsWith('C') ? 'gr-c' : 'gr-d';
                pvBody.innerHTML += `<tr>
                    <td>${esc(r.course.course_name)}</td>
                    <td class="${gc}">${esc(r.grade)}</td>
                    <td>${esc(getSkillName(r.skillId))}</td>
                    <td>${r.score}/10</td>
                </tr>`;
            });
            pvWrap.classList.add('show');

            // Store courses on applyBtn
            applyBtn._courses = foundCourses;
        } else {
            logLine('⚠ Tidak ada matkul terdeteksi. Pastikan PDF berisi teks (bukan scan gambar).', 'log-warn');
        }
    } catch(err) {
        ppStatus.textContent = 'Gagal membaca PDF.';
        ppSpinner.style.display = 'none';
        logLine('✗ ' + err.message, 'log-warn');
    }
    parseBtn.disabled = false;
});

// Get skill name from slider label (DOM)
function getSkillName(skillId) {
    const lbl = document.querySelector(`label[for="rng-${skillId}"]`);
    if (lbl) {
        const card = lbl.closest('.skill-card');
        if (card) return card.querySelector('.skill-name').textContent;
    }
    return 'Skill #' + skillId;
}

// Apply to sliders + save to DB via AJAX
applyBtn.addEventListener('click', async () => {
    let applied = 0;

    // Apply sliders
    parsedResults.forEach(r => {
        const rng = document.getElementById('rng-' + r.skillId);
        if (!rng) return;
        const ind = parseInt(rng.dataset.industry || 10);
        const cur = parseInt(rng.value);
        if (r.score > cur || cur === 0) {
            rng.value = r.score;
            updateSkill(r.skillId, ind, r.score);
            const card = document.getElementById('scard-' + r.skillId);
            if (card) { card.classList.add('auto-filled'); setTimeout(() => card.classList.remove('auto-filled'), 2500); }
            applied++;
        }
    });

    // AJAX save to DB
    const payload = {
        ipk     : detectedIPK || (parseFloat(ipkDisplay.value) || null),
        skills  : parsedResults.map(r => ({skill_id: r.skillId, level: r.score})),
        courses : applyBtn._courses || [],
    };

    try {
        applyBtn.disabled = true;
        applyBtn.textContent = '⏳ Menyimpan ke DB...';
        const res = await fetch('process_transcript.php', {
            method : 'POST',
            headers: {'Content-Type':'application/json'},
            body   : JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.success) {
            applyBtn.textContent = `✓ ${applied} skill ter-update · ${json.courses_saved} matkul tersimpan. Scroll ke bawah & simpan!`;
            applyBtn.style.background = 'rgba(16,185,129,.25)';
            if (json.ipk_saved) {
                ipkHidden.value = detectedIPK;
            }
        } else {
            applyBtn.textContent = '⚠ Gagal simpan: ' + (json.error || 'Unknown error');
        }
    } catch(e) {
        applyBtn.textContent = '⚠ Network error';
    }
    applyBtn.disabled = false;

    setTimeout(() => {
        document.getElementById('skillForm').scrollIntoView({behavior:'smooth', block:'start'});
    }, 600);
});

// IPK display sync
ipkDisplay.addEventListener('input', () => { ipkHidden.value = ipkDisplay.value; });

// ── Sidebar
document.getElementById('sidebarToggle')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.toggle('open'));

// ── Animate bars
document.querySelectorAll('[data-width]').forEach(el =>
    setTimeout(() => el.style.width = el.dataset.width + '%', 100));

// ── Toggle course list
function toggleCourses(id) {
    const list   = document.getElementById('clist-' + id);
    const toggle = document.getElementById('ctoggle-' + id);
    list.classList.toggle('open');
    toggle.classList.toggle('open');
}

// ── Update skill card live
function updateSkill(id, ind, val) {
    val = parseInt(val);
    document.getElementById('val-' + id).textContent = val;
    document.getElementById('score-' + id).textContent = val + '/' + ind;
    const fill = document.getElementById('fill-' + id);
    const gap  = ind - val;
    fill.style.width      = (val / 10 * 100) + '%';
    fill.style.background = gap <= 1 ? '#10b981' : (gap <= 3 ? '#f59e0b' : '#ef4444');
    const gtag = document.getElementById('gtag-' + id);
    if (gtag) {
        gtag.textContent = 'Gap: ' + (gap <= 1 ? 'Rendah' : gap <= 3 ? 'Sedang' : 'Tinggi');
        gtag.className   = 'gap-tag ' + (gap <= 1 ? 'gap-low' : gap <= 3 ? 'gap-mid' : 'gap-high');
    }
}

function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
</script>
</body>
</html>