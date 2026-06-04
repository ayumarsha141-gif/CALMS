<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
$studentId = $profile['id'];

// Handle add cert
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_cert'])) {
        $certName = trim($_POST['cert_name'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $tier     = (int)($_POST['tier'] ?? 3);
        $status   = $_POST['status'] ?? 'owned';
        $date     = $_POST['obtained_date'] ?? null;
        $score    = $tier === 1 ? 100 : ($tier === 2 ? 75 : 50);
        if ($certName) {
            $stmt = $db->prepare("INSERT INTO student_certifications (student_id, cert_name, provider, tier, score, status, obtained_date) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$studentId, $certName, $provider, $tier, $score, $status, $date ?: null]);
        }
        header('Location: certifications.php?saved=1');
        exit;
    }
    if (isset($_POST['delete_cert'])) {
        $certId = (int)$_POST['cert_id'];
        $stmt = $db->prepare("DELETE FROM student_certifications WHERE id = ? AND student_id = ?");
        $stmt->execute([$certId, $studentId]);
        header('Location: certifications.php?deleted=1');
        exit;
    }
}

// My certifications
$stmt = $db->prepare("SELECT * FROM student_certifications WHERE student_id = ? ORDER BY tier ASC, obtained_date DESC");
$stmt->execute([$studentId]);
$myCerts = $stmt->fetchAll();

// All certifications catalog
$stmt = $db->query("SELECT * FROM certifications ORDER BY tier ASC, score DESC");
$catalog = $stmt->fetchAll();

$myOwnedNames = array_column(array_filter($myCerts, fn($c) => $c['status']==='owned'), 'cert_name');

$activePage = 'certifications';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikasi — CALMS</title>
    <meta name="description" content="Kelola dan temukan sertifikasi yang relevan untuk karirmu.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .cert-tabs { display:flex; gap:4px; background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:4px; margin-bottom:24px; width:fit-content; }
        .cert-tab { padding:8px 20px; border-radius:var(--radius-sm); font-size:13px; font-weight:500; cursor:pointer; color:var(--text-muted); transition:var(--transition); background:transparent; border:none; }
        .cert-tab.active { background:rgba(34,211,238,0.1); color:var(--cyan); }
        .cert-section { display:none; }
        .cert-section.active { display:block; }
        .cert-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:16px; }
        .cert-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:20px; transition:var(--transition); position:relative; }
        .cert-card:hover { border-color:var(--border-hover); transform:translateY(-2px); }
        .cert-card-header { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:12px; }
        .cert-card-name { font-size:14px; font-weight:600; line-height:1.4; flex:1; }
        .tier-badge { flex-shrink:0; font-size:10px; padding:3px 8px; border-radius:999px; font-weight:700; }
        .tier-1 { background:rgba(250,204,21,0.15); color:#facc15; border:1px solid rgba(250,204,21,0.3); }
        .tier-2 { background:rgba(148,163,184,0.15); color:#94a3b8; border:1px solid rgba(148,163,184,0.3); }
        .tier-3 { background:rgba(180,120,80,0.15); color:#b47850; border:1px solid rgba(180,120,80,0.3); }
        .cert-provider { font-size:12px; color:var(--text-muted); margin-bottom:10px; }
        .cert-relevance { font-size:11px; color:var(--text-secondary); line-height:1.5; }
        .cert-actions { margin-top:14px; display:flex; gap:8px; }
        .btn-add-cert { font-size:11px; padding:6px 14px; background:rgba(34,211,238,0.08); border:1px solid rgba(34,211,238,0.2); color:var(--cyan); border-radius:999px; cursor:pointer; transition:var(--transition); font-family:var(--font-sans); font-weight:600; }
        .btn-add-cert:hover { background:rgba(34,211,238,0.15); }
        .owned-badge { font-size:11px; padding:6px 12px; background:rgba(16,185,129,0.1); color:#10b981; border-radius:999px; border:1px solid rgba(16,185,129,0.2); }
        .add-form-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:24px; }
        .add-form-title { font-size:15px; font-weight:600; margin-bottom:18px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-label { font-size:12px; color:var(--text-muted); font-weight:500; }
        .form-input { background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:9px 12px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); transition:var(--transition); }
        .form-input:focus { outline:none; border-color:var(--cyan); }
        .my-cert-row { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:16px 20px; margin-bottom:10px; display:flex; align-items:center; gap:16px; }
        .my-cert-info { flex:1; }
        .my-cert-name { font-size:14px; font-weight:600; margin-bottom:2px; }
        .my-cert-meta { font-size:12px; color:var(--text-muted); }
        .btn-delete { background:rgba(239,68,68,0.08); border:1px solid rgba(239,68,68,0.2); color:#ef4444; padding:5px 12px; border-radius:999px; font-size:11px; cursor:pointer; font-family:var(--font-sans); transition:var(--transition); }
        .btn-delete:hover { background:rgba(239,68,68,0.15); }
        .alert-success { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); color:#10b981; padding:12px 18px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:13px; }
        .cert-score-bar { height:4px; background:var(--border); border-radius:999px; margin-top:10px; overflow:hidden; }
        .cert-score-fill { height:100%; border-radius:999px; }
        .empty-my-cert { text-align:center; padding:40px; color:var(--text-muted); font-size:14px; }
        @media(max-width:640px){ .form-row{grid-template-columns:1fr;} }
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
                <h1 class="page-title">Sertifikasi</h1>
                <p class="page-sub">Kelola sertifikasi yang kamu miliki & temukan yang baru</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge"><?= count($myOwnedNames) ?> Dimiliki</span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Sertifikasi berhasil ditambahkan!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert-success" style="color:#f59e0b;background:rgba(245,158,11,0.1);border-color:rgba(245,158,11,0.3);">🗑️ Sertifikasi dihapus.</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="cert-tabs">
        <button class="cert-tab active" onclick="switchTab('my-certs',this)" id="tab-my">Sertifikasiku (<?= count($myCerts) ?>)</button>
        <button class="cert-tab" onclick="switchTab('catalog',this)" id="tab-catalog">Katalog Sertifikasi</button>
        <button class="cert-tab" onclick="switchTab('add-cert',this)" id="tab-add">+ Tambah</button>
    </div>

    <!-- My Certs -->
    <div class="cert-section active" id="my-certs">
        <?php if (empty($myCerts)): ?>
        <div class="empty-my-cert">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;color:var(--text-muted)"><circle cx="12" cy="8" r="6"/><path d="M9 12l2 2 4-4m-8 8h10"/></svg>
            <p>Belum ada sertifikasi.</p>
            <button onclick="switchTab('add-cert',document.getElementById('tab-add'))" class="btn-add-cert" style="margin-top:12px;font-size:13px;padding:8px 20px">+ Tambah Sertifikasi</button>
        </div>
        <?php else: ?>
        <?php foreach ($myCerts as $cert):
            $tc = $cert['tier'] === 1 ? '#facc15' : ($cert['tier'] === 2 ? '#94a3b8' : '#b47850');
            $tl = $cert['tier'] === 1 ? 'Internasional' : ($cert['tier'] === 2 ? 'Nasional BNSP' : 'Kursus Online');
        ?>
        <div class="my-cert-row">
            <div style="width:40px;height:40px;border-radius:50%;background:<?= $tc ?>22;border:1px solid <?= $tc ?>44;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <svg width="18" height="18" fill="none" stroke="<?= $tc ?>" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M9 12l2 2 4-4"/></svg>
            </div>
            <div class="my-cert-info">
                <div class="my-cert-name"><?= htmlspecialchars($cert['cert_name']) ?></div>
                <div class="my-cert-meta">
                    <?= htmlspecialchars($cert['provider'] ?? '-') ?> · <?= $tl ?>
                    <?php if ($cert['obtained_date']): ?> · <?= date('M Y', strtotime($cert['obtained_date'])) ?><?php endif; ?>
                    <?php if ($cert['status'] === 'recommended'): ?> · <span style="color:#f59e0b">Rekomendasi</span><?php else: ?> · <span style="color:#10b981">Dimiliki</span><?php endif; ?>
                </div>
            </div>
            <form method="POST" onsubmit="return confirm('Hapus sertifikasi ini?')">
                <input type="hidden" name="cert_id" value="<?= $cert['id'] ?>">
                <button type="submit" name="delete_cert" class="btn-delete">Hapus</button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Catalog -->
    <div class="cert-section" id="catalog">
        <?php
        $tiers = [1 => ['label'=>'🏆 Tier 1 — Internasional','color'=>'#facc15'], 2=>['label'=>'🎖️ Tier 2 — Nasional BNSP','color'=>'#94a3b8'], 3=>['label'=>'📚 Tier 3 — Kursus Online','color'=>'#b47850']];
        $catalogByTier = [];
        foreach ($catalog as $c) $catalogByTier[$c['tier']][] = $c;
        foreach ($tiers as $t => $info): ?>
        <div style="margin-bottom:28px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:14px;color:<?= $info['color'] ?>"><?= $info['label'] ?></div>
            <div class="cert-grid">
                <?php foreach ($catalogByTier[$t] ?? [] as $cert):
                    $isOwned = in_array($cert['cert_name'], $myOwnedNames);
                ?>
                <div class="cert-card">
                    <div class="cert-card-header">
                        <div class="cert-card-name"><?= htmlspecialchars($cert['cert_name']) ?></div>
                        <span class="tier-badge tier-<?= $t ?>">T<?= $t ?></span>
                    </div>
                    <div class="cert-provider"><?= htmlspecialchars($cert['provider']) ?></div>
                    <div class="cert-relevance">🎯 <?= htmlspecialchars($cert['career_relevance'] ?? '-') ?></div>
                    <div class="cert-score-bar">
                        <div class="cert-score-fill" style="width:<?= $cert['score'] ?>%;background:<?= $info['color'] ?>"></div>
                    </div>
                    <div class="cert-actions">
                        <?php if ($isOwned): ?>
                        <span class="owned-badge">✓ Dimiliki</span>
                        <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="cert_name" value="<?= htmlspecialchars($cert['cert_name']) ?>">
                            <input type="hidden" name="provider" value="<?= htmlspecialchars($cert['provider']) ?>">
                            <input type="hidden" name="tier" value="<?= $t ?>">
                            <input type="hidden" name="status" value="owned">
                            <button type="submit" name="add_cert" class="btn-add-cert">+ Tambah ke Daftarku</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Add Form -->
    <div class="cert-section" id="add-cert">
        <div class="add-form-card">
            <div class="add-form-title">Tambah Sertifikasi Manual</div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Sertifikasi *</label>
                        <input class="form-input" type="text" name="cert_name" placeholder="e.g. AWS Certified Cloud Practitioner" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Provider / Penerbit</label>
                        <input class="form-input" type="text" name="provider" placeholder="e.g. Amazon Web Services">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Tier</label>
                        <select class="form-input" name="tier">
                            <option value="1">Tier 1 — Internasional</option>
                            <option value="2">Tier 2 — Nasional BNSP</option>
                            <option value="3" selected>Tier 3 — Kursus Online</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option value="owned">Dimiliki</option>
                            <option value="recommended">Target / Rekomendasi</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label">Tanggal Diperoleh (opsional)</label>
                    <input class="form-input" type="date" name="obtained_date" style="max-width:250px;">
                </div>
                <button type="submit" name="add_cert" class="save-btn">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Tambah Sertifikasi
                </button>
            </form>
        </div>
    </div>
</main>

<script src="main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

function switchTab(sectionId, btn) {
    document.querySelectorAll('.cert-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.cert-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    btn.classList.add('active');
}
</script>
</body>
</html>
