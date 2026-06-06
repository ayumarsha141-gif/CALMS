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

// Get target career
$targetCareerName = $profile['target_career'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_career'])) {
    $newCareer = trim($_POST['target_career'] ?? '');
    if ($newCareer) {
        $db->prepare("UPDATE mahasiswa_profiles SET target_career = ? WHERE id = ?")->execute([$newCareer, $studentId]);
        $targetCareerName = $newCareer;
        header('Location: skill_gap.php?career_updated=1');
        exit;
    }
}

// Handle Checkbox Saves
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_checkboxes'])) {
    $skills = $_POST['skills'] ?? [];
    
    // First, reset all career_skills to 0 for this student (we don't delete, we just update or leave alone, but wait, it's easier to just update what is submitted)
    // Actually, to handle unchecks, we should reset all independent skills for this career to 0 first, then set the checked ones to 1.
    // Fetch career id
    $stmt = $db->query("SELECT id FROM career_positions WHERE position_name = " . $db->quote($targetCareerName));
    $careerIdRow = $stmt->fetch();
    if ($careerIdRow) {
        $cId = $careerIdRow['id'];
        $db->prepare("
            UPDATE student_skills ss 
            JOIN career_skills cs ON cs.skill_id = ss.skill_id
            SET ss.student_level = 0
            WHERE ss.student_id = ? AND cs.career_id = ?
        ")->execute([$studentId, $cId]);
        
        // Insert or update checked skills to 1
        foreach ($skills as $skillId => $val) {
            if ($val == '1') {
                $stmt = $db->prepare("INSERT INTO student_skills (student_id, skill_id, student_level) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE student_level = 1");
                $stmt->execute([$studentId, $skillId]);
            }
        }
    }
    
    // Also save IPK if posted manually
    if (isset($_POST['ipk_value']) && $_POST['ipk_value'] !== '') {
        $newIpk = round(max(0, min(4.0, (float)$_POST['ipk_value'])), 2);
        $db->prepare("UPDATE mahasiswa_profiles SET ipk = ? WHERE id = ?")->execute([$newIpk, $studentId]);
    }
    
    header('Location: skill_gap.php?saved=1');
    exit;
}

// All available roles
$stmt = $db->query("SELECT id, position_name FROM career_positions");
$allRoles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (!in_array($targetCareerName, $allRoles) && !empty($allRoles)) {
    $targetCareerName = current($allRoles);
}
$careerId = array_search($targetCareerName, $allRoles);

// Fetch required academic courses
$academicCourses = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT c.id, c.course_code, c.course_name_id, sc.grade
        FROM career_courses cc
        JOIN courses c ON c.id = cc.course_id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        WHERE cc.career_id = ?
        ORDER BY c.semester ASC, c.course_name_id ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $academicCourses = $stmt->fetchAll();
}

// Fetch required independent skills
$independentSkills = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT s.id, s.skill_name, COALESCE(ss.student_level, 0) as is_mastered
        FROM career_skills cs
        JOIN skills s ON s.id = cs.skill_id
        LEFT JOIN student_skills ss ON ss.skill_id = s.id AND ss.student_id = ?
        WHERE cs.career_id = ?
        ORDER BY s.skill_name ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $independentSkills = $stmt->fetchAll();
}

