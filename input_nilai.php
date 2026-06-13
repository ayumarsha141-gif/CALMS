<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("
    SELECT mp.*, u.fullname
    FROM mahasiswa_profiles mp
    JOIN users u ON u.id = mp.user_id
    WHERE mp.user_id = ?
");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];
$currentSemester = (int)($profile['semester'] ?? 1);

$stmt = $db->prepare("SELECT * FROM courses WHERE semester <= ? ORDER BY semester, id");
$stmt->execute([$currentSemester]);
$semesterCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ?");
$stmt->execute([$studentId]);
$studentCourses = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c)
    $studentCourses[$c['course_id']] = $c;

$allCourses = $db->query("
    SELECT id, course_code, course_name, course_name_id, semester
    FROM courses ORDER BY semester, id
")->fetchAll(PDO::FETCH_ASSOC);
$coursesJson = json_encode($allCourses, JSON_UNESCAPED_UNICODE);

$scoreMap = ['A'=>95,'A-'=>90,'B+'=>85,'B'=>80,'B-'=>75,'C+'=>70,'C'=>65,'D'=>50,'E'=>0];
$gradePoint = ['A'=>4.0,'A-'=>3.7,'B+'=>3.3,'B'=>3.0,'B-'=>2.7,'C+'=>2.3,'C'=>2.0,'D'=>1.0,'E'=>0.0];

// ── Handle POST ──
$message = null;
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['save_ipk'])) {
        $ipk = floatval($_POST['manual_ipk']);
        if ($ipk >= 0 && $ipk <= 4) {
            $db->prepare("UPDATE mahasiswa_profiles SET ipk=? WHERE id=?")
               ->execute([$ipk, $studentId]);
            $profile['ipk'] = $ipk;
            $message = "✅ IPK berhasil disimpan.";
        }
    }

    if (isset($_POST['save_courses'])) {
        $saved = 0;
        foreach ($_POST['grades'] ?? [] as $courseId => $grade) {
            if (trim($grade) === '') continue;
            $score = $scoreMap[$grade] ?? 0;
            $db->prepare("
                INSERT INTO student_courses (student_id,course_id,grade,score,source,semester_taken)
                VALUES (?,?,?,?,'manual',(SELECT semester FROM courses WHERE id=?))
                ON DUPLICATE KEY UPDATE grade=VALUES(grade),score=VALUES(score),semester_taken=VALUES(semester_taken)
            ")->execute([$studentId, $courseId, $grade, $score, $courseId]);
            $saved++;
        }
        $message = "✅ $saved nilai berhasil disimpan.";
        // Reload
        $stmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $studentCourses = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) $studentCourses[$c['course_id']] = $c;
    }

    if (isset($_POST['add_extra'])) {
        if (empty($_POST['extra_course_id'])) {
            $message = '⚠️ Pilih mata kuliah dari hasil pencarian.';
            $msgType = 'warning';
        } else {
            $grade = $_POST['extra_grade'] ?? '';
            $score = $scoreMap[$grade] ?? 0;
            $semCol = $db->prepare("SELECT semester FROM courses WHERE id = ?");
            $semCol->execute([$_POST['extra_course_id']]);
            $sem = $semCol->fetchColumn();
            $db->prepare("
                INSERT INTO student_courses (student_id,course_id,grade,score,source,semester_taken)
                VALUES (?,?,?,?,'manual',?)
                ON DUPLICATE KEY UPDATE grade=VALUES(grade),score=VALUES(score),semester_taken=VALUES(semester_taken)
            ")->execute([$studentId, $_POST['extra_course_id'], $grade, $score, $sem]);
            $message = '✅ Mata kuliah berhasil ditambahkan.';
            $stmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ?");
            $stmt->execute([$studentId]);
            $studentCourses = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) $studentCourses[$c['course_id']] = $c;
        }
    }

    if (isset($_POST['delete_course'])) {
        $db->prepare("DELETE FROM student_courses WHERE student_id=? AND course_id=?")
           ->execute([$studentId, $_POST['delete_course']]);
        $message = '🗑️ Nilai dihapus.';
        $msgType = 'warning';
        $stmt = $db->prepare("SELECT * FROM student_courses WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $studentCourses = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) $studentCourses[$c['course_id']] = $c;
    }
}

