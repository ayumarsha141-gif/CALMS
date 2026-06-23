<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];
$careerTargetId = $profile['career_target_id'] ?? 0;

$currentSemester = (int)($profile['semester'] ?? 1);

$stmt = $db->prepare("
SELECT
    s.id,
    s.skill_name,
    s.category,
    s.industry_level,
    COALESCE(ss.student_level,0) AS student_level,
    (s.industry_level - COALESCE(ss.student_level,0)) AS gap
FROM skills s
JOIN career_skills cs
    ON UPPER(TRIM(cs.skill_name)) = UPPER(TRIM(s.skill_name))
JOIN career_positions cp
    ON cp.id = cs.career_id
LEFT JOIN student_skills ss
    ON ss.skill_id = s.id
   AND ss.student_id = ?
WHERE cp.position_name = ?
ORDER BY gap DESC
");
$stmt->execute([$studentId, $profile['target_career']]);
$allSkills = $stmt->fetchAll();

$categories = [];
foreach ($allSkills as $sk) $categories[$sk['category']][] = $sk;

$coursesBySkill  = [];
$allCoursesForJS = [];
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
    foreach ($stmt->fetchAll() as $row) $coursesBySkill[$row['skill_id']][] = $row;
    $allCoursesForJS = $db->query("SELECT id, course_name, course_name_id, course_code, semester FROM courses ORDER BY semester")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $coursesBySkill = $allCoursesForJS = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_skills'])) {
        foreach ($_POST['levels'] ?? [] as $skillId => $level) {
            $level = max(0, min(10, (int)$level));
            $db->prepare("INSERT INTO student_skills (student_id, skill_id, student_level) VALUES (?,?,?) ON DUPLICATE KEY UPDATE student_level=?")->execute([$studentId, $skillId, $level, $level]);
        }
        if (isset($_POST['ipk_value']) && $_POST['ipk_value'] !== '') {
            $newIpk = round(max(0, min(4.0, (float)$_POST['ipk_value'])), 2);
            $db->prepare("UPDATE mahasiswa_profiles SET ipk=? WHERE id=?")->execute([$newIpk, $studentId]);
        }
        header('Location: skill_gap.php?saved=1'); exit;
    }
}

$stmt = $db->prepare("SELECT sc.*,c.course_code,c.course_name,c.semester FROM student_courses sc JOIN courses c ON c.id=sc.course_id WHERE sc.student_id=? ORDER BY sc.semester_taken,c.semester");
$stmt->execute([$studentId]);
$historyCourses = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

$totalSkill = 0;
foreach ($allSkills as $sk) {
    $totalSkill += ($sk['student_level'] / max(1, $sk['industry_level'])) * 100;
}
$skillPart = count($allSkills) ? $totalSkill / count($allSkills) : 0;

$stmt = $db->prepare("SELECT AVG(score) FROM student_courses WHERE student_id=?");
$stmt->execute([$studentId]);
$coursePart = (float)$stmt->fetchColumn();

$avgReadiness = round(($skillPart * 0.6) + ($coursePart * 0.4), 1);

$prioritySkills = $allSkills;
usort($prioritySkills, fn($a,$b) => $b['gap'] <=> $a['gap']);

function gradeClass(string $g): string {
    $g = strtoupper($g);
    if (in_array($g, ['A','A+','A-'])) return 'gr-a';
    if (in_array($g, ['B+','B','B-']))  return 'gr-b';
    if (in_array($g, ['C+','C','C-']))  return 'gr-c';
    return 'gr-d';
}

$activePage  = 'skill_gap';
$coursesJson = json_encode($allCoursesForJS, JSON_UNESCAPED_UNICODE);