// Full course list for JS matching (for transcript parsing)
$allCoursesForJS = [];
try {
    $stmt2 = $db->query("SELECT id, course_name, course_name_id, course_code, semester FROM courses ORDER BY semester");
    $allCoursesForJS = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$activePage = 'skill_gap';
$coursesJson = json_encode($allCoursesForJS, JSON_UNESCAPED_UNICODE);

// Grade → colour helper
function gradeClass(string $g): string {
    if (in_array($g, ['A','A+'])) return 'gr-a';
    if ($g === 'A-') return 'gr-a';
    if (in_array($g, ['B+','B'])) return 'gr-b';
    if ($g === 'B-') return 'gr-b';
    if (in_array($g, ['C+','C'])) return 'gr-c';
    return 'gr-d';
}

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
        /* Career Selector */
        .career-selector { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:18px 22px; margin-bottom:24px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
        .career-selector label { font-size:13px; color:var(--text-secondary); font-weight:500; flex-shrink:0; }
        .career-select { background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; cursor:pointer; font-family:var(--font-sans); flex:1; min-width:200px; }
        .career-select:focus { outline:none; border-color:var(--cyan); }
        .career-change-btn { padding:8px 18px; background:var(--cyan); color:#0a0f1a; border:none; border-radius:var(--radius-sm); font-size:13px; font-weight:700; cursor:pointer; transition:var(--transition); }
        .career-change-btn:hover { opacity:.85; }

        /* Stats */
        .sg-stats{display:grid;grid-template-columns:repeat(2,1fr);gap:16px;margin-bottom:28px}
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

        /* Layout & Cards */
        .gap-section-title { font-size: 16px; font-weight: 700; color: #fff; margin-bottom: 15px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .course-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; margin-bottom: 30px; }
        .course-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;}
        .course-card .name { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .course-card .code { font-size: 11px; color: var(--text-muted); font-family: var(--font-mono); margin-top: 4px; }
        .grade-badge { padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
        .grade-none { background: rgba(255,255,255,0.1); color: var(--text-muted); }

        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; margin-bottom: 30px; }
        .checkbox-card { background: rgba(34,211,238,0.05); border: 1px solid rgba(34,211,238,0.2); border-radius: 8px; padding: 15px; display: flex; align-items: center; gap: 10px; cursor: pointer; transition: 0.2s; }
        .checkbox-card:hover { border-color: var(--cyan); background: rgba(34,211,238,0.1); }
        .checkbox-card input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--cyan); cursor: pointer; }
        .checkbox-card label { font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; flex: 1; }

        .save-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 24px;background:var(--cyan);color:#0a0f1a;border:none;border-radius:999px;font-size:13px;font-weight:700;cursor:pointer;transition:var(--transition)}
        .save-btn:hover{opacity:.85;transform:translateY(-1px)}
        .alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#10b981;padding:12px 18px;border-radius:var(--radius-sm);margin-bottom:20px;font-size:13px}
        .spinner{width:15px;height:15px;border:2px solid rgba(10,15,26,.3);border-top-color:#0a0f1a;border-radius:50%;animation:spin .7s linear infinite;display:inline-block}
        @keyframes spin{to{transform:rotate(360deg)}}
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
                <p class="page-sub">Kualifikasi Akademik & Belajar Mandiri</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge">Semester <?= $profile['semester'] ?? '-' ?></span>
        </div>
    </div>

    <?php if (isset($_GET['career_updated'])): ?>
    <div class="alert-success">✅ Target karir berhasil diperbarui!</div>
    <?php endif; ?>
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Pilihan skill berhasil disimpan! Nilai terhubung ke Simulasi Rekrutmen.</div>
    <?php endif; ?>

    <!-- Career Selector -->
    <form method="POST" class="career-selector">
        <label>🎯 Target Karir:</label>
        <select name="target_career" class="career-select">
            <?php foreach ($allRoles as $role): ?>
            <option value="<?= htmlspecialchars($role) ?>" <?= $targetCareerName === $role ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="change_career" class="career-change-btn">Pilih & Muat Data</button>
    </form>

    <!-- ══ Upload Transkrip ══ -->
    <div class="tc-card">
        <div class="tc-hd">
            <div class="tc-icon">
                <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div>
                <div class="tc-title">🎓 Auto-Fetch Nilai Akademik dari Transkrip</div>
                <div class="tc-sub">Unggah PDF SIAKAD. Sistem akan otomatis mengisi nilai matkul kamu.</div>
            </div>
        </div>
        <div class="tc-body">
            <div class="upload-zone" id="dropZone">
                <input type="file" id="pdfInput" accept="application/pdf">
                <svg width="30" height="30" fill="none" stroke="var(--cyan)" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div style="font-size:13px;font-weight:600">Klik atau seret PDF transkrip</div>
                <div class="upload-fn" id="uploadFn"></div>
            </div>
            <button class="parse-btn" id="parseBtn" disabled>
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                Baca Transkrip
            </button>
        </div>

        <div class="ipk-banner" id="ipkBanner">
            <div>
                <div class="ipk-label-txt">📊 IPK Terdeteksi</div>
            </div>
            <input type="number" class="ipk-val-input" id="ipkDisplay" step="0.01" min="0" max="4" placeholder="0.00" readonly>
            <div class="ipk-sim-hint">Tersambung otomatis ke Simulasi Rekrutmen.</div>
        </div>

        <div class="pp-wrap" id="ppWrap">
            <div class="pp-hd">
                <span class="spinner" id="ppSpinner"></span>
                <span id="ppStatus">Membaca PDF...</span>
            </div>
            <div class="pp-track"><div class="pp-bar" id="ppBar"></div></div>
            <div class="pp-log" id="ppLog"></div>
        </div>

        <div class="pv-wrap" id="pvWrap">
            <div class="pv-title">
                Hasil Pembacaan — <span id="pvCount">0</span> matkul terdeteksi
            </div>
            <button class="apply-btn" id="applyBtn">Simpan ke Database (Refresh Halaman Otomatis)</button>
        </div>
    </div>

    <!-- MAIN SKILL GAP CONTENT -->
    <?php if (empty($academicCourses) && empty($independentSkills)): ?>
        <div style="padding: 30px; text-align: center; background: rgba(255,255,255,0.05); border-radius: 8px;">
            Tidak ada kualifikasi khusus yang dikonfigurasi untuk karir ini.
        </div>
    <?php else: ?>
        <form method="POST" id="skillForm">
            <input type="hidden" name="ipk_value" id="ipkHidden" value="<?= htmlspecialchars($profile['ipk'] ?? '') ?>">

            <h2 class="gap-section-title">📚 Kualifikasi Akademik (Mata Kuliah)</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">Matkul berikut menjadi standar penilaian untuk <strong><?= htmlspecialchars($targetCareerName) ?></strong>. (Auto-fetch dari transkrip)</p>
            
            <div class="course-grid">
                <?php foreach ($academicCourses as $c): 
                    $grade = $c['grade'] ?? '-';
                    $gClass = $grade !== '-' ? gradeClass($grade) : 'grade-none';
                ?>
                <div class="course-card">
                    <div>
                        <div class="name"><?= htmlspecialchars($c['course_name_id'] ?? '') ?></div>
                        <div class="code"><?= htmlspecialchars($c['course_code'] ?? '') ?></div>
                    </div>
                    <div class="grade-badge <?= $gClass ?>"><?= htmlspecialchars($grade) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <h2 class="gap-section-title">🚀 Kualifikasi Belajar Mandiri</h2>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 15px;">Centang skill yang sudah kamu pelajari secara mandiri di luar kelas (misal: bootcamp, kursus online).</p>
            
            <div class="checkbox-grid">
                <?php foreach ($independentSkills as $s): ?>
                <label class="checkbox-card">
                    <input type="checkbox" name="skills[<?= $s['id'] ?>]" value="1" <?= $s['is_mastered'] ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($s['skill_name']) ?></span>
                </label>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                <button type="submit" name="save_checkboxes" class="save-btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    Simpan Skill Belajar Mandiri
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const DB_COURSES = <?= $coursesJson ?>;

function gradeToScore(g) {
    const m = {'A':10,'A+':10,'A-':9,'B+':8,'B':7,'B-':6,'C+':5,'C':4,'C-':3,'D':2,'E':0,'K':0};
    return m[g.trim().toUpperCase()] ?? null;
}

function norm(s){ return (s||'').toLowerCase().replace(/[^a-z0-9\s]/g,' ').replace(/\s+/g,' ').trim(); }

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
const pvCount   = document.getElementById('pvCount');
const applyBtn  = document.getElementById('applyBtn');
const ipkBanner = document.getElementById('ipkBanner');
const ipkDisplay= document.getElementById('ipkDisplay');

let detectedIPK   = null;

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

        detectedIPK = extractIPK(allLines);
        if (detectedIPK) logLine(`✓ IPK terdeteksi: ${detectedIPK}`, 'log-ok');

        const foundCourses = [];

        for (let i = 0; i < allLines.length; i++) {
            const line  = allLines[i];
            const codeMatch = line.match(/\b(BIE\d{3,4})\b/i);
            let dbCourse = null;
            if (codeMatch) {
                const code = codeMatch[1].toUpperCase();
                dbCourse = DB_COURSES.find(c => c.course_code && c.course_code.toUpperCase() === code);
            }
            if (!dbCourse) dbCourse = matchDBCourse(line);
            if (!dbCourse) continue;

            let grade = extractGrade(line);
            if (!grade) {
                for (let j = i+1; j <= Math.min(i+5, allLines.length-1); j++) {
                    grade = extractGrade(allLines[j]);
                    if (grade) break;
                }
                if (!grade) {
                    for (let j = i-1; j >= Math.max(0, i-2); j--) {
                        grade = extractGrade(allLines[j]);
                        if (grade) break;
                    }
                }
            }
            if (!grade) continue;

            const score = gradeToScore(grade);
            if (score === null) continue;

            if (!foundCourses.find(c => c.course_id === dbCourse.id)) {
                foundCourses.push({ course_id: dbCourse.id, course_name: dbCourse.course_name, grade, score });
                logLine(`✓ ${dbCourse.course_name} (${dbCourse.course_code}) → ${grade}`, 'log-ok');
            }
        }

        ppBar.style.width = '100%';
        ppStatus.textContent = `Selesai! ${foundCourses.length} matkul terdeteksi.`;
        ppSpinner.style.display = 'none';

        if (detectedIPK) {
            ipkDisplay.value = detectedIPK;
            ipkBanner.classList.add('show');
        }

        if (foundCourses.length > 0) {
            pvCount.textContent = foundCourses.length;
            pvWrap.classList.add('show');
            applyBtn._courses = foundCourses;
        } else {
            logLine('⚠ Tidak ada matkul terdeteksi. Pastikan PDF berisi teks.', 'log-warn');
        }
    } catch(err) {
        ppStatus.textContent = 'Gagal membaca PDF.';
        ppSpinner.style.display = 'none';
        logLine('✗ ' + err.message, 'log-warn');
    }
    parseBtn.disabled = false;
});

applyBtn.addEventListener('click', async () => {
    const payload = {
        ipk     : detectedIPK,
        skills  : [], // No longer extracting skills via PDF logic
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
            applyBtn.textContent = `✓ Berhasil tersimpan. Memuat ulang halaman...`;
            applyBtn.style.background = 'rgba(16,185,129,.25)';
            setTimeout(() => window.location.reload(), 1000);
        } else {
            applyBtn.textContent = '⚠ Gagal simpan: ' + (json.error || 'Unknown error');
            applyBtn.disabled = false;
        }
    } catch(e) {
        applyBtn.textContent = '⚠ Network error';
        applyBtn.disabled = false;
    }
});

document.getElementById('sidebarToggle')?.addEventListener('click', () =>
    document.getElementById('sidebar').classList.toggle('open'));

</script>
</body>
</html>