// ── Riwayat final ──
$stmt = $db->prepare("
    SELECT sc.*, c.course_code, c.course_name, c.semester, c.credits
    FROM student_courses sc
    JOIN courses c ON c.id = sc.course_id
    WHERE sc.student_id = ?
    ORDER BY sc.semester_taken, c.semester, c.id
");
$stmt->execute([$studentId]);
$historyCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Hitung IPK sementara ──
$totalWeight = 0; $totalCredit = 0;
foreach ($historyCourses as $h) {
    $gp  = $gradePoint[strtoupper($h['grade'])] ?? 0;
    $sks = (int)($h['credits'] ?? 3);
    $totalWeight += $gp * $sks;
    $totalCredit += $sks;
}
$calculatedIpk = $totalCredit > 0 ? round($totalWeight / $totalCredit, 2) : 0;
$ipk = ($profile['ipk'] ?? 0) > 0 ? $profile['ipk'] : $calculatedIpk;

// Kelompokkan per semester
$bySemester = [];
foreach ($semesterCourses as $c) $bySemester[$c['semester']][] = $c;

// Matkul ekstra (luar kurikulum aktif)
$semIds = array_column($semesterCourses, 'id');
$extras = array_filter($historyCourses, fn($h) => !in_array($h['course_id'], $semIds));

function gradeClass(string $g): string {
    $g = strtoupper($g);
    if (in_array($g, ['A','A+','A-'])) return 'gr-a';
    if (in_array($g, ['B+','B','B-'])) return 'gr-b';
    if (in_array($g, ['C+','C','C-'])) return 'gr-c';
    return 'gr-d';
}

$activePage = 'input_nilai'; // tetap highlight menu skill_gap
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Input Nilai — CALMS</title>
    <!-- SAMA dengan halaman lain -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ══ IPK Banner ══ */
        .ipk-banner {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            background: linear-gradient(135deg, rgba(34,211,238,.06), rgba(99,102,241,.05));
            border: 1px solid rgba(34,211,238,.22);
            border-radius: 16px;
            padding: 20px 26px;
            margin-bottom: 24px;
        }
        .ipk-big {
            font-size: 42px;
            font-weight: 700;
            font-family: var(--font-mono);
            color: var(--cyan);
            line-height: 1;
        }
        .ipk-label {
            font-size: 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-top: 4px;
        }
        .ipk-divider { width: 1px; height: 48px; background: var(--border); flex-shrink: 0; }
        .ipk-stat { text-align: center; }
        .ipk-stat-num { font-size: 22px; font-weight: 700; font-family: var(--font-mono); }
        .ipk-stat-lbl { font-size: 10px; color: var(--text-muted); margin-top: 3px; }
        .ipk-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ipk-input {
            width: 100px;
            padding: 9px 12px;
            background: rgba(34,211,238,.06);
            border: 1.5px solid rgba(34,211,238,.25);
            border-radius: 10px;
            color: var(--cyan);
            font-size: 16px;
            font-weight: 700;
            font-family: var(--font-mono);
            text-align: center;
            transition: border-color .2s;
        }
        .ipk-input:focus { outline: none; border-color: var(--cyan); }
        .btn-save-ipk {
            padding: 9px 18px;
            background: linear-gradient(135deg, #22d3ee, #3b82f6);
            border: none;
            border-radius: 10px;
            color: #000;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            font-family: var(--font-main);
            transition: all .2s;
        }
        .btn-save-ipk:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(34,211,238,.3); }

        /* ══ Tabs ══ */
        .tab-strip {
            display: flex;
            gap: 3px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 4px;
            width: fit-content;
            margin-bottom: 24px;
        }
        .tab-btn {
            padding: 8px 20px;
            border: none;
            border-radius: 9px;
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            font-family: var(--font-main);
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
        }
        .tab-btn.active { background: rgba(34,211,238,.12); color: #22d3ee; }
        .tab-btn:hover:not(.active) { background: rgba(255,255,255,.04); color: var(--text-secondary); }
        .tab-pane { display: none; animation: fadeUp .2s ease; }
        .tab-pane.active { display: block; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        /* ══ Semester block ══ */
        .sem-block { margin-bottom: 24px; }
        .sem-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .sem-label::after {
            content: '';
            display: block;
            width: 60px;
            height: 1px;
            background: var(--border);
        }

        /* ══ Course row ══ */
        .course-row {
            display: grid;
            grid-template-columns: 90px 1fr 80px;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            margin-bottom: 6px;
            transition: border-color .2s, background .2s;
        }
        .course-row:hover { border-color: rgba(255,255,255,.12); }
        .course-row.has-grade {
            border-color: rgba(34,211,238,.18);
            background: rgba(34,211,238,.025);
        }
        .course-code {
            font-size: 11px;
            font-family: var(--font-mono);
            color: var(--text-muted);
            line-height: 1.3;
        }
        .course-name-txt { font-size: 13px; color: var(--text-primary); line-height: 1.35; }
        .course-sks { font-size: 10px; color: var(--text-muted); margin-top: 2px; }
        .grade-select {
            width: 100%;
            padding: 7px 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 13px;
            font-family: var(--font-mono);
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            transition: border-color .2s;
            appearance: none;
        }
        .grade-select:focus { outline: none; border-color: var(--cyan); }
        .grade-select.gr-a { border-color: rgba(16,185,129,.35); color: #10b981; background: rgba(16,185,129,.05); }
        .grade-select.gr-b { border-color: rgba(34,211,238,.35); color: var(--cyan); background: rgba(34,211,238,.04); }
        .grade-select.gr-c { border-color: rgba(245,158,11,.35); color: #f59e0b; background: rgba(245,158,11,.04); }
        .grade-select.gr-d { border-color: rgba(239,68,68,.35); color: #ef4444; background: rgba(239,68,68,.04); }

        /* ══ Sticky save bar ══ */
        .save-bar {
            position: sticky;
            bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(13,20,36,.9);
            backdrop-filter: blur(14px);
            border: 1px solid var(--border);
            border-radius: 14px;
            margin-top: 20px;
            z-index: 20;
            flex-wrap: wrap;
        }
        .save-bar-hint { font-size: 12px; color: var(--text-muted); }
        .input-nilai-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 24px;
            background: linear-gradient(135deg, #22d3ee, #3b82f6);
            color: #000;
            border: none;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            font-family: var(--font-main);
            cursor: pointer;
            transition: all .2s;
        }
        .input-nilai-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,211,238,.3); }
        .input-nilai-btn-ghost {
            padding: 9px 18px;
            background: transparent;
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-secondary);
            font-size: 12px;
            font-family: var(--font-main);
            cursor: pointer;
            transition: all .2s;
        }
        .input-nilai-btn-ghost:hover { border-color: rgba(255,255,255,.2); color: var(--text-primary); }

        /* ══ Extra course form ══ */
        .extra-form-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 20px;
        }
        .extra-form-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .extra-search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-field {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 13px;
            font-family: var(--font-main);
            transition: border-color .2s;
        }
        .search-input-field:focus { outline: none; border-color: var(--cyan); }
        #extraSearchResults {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            background: #111827;
            border: 1px solid #334155;
            border-radius: 10px;
            max-height: 220px;
            overflow-y: auto;
            z-index: 100;
            margin-top: 4px;
            box-shadow: 0 8px 24px rgba(0,0,0,.4);
        }
        #extraSearchResults .search-item { padding: 9px 14px; cursor: pointer; font-size: 12px; border-bottom: 1px solid rgba(255,255,255,.05); color: var(--text-secondary); transition: background .15s; }
        #extraSearchResults .search-item:hover { background: #1e293b; color: var(--text-primary); }
        .grade-select-sm {
            width: 80px;
            padding: 9px 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 13px;
            font-family: var(--font-mono);
            cursor: pointer;
        }
        .grade-select-sm:focus { outline: none; border-color: var(--cyan); }
        .selected-course-confirm { font-size: 11px; color: var(--cyan); min-height: 16px; margin-top: 4px; }

        /* ══ Extra items list ══ */
        .extra-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .extra-item:last-child { border-bottom: none; }
        .btn-del {
            padding: 5px 10px;
            background: rgba(239,68,68,.08);
            border: 1px solid rgba(239,68,68,.2);
            border-radius: 7px;
            color: #ef4444;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--font-main);
            transition: background .2s;
            flex-shrink: 0;
        }
        .btn-del:hover { background: rgba(239,68,68,.18); }

        /* ══ History table ══ */
        .hist-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .hist-table th {
            text-align: left;
            padding: 9px 12px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .hist-table td { padding: 10px 12px; border-bottom: 1px solid rgba(255,255,255,.04); color: var(--text-secondary); vertical-align: middle; }
        .hist-table tr:last-child td { border-bottom: none; }
        .hist-table tr:hover td { background: rgba(255,255,255,.02); }

        /* ══ Grade pills ══ */
        .gp { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
        .gr-a { background: rgba(16,185,129,.15); color: #10b981; }
        .gr-b { background: rgba(34,211,238,.12); color: var(--cyan); }
        .gr-c { background: rgba(245,158,11,.12); color: #f59e0b; }
        .gr-d { background: rgba(239,68,68,.12); color: #ef4444; }

        /* ══ Alerts ══ */
        .alert {
            padding: 11px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
            animation: fadeUp .3s ease;
        }
        .alert-success { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.25); color: #10b981; }
        .alert-warning { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25); color: #f59e0b; }

        /* ══ Empty ══ */
        .empty-state { text-align: center; padding: 36px 20px; color: var(--text-muted); font-size: 13px; }

        /* ══ Section card ══ */
        .section-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 22px 24px;
            margin-bottom: 20px;
        }
        .section-card-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        .section-card-title-icon {
            width: 28px; height: 28px;
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
        }

        @media(max-width:640px) {
            .course-row { grid-template-columns: 1fr 72px; }
            .course-code { display: none; }
            .ipk-divider { display: none; }
        }
    </style>
</head>
<body class="dashboard-body">
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">

    <!-- Topbar — identik dengan halaman lain -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Input Nilai</h1>
                <p class="page-sub">Semester <?= $currentSemester ?> · <?= htmlspecialchars($profile['fullname']) ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="skill_gap.php" class="input-nilai-btn-ghost">← Skill Gap</a>
            <span class="semester-badge"><?= count($historyCourses) ?> matkul</span>
        </div>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
    <?php endif; ?>

    <!-- ══ IPK BANNER ══ -->
    <div class="ipk-banner">
        <!-- IPK besar -->
        <div>
            <div class="ipk-big"><?= number_format($ipk, 2) ?></div>
            <div class="ipk-label">IPK<?= ($profile['ipk']??0)>0 ? ' (manual)' : ' (kalkulasi)' ?></div>
        </div>

        <div class="ipk-divider"></div>

        <!-- Stat: matkul -->
        <div class="ipk-stat">
            <div class="ipk-stat-num" style="color:var(--cyan)"><?= count($historyCourses) ?></div>
            <div class="ipk-stat-lbl">Matkul Tercatat</div>
        </div>
        <div class="ipk-stat">
            <div class="ipk-stat-num" style="color:#10b981"><?= count(array_filter($historyCourses, fn($h)=>in_array($h['grade'],['A','A-']))) ?></div>
            <div class="ipk-stat-lbl">Nilai A</div>
        </div>
        <div class="ipk-stat">
            <div class="ipk-stat-num" style="color:#a78bfa"><?= $totalCredit ?></div>
            <div class="ipk-stat-lbl">Total SKS</div>
        </div>

        <div class="ipk-divider"></div>

        <!-- Input IPK manual -->
        <div>
            <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px">Update IPK Manual</div>
            <form method="POST">
                <div class="ipk-input-wrap">
                    <input
                        type="number"
                        step="0.01" min="0" max="4"
                        name="manual_ipk"
                        value="<?= $profile['ipk'] ?? '' ?>"
                        placeholder="0.00"
                        class="ipk-input"
                    >
                    <button type="submit" name="save_ipk" class="btn-save-ipk">Simpan IPK</button>
                </div>
            </form>
            <div style="font-size:10px;color:#475569;margin-top:5px">Terpakai di <a href="simulation.php" style="color:var(--cyan)">Simulasi SAW</a></div>
        </div>
    </div>

    <!-- ══ TABS ══ -->
    <div class="tab-strip">
        <button class="tab-btn active" onclick="switchTab('kurikulum', this)">📋 Kurikulum Semester</button>
        <button class="tab-btn" onclick="switchTab('ekstra', this)">➕ Tambah Matkul</button>
        <button class="tab-btn" onclick="switchTab('riwayat', this)">📜 Riwayat & Hapus</button>
    </div>

    <!-- ════════════════════════════
         TAB 1: KURIKULUM
    ════════════════════════════ -->
    <div class="tab-pane active" id="tab-kurikulum">
        <form method="POST" id="formKurikulum">
            <?php foreach ($bySemester as $sem => $courses): ?>
            <div class="sem-block">
                <div class="sem-label">Semester <?= $sem ?></div>
                <?php foreach ($courses as $course):
                    $existing = $studentCourses[$course['id']] ?? null;
                    $grade    = $existing['grade'] ?? '';
                    $gc       = $grade ? gradeClass($grade) : '';
                ?>
                <div class="course-row <?= $grade ? 'has-grade' : '' ?>" id="row-<?= $course['id'] ?>">
                    <span class="course-code"><?= htmlspecialchars($course['course_code']) ?></span>
                    <div>
                        <div class="course-name-txt"><?= htmlspecialchars($course['course_name']) ?></div>
                        <?php if (!empty($course['credits'])): ?>
                        <div class="course-sks"><?= $course['credits'] ?> SKS</div>
                        <?php endif; ?>
                    </div>
                    <select
                        name="grades[<?= $course['id'] ?>]"
                        class="grade-select <?= $gc ?>"
                        onchange="onGradeChange(this, <?= $course['id'] ?>)"
                    >
                        <option value="">—</option>
                        <?php foreach (['A','A-','B+','B','B-','C+','C','D','E'] as $g): ?>
                        <option value="<?= $g ?>" <?= $grade===$g?'selected':'' ?>><?= $g ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <div class="save-bar">
                <span class="save-bar-hint">Isi nilai lalu simpan — otomatis tersambung ke Skill Gap & Simulasi.</span>
                <div style="display:flex;gap:10px">
                    <button type="button" class="input-nilai-btn-ghost" onclick="clearAll()">Reset</button>
                    <button type="submit" name="save_courses" class="input-nilai-btn-primary">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Nilai
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- ════════════════════════════
         TAB 2: TAMBAH MATKUL EKSTRA
    ════════════════════════════ -->
    <div class="tab-pane" id="tab-ekstra">
        <div class="extra-form-card">
            <div class="extra-form-title">
                <div class="section-card-title-icon" style="background:rgba(167,139,250,.1)">➕</div>
                Tambah Mata Kuliah di Luar Jadwal Semester
            </div>
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px;line-height:1.6">
                Untuk matkul yang diambil lintas semester, mengulang, atau mata kuliah pilihan yang tidak ada di kurikulum semester aktif.
            </p>
            <form method="POST">
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                    <div class="extra-search-wrap">
                        <label style="font-size:11px;color:var(--text-muted);font-weight:600;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em">Nama Mata Kuliah</label>
                        <input type="text" id="extraSearch" placeholder="Ketik nama atau kode matkul…" autocomplete="off" class="search-input-field">
                        <div id="extraSearchResults"></div>
                        <div class="selected-course-confirm" id="extraCourseName"></div>
                    </div>
                    <input type="hidden" name="extra_course_id" id="extraCourseId">
                    <div>
                        <label style="font-size:11px;color:var(--text-muted);font-weight:600;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.06em">Nilai</label>
                        <select name="extra_grade" class="grade-select-sm" required>
                            <option value="">—</option>
                            <?php foreach (['A','A-','B+','B','B-','C+','C','D','E'] as $g): ?>
                            <option value="<?= $g ?>"><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="add_extra" class="input-nilai-btn-primary" style="padding:10px 20px">+ Tambah</button>
                </div>
            </form>
        </div>

        <!-- Matkul ekstra yang sudah ditambahkan -->
        <div class="section-card">
            <div class="section-card-title">
                <div class="section-card-title-icon" style="background:rgba(34,211,238,.08)">📋</div>
                Matkul Tambahan Tersimpan
                <span style="font-size:12px;color:var(--text-muted);font-weight:400;margin-left:auto"><?= count($extras) ?> matkul</span>
            </div>
            <?php if (empty($extras)): ?>
            <div class="empty-state">
                <div style="font-size:28px;margin-bottom:8px">📭</div>
                Belum ada matkul tambahan.
            </div>
            <?php else: ?>
            <?php foreach ($extras as $h): ?>
            <div class="extra-item">
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;color:var(--text-primary)"><?= htmlspecialchars($h['course_name']) ?></div>
                    <div style="font-size:10px;color:var(--text-muted);margin-top:2px"><?= htmlspecialchars($h['course_code']) ?> · Sem <?= $h['semester_taken'] ?></div>
                </div>
                <span class="gp <?= gradeClass($h['grade']) ?>"><?= $h['grade'] ?></span>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="delete_course" value="<?= $h['course_id'] ?>">
                    <button type="submit" class="btn-del" onclick="return confirm('Hapus nilai ini?')">Hapus</button>
                </form>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ════════════════════════════
         TAB 3: RIWAYAT & HAPUS
    ════════════════════════════ -->
    <div class="tab-pane" id="tab-riwayat">
        <div class="section-card">
            <div class="section-card-title">
                <div class="section-card-title-icon" style="background:rgba(251,191,36,.08)">📜</div>
                Semua Nilai Tercatat
                <span style="font-size:12px;color:var(--text-muted);font-weight:400;margin-left:auto"><?= count($historyCourses) ?> matkul</span>
            </div>
            <?php if (empty($historyCourses)): ?>
            <div class="empty-state">
                <div style="font-size:28px;margin-bottom:8px">📋</div>
                Belum ada nilai yang diinput.
            </div>
            <?php else: ?>
            <table class="hist-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Mata Kuliah</th>
                        <th style="text-align:center">SKS</th>
                        <th>Nilai</th>
                        <th style="text-align:center">Sem</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($historyCourses as $h):
                    $gp = $gradePoint[strtoupper($h['grade'])] ?? 0;
                ?>
                <tr>
                    <td style="font-family:var(--font-mono);font-size:11px"><?= htmlspecialchars($h['course_code']) ?></td>
                    <td><?= htmlspecialchars($h['course_name']) ?></td>
                    <td style="text-align:center;color:var(--text-muted)"><?= $h['credits'] ?? '—' ?></td>
                    <td>
                        <span class="gp <?= gradeClass($h['grade']) ?>"><?= $h['grade'] ?></span>
                        <span style="font-size:10px;color:var(--text-muted);margin-left:4px">(<?= number_format($gp,1) ?>)</span>
                    </td>
                    <td style="text-align:center;color:var(--text-muted)"><?= $h['semester_taken'] ?></td>
                    <td>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Hapus nilai <?= htmlspecialchars($h['course_name']) ?>?')">
                            <input type="hidden" name="delete_course" value="<?= $h['course_id'] ?>">
                            <button type="submit" class="btn-del">Hapus</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</main>

<script src="main.js"></script>
<script>
// Sidebar
document.getElementById('sidebarToggle')
    ?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));

// Tabs
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}

// Grade select styling — live feedback saat ganti nilai
function onGradeChange(sel, courseId) {
    sel.className = 'grade-select';
    const row = document.getElementById('row-' + courseId);
    if (sel.value) {
        const g = sel.value.toUpperCase();
        const gc = ['A','A-','A+'].includes(g) ? 'gr-a'
                 : ['B+','B','B-'].includes(g)  ? 'gr-b'
                 : ['C+','C','C-'].includes(g)  ? 'gr-c' : 'gr-d';
        sel.classList.add(gc);
        row.classList.add('has-grade');
    } else {
        row.classList.remove('has-grade');
    }
}

// Reset semua pilihan
function clearAll() {
    if (!confirm('Reset semua nilai yang belum disimpan?')) return;
    document.querySelectorAll('.grade-select').forEach(s => {
        s.value = '';
        s.className = 'grade-select';
    });
    document.querySelectorAll('.course-row').forEach(r => r.classList.remove('has-grade'));
}

// Autocomplete ekstra matkul
const courses = <?= $coursesJson ?>;

document.getElementById('extraSearch')?.addEventListener('input', function () {
    const kw  = this.value.toLowerCase().trim();
    const box = document.getElementById('extraSearchResults');
    if (kw.length < 1) { box.innerHTML = ''; return; }

    const hits = courses.filter(c =>
        (c.course_name || c.course_name_id || '').toLowerCase().includes(kw) ||
        (c.course_code || '').toLowerCase().includes(kw)
    ).slice(0, 12);

    if (!hits.length) {
        box.innerHTML = '<div class="search-item" style="cursor:default;color:var(--text-muted)">Tidak ditemukan</div>';
        return;
    }

    box.innerHTML = hits.map(c => {
        const name = (c.course_name || c.course_name_id || '').replace(/'/g, "\\'");
        return `<div class="search-item" onclick="selectCourse(${c.id},'${name}','${c.course_code}')">
            <strong>${c.course_code}</strong> — ${c.course_name || c.course_name_id}
            <span style="float:right;font-size:10px;color:var(--text-muted)">Sem ${c.semester}</span>
        </div>`;
    }).join('');
});

function selectCourse(id, name, code) {
    document.getElementById('extraCourseId').value = id;
    document.getElementById('extraSearch').value   = name;
    document.getElementById('extraCourseName').textContent = '✓ ' + code + ' dipilih';
    document.getElementById('extraSearchResults').innerHTML = '';
}

document.addEventListener('click', e => {
    if (!e.target.closest('#extraSearch') && !e.target.closest('#extraSearchResults'))
        document.getElementById('extraSearchResults').innerHTML = '';
});
</script>
</body>
</html>