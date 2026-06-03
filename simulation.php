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

// --- Monte Carlo Logic ---
function normalRandom(float $mean, float $sd): float {
    $u1 = max(1e-10, mt_rand(1, mt_getrandmax()) / mt_getrandmax());
    $u2 = mt_rand(1, mt_getrandmax()) / mt_getrandmax();
    return $mean + $sd * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
}

function runMonteCarlo(float $ipkScore, float $skillScore, float $certScore, float $portScore, int $n = 10000): float {
    $weights   = [0.30, 0.30, 0.25, 0.15];
    $sds       = [5.0, 10.0, 8.0, 8.0];
    $scores    = [$ipkScore, $skillScore, $certScore, $portScore];
    $threshold = 60.0;
    $hits      = 0;

    for ($i = 0; $i < $n; $i++) {
        $total = 0;
        foreach ($scores as $j => $base) {
            $sim    = normalRandom($base, $sds[$j]);
            $sim    = max(0.0, min(100.0, $sim));
            $total += $sim * $weights[$j];
        }
        if ($total >= $threshold) $hits++;
    }
    return $hits / $n;
}

$result  = null;
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_simulation'])) {
    // --- 1. IPK ---
    $ipk = floatval($_POST['ipk'] ?? 0);
    if ($ipk < 0 || $ipk > 4) $errors[] = 'IPK harus antara 0.00 – 4.00.';
    $ipkScore = ($ipk / 4.0) * 100;

    // --- 2. Skill Score ---
    $skillLevels  = $_POST['skill_level'] ?? [];
    $skillIndustr = $_POST['skill_industry'] ?? [];
    $skillScore   = 0;
    $skillCount   = 0;
    foreach ($skillLevels as $sk => $sl) {
        $il = floatval($skillIndustr[$sk] ?? 8);
        if ($il > 0) {
            $skillScore += (floatval($sl) / $il) * 100;
            $skillCount++;
        }
    }
    $skillScore = $skillCount > 0 ? round($skillScore / $skillCount, 2) : 0;

    // --- 3. Certification Score (Tiering) ---
    // Tier 1 = 100pts, Tier 2 = 75pts, Tier 3 = 50pts
    // Max cap = 300 (enough for 3 top-tier certs)
    $certTiers  = $_POST['cert_tier'] ?? [];
    $certTotal  = 0;
    $certCounts = [1 => 0, 2 => 0, 3 => 0];
    foreach ($certTiers as $tier) {
        $tier  = intval($tier);
        $pts   = $tier === 1 ? 100 : ($tier === 2 ? 75 : 50);
        $certTotal += $pts;
        if (isset($certCounts[$tier])) $certCounts[$tier]++;
    }
    $certScore = min(100, round(($certTotal / 300) * 100, 2));

    // --- 4. Portfolio Score ---
    // Besar = 40pts, Kecil = 20pts
    // Max cap = 200 (5 large projects)
    $portScales = $_POST['project_scale'] ?? [];
    $portTotal  = 0;
    $portCounts = ['besar' => 0, 'kecil' => 0];
    foreach ($portScales as $scale) {
        $pts = $scale === 'besar' ? 40 : 20;
        $portTotal += $pts;
        if (isset($portCounts[$scale])) $portCounts[$scale]++;
    }
    $portScore = min(100, round(($portTotal / 200) * 100, 2));

    if (empty($errors)) {
        $prob      = runMonteCarlo($ipkScore, $skillScore, $certScore, $portScore);
        $probPct   = round($prob * 100, 1);
        $targetRole    = trim($_POST['target_role'] ?? '');
        $targetCompany = trim($_POST['target_company'] ?? '');

        // Save to DB
        if ($profile) {
            $stmt = $db->prepare("INSERT INTO simulations (student_id, target_role, target_company, ipk_score, skill_score, cert_score, portfolio_score, probability_score, iterations) VALUES (?,?,?,?,?,?,?,?,10000)");
            $stmt->execute([$profile['id'], $targetRole, $targetCompany, $ipkScore, $skillScore, $certScore, $portScore, $prob]);
        }

        $result = compact('probPct','ipkScore','skillScore','certScore','portScore','certCounts','portCounts','ipk','targetRole','targetCompany');
        $success = true;
    }
}

