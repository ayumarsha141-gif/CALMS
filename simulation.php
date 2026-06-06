<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user    = getCurrentUser();
$db      = getDB();

// Fetch profile
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
$studentId = $profile['id'];
$targetCareer = $profile['target_career'] ?? '';

// Fetch system config
$sysConfig = [];
$stmt = $db->query("SELECT config_key, config_val FROM system_config WHERE config_key LIKE 'saw_%'");
while ($row = $stmt->fetch()) {
    $sysConfig[$row['config_key']] = (float)$row['config_val'];
}

$w1 = $sysConfig['saw_weight_academic'] ?? 0.40;
$w2 = $sysConfig['saw_weight_practical'] ?? 0.30;
$w3 = $sysConfig['saw_weight_portfolio'] ?? 0.20;
$w4 = $sysConfig['saw_weight_certification'] ?? 0.10;
$c1_course_w = $sysConfig['saw_sub_weight_course'] ?? 0.70;
$c1_ipk_w    = $sysConfig['saw_sub_weight_ipk'] ?? 0.30;
$tier1 = $sysConfig['saw_tier1_min'] ?? 85;
$tier2 = $sysConfig['saw_tier2_min'] ?? 70;
$tier3 = $sysConfig['saw_tier3_min'] ?? 55;

// Fetch career ID
$careerId = null;
if ($targetCareer) {
    $stmt = $db->prepare("SELECT id FROM career_positions WHERE position_name = ?");
    $stmt->execute([$targetCareer]);
    $careerId = $stmt->fetchColumn();
}

// Auto-fetch C1 (Academic)
$ipk = (float)($profile['ipk'] ?? 0);
$ipkScore = ($ipk / 4.0) * 100;

