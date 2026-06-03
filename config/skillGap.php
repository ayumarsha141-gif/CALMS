<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';
 
requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();
 
// Fetch profile
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
 
$errors  = [];
$success = '';
 
// ── SAVE SKILLS (POST) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_skills']) && $profile) {
    $levels = $_POST['skill_level'] ?? [];
    foreach ($levels as $skillId => $level) {
        $skillId = intval($skillId);
        $level   = max(0, min(10, intval($level)));
        if ($level > 0) {
            $stmt = $db->prepare("
                INSERT INTO student_skills (student_id, skill_id, student_level)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE student_level = VALUES(student_level)
            ");
            $stmt->execute([$profile['id'], $skillId, $level]);
        } else {
            // Level 0 = hapus dari list
            $stmt = $db->prepare("DELETE FROM student_skills WHERE student_id = ? AND skill_id = ?");
            $stmt->execute([$profile['id'], $skillId]);
        }
    }
    $success = 'Skill berhasil disimpan!';
}
 
// ── FETCH ALL SKILLS ──
$allSkills = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();
 
// ── FETCH STUDENT SKILLS ──
$studentSkillMap = [];
if ($profile) {
    $stmt = $db->prepare("SELECT skill_id, student_level FROM student_skills WHERE student_id = ?");
    $stmt->execute([$profile['id']]);
    foreach ($stmt->fetchAll() as $row) {
        $studentSkillMap[$row['skill_id']] = $row['student_level'];
    }
}
 
// ── GROUP BY CATEGORY ──
$grouped = [];
foreach ($allSkills as $sk) {
    $grouped[$sk['category']][] = $sk;
}
$categories = array_keys($grouped);
 
// ── COMPUTE STATS ──
$totalSkillsOwned = count($studentSkillMap);
$totalGapPoints   = 0;
$highGapCount     = 0;
$avgReadiness     = 0;
$readinessList    = [];
 
foreach ($allSkills as $sk) {
    $level = $studentSkillMap[$sk['id']] ?? 0;
    if ($level > 0) {
        $gap = $sk['industry_level'] - $level;
        $totalGapPoints += max(0, $gap);
        if ($gap >= 4) $highGapCount++;
        $readinessList[] = ($level / $sk['industry_level']) * 100;
    }
}
$avgReadiness = count($readinessList) > 0 ? round(array_sum($readinessList) / count($readinessList)) : 0;
 
// ── HELPERS ──
function gapLabel(int $gap): string {
    if ($gap <= 1) return 'Rendah';
    if ($gap <= 3) return 'Sedang';
    return 'Tinggi';
}
function gapClass(int $gap): string {
    if ($gap <= 1) return 'gap-low';
    if ($gap <= 3) return 'gap-mid';
    return 'gap-high';
}
function barColor(int $gap): string {
    if ($gap <= 1) return '#10b981';
    if ($gap <= 3) return '#f59e0b';
    return '#ef4444';
}
function readinessColor(int $score): string {
    if ($score >= 70) return '#10b981';
    if ($score >= 40) return '#f59e0b';
    return '#ef4444';
}
 