$descMap = [
    'Python'           => 'Bahasa utama Data Science & AI',
    'TensorFlow'       => 'Framework Deep Learning dari Google',
    'SQL'              => 'Mengolah & mengambil data dari database',
    'Machine Learning' => 'Membangun model prediksi & klasifikasi',
    'Data Mining'      => 'Analisis pola dari data besar',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>Skill Gap — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link rel="stylesheet" href="../../styles/style_patch.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>

        .sg-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 28px;
        }
        .sg-stat {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px 20px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: var(--transition);
        }
        .sg-stat:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }
        .sg-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .sg-stat-body { display: flex; flex-direction: column; gap: 3px; }
        .sg-stat-num {
            font-size: 28px;
            font-weight: 700;
            font-family: var(--font-mono);
            line-height: 1;
        }
        .sg-stat-label {
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.03em;
        }

        .sg-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .skill-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px;
            transition: var(--transition);
        }
        .skill-card:hover { border-color: var(--border-hover); }
        .skill-hd { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .skill-name { font-size: 14px; font-weight: 600; margin: 0; }
        .skill-desc { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
        .gap-tag { font-size: 10px; font-weight: 600; padding: 3px 9px; border-radius: 999px; white-space: nowrap; flex-shrink: 0; }
        .gap-low  { background: rgba(16,185,129,.15); color: #10b981; }
        .gap-mid  { background: rgba(245,158,11,.15);  color: #f59e0b; }
        .gap-high { background: rgba(239,68,68,.15);   color: #ef4444; }

        .bar-wrap { position: relative; height: 8px; background: #1e293b; border-radius: 999px; margin-bottom: 12px; overflow: hidden; }
        .bar-fill { position: absolute; top: 0; left: 0; height: 100%; border-radius: 999px; transition: width .7s ease; }

        .level-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
        .level-box { background: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 10px; text-align: center; }
        .level-num { font-size: 20px; font-weight: 700; font-family: var(--font-mono); line-height: 1; }
        .level-sub { font-size: 10px; color: #64748b; margin-top: 3px; }
        .level-tag { font-size: 10px; color: #94a3b8; margin-top: 2px; }

        .slider-row { display: flex; align-items: center; gap: 8px; }
        .slider-row label { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
        .skill-range { flex: 1; accent-color: var(--cyan); }
        .skill-val { font-size: 12px; font-family: var(--font-mono); color: var(--cyan); min-width: 18px; text-align: right; }

        .skill-explain {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #0f172a;
            border: 1px solid #1e293b;
        }
        .ex-title { font-size: 10px; color: #94a3b8; margin-bottom: 3px; }
        .ex-progress { font-size: 12px; font-weight: 600; color: #22d3ee; }

        .legend-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .legend-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--text-muted); }
        .legend-dot { width: 20px; height: 6px; border-radius: 3px; }

        .save-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 26px; background: var(--cyan); color: #0a0f1a; border: none; border-radius: 999px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .save-btn:hover { opacity: .85; }
        .btn-plain { padding: 8px 18px; background: transparent; border: 1px solid var(--border); border-radius: 999px; color: var(--text-secondary); font-size: 12px; cursor: pointer; font-family: inherit; }
        .btn-plain:hover { border-color: var(--border-hover); }

        .alert-success { background: rgba(16,185,129,.08); border: 1px solid rgba(16,185,129,.25); color: #10b981; padding: 10px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; }

        .form-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); }
        .form-footer-note { font-size: 12px; color: var(--text-muted); }

        .gr-a { background: rgba(16,185,129,.15); color: #10b981; }
        .gr-b { background: rgba(34,211,238,.12); color: var(--cyan); }
        .gr-c { background: rgba(245,158,11,.12); color: #f59e0b; }
        .gr-d { background: rgba(239,68,68,.12); color: #ef4444; }

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
<body class="dashboard-body">
<?php include '../../includes/sidebar.php'; ?>

<main class="main-content">

    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Skill Gap Analysis</h1>
                <p class="page-sub">Level kamu vs standar industri · <?= htmlspecialchars($profile['target_career'] ?? '-') ?></p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="input_nilai.php" class="btn-plain" style="margin-right:8px">📚 Input Nilai</a>
            <span class="semester-badge">Semester <?= $profile['semester'] ?? '-' ?></span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Data berhasil disimpan.</div>
    <?php endif; ?>

    <div class="sg-stats">

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(167,139,250,.1)">🎓</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:#a78bfa"><?= number_format($profile['ipk'] ?? 0, 2) ?></div>
                <div class="sg-stat-label">IPK</div>
            </div>
        </div>

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(34,211,238,.1)">📊</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:var(--cyan)" id="avgReadiness"><?= number_format($avgReadiness, 1) ?>%</div>
                <div class="sg-stat-label">Career Readiness</div>
            </div>
        </div>

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(59,130,246,.1)">🎯</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:#3b82f6"><?= count($allSkills) ?></div>
                <div class="sg-stat-label">Total Skill Dipantau</div>
            </div>
        </div>

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(16,185,129,.1)">✅</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:#10b981" id="cntLow"><?= $gapLow ?></div>
                <div class="sg-stat-label">Gap Rendah ≤1</div>
            </div>
        </div>

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(245,158,11,.1)">⚡</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:#f59e0b" id="cntMid"><?= $gapMid ?></div>
                <div class="sg-stat-label">Gap Sedang 2–3</div>
            </div>
        </div>

        <div class="sg-stat">
            <div class="sg-stat-icon" style="background:rgba(239,68,68,.1)">🔴</div>
            <div class="sg-stat-body">
                <div class="sg-stat-num" style="color:#ef4444" id="cntHigh"><?= $gapHigh ?></div>
                <div class="sg-stat-label">Gap Tinggi ≥4</div>
            </div>
        </div>

    </div>

    <div class="legend-row">
        <div class="legend-item"><div class="legend-dot" style="background:var(--cyan)"></div>Level kamu</div>
        <div class="legend-item"><div class="legend-dot" style="background:#334155"></div>Target industri</div>
    </div>

    <form method="POST" id="skillForm">
        <input type="hidden" name="save_skills" value="1">
        <input type="hidden" name="ipk_value" id="ipkHidden" value="<?= htmlspecialchars($profile['ipk'] ?? '') ?>">

        <div class="sg-content">
        <?php foreach ($allSkills as $sk):
            $gap      = (int)$sk['gap'];
            $gClass   = $gap <= 1 ? 'gap-low' : ($gap <= 3 ? 'gap-mid' : 'gap-high');
            $gLabel   = $gap <= 1 ? 'Rendah' : ($gap <= 3 ? 'Sedang' : 'Tinggi');
            $barColor = $gap <= 1 ? '#10b981' : ($gap <= 3 ? '#f59e0b' : '#ef4444');
            $pct      = $sk['industry_level'] > 0 ? round(($sk['student_level'] / $sk['industry_level']) * 100) : 0;
            $tLabel   = $sk['industry_level'] >= 8 ? 'Advanced' : ($sk['industry_level'] >= 5 ? 'Intermediate' : 'Basic');
            $mLabel   = $sk['student_level'] >= 8 ? 'Advanced' : ($sk['student_level'] >= 5 ? 'Intermediate' : ($sk['student_level'] >= 2 ? 'Basic' : 'Belum mulai'));
            $desc     = $descMap[$sk['skill_name']] ?? 'Skill pendukung karir industri';
        ?>
        <div class="skill-card" id="scard-<?= $sk['id'] ?>">

            <div class="skill-hd">
                <div>
                    <div class="skill-name"><?= htmlspecialchars($sk['skill_name']) ?></div>
                    <div class="skill-desc"><?= $desc ?></div>
                </div>
                <span class="gap-tag <?= $gClass ?>">Gap <?= $gap ?> — <?= $gLabel ?></span>
            </div>

            <div class="skill-explain">
                <div class="ex-title">Self Assessment</div>
                <div class="ex-progress">Sesuaikan level kemampuanmu</div>
            </div>

            <div class="bar-wrap">
                <div class="bar-fill" id="bar-<?= $sk['id'] ?>"
                     style="width:<?= ($sk['student_level']/10)*100 ?>%;background:<?= $barColor ?>"></div>
            </div>

            <div class="level-row">
                <div class="level-box">
                    <div class="level-num" style="color:var(--cyan)"><?= $sk['student_level'] ?><span style="font-size:11px;color:#64748b">/10</span></div>
                    <div class="level-sub">Level Kamu</div>
                    <div class="level-tag"><?= $mLabel ?></div>
                </div>
                <div class="level-box">
                    <div class="level-num" style="color:<?= $barColor ?>"><?= $gap ?><span style="font-size:11px;color:#64748b"> poin</span></div>
                    <div class="level-sub">Gap</div>
                    <div class="level-tag"><?= $pct ?>% siap</div>
                </div>
                <div class="level-box">
                    <div class="level-num" style="color:#64748b"><?= $sk['industry_level'] ?><span style="font-size:11px;color:#64748b">/10</span></div>
                    <div class="level-sub">Target Industri</div>
                    <div class="level-tag"><?= $tLabel ?></div>
                </div>
            </div>

            <div class="slider-row">
                <label for="sl-<?= $sk['id'] ?>">Perbarui:</label>
                <input type="range" id="sl-<?= $sk['id'] ?>"
                       name="levels[<?= $sk['id'] ?>]"
                       min="0" max="10" step="1"
                       value="<?= $sk['student_level'] ?>"
                       class="skill-range"
                       oninput="onSlider(<?= $sk['id'] ?>,<?= $sk['industry_level'] ?>,this.value)">
                <span class="skill-val" id="sv-<?= $sk['id'] ?>"><?= $sk['student_level'] ?></span>
            </div>

        </div>
        <?php endforeach; ?>
        </div>

        <div class="form-footer">
            <span class="form-footer-note">Perubahan level akan langsung memperbarui Career Readiness</span>
            <button type="submit" class="save-btn">💾 Simpan Semua Level</button>
        </div>
    </form>

</main>

<div id="sidebar-overlay"></div>

<script>
const sidebarEl  = document.getElementById('sidebar');
console.log(sidebarEl);
const toggleBtn  = document.getElementById('sidebarToggle');
const overlayEl  = document.getElementById('sidebar-overlay');

function openSidebar()  { sidebarEl?.classList.add('open');    overlayEl?.classList.add('show'); }
function closeSidebar() { sidebarEl?.classList.remove('open'); overlayEl?.classList.remove('show'); }

toggleBtn?.addEventListener('click', (e) => {console.log('TOGGLE CLICKED');
    e.stopPropagation();
    sidebarEl?.classList.contains('open') ? closeSidebar() : openSidebar();
});
overlayEl?.addEventListener('click', closeSidebar);

const skillState = {};
<?php foreach ($allSkills as $sk): ?>
skillState[<?= $sk['id'] ?>] = { s: <?= $sk['student_level'] ?>, i: <?= $sk['industry_level'] ?> };
<?php endforeach; ?>

function onSlider(id, industry, val) {
    val = parseInt(val);
    skillState[id].s = val;
    document.getElementById('sv-' + id).textContent = val;
    const bar = document.getElementById('bar-' + id);
    if (bar) {
        const gap = industry - val;
        bar.style.width = ((val / 10) * 100) + '%';
        bar.style.background = gap <= 1 ? '#10b981' : gap <= 3 ? '#f59e0b' : '#ef4444';
    }
    recalcStats();
}

function recalcStats() {
    let total = 0, tracked = 0, low = 0, mid = 0, high = 0;
    Object.values(skillState).forEach(sk => {
        if (sk.s > 0) {
            tracked++;
            total += (sk.s / sk.i) * 100;
            const g = sk.i - sk.s;
            if (g <= 1) low++; else if (g <= 3) mid++; else high++;
        }
    });
    const avg = tracked > 0 ? Math.round(total / tracked) : 0;
    document.getElementById('avgReadiness').textContent = avg + '%';
    document.getElementById('cntLow').textContent = low;
    document.getElementById('cntMid').textContent = mid;
    document.getElementById('cntHigh').textContent = high;
}

</script>
</body>
</html>