$avgCourseScore = 0;
if ($careerId) {
    $stmt = $db->prepare("
        SELECT AVG(sc.score) as avg_score
        FROM career_courses cc
        JOIN student_courses sc ON sc.course_id = cc.course_id
        WHERE cc.career_id = ? AND sc.student_id = ? AND sc.score > 0
    ");
    $stmt->execute([$careerId, $studentId]);
    $avgCourseScore = (float)$stmt->fetchColumn();
}
$c1_score = ($avgCourseScore * $c1_course_w) + ($ipkScore * $c1_ipk_w);

// Auto-fetch C2 (Practical / Independent Skills)
$c2_score = 0;
if ($careerId) {
    $stmt = $db->prepare("
        SELECT 
            COUNT(cs.skill_id) as total_skills,
            SUM(CASE WHEN ss.student_level > 0 THEN 1 ELSE 0 END) as mastered_skills
        FROM career_skills cs
        LEFT JOIN student_skills ss ON ss.skill_id = cs.skill_id AND ss.student_id = ?
        WHERE cs.career_id = ?
    ");
    $stmt->execute([$studentId, $careerId]);
    $skillData = $stmt->fetch();
    $totalSkills = (int)$skillData['total_skills'];
    $masteredSkills = (int)$skillData['mastered_skills'];
    if ($totalSkills > 0) {
        $c2_score = ($masteredSkills / $totalSkills) * 100;
    } else {
        $c2_score = 100; // If no specific independent skills required, assume 100% ready for practical
    }
}

$result  = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_simulation'])) {
    
    // Calculate C3: Portfolio
    $portScales = $_POST['project_scale'] ?? [];
    $portTotal  = 0;
    $portCounts = ['besar' => 0, 'kecil' => 0];
    foreach ($portScales as $scale) {
        $pts = $scale === 'besar' ? 40 : 20;
        $portTotal += $pts;
        if (isset($portCounts[$scale])) $portCounts[$scale]++;
    }
    $c3_score = min(100.0, round(($portTotal / 200) * 100, 2));

    // Calculate C4: Certification
    $certTiers  = $_POST['cert_tier'] ?? [];
    $certTotal  = 0;
    $certCounts = [1 => 0, 2 => 0, 3 => 0];
    foreach ($certTiers as $tier) {
        $tier  = intval($tier);
        $pts   = $tier === 1 ? 100 : ($tier === 2 ? 75 : 50);
        $certTotal += $pts;
        if (isset($certCounts[$tier])) $certCounts[$tier]++;
    }
    $c4_score = min(100.0, round(($certTotal / 300) * 100, 2));

    // Final SAW Calculation
    $total_score = ($c1_score * $w1) + ($c2_score * $w2) + ($c3_score * $w3) + ($c4_score * $w4);
    
    // Evaluate Tier
    $verdictLabel = "Belum Memenuhi Syarat";
    $verdictClass = "verdict-red";
    if ($total_score >= $tier1) {
        $verdictLabel = "Sangat Siap (Tier 1 - Internasional)";
        $verdictClass = "verdict-green";
    } else if ($total_score >= $tier2) {
        $verdictLabel = "Siap (Tier 2 - Nasional)";
        $verdictClass = "verdict-blue";
    } else if ($total_score >= $tier3) {
        $verdictLabel = "Cukup (Tier 3 - Lokal)";
        $verdictClass = "verdict-amber";
    }

    $result = compact('c1_score', 'c2_score', 'c3_score', 'c4_score', 'total_score', 'verdictLabel', 'verdictClass', 'certCounts', 'portCounts');
    $success = true;
}

$activePage = 'simulation';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulasi Rekrutmen (SAW) — CALMS</title>
    <meta name="description" content="Simulasi otomatis peluang rekrutmen IT berdasarkan metode Simple Additive Weighting.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .sim-page { display: flex; flex-direction: column; gap: 2rem; }

        .predictor-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        @media (max-width: 900px) { .predictor-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 500px) { .predictor-grid { grid-template-columns: 1fr; } }

        .predictor-card {
            background: var(--surface-2, #1e293b);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
        }
        .predictor-card .pred-label { font-size: .72rem; color: #64748b; text-transform: uppercase; letter-spacing: .08em; }
        .predictor-card .pred-score { font-size: 2rem; font-weight: 700; margin: .3rem 0 .2rem; font-family: 'JetBrains Mono', monospace; }
        .predictor-card .pred-weight { font-size: .72rem; color: #475569; }
        .score-cyan { color: #22d3ee; }
        .score-blue { color: #60a5fa; }
        .score-purple { color: #a78bfa; }
        .score-amber { color: #fbbf24; }

        /* Result hero */
        .result-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 1px solid rgba(34,211,238,.2);
            border-radius: 20px;
            padding: 2.5rem;
            display: flex;
            align-items: center;
            gap: 2.5rem;
            flex-wrap: wrap;
        }
        .result-ring-wrap { position: relative; width: 160px; height: 160px; flex-shrink: 0; }
        .result-ring-wrap svg { width: 160px; height: 160px; }
        .result-ring-center {
            position: absolute; inset: 0;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
        }
        .result-prob { font-size: 2.4rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
        .result-prob-label { font-size: .72rem; color: #64748b; text-transform: uppercase; }
        .result-meta { flex: 1; min-width: 200px; }
        .result-meta h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .result-meta p { color: #94a3b8; font-size: .9rem; line-height: 1.6; }
        .result-verdict {
            display: inline-block;
            margin-top: .75rem;
            padding: .5rem 1rem;
            border-radius: 999px;
            font-size: .9rem;
            font-weight: 600;
        }
        .verdict-green { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
        .verdict-blue { background: rgba(59,130,246,.15); color: #60a5fa; border: 1px solid rgba(59,130,246,.3); }
        .verdict-amber { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
        .verdict-red   { background: rgba(239,68,68,.15);  color: #f87171; border: 1px solid rgba(239,68,68,.3); }

        /* Form sections */
        .sim-section {
            background: var(--surface-2, #1e293b);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 16px;
            overflow: hidden;
        }
        .sim-section-head {
            display: flex; align-items: center; gap: .75rem;
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .sim-section-num {
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem; font-weight: 700;
            background: linear-gradient(135deg, #22d3ee, #3b82f6);
            color: #000;
            flex-shrink: 0;
        }
        .sim-section-title { font-weight: 600; font-size: 1rem; }
        .sim-section-sub { font-size: .75rem; color: #64748b; margin-left: auto; }
        .sim-section-body { padding: 1.5rem; }

        /* Dynamic rows (cert / project) */
        .dynamic-list { display: flex; flex-direction: column; gap: .75rem; }
        .dynamic-row {
            display: grid; gap: .75rem; align-items: center;
            background: rgba(255,255,255,.03); border-radius: 10px; padding: .85rem 1rem;
        }
        .cert-row-grid { grid-template-columns: 1fr 1fr auto auto; }
        .proj-row-grid { grid-template-columns: 1fr auto auto; }
        @media (max-width: 650px) {
            .cert-row-grid { grid-template-columns: 1fr 1fr; }
            .proj-row-grid { grid-template-columns: 1fr auto; }
        }
        .dynamic-row input, .dynamic-row select {
            background: #0f172a; border: 1px solid rgba(255,255,255,.1);
            border-radius: 8px; color: #e2e8f0; padding: .55rem .75rem; font-size: .85rem;
        }
        .dynamic-row input:focus, .dynamic-row select:focus { outline: none; border-color: #22d3ee; }
        .btn-remove {
            width: 32px; height: 32px; border-radius: 8px; border: none;
            background: rgba(239,68,68,.15); color: #f87171; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; transition: background .2s;
        }
        .btn-remove:hover { background: rgba(239,68,68,.3); }
        .btn-add-row {
            display: flex; align-items: center; gap: .5rem;
            padding: .6rem 1.1rem; border-radius: 10px; border: 1px dashed rgba(34,211,238,.3);
            background: transparent; color: #22d3ee; cursor: pointer; font-size: .85rem;
            transition: all .2s; margin-top: .25rem;
        }
        .btn-add-row:hover { background: rgba(34,211,238,.06); border-color: #22d3ee; }

        /* Tier badges */
        .tier-badge { padding: .25rem .6rem; border-radius: 6px; font-size: .7rem; font-weight: 600; }
        .tier-1 { background: rgba(34,211,238,.15); color: #22d3ee; }
        .tier-2 { background: rgba(96,165,250,.15); color: #60a5fa; }
        .tier-3 { background: rgba(148,163,184,.12); color: #94a3b8; }

        /* Submit */
        .sim-submit-bar { display: flex; justify-content: flex-end; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .btn-run {
            padding: .8rem 2rem; background: linear-gradient(135deg, #22d3ee, #3b82f6);
            border: none; border-radius: 12px; color: #000; font-weight: 700;
            font-size: 1rem; cursor: pointer; transition: transform .2s, box-shadow .2s;
            font-family: 'Space Grotesk', sans-serif;
        }
        .btn-run:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(34,211,238,.3); }

        .score-bars { display: flex; flex-direction: column; gap: .8rem; }
        .score-bar-row { display: flex; flex-direction: column; gap: .3rem; }
        .score-bar-label { display: flex; justify-content: space-between; font-size: .8rem; }
        .score-bar-track { height: 8px; background: rgba(255,255,255,.06); border-radius: 4px; overflow: hidden; }
        .score-bar-fill { height: 100%; border-radius: 4px; transition: width .8s cubic-bezier(.4,0,.2,1); }
        .fill-cyan   { background: linear-gradient(90deg,#22d3ee,#06b6d4); }
        .fill-blue   { background: linear-gradient(90deg,#60a5fa,#3b82f6); }
        .fill-purple { background: linear-gradient(90deg,#a78bfa,#8b5cf6); }
        .fill-amber  { background: linear-gradient(90deg,#fbbf24,#f59e0b); }
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
                <h1 class="page-title">Simulasi Rekrutmen</h1>
                <p class="page-sub">Simple Additive Weighting (SAW) Berbasis 4 Komponen Utama</p>
            </div>
        </div>
    </div>

    <div class="sim-page">

        <?php if ($success && $result): ?>
        <!-- ═══════════════ HASIL SIMULASI ═══════════════ -->
        <?php
            $ts = round($result['total_score'], 1);
            $circumference = 2 * M_PI * 58;
            $offset = $circumference - ($ts / 100) * $circumference;
            $color = $ts >= $tier1 ? '#4ade80' : ($ts >= $tier2 ? '#60a5fa' : ($ts >= $tier3 ? '#fbbf24' : '#f87171'));
        ?>
        <div class="result-hero">
            <div class="result-ring-wrap">
                <svg viewBox="0 0 140 140">
                    <circle cx="70" cy="70" r="58" fill="none" stroke="#1e293b" stroke-width="10"/>
                    <circle cx="70" cy="70" r="58" fill="none"
                        stroke="<?= $color ?>" stroke-width="10"
                        stroke-dasharray="<?= round($circumference,2) ?>"
                        stroke-dashoffset="<?= round($offset,2) ?>"
                        stroke-linecap="round"
                        transform="rotate(-90 70 70)"/>
                </svg>
                <div class="result-ring-center">
                    <span class="result-prob" style="color:<?= $color ?>"><?= $ts ?></span>
                    <span class="result-prob-label">Total Skor</span>
                </div>
            </div>
            <div class="result-meta">
                <h2>Skor Kelulusan SAW</h2>
                <p>
                    Target: <strong><?= htmlspecialchars($targetCareer ?: 'Umum') ?></strong><br>
                    Perhitungan dari Akademik (<?= $w1*100 ?>%), Praktis (<?= $w2*100 ?>%), Portofolio (<?= $w3*100 ?>%), dan Sertifikasi (<?= $w4*100 ?>%).
                </p>
                <span class="result-verdict <?= $result['verdictClass'] ?>"><?= $result['verdictLabel'] ?></span>
            </div>
            <div style="min-width:220px; flex:1;">
                <div class="score-bars">
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>C1: Akademik <small>(W1: <?= $w1*100 ?>%)</small></span><span><?= round($result['c1_score'],1) ?></span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-cyan" style="width:<?= $result['c1_score'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>C2: Praktis <small>(W2: <?= $w2*100 ?>%)</small></span><span><?= round($result['c2_score'],1) ?></span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-blue" style="width:<?= $result['c2_score'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>C3: Portofolio <small>(W3: <?= $w3*100 ?>%)</small></span><span><?= round($result['c3_score'],1) ?></span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-amber" style="width:<?= $result['c3_score'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>C4: Sertifikasi <small>(W4: <?= $w4*100 ?>%)</small></span><span><?= round($result['c4_score'],1) ?></span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-purple" style="width:<?= $result['c4_score'] ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div style="text-align:center;">
            <a href="simulation.php" class="btn-run" style="text-decoration:none; display:inline-block;">↺ Sesuaikan Kembali Form</a>
        </div>
        <?php endif; ?>

        <!-- ═══════════════ FORM INPUT ═══════════════ -->
        <?php if (!$success): ?>
        <form method="POST" id="simForm">
            <div style="background:rgba(34,211,238,.05);border:1px solid rgba(34,211,238,.2);border-radius:12px;padding:16px 20px;margin-bottom:24px;color:var(--text-secondary);font-size:13px;">
                <strong style="color:var(--cyan)">Info:</strong> Nilai Akademik (C1) dan Praktis (C2) akan ditarik secara otomatis berdasarkan pilihan Karir kamu di halaman Skill Gap. Kamu hanya perlu menginput sertifikasi dan portofolio untuk melengkapi parameter Simulasi.
            </div>

            <!-- C1 & C2 Preview -->
            <div class="predictor-grid" style="margin-bottom: 2rem;">
                <div class="predictor-card">
                    <div class="pred-label">C1: Nilai Akademik</div>
                    <div class="pred-score score-cyan"><?= round($c1_score, 1) ?></div>
                    <div class="pred-weight">IPK + Rata-rata Matkul · W1: <?= $w1*100 ?>%</div>
                </div>
                <div class="predictor-card">
                    <div class="pred-label">C2: Skill Praktis</div>
                    <div class="pred-score score-blue"><?= round($c2_score, 1) ?></div>
                    <div class="pred-weight">Checkbox Belajar Mandiri · W2: <?= $w2*100 ?>%</div>
                </div>
                <div class="predictor-card" style="opacity: 0.5;">
                    <div class="pred-label">C3: Portofolio</div>
                    <div class="pred-score score-amber">?</div>
                    <div class="pred-weight">Menunggu input form... · W3: <?= $w3*100 ?>%</div>
                </div>
                <div class="predictor-card" style="opacity: 0.5;">
                    <div class="pred-label">C4: Sertifikasi</div>
                    <div class="pred-score score-purple">?</div>
                    <div class="pred-weight">Menunggu input form... · W4: <?= $w4*100 ?>%</div>
                </div>
            </div>

            <!-- 3. Portofolio -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">C3</div>
                    <div>
                        <div class="sim-section-title">Portofolio Proyek (Pengalaman Praktis)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: <?= $w3*100 ?>%</div>
                </div>
                <div class="sim-section-body">
                    <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
                        <div style="padding:.6rem 1rem;border-radius:10px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);font-size:.8rem;">
                            <strong style="color:#fbbf24">Skala Besar</strong> — Tugas Akhir / Project Client / Teamwork = <strong style="color:#fbbf24">40 poin</strong>
                        </div>
                        <div style="padding:.6rem 1rem;border-radius:10px;background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.2);font-size:.8rem;">
                            <strong style="color:#94a3b8">Skala Kecil</strong> — Tugas Harian / Individual = <strong style="color:#94a3b8">20 poin</strong>
                        </div>
                    </div>
                    <div class="dynamic-list" id="projList"></div>
                    <button type="button" class="btn-add-row" onclick="addProject()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah Proyek
                    </button>
                </div>
            </div>

            <!-- 4. Sertifikasi -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">C4</div>
                    <div>
                        <div class="sim-section-title">Sertifikasi Profesional (Validasi Pihak Ketiga)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: <?= $w4*100 ?>%</div>
                </div>
                <div class="sim-section-body">
                    <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;">
                        <div style="padding:.6rem 1rem;border-radius:10px;background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.2);font-size:.8rem;">
                            <span class="tier-badge tier-1">Tier 1</span> Internasional (AWS, Google, Cisco, Oracle...) = <strong style="color:#22d3ee">100 poin</strong>
                        </div>
                        <div style="padding:.6rem 1rem;border-radius:10px;background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2);font-size:.8rem;">
                            <span class="tier-badge tier-2">Tier 2</span> Nasional BNSP = <strong style="color:#60a5fa">75 poin</strong>
                        </div>
                        <div style="padding:.6rem 1rem;border-radius:10px;background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.2);font-size:.8rem;">
                            <span class="tier-badge tier-3">Tier 3</span> Kursus (Coursera, Udemy, dll) = <strong style="color:#94a3b8">50 poin</strong>
                        </div>
                    </div>
                    <div class="dynamic-list" id="certList"></div>
                    <button type="button" class="btn-add-row" onclick="addCert()">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Tambah Sertifikasi
                    </button>
                </div>
            </div>

            <div class="sim-submit-bar">
                <button type="submit" name="run_simulation" class="btn-run">
                    Hitung Simulasi SAW
                </button>
            </div>
        </form>
        <?php endif; ?>

    </div><!-- .sim-page -->
</main>

<script src="main.js"></script>
<script>
// Sidebar toggle
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

// ── Dynamic Cert Rows ──
let certCount = 0;
function addCert() {
    const n = certCount++;
    const row = document.createElement('div');
    row.className = 'dynamic-row cert-row-grid';
    row.innerHTML = `
        <input type="text" name="cert_name[]" placeholder="Nama sertifikasi..." required>
        <input type="text" name="cert_provider[]" placeholder="Penerbit (AWS, BNSP, Udemy...)">
        <select name="cert_tier[]" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;padding:.55rem .75rem;font-size:.85rem;">
            <option value="1">Tier 1 — Internasional (100 poin)</option>
            <option value="2">Tier 2 — Nasional BNSP (75 poin)</option>
            <option value="3" selected>Tier 3 — Kursus Biasa (50 poin)</option>
        </select>
        <button type="button" class="btn-remove" onclick="this.closest('.dynamic-row').remove()">✕</button>
    `;
    document.getElementById('certList').appendChild(row);
}

// ── Dynamic Project Rows ──
let projCount = 0;
function addProject() {
    const n = projCount++;
    const row = document.createElement('div');
    row.className = 'dynamic-row proj-row-grid';
    row.innerHTML = `
        <input type="text" name="project_name[]" placeholder="Nama proyek..." required>
        <select name="project_scale[]" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;padding:.55rem .75rem;font-size:.85rem;">
            <option value="besar">Skala Besar — TA/Client/Teamwork (40 poin)</option>
            <option value="kecil" selected>Skala Kecil — Tugas Harian (20 poin)</option>
        </select>
        <button type="button" class="btn-remove" onclick="this.closest('.dynamic-row').remove()">✕</button>
    `;
    document.getElementById('projList').appendChild(row);
}
</script>
</body>
</html>