$activePage = 'skill_gap';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Gap Analysis — CALMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Skill Gap page styles ── */
        .sg-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1100px) { .sg-layout { grid-template-columns: 1fr; } }
 
        /* Stat summary */
        .sg-stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        @media (max-width: 900px)  { .sg-stat-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px)  { .sg-stat-grid { grid-template-columns: 1fr 1fr; } }
 
        /* Filter tabs */
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }
        .filter-tab:hover { background: rgba(255,255,255,.04); color: var(--text-primary); }
        .filter-tab.active {
            background: rgba(34,211,238,.1);
            border-color: rgba(34,211,238,.3);
            color: #22d3ee;
        }
 
        /* Skill section per category */
        .skill-category-block {
            margin-bottom: 28px;
        }
        .skill-category-block.hidden { display: none; }
        .category-label {
            font-size: 11px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .category-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
 
        /* Skill row */
        .sg-skill-row {
            display: grid;
            grid-template-columns: 180px 1fr 90px 70px;
            align-items: center;
            gap: 14px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.04);
        }
        .sg-skill-row:last-child { border-bottom: none; }
        @media (max-width: 700px) {
            .sg-skill-row {
                grid-template-columns: 1fr 1fr;
                grid-template-rows: auto auto;
            }
            .sg-bars-wrap { grid-column: 1 / -1; }
        }
 
        .sg-skill-name {
            font-size: 13px;
            font-weight: 500;
        }
 
        /* Double bar */
        .sg-bars-wrap {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .sg-bar-track {
            height: 7px;
            border-radius: 999px;
            background: #1e293b;
            position: relative;
            overflow: hidden;
        }
        .sg-bar-fill {
            position: absolute;
            top: 0; left: 0; height: 100%;
            border-radius: 999px;
            transition: width .9s cubic-bezier(.25,.8,.25,1);
        }
 
        /* Slider */
        .sg-slider-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sg-slider {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 4px;
            border-radius: 999px;
            background: #1e293b;
            outline: none;
            cursor: pointer;
        }
        .sg-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px; height: 14px;
            border-radius: 50%;
            background: #22d3ee;
            cursor: pointer;
            transition: transform .15s;
        }
        .sg-slider::-webkit-slider-thumb:hover { transform: scale(1.25); }
        .sg-slider::-moz-range-thumb {
            width: 14px; height: 14px;
            border-radius: 50%;
            background: #22d3ee;
            cursor: pointer;
            border: none;
        }
        .sg-level-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #94a3b8;
            min-width: 28px;
            text-align: right;
        }
 
        /* Gap badge in row */
        .sg-gap-col {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 3px;
        }
 
        /* Right sidebar panel */
        .sg-side-panel {
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: sticky;
            top: 28px;
        }
 
        /* Radar / category readiness */
        .cat-readiness-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .cat-ready-row {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .cat-ready-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
        }
        .cat-ready-meta span:first-child { color: var(--text-secondary); }
        .cat-ready-meta span:last-child  { font-family: 'JetBrains Mono', monospace; color: #94a3b8; }
 
        /* Tips panel */
        .tip-item {
            display: flex;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
        }
        .tip-item:last-child { border-bottom: none; padding-bottom: 0; }
        .tip-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .tip-item strong { display: block; color: var(--text-primary); font-size: 12px; margin-bottom: 2px; }
 
        /* Save bar */
        .sg-save-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            background: rgba(34,211,238,.06);
            border: 1px solid rgba(34,211,238,.15);
            border-radius: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .sg-save-bar p { font-size: 13px; color: var(--text-secondary); }
        .btn-save {
            padding: 9px 22px;
            background: #22d3ee;
            color: #0f172a;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: opacity .15s;
        }
        .btn-save:hover { opacity: .85; }
 
        /* Legend */
        .sg-legend {
            display: flex;
            gap: 18px;
            margin-bottom: 6px;
        }
        .sg-legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: var(--text-muted);
        }
        .sg-legend-dot {
            width: 10px; height: 6px;
            border-radius: 3px;
        }
 
        /* Alert */
        .alert-success {
            background: rgba(16,185,129,.1);
            border: 1px solid rgba(16,185,129,.25);
            color: #10b981;
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
 
        /* Empty category note */
        .empty-cat {
            font-size: 12px;
            color: var(--text-muted);
            padding: 8px 0;
            font-style: italic;
        }
 
        /* Readiness ring */
        .side-ring-wrap {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .side-ring-info { flex: 1; }
        .side-ring-info strong { font-size: 14px; font-weight: 600; display: block; margin-bottom: 4px; }
        .side-ring-info p { font-size: 12px; color: var(--text-secondary); line-height: 1.5; }
    </style>
</head>
<body class="dashboard-body">
 
<?php
$activePage = 'skill_gap';
include 'includes/sidebar.php';
?>
 
<main class="main-content">
 
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Skill Gap Analysis</h1>
                <p class="page-sub">Bandingkan kemampuanmu dengan standar industri</p>
            </div>
        </div>
        <div class="topbar-right">
            <?php if ($profile): ?>
                <span class="semester-badge">Semester <?= $profile['semester'] ?></span>
                <?php if ($profile['target_career']): ?>
                    <span class="career-badge">🎯 <?= htmlspecialchars($profile['target_career']) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
 
    <?php if ($success): ?>
        <div class="alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
 
    <!-- Stat Summary -->
    <div class="sg-stat-grid">
        <div class="stat-card">
            <div class="stat-icon stat-icon--cyan">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                </svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Skill Diisi</span>
                <span class="stat-value value--cyan"><?= $totalSkillsOwned ?></span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill stat-bar-fill--cyan" data-width="<?= round(($totalSkillsOwned / count($allSkills)) * 100) ?>"></div>
            </div>
        </div>
 
        <div class="stat-card">
            <div class="stat-icon stat-icon--green">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Rata-rata Readiness</span>
                <span class="stat-value" style="color:<?= readinessColor($avgReadiness) ?>"><?= $avgReadiness ?>%</span>
            </div>
            <div class="stat-bar">
                <div class="stat-bar-fill" style="background:<?= readinessColor($avgReadiness) ?>" data-width="<?= $avgReadiness ?>"></div>
            </div>
        </div>
 
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1);color:#ef4444;">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Gap Tinggi</span>
                <span class="stat-value value--red"><?= $highGapCount ?></span>
            </div>
        </div>
 
        <div class="stat-card">
            <div class="stat-icon stat-icon--blue">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2"/><polyline points="8 21 12 17 16 21"/>
                </svg>
            </div>
            <div class="stat-body">
                <span class="stat-label">Total Skill Katalog</span>
                <span class="stat-value value--blue"><?= count($allSkills) ?></span>
            </div>
        </div>
    </div>
 
    <div class="sg-layout">
 
        <!-- LEFT: Main skill editor -->
        <div>
            <form method="POST" action="skill_gap.php" id="skillForm">
                <input type="hidden" name="save_skills" value="1">
 
                <!-- Save bar -->
                <div class="sg-save-bar">
                    <p>Atur level skill-mu (0 = belum dikuasai, 10 = ahli). Klik simpan setelah selesai.</p>
                    <button type="submit" class="btn-save">💾 Simpan Perubahan</button>
                </div>
 
                <!-- Filter tabs -->
                <div class="filter-tabs">
                    <button type="button" class="filter-tab active" data-cat="all">Semua</button>
                    <?php foreach ($categories as $cat): ?>
                        <button type="button" class="filter-tab" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                    <?php endforeach; ?>
                </div>
 
                <!-- Legend -->
                <div class="sg-legend">
                    <div class="sg-legend-item">
                        <div class="sg-legend-dot" style="background:#1e293b; border:1px solid #334155;"></div>
                        Standar Industri
                    </div>
                    <div class="sg-legend-item">
                        <div class="sg-legend-dot" style="background:#22d3ee;"></div>
                        Level Kamu
                    </div>
                </div>
 
                <!-- Skill list per category -->
                <?php foreach ($grouped as $category => $skills): ?>
                <div class="skill-category-block" data-cat="<?= htmlspecialchars($category) ?>">
                    <div class="category-label"><?= htmlspecialchars($category) ?></div>
 
                    <?php foreach ($skills as $sk):
                        $level   = $studentSkillMap[$sk['id']] ?? 0;
                        $gap     = $level > 0 ? max(0, $sk['industry_level'] - $level) : $sk['industry_level'];
                        $indPct  = ($sk['industry_level'] / 10) * 100;
                        $stuPct  = ($level / 10) * 100;
                        $color   = $level > 0 ? barColor($gap) : '#475569';
                    ?>
                    <div class="sg-skill-row" data-cat="<?= htmlspecialchars($category) ?>">
 
                        <!-- Name -->
                        <div class="sg-skill-name"><?= htmlspecialchars($sk['skill_name']) ?></div>
 
                        <!-- Double bar -->
                        <div class="sg-bars-wrap">
                            <div class="sg-bar-track">
                                <div class="sg-bar-fill" style="width:<?= $indPct ?>%; background:#334155;"></div>
                            </div>
                            <div class="sg-bar-track">
                                <div class="sg-bar-fill" id="bar-<?= $sk['id'] ?>" style="width:<?= $stuPct ?>%; background:<?= $color ?>;"></div>
                            </div>
                        </div>
 
                        <!-- Slider -->
                        <div class="sg-slider-wrap">
                            <input
                                type="range"
                                class="sg-slider"
                                name="skill_level[<?= $sk['id'] ?>]"
                                min="0" max="10" step="1"
                                value="<?= $level ?>"
                                data-skill-id="<?= $sk['id'] ?>"
                                data-industry="<?= $sk['industry_level'] ?>"
                                id="slider-<?= $sk['id'] ?>">
                            <span class="sg-level-val" id="val-<?= $sk['id'] ?>"><?= $level ?></span>
                        </div>
 
                        <!-- Gap badge -->
                        <div class="sg-gap-col">
                            <?php if ($level > 0): ?>
                                <span class="gap-tag <?= gapClass($gap) ?>" id="gaptag-<?= $sk['id'] ?>">
                                    <?= gapLabel($gap) ?>
                                </span>
                                <span style="font-size:11px;color:#475569;font-family:'JetBrains Mono',monospace;" id="gapval-<?= $sk['id'] ?>">
                                    <?= $level ?>/<?= $sk['industry_level'] ?>
                                </span>
                            <?php else: ?>
                                <span class="gap-tag" style="background:rgba(71,85,105,.15);color:#475569;" id="gaptag-<?= $sk['id'] ?>">
                                    Belum
                                </span>
                                <span style="font-size:11px;color:#475569;font-family:'JetBrains Mono',monospace;" id="gapval-<?= $sk['id'] ?>">
                                    0/<?= $sk['industry_level'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
 
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
 
                <!-- Bottom save -->
                <div style="margin-top:20px;text-align:right;">
                    <button type="submit" class="btn-save">💾 Simpan Semua Skill</button>
                </div>
            </form>
        </div>
 
        <!-- RIGHT: Summary sidebar -->
        <div class="sg-side-panel">
 
            <!-- Overall readiness ring -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Career Readiness</h2>
                        <p class="panel-sub">Berdasarkan skill yang diisi</p>
                    </div>
                </div>
                <?php
                    $circumference = 2 * M_PI * 38;
                    $offset = $circumference - ($avgReadiness / 100) * $circumference;
                    $ringColor = readinessColor($avgReadiness);
                    $rlabel = $avgReadiness >= 70 ? 'Siap Kerja' : ($avgReadiness >= 40 ? 'Perlu Peningkatan' : 'Masih Berkembang');
                ?>
                <div class="side-ring-wrap">
                    <div style="position:relative;flex-shrink:0;">
                        <svg viewBox="0 0 100 100" width="90" height="90">
                            <circle cx="50" cy="50" r="38" fill="none" stroke="#1e293b" stroke-width="8"/>
                            <circle cx="50" cy="50" r="38" fill="none"
                                stroke="<?= $ringColor ?>"
                                stroke-width="8"
                                stroke-dasharray="<?= round($circumference, 2) ?>"
                                stroke-dashoffset="<?= round($offset, 2) ?>"
                                stroke-linecap="round"
                                transform="rotate(-90 50 50)"/>
                        </svg>
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;font-family:'JetBrains Mono',monospace;color:<?= $ringColor ?>">
                            <?= $avgReadiness ?>%
                        </div>
                    </div>
                    <div class="side-ring-info">
                        <strong><?= $rlabel ?></strong>
                        <p><?= $avgReadiness >= 70 ? 'Skillmu sudah kompetitif di industri.' : ($avgReadiness >= 40 ? 'Tingkatkan skill-skill dengan gap tinggi.' : 'Fokus pada skill dasar terlebih dahulu.') ?></p>
                        <a href="simulation.php" class="btn-sm-cyan" style="margin-top:8px;display:inline-block;">Coba Simulasi →</a>
                    </div>
                </div>
            </div>
 
            <!-- Readiness per category -->
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Readiness per Kategori</h2>
                        <p class="panel-sub">Rata-rata skill vs industri</p>
                    </div>
                </div>
                <div class="cat-readiness-list">
                    <?php
                    foreach ($grouped as $cat => $skills):
                        $catLevels = [];
                        foreach ($skills as $sk) {
                            $lvl = $studentSkillMap[$sk['id']] ?? 0;
                            if ($lvl > 0) {
                                $catLevels[] = ($lvl / $sk['industry_level']) * 100;
                            }
                        }
                        if (empty($catLevels)) continue;
                        $catAvg   = round(array_sum($catLevels) / count($catLevels));
                        $catColor = readinessColor($catAvg);
                    ?>
                    <div class="cat-ready-row">
                        <div class="cat-ready-meta">
                            <span><?= htmlspecialchars($cat) ?></span>
                            <span><?= $catAvg ?>%</span>
                        </div>
                        <div class="sg-bar-track">
                            <div class="sg-bar-fill" style="width:<?= $catAvg ?>%; background:<?= $catColor ?>;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($studentSkillMap)): ?>
                        <p style="font-size:12px;color:var(--text-muted);font-style:italic;">Isi skill-mu dulu untuk melihat readiness per kategori.</p>
                    <?php endif; ?>
                </div>
            </div>
 
            <!-- Top gaps -->
            <?php
            $gapList = [];
            foreach ($allSkills as $sk) {
                $lvl = $studentSkillMap[$sk['id']] ?? 0;
                if ($lvl > 0) {
                    $gap = $sk['industry_level'] - $lvl;
                    if ($gap > 0) {
                        $gapList[] = ['name' => $sk['skill_name'], 'gap' => $gap, 'level' => $lvl, 'ind' => $sk['industry_level']];
                    }
                }
            }
            usort($gapList, fn($a, $b) => $b['gap'] - $a['gap']);
            $topGaps = array_slice($gapList, 0, 5);
            ?>
            <?php if (!empty($topGaps)): ?>
            <div class="dash-panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Top Gap Terbesar</h2>
                        <p class="panel-sub">Prioritas untuk ditingkatkan</p>
                    </div>
                </div>
                <?php foreach ($topGaps as $i => $g): ?>
                <div class="cert-row">
                    <div class="cert-rank rank-<?= min($i+1, 3) ?>"><?= $i+1 ?></div>
                    <div class="cert-info">
                        <strong><?= htmlspecialchars($g['name']) ?></strong>
                        <span>Level <?= $g['level'] ?>/<?= $g['ind'] ?> — gap <?= $g['gap'] ?> poin</span>
                    </div>
                    <span class="gap-tag <?= gapClass($g['gap']) ?>"><?= gapLabel($g['gap']) ?></span>
                </div>
                <?php endforeach; ?>
                <div style="margin-top:12px;">
                    <a href="certifications.php" class="btn-sm-cyan">Lihat Sertifikasi Relevan →</a>
                </div>
            </div>
            <?php endif; ?>
 
            <!-- Tips -->
            <div class="dash-panel">
                <div class="panel-header">
                    <h2 class="panel-title">Tips Meningkatkan Skill</h2>
                </div>
                <div>
                    <div class="tip-item">
                        <span class="tip-icon">🎯</span>
                        <div>
                            <strong>Fokus pada Gap Tinggi</strong>
                            Prioritaskan skill dengan gap ≥ 4 poin karena paling berpengaruh terhadap peluang rekrutmen.
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📜</span>
                        <div>
                            <strong>Perkuat dengan Sertifikasi</strong>
                            Sertifikasi Tier 1 (AWS, Google, Cisco) memberi bobot signifikan di simulasi Monte Carlo.
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">💻</span>
                        <div>
                            <strong>Bangun Portofolio Nyata</strong>
                            Project skala besar (TA, client, teamwork) memberi 2× poin dibanding tugas harian.
                        </div>
                    </div>
                    <div class="tip-item">
                        <span class="tip-icon">📊</span>
                        <div>
                            <strong>Cek Simulasi Berkala</strong>
                            Update skill lalu jalankan ulang simulasi rekrutmen untuk melihat progres peluangmu.
                        </div>
                    </div>
                </div>
            </div>
 
        </div>
    </div>
 
</main>
 
<script src="main.js"></script>
<script>
// Sidebar toggle
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
 
// Animate bar fills on load
document.querySelectorAll('.sg-bar-fill').forEach(el => {
    const w = el.style.width;
    el.style.width = '0%';
    setTimeout(() => { el.style.width = w; }, 100);
});
 
// Filter tabs
document.querySelectorAll('.filter-tab').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('.skill-category-block').forEach(block => {
            if (cat === 'all' || block.dataset.cat === cat) {
                block.classList.remove('hidden');
            } else {
                block.classList.add('hidden');
            }
        });
    });
});
 