// Fetch skills catalog for form
$skillsAll = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();

// Fetch student's saved skills
$savedSkills = [];
if ($profile) {
    $stmt = $db->prepare("SELECT ss.skill_id, ss.student_level FROM student_skills ss WHERE ss.student_id = ?");
    $stmt->execute([$profile['id']]);
    foreach ($stmt->fetchAll() as $row) $savedSkills[$row['skill_id']] = $row['student_level'];
}

// Last simulation
$lastSim = null;
if ($profile) {
    $stmt = $db->prepare("SELECT * FROM simulations WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$profile['id']]);
    $lastSim = $stmt->fetch();
}

$activePage = 'simulation';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulasi Rekrutmen — CALMS</title>
    <meta name="description" content="Simulasi Monte Carlo peluang rekrutmen IT berdasarkan 4 prediktor utama: IPK, Hard Skill, Sertifikasi, dan Portofolio.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Simulation-specific styles ── */
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

        /* Result ring */
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
            padding: .35rem .9rem;
            border-radius: 999px;
            font-size: .8rem;
            font-weight: 600;
        }
        .verdict-green { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
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

        /* IPK input */
        .ipk-wrap { display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .ipk-input-big {
            width: 160px; height: 64px; font-size: 2rem; font-weight: 700;
            text-align: center; background: #0f172a;
            border: 2px solid rgba(34,211,238,.3); border-radius: 12px;
            color: #22d3ee; font-family: 'JetBrains Mono', monospace;
        }
        .ipk-input-big:focus { outline: none; border-color: #22d3ee; }
        .ipk-hint { font-size: .8rem; color: #64748b; }
        .ipk-hint strong { color: #22d3ee; }

        /* Skill table */
        .skill-table { width: 100%; border-collapse: collapse; }
        .skill-table th { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; padding: .5rem .75rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,.07); }
        .skill-table td { padding: .6rem .75rem; border-bottom: 1px solid rgba(255,255,255,.04); font-size: .88rem; }
        .skill-table tr:last-child td { border-bottom: none; }
        .cat-badge { font-size: .68rem; padding: .2rem .55rem; border-radius: 999px; background: rgba(255,255,255,.06); color: #94a3b8; }
        .skill-range { -webkit-appearance: none; appearance: none; width: 100%; height: 6px; border-radius: 3px; background: #1e293b; outline: none; cursor: pointer; accent-color: #22d3ee; }
        .skill-val { font-family: 'JetBrains Mono', monospace; font-size: .85rem; color: #22d3ee; min-width: 28px; text-align: center; }
        .skill-filter { display: flex; gap: .5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .filter-btn {
            padding: .3rem .75rem; border-radius: 999px; font-size: .75rem;
            border: 1px solid rgba(255,255,255,.1); background: transparent;
            color: #94a3b8; cursor: pointer; transition: all .2s;
        }
        .filter-btn.active, .filter-btn:hover { background: rgba(34,211,238,.12); color: #22d3ee; border-color: rgba(34,211,238,.3); }

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

        /* Progress bars for scores */
        .score-bars { display: flex; flex-direction: column; gap: .8rem; }
        .score-bar-row { display: flex; flex-direction: column; gap: .3rem; }
        .score-bar-label { display: flex; justify-content: space-between; font-size: .8rem; }
        .score-bar-track { height: 8px; background: rgba(255,255,255,.06); border-radius: 4px; overflow: hidden; }
        .score-bar-fill { height: 100%; border-radius: 4px; transition: width .8s cubic-bezier(.4,0,.2,1); }
        .fill-cyan   { background: linear-gradient(90deg,#22d3ee,#06b6d4); }
        .fill-blue   { background: linear-gradient(90deg,#60a5fa,#3b82f6); }
        .fill-purple { background: linear-gradient(90deg,#a78bfa,#8b5cf6); }
        .fill-amber  { background: linear-gradient(90deg,#fbbf24,#f59e0b); }

        .alert-err { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); color: #f87171; border-radius: 10px; padding: .85rem 1.1rem; margin-bottom: 1rem; font-size: .88rem; }
        .monte-note { font-size: .78rem; color: #475569; margin-top: .5rem; }
        .empty-skill { color: #475569; font-size: .88rem; text-align: center; padding: 1rem; }
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
                <p class="page-sub">Monte Carlo · 10.000 Iterasi · 4 Prediktor Utama</p>
            </div>
        </div>
    </div>

    <div class="sim-page">

        <?php if (!empty($errors)): ?>
        <div class="alert-err">⚠ <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
        <?php endif; ?>

        <?php if ($success && $result): ?>
        <!-- ═══════════════ HASIL SIMULASI ═══════════════ -->
        <?php
            $pp = $result['probPct'];
            $circumference = 2 * M_PI * 58;
            $offset = $circumference - ($pp / 100) * $circumference;
            $color  = $pp >= 70 ? '#4ade80' : ($pp >= 40 ? '#fbbf24' : '#f87171');
            $verdict = $pp >= 70 ? ['label'=>'Sangat Siap Rekrutmen','cls'=>'verdict-green'] : ($pp >= 40 ? ['label'=>'Perlu Peningkatan','cls'=>'verdict-amber'] : ['label'=>'Butuh Kerja Keras','cls'=>'verdict-red']);
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
                    <span class="result-prob" style="color:<?= $color ?>"><?= $pp ?>%</span>
                    <span class="result-prob-label">Peluang</span>
                </div>
            </div>
            <div class="result-meta">
                <h2>Peluang Lolos Rekrutmen</h2>
                <p>
                    Target: <strong><?= htmlspecialchars($result['targetRole'] ?: 'Umum') ?></strong>
                    <?= $result['targetCompany'] ? ' @ <strong>' . htmlspecialchars($result['targetCompany']) . '</strong>' : '' ?><br>
                    Berdasarkan 10.000 iterasi Monte Carlo menggunakan distribusi normal pada 4 prediktor.
                </p>
                <span class="result-verdict <?= $verdict['cls'] ?>"><?= $verdict['label'] ?></span>
            </div>
            <div style="min-width:220px; flex:1;">
                <div class="score-bars">
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>IPK <small>(bobot 30%)</small></span><span><?= round($result['ipkScore'],1) ?>%</span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-cyan" style="width:<?= $result['ipkScore'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>Hard Skill <small>(bobot 30%)</small></span><span><?= round($result['skillScore'],1) ?>%</span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-blue" style="width:<?= $result['skillScore'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>Sertifikasi <small>(bobot 25%)</small></span><span><?= round($result['certScore'],1) ?>%</span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-purple" style="width:<?= $result['certScore'] ?>%"></div></div>
                    </div>
                    <div class="score-bar-row">
                        <div class="score-bar-label"><span>Portofolio <small>(bobot 15%)</small></span><span><?= round($result['portScore'],1) ?>%</span></div>
                        <div class="score-bar-track"><div class="score-bar-fill fill-amber" style="width:<?= $result['portScore'] ?>%"></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Score cards -->
        <div class="predictor-grid">
            <div class="predictor-card">
                <div class="pred-label">IPK</div>
                <div class="pred-score score-cyan"><?= $result['ipk'] ?></div>
                <div class="pred-weight">Skor: <?= round($result['ipkScore'],1) ?>% · Bobot 30%</div>
            </div>
            <div class="predictor-card">
                <div class="pred-label">Hard Skill</div>
                <div class="pred-score score-blue"><?= round($result['skillScore'],0) ?><span style="font-size:1rem">%</span></div>
                <div class="pred-weight">Rata-rata skill · Bobot 30%</div>
            </div>
            <div class="predictor-card">
                <div class="pred-label">Sertifikasi</div>
                <div class="pred-score score-purple"><?= round($result['certScore'],0) ?><span style="font-size:1rem">%</span></div>
                <div class="pred-weight">T1:<?= $result['certCounts'][1] ?> · T2:<?= $result['certCounts'][2] ?> · T3:<?= $result['certCounts'][3] ?> · Bobot 25%</div>
            </div>
            <div class="predictor-card">
                <div class="pred-label">Portofolio</div>
                <div class="pred-score score-amber"><?= round($result['portScore'],0) ?><span style="font-size:1rem">%</span></div>
                <div class="pred-weight">Besar:<?= $result['portCounts']['besar'] ?> · Kecil:<?= $result['portCounts']['kecil'] ?> · Bobot 15%</div>
            </div>
        </div>

        <div style="text-align:center;">
            <a href="simulation.php" class="btn-run" style="text-decoration:none; display:inline-block;">↺ Jalankan Ulang Simulasi</a>
        </div>
        <?php endif; ?>

        <!-- ═══════════════ FORM INPUT ═══════════════ -->
        <form method="POST" id="simForm">

            <!-- Target Position -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">🎯</div>
                    <div>
                        <div class="sim-section-title">Target Posisi</div>
                    </div>
                </div>
                <div class="sim-section-body">
                    <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                        <div>
                            <label style="font-size:.8rem;color:#94a3b8;display:block;margin-bottom:.4rem;">Role / Posisi</label>
                            <select name="target_role" style="width:100%;background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;padding:.55rem .75rem;font-size:.88rem;">
                                <option value="">-- Pilih posisi --</option>
                                <?php foreach (['Backend Developer','Frontend Developer','Full Stack Developer','Data Scientist','Data Analyst','ML Engineer','Cloud Engineer','DevOps Engineer','Cybersecurity Analyst','Mobile Developer','UI/UX Designer'] as $r): ?>
                                <option value="<?= $r ?>"><?= $r ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="font-size:.8rem;color:#94a3b8;display:block;margin-bottom:.4rem;">Perusahaan Target (opsional)</label>
                            <input type="text" name="target_company" placeholder="cth: Tokopedia, Gojek, Telkom..." style="width:100%;background:#0f172a;border:1px solid rgba(255,255,255,.1);border-radius:8px;color:#e2e8f0;padding:.55rem .75rem;font-size:.88rem;box-sizing:border-box;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1. IPK -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">1</div>
                    <div>
                        <div class="sim-section-title">IPK (Dimensi Akademik)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: 30%</div>
                </div>
                <div class="sim-section-body">
                    <div class="ipk-wrap">
                        <input type="number" name="ipk" id="ipkInput" class="ipk-input-big"
                            min="0" max="4" step="0.01"
                            value="<?= htmlspecialchars($profile['ipk'] ?? '0.00') ?>"
                            required>
                        <div>
                            <div class="ipk-hint">Masukkan IPK terakhirmu (skala 0.00 – 4.00)</div>
                            <div class="ipk-hint" style="margin-top:.4rem">
                                Skor: <strong id="ipkScoreDisplay"><?= round((floatval($profile['ipk'] ?? 0) / 4.0) * 100, 1) ?>%</strong>
                                dari skala IPK → Persentase
                            </div>
                            <div class="ipk-hint" style="margin-top:.4rem">
                                💡 Kamu bisa upload Transkrip Nilai di halaman <a href="profile.php" style="color:#22d3ee">Profil</a> untuk update IPK otomatis.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Hard Skill -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">2</div>
                    <div>
                        <div class="sim-section-title">Hard Skill (Kesesuaian Kompetensi Teknis)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: 30%</div>
                </div>
                <div class="sim-section-body">
                    <div class="skill-filter" id="catFilter">
                        <button type="button" class="filter-btn active" data-cat="all">Semua</button>
                        <?php
                        $cats = array_unique(array_column($skillsAll, 'category'));
                        foreach ($cats as $cat):
                        ?>
                        <button type="button" class="filter-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <?php if (empty($skillsAll)): ?>
                    <div class="empty-skill">Tidak ada data skill. Pastikan database sudah di-import.</div>
                    <?php else: ?>
                    <table class="skill-table" id="skillTable">
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Kategori</th>
                                <th>Standar Industri</th>
                                <th style="min-width:160px">Level Kamu</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($skillsAll as $sk):
                            $savedLevel = $savedSkills[$sk['id']] ?? 0;
                        ?>
                        <tr data-cat="<?= htmlspecialchars($sk['category']) ?>">
                            <td><?= htmlspecialchars($sk['skill_name']) ?>
                                <input type="hidden" name="skill_industry[<?= $sk['id'] ?>]" value="<?= $sk['industry_level'] ?>">
                            </td>
                            <td><span class="cat-badge"><?= htmlspecialchars($sk['category']) ?></span></td>
                            <td style="font-family:'JetBrains Mono',monospace;color:#64748b;"><?= $sk['industry_level'] ?>/10</td>
                            <td>
                                <input type="range" class="skill-range" name="skill_level[<?= $sk['id'] ?>]"
                                    min="0" max="10" step="1" value="<?= $savedLevel ?>"
                                    oninput="this.closest('tr').querySelector('.skill-val').textContent=this.value">
                            </td>
                            <td><span class="skill-val"><?= $savedLevel ?></span>/10</td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <p class="monte-note">💡 Geser slider ke 0 jika kamu belum menguasai skill tersebut. Hanya skill dengan level > 0 yang dihitung.</p>
                </div>
            </div>

            <!-- 3. Sertifikasi -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">3</div>
                    <div>
                        <div class="sim-section-title">Sertifikasi Profesional (Validasi Pihak Ketiga)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: 25%</div>
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
                    <p class="monte-note">Max skor = 300 poin (3 sertifikasi Tier 1). Skor > 300 tetap dihitung sebagai 100%.</p>
                </div>
            </div>

            <!-- 4. Portofolio -->
            <div class="sim-section" style="margin-bottom:1.5rem;">
                <div class="sim-section-head">
                    <div class="sim-section-num">4</div>
                    <div>
                        <div class="sim-section-title">Portofolio Proyek (Pengalaman Praktis)</div>
                    </div>
                    <div class="sim-section-sub">Bobot: 15%</div>
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
                    <p class="monte-note">Max skor = 200 poin (5 proyek skala besar). Skor > 200 tetap dihitung sebagai 100%.</p>
                </div>
            </div>

            <div class="sim-submit-bar">
                <span class="monte-note">Simulasi menggunakan distribusi normal dengan 10.000 iterasi</span>
                <button type="submit" name="run_simulation" class="btn-run">
                    🎲 Jalankan Simulasi Monte Carlo
                </button>
            </div>
        </form>

        <?php if ($lastSim && !$success): ?>
        <div class="dash-panel" style="padding:1.25rem 1.5rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                <h3 style="font-size:.95rem;font-weight:600;">Simulasi Terakhir</h3>
                <span style="font-size:.75rem;color:#475569;"><?= date('d M Y H:i', strtotime($lastSim['created_at'])) ?></span>
            </div>
            <div style="display:flex;gap:1.5rem;align-items:center;flex-wrap:wrap;">
                <div style="font-size:2rem;font-weight:700;font-family:'JetBrains Mono',monospace;
                    color:<?= $lastSim['probability_score'] >= 0.7 ? '#4ade80' : ($lastSim['probability_score'] >= 0.4 ? '#fbbf24' : '#f87171') ?>">
                    <?= round($lastSim['probability_score'] * 100, 1) ?>%
                </div>
                <div style="font-size:.88rem;color:#94a3b8;">
                    <strong><?= htmlspecialchars($lastSim['target_role'] ?: '-') ?></strong>
                    <?= $lastSim['target_company'] ? ' @ ' . htmlspecialchars($lastSim['target_company']) : '' ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- .sim-page -->
</main>

<script src="main.js"></script>
<script>
// Sidebar toggle
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

// IPK live preview
document.getElementById('ipkInput')?.addEventListener('input', function() {
    const v = parseFloat(this.value) || 0;
    const pct = Math.min(100, Math.max(0, (v / 4.0) * 100)).toFixed(1);
    const el = document.getElementById('ipkScoreDisplay');
    if (el) el.textContent = pct + '%';
});

// Category filter
document.querySelectorAll('.filter-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('#skillTable tbody tr').forEach(row => {
            row.style.display = (cat === 'all' || row.dataset.cat === cat) ? '' : 'none';
        });
    });
});

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

// Add 1 row by default
addCert();
addProject();
</script>
</body>
</html>
