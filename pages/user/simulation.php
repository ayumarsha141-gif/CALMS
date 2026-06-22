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

if (isset($_GET['delete_project'])) {
    $db->prepare("DELETE FROM student_projects WHERE id=? AND student_id=?")->execute([$_GET['delete_project'], $studentId]);
    header("Location: simulation.php");
    exit;
}

if (isset($_GET['delete_cert'])) {
    $db->prepare("DELETE FROM student_certifications WHERE id=? AND student_id=?")->execute([$_GET['delete_cert'], $studentId]);
    header("Location: simulation.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_single_project'])) {
    $name  = trim($_POST['project_name'] ?? '');
    $scale = $_POST['project_scale'] ?? 'kecil';
    if ($name !== '') {
        $score = $scale == 'besar' ? 40 : 20;
        $db->prepare("INSERT INTO student_projects (student_id, project_name, scale, score) VALUES (?,?,?,?)")
            ->execute([$studentId, $name, $scale, $score]);
    }
    header("Location: simulation.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_single_cert'])) {
    $name     = trim($_POST['cert_name'] ?? '');
    $provider = trim($_POST['cert_provider'] ?? '');
    $tier     = (int)($_POST['cert_tier'] ?? 3);
    if ($name !== '') {
        $score = $tier == 1 ? 100 : ($tier == 2 ? 75 : 50);
        $db->prepare("INSERT INTO student_certifications (student_id, cert_name, provider, tier, score, status, obtained_date) VALUES (?,?,?,?,?,?,?)")
            ->execute([$studentId, $name, $provider, $tier, $score, 'owned', null]);
    }
    header("Location: simulation.php");
    exit;
}

$targetCareer = $profile['target_career'] ?? '';

// Bobot 3 komponen: Skill Praktis, Portofolio, Sertifikasi
$w1 = 0.50; 
$w2 = 0.30; 
$w3 = 0.20; 

$tier1 = 85;
$tier2 = 70;
$tier3 = 55;

$stmt     = $db->query("SELECT id, position_name FROM career_positions ORDER BY position_name ASC");
$allRoles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$careerId = array_search($targetCareer, $allRoles, true);
if (!$careerId && !empty($allRoles)) {
    $careerId     = array_key_first($allRoles);
    $targetCareer = $allRoles[$careerId];
    $db->prepare("UPDATE mahasiswa_profiles SET target_career = ? WHERE id = ?")->execute([$targetCareer, $studentId]);
}

$row = $db->prepare("
    SELECT AVG(LEAST(ss.student_level / NULLIF(sk.industry_level,0), 1)) * 100 AS readiness
    FROM career_skills cs
    JOIN skills sk ON sk.skill_name COLLATE utf8mb4_unicode_ci = cs.skill_name COLLATE utf8mb4_unicode_ci
    LEFT JOIN student_skills ss ON ss.skill_id = sk.id AND ss.student_id = ?
    WHERE cs.career_id = ?
");
$row->execute([$studentId, $careerId]);
$data = $row->fetch();
$c1_score = round($data['readiness'] ?? 0, 2);

$stmt = $db->prepare("SELECT * FROM student_projects WHERE student_id=? ORDER BY id DESC");
$stmt->execute([$studentId]);
$projects = $stmt->fetchAll();

$stmt = $db->prepare("SELECT * FROM student_certifications WHERE student_id=? ORDER BY id DESC");
$stmt->execute([$studentId]);
$certs = $stmt->fetchAll();

$totalProject = count($projects);
$c2_score = min(100, $totalProject * 20);
$totalCertScore = array_sum(array_column($certs, 'score'));
$c3_score = min(100, $totalCertScore);

$result = null;
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_simulation'])) {
    $total_score = ($c1_score * $w1) + ($c2_score * $w2) + ($c3_score * $w3);
    if ($total_score >= 85) { $verdictClass = 'verdict-green'; $verdictLabel = 'Sangat Siap'; }
    elseif ($total_score >= 70) { $verdictClass = 'verdict-blue'; $verdictLabel = 'Siap'; }
    elseif ($total_score >= 55) { $verdictClass = 'verdict-amber'; $verdictLabel = 'Perlu Pengembangan'; }
    else { $verdictClass = 'verdict-red'; $verdictLabel = 'Belum Siap'; }
    $result = ['total_score' => $total_score, 'c1_score' => $c1_score, 'c2_score' => $c2_score, 'c3_score' => $c3_score, 'verdictClass' => $verdictClass, 'verdictLabel' => $verdictLabel];
    $success = true;

    $probability = round($total_score / 100, 4);

    $stmt = $db->prepare("
    INSERT INTO simulations
    (
        student_id,
        target_role,
        ipk_score,
        skill_score,
        cert_score,
        portfolio_score,
        probability_score
    )
    VALUES (?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $studentId,
        $targetCareer,
        0,
        round($c1_score,2),
        round($c3_score,2),
        round($c2_score,2),
        $probability
    ]);
}

$activePage = 'simulation';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simulasi Rekrutmen (SAW) — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .sim-page { display: flex; flex-direction: column; gap: 2rem; }
        .predictor-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        @media(max-width: 700px) { .predictor-grid { grid-template-columns: repeat(2, 1fr); } }
        @media(max-width: 480px) { .predictor-grid { grid-template-columns: 1fr; } }

        .predictor-card {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px;
            padding: 1.25rem;
            text-align: center;
            transition: border-color .2s;
        }
        .predictor-card:hover { border-color: rgba(34,211,238,.2); }
        .pred-label { font-size: .7rem; color: #64748b; text-transform: uppercase; letter-spacing: .1em; margin-bottom: .4rem; }
        .pred-score { font-size: 2rem; font-weight: 700; margin: .2rem 0; font-family: 'JetBrains Mono', monospace; }
        .pred-weight { font-size: .72rem; color: #475569; }
        .score-cyan { color: #22d3ee; }
        .score-blue { color: #60a5fa; }
        .score-purple { color: #a78bfa; }
        .score-amber { color: #fbbf24; }

        .result-hero { background: linear-gradient(135deg, #0f172a, #1e293b); border: 1px solid rgba(34,211,238,.2); border-radius: 20px; padding: 2.5rem; display: flex; align-items: center; gap: 2.5rem; flex-wrap: wrap; }
        .result-ring-wrap { position: relative; width: 160px; height: 160px; flex-shrink: 0; }
        .result-ring-wrap svg { width: 160px; height: 160px; }
        .result-ring-center { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .result-prob { font-size: 2.4rem; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
        .result-prob-label { font-size: .72rem; color: #64748b; text-transform: uppercase; }
        .result-meta { flex: 1; min-width: 200px; }
        .result-meta h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: .5rem; }
        .result-meta p { color: #94a3b8; font-size: .9rem; line-height: 1.6; }
        .result-verdict { display: inline-block; margin-top: .75rem; padding: .5rem 1rem; border-radius: 999px; font-size: .9rem; font-weight: 600; }
        .verdict-green { background: rgba(34,197,94,.15); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
        .verdict-blue { background: rgba(59,130,246,.15); color: #60a5fa; border: 1px solid rgba(59,130,246,.3); }
        .verdict-amber { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
        .verdict-red { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.3); }

        .score-bars { display: flex; flex-direction: column; gap: .8rem; min-width: 220px; flex: 1; }
        .score-bar-row { display: flex; flex-direction: column; gap: .3rem; }
        .score-bar-label { display: flex; justify-content: space-between; font-size: .8rem; }
        .score-bar-track { height: 8px; background: rgba(255,255,255,.06); border-radius: 4px; overflow: hidden; }
        .score-bar-fill { height: 100%; border-radius: 4px; transition: width .6s ease; }
        .fill-cyan { background: linear-gradient(90deg, #22d3ee, #06b6d4); }
        .fill-blue { background: linear-gradient(90deg, #60a5fa, #3b82f6); }
        .fill-purple { background: linear-gradient(90deg, #a78bfa, #8b5cf6); }
        .fill-amber { background: linear-gradient(90deg, #fbbf24, #f59e0b); }

        .sim-section { background: var(--bg-card); border: 1px solid rgba(255,255,255,.07); border-radius: 16px; overflow: hidden; }
        .sim-section-head { display: flex; align-items: center; gap: .75rem; padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.06); }
        .sim-section-num { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 700; background: linear-gradient(135deg, #22d3ee, #3b82f6); color: #000; flex-shrink: 0; }
        .sim-section-title { font-weight: 600; font-size: 1rem; }
        .sim-section-sub { font-size: .75rem; color: #64748b; margin-left: auto; }
        .sim-section-body { padding: 1.5rem; }

        .tier-badge { padding: .25rem .6rem; border-radius: 6px; font-size: .7rem; font-weight: 600; }
        .tier-1 { background: rgba(34,211,238,.15); color: #22d3ee; }
        .tier-2 { background: rgba(96,165,250,.15); color: #60a5fa; }
        .tier-3 { background: rgba(148,163,184,.12); color: #94a3b8; }

        .add-item-form {
            background: rgba(255,255,255,.02);
            border: 1px dashed rgba(34,211,238,.25);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: grid;
            gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .add-form-proj { grid-template-columns: 1fr auto auto; }
        .add-form-cert { grid-template-columns: 1fr 1fr auto auto; }
        @media(max-width: 650px) {
            .add-form-proj { grid-template-columns: 1fr 1fr; }
            .add-form-cert { grid-template-columns: 1fr 1fr; }
        }
        .add-item-form input,
        .add-item-form select {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 9px;
            color: #e2e8f0;
            padding: .6rem .85rem;
            font-size: .85rem;
            font-family: 'Space Grotesk', sans-serif;
            transition: border-color .2s;
        }
        .add-item-form input:focus,
        .add-item-form select:focus { outline: none; border-color: #22d3ee; }
        .btn-add-submit {
            padding: .6rem 1.2rem;
            background: linear-gradient(135deg, #22d3ee, #3b82f6);
            border: none;
            border-radius: 9px;
            color: #000;
            font-weight: 700;
            font-size: .82rem;
            cursor: pointer;
            font-family: 'Space Grotesk', sans-serif;
            white-space: nowrap;
            transition: transform .15s, box-shadow .15s;
            display: flex; align-items: center; gap: .4rem;
        }
        .btn-add-submit:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(34,211,238,.3); }

        .saved-items { display: flex; flex-direction: column; gap: .6rem; }
        .saved-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 10px;
            padding: .75rem 1rem;
            transition: border-color .2s;
        }
        .saved-item:hover { border-color: rgba(255,255,255,.12); }
        .saved-item-icon {
            width: 34px; height: 34px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .icon-proj { background: rgba(251,191,36,.12); }
        .icon-cert { background: rgba(34,211,238,.1); }
        .saved-item-info { flex: 1; min-width: 0; }
        .saved-item-name { font-weight: 600; font-size: .88rem; color: #e2e8f0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .saved-item-meta { font-size: .73rem; color: #64748b; margin-top: .15rem; }
        .saved-item-badge { flex-shrink: 0; }
        .scale-badge {
            padding: .2rem .55rem;
            border-radius: 6px;
            font-size: .7rem;
            font-weight: 600;
        }
        .scale-besar { background: rgba(251,191,36,.15); color: #fbbf24; }
        .scale-kecil { background: rgba(148,163,184,.1); color: #94a3b8; }
        .score-pill {
            padding: .2rem .6rem;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            background: rgba(34,211,238,.1);
            color: #22d3ee;
        }
        .btn-del {
            width: 30px; height: 30px;
            border-radius: 7px;
            border: none;
            background: rgba(239,68,68,.1);
            color: #f87171;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem;
            flex-shrink: 0;
            transition: background .2s;
            text-decoration: none;
        }
        .btn-del:hover { background: rgba(239,68,68,.25); }
        .empty-state {
            text-align: center;
            padding: 2rem 1rem;
            color: #475569;
            font-size: .85rem;
        }
        .empty-state svg { margin-bottom: .5rem; opacity: .3; }

        .info-banner { background: rgba(34,211,238,.05); border: 1px solid rgba(34,211,238,.2); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; color: var(--text-secondary); font-size: 13px; }

        .sim-submit-bar { display: flex; justify-content: flex-end; gap: 1rem; align-items: center; flex-wrap: wrap; }
        .btn-run {
            padding: .85rem 2.2rem;
            background: linear-gradient(135deg, #22d3ee, #3b82f6);
            border: none;
            border-radius: 13px;
            color: #000;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            font-family: 'Space Grotesk', sans-serif;
            transition: transform .2s, box-shadow .2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-run:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(34,211,238,.35); }

        .hint-pills { display: flex; gap: .75rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .hint-pill { padding: .5rem .9rem; border-radius: 9px; font-size: .78rem; display: flex; align-items: center; gap: .4rem; }
    </style>
</head>
<body class="dashboard-body">
<?php include '../../includes/sidebar.php'; ?>
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Simulasi Rekrutmen</h1>
                <p class="page-sub">Simple Additive Weighting (SAW) — 3 Komponen</p>
            </div>
        </div>
    </div>

    <div class="sim-page">

        <?php if ($success && $result):
            $ts = round($result['total_score'], 1);
            $circumference = 2 * M_PI * 58;
            $offset = $circumference - ($ts / 100) * $circumference;
            $color = $ts >= $tier1 ? '#4ade80' : ($ts >= $tier2 ? '#60a5fa' : ($ts >= $tier3 ? '#fbbf24' : '#f87171'));
        ?>
        <div class="result-hero">
            <div class="result-ring-wrap">
                <svg viewBox="0 0 140 140">
                    <circle cx="70" cy="70" r="58" fill="none" stroke="#1e293b" stroke-width="10"/>
                    <circle cx="70" cy="70" r="58" fill="none" stroke="<?= $color ?>" stroke-width="10"
                        stroke-dasharray="<?= round($circumference, 2) ?>" stroke-dashoffset="<?= round($offset, 2) ?>"
                        stroke-linecap="round" transform="rotate(-90 70 70)"/>
                </svg>
                <div class="result-ring-center">
                    <span class="result-prob" style="color:<?= $color ?>"><?= $ts ?></span>
                    <span class="result-prob-label">Total Skor</span>
                </div>
            </div>
            <div class="result-meta">
                <h2>Skor Kelulusan SAW</h2>
                <p>Target: <strong><?= htmlspecialchars($targetCareer) ?></strong><br>
                Skill Praktis (<?= $w1 * 100 ?>%) + Portofolio (<?= $w2 * 100 ?>%) + Sertifikasi (<?= $w3 * 100 ?>%)</p>
                <span class="result-verdict <?= $result['verdictClass'] ?>"><?= $result['verdictLabel'] ?></span>
            </div>
            <div class="score-bars">
                <?php
                $bars = [
                    ['C1: Skill Praktis', $result['c1_score'], 'fill-cyan', "W1: {$w1}"],
                    ['C2: Portofolio', $result['c2_score'], 'fill-amber', "W2: {$w2}"],
                    ['C3: Sertifikasi', $result['c3_score'], 'fill-purple', "W3: {$w3}"],
                ];
                foreach ($bars as [$label, $score, $cls, $wLabel]): ?>
                <div class="score-bar-row">
                    <div class="score-bar-label">
                        <span><?= $label ?> (<?= $wLabel ?>)</span>
                        <span><?= round($score, 1) ?></span>
                    </div>
                    <div class="score-bar-track">
                        <div class="score-bar-fill <?= $cls ?>" style="width:<?= min(100, $score) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="text-align:center">
            <a href="simulation.php" class="btn-run">↺ Hitung Ulang</a>
        </div>
        <?php endif; ?>

        <div class="info-banner">
            <strong style="color:var(--cyan)">Info:</strong> Skor Skill Praktis diambil otomatis dari data Skill Gap untuk karir
            <strong><?= htmlspecialchars($targetCareer) ?></strong>.
            Ubah karir atau perbarui level skill di halaman <a href="skill_gap.php" style="color:var(--cyan)">Skill Gap</a>.
        </div>

        <div class="predictor-grid">
            <div class="predictor-card">
                <div class="pred-label">C1: Skill Praktis</div>
                <div class="pred-score score-cyan"><?= round($c1_score, 1) ?></div>
                <div class="pred-weight">Dari Skill Gap</div>
            </div>
            <div class="predictor-card">
                <div class="pred-label">C2: Portofolio</div>
                <div class="pred-score score-amber"><?= round($c2_score, 1) ?></div>
                <div class="pred-weight"><?= count($projects) ?> Proyek</div>
            </div>
            <div class="predictor-card">
                <div class="pred-label">C3: Sertifikasi</div>
                <div class="pred-score score-purple"><?= round($c3_score, 1) ?></div>
                <div class="pred-weight"><?= count($certs) ?> Sertifikat</div>
            </div>
        </div>

        <!-- portopolio -->
        <div class="sim-section">
            <div class="sim-section-head">
                <div class="sim-section-num">C2</div>
                <div>
                    <div class="sim-section-title">Portofolio Proyek</div>
                </div>
                <div class="sim-section-sub">Bobot: <?= $w2 * 100 ?>%</div>
            </div>
            <div class="sim-section-body">
              
                <div class="hint-pills">
                    <div class="hint-pill" style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2)">
                        <span class="scale-badge scale-besar">Besar</span>
                        <span style="color:#94a3b8">TA / Client / Teamwork</span>
                        <strong style="color:#fbbf24">= 40 poin</strong>
                    </div>
                    <div class="hint-pill" style="background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.15)">
                        <span class="scale-badge scale-kecil">Kecil</span>
                        <span style="color:#94a3b8">Tugas Harian</span>
                        <strong style="color:#94a3b8">= 20 poin</strong>
                    </div>
                </div>

                <!-- tambahkan project -->
                <form method="POST">
                    <input type="hidden" name="add_single_project" value="1">
                    <div class="add-item-form add-form-proj">
                        <input
                            type="text"
                            name="project_name"
                            placeholder="Nama proyek..."
                            required
                        >
                        <select name="project_scale">
                            <option value="besar">🏆 Besar (40 poin)</option>
                            <option value="kecil" selected>📄 Kecil (20 poin)</option>
                        </select>
                        <button type="submit" class="btn-add-submit">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Simpan
                        </button>
                    </div>
                </form>

                <!-- simpan list project -->
                <?php if ($projects): ?>
                <div class="saved-items">
                    <?php foreach ($projects as $p): ?>
                    <div class="saved-item">
                        <div class="saved-item-icon icon-proj">
                            <?= $p['scale'] == 'besar' ? '🏆' : '📄' ?>
                        </div>
                        <div class="saved-item-info">
                            <div class="saved-item-name"><?= htmlspecialchars($p['project_name']) ?></div>
                            <div class="saved-item-meta">Ditambahkan ke portofolio</div>
                        </div>
                        <span class="scale-badge <?= $p['scale'] == 'besar' ? 'scale-besar' : 'scale-kecil' ?>">
                            <?= ucfirst($p['scale']) ?>
                        </span>
                        <span class="score-pill">+<?= $p['score'] ?> poin</span>
                        <a
                            href="?delete_project=<?= $p['id'] ?>"
                            class="btn-del"
                            title="Hapus"
                            onclick="return confirm('Hapus proyek \'<?= addslashes($p['project_name']) ?>\'?')"
                        >✕</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="15" x2="12" y2="15"/></svg>
                    <div>Belum ada proyek. Tambahkan proyek di atas!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- sertip -->
        <div class="sim-section">
            <div class="sim-section-head">
                <div class="sim-section-num">C3</div>
                <div>
                    <div class="sim-section-title">Sertifikasi Profesional</div>
                </div>
                <div class="sim-section-sub">Bobot: <?= $w3 * 100 ?>%</div>
            </div>
            <div class="sim-section-body">
              
                <div class="hint-pills">
                    <div class="hint-pill" style="background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.2)">
                        <span class="tier-badge tier-1">Tier 1</span>
                        <span style="color:#94a3b8">Internasional</span>
                        <strong style="color:#22d3ee">= 100 poin</strong>
                    </div>
                    <div class="hint-pill" style="background:rgba(96,165,250,.08);border:1px solid rgba(96,165,250,.2)">
                        <span class="tier-badge tier-2">Tier 2</span>
                        <span style="color:#94a3b8">Nasional BNSP</span>
                        <strong style="color:#60a5fa">= 75 poin</strong>
                    </div>
                    <div class="hint-pill" style="background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.15)">
                        <span class="tier-badge tier-3">Tier 3</span>
                        <span style="color:#94a3b8">Kursus Online</span>
                        <strong style="color:#94a3b8">= 50 poin</strong>
                    </div>
                </div>

                <!-- tambahkan sertip -->
                <form method="POST">
                    <input type="hidden" name="add_single_cert" value="1">
                    <div class="add-item-form add-form-cert">
                        <input
                            type="text"
                            name="cert_name"
                            placeholder="Nama sertifikat..."
                            required
                        >
                        <input
                            type="text"
                            name="cert_provider"
                            placeholder="Penerbit (Google, BNSP, dll)"
                        >
                        <select name="cert_tier">
                            <option value="1">🌐 Tier 1 — Internasional (100)</option>
                            <option value="2">🏅 Tier 2 — Nasional BNSP (75)</option>
                            <option value="3" selected>📜 Tier 3 — Kursus (50)</option>
                        </select>
                        <button type="submit" class="btn-add-submit">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Simpan
                        </button>
                    </div>
                </form>

                <!-- simpan list sertip -->
                <?php if ($certs): ?>
                <div class="saved-items">
                    <?php foreach ($certs as $c):
                        $tierColors = [1 => 'tier-1', 2 => 'tier-2', 3 => 'tier-3'];
                        $tierEmoji  = [1 => '🌐', 2 => '🏅', 3 => '📜'];
                    ?>
                    <div class="saved-item">
                        <div class="saved-item-icon icon-cert">
                            <?= $tierEmoji[$c['tier']] ?? '📜' ?>
                        </div>
                        <div class="saved-item-info">
                            <div class="saved-item-name"><?= htmlspecialchars($c['cert_name']) ?></div>
                            <div class="saved-item-meta">
                                <?= htmlspecialchars($c['provider'] ?: '—') ?>
                                <?php if (($c['status'] ?? 'owned') === 'recommended'): ?>
                                · <span style="color:#f59e0b;font-size:.7rem">Target</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="tier-badge <?= $tierColors[$c['tier']] ?? 'tier-3' ?>">Tier <?= $c['tier'] ?></span>
                        <span class="score-pill">+<?= $c['score'] ?> poin</span>
                        <a
                            href="?delete_cert=<?= $c['id'] ?>"
                            class="btn-del"
                            title="Hapus"
                            onclick="return confirm('Hapus sertifikat \'<?= addslashes($c['cert_name']) ?>\'?')"
                        >✕</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M8 14l-2 7 6-3 6 3-2-7"/></svg>
                    <div>Belum ada sertifikat. Tambahkan sertifikat di atas!</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!--HITUNG SIMULASI -->
        <form method="POST">
            <input type="hidden" name="run_simulation" value="1">
            <div class="sim-submit-bar">
                <div style="color:#64748b;font-size:.85rem">
                    <?= count($projects) ?> proyek · <?= count($certs) ?> sertifikat
                </div>
                <button type="submit" class="btn-run">
                    ⚡ Hitung Simulasi SAW
                </button>
            </div>
        </form>

    </div>
</main>

<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});
</script>
</body>
</html>