// Live slider update
document.querySelectorAll('.sg-slider').forEach(slider => {
    slider.addEventListener('input', function() {
        const id  = this.dataset.skillId;
        const ind = parseInt(this.dataset.industry);
        const val = parseInt(this.value);
 
        // Update value label
        document.getElementById('val-' + id).textContent = val;
 
        // Update score label
        document.getElementById('gapval-' + id).textContent = val + '/' + ind;
 
        // Update bar
        const bar = document.getElementById('bar-' + id);
        const pct = (val / 10) * 100;
        bar.style.width = pct + '%';
 
        // Update gap tag
        const tag = document.getElementById('gaptag-' + id);
        const gap = Math.max(0, ind - val);
 
        if (val === 0) {
            bar.style.background = '#475569';
            tag.textContent = 'Belum';
            tag.className = 'gap-tag';
            tag.style.cssText = 'background:rgba(71,85,105,.15);color:#475569;';
        } else {
            let color, label, cls;
            if (gap <= 1)       { color = '#10b981'; label = 'Rendah'; cls = 'gap-low'; }
            else if (gap <= 3)  { color = '#f59e0b'; label = 'Sedang'; cls = 'gap-mid'; }
            else                { color = '#ef4444'; label = 'Tinggi'; cls = 'gap-high'; }
 
            bar.style.background = color;
            tag.textContent = label;
            tag.className = 'gap-tag ' + cls;
            tag.style.cssText = '';
        }
    });
});
 
// data-width support from dashboard.css
document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = el.dataset.width + '%';
});
</script>
 
</body>
</html>
 