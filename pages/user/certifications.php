<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
$studentId = $profile['id'];

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
        header('Location: certifications.php?saved=cert&tab=my-certs');
        exit;
    }
   
    if (isset($_POST['delete_cert'])) {
        $certId = (int)$_POST['cert_id'];
        $stmt = $db->prepare("DELETE FROM student_certifications WHERE id = ? AND student_id = ?");
        $stmt->execute([$certId, $studentId]);
        header('Location: certifications.php?deleted=cert&tab=my-certs');
        exit;
    }

    if (isset($_POST['add_project'])) {
        $name  = trim($_POST['project_name'] ?? '');
        $scale = $_POST['project_scale'] ?? 'kecil';
        $desc  = trim($_POST['project_desc'] ?? '');
        $date  = $_POST['project_date'] ?? null;
        if ($name !== '') {
            $score = $scale === 'besar' ? 40 : 20;
            $stmt = $db->prepare("INSERT INTO student_projects (student_id, project_name, scale, score, description, created_at) VALUES (?,?,?,?,?,?)");
           
            try {
                $stmt->execute([$studentId, $name, $scale, $score, $desc, $date ?: date('Y-m-d')]);
            } catch (\PDOException $e) {
               
                $db->prepare("INSERT INTO student_projects (student_id, project_name, scale, score) VALUES (?,?,?,?)")
                    ->execute([$studentId, $name, $scale, $score]);
            }
        }
        header('Location: certifications.php?saved=project&tab=my-projects');
        exit;
    }
   
    if (isset($_POST['delete_project'])) {
        $projId = (int)$_POST['project_id'];
        $db->prepare("DELETE FROM student_projects WHERE id = ? AND student_id = ?")->execute([$projId, $studentId]);
        header('Location: certifications.php?deleted=project&tab=my-projects');
        exit;
    }
}

$stmt = $db->prepare("SELECT * FROM student_certifications WHERE student_id = ? ORDER BY tier ASC, obtained_date DESC");
$stmt->execute([$studentId]);
$myCerts = $stmt->fetchAll();
$myOwnedNames = array_column(array_filter($myCerts, fn($c) => $c['status']==='owned'), 'cert_name');

$catalog = [];
try {
    $stmt = $db->query("SELECT * FROM certifications ORDER BY tier ASC, score DESC");
    $catalog = $stmt->fetchAll();
} catch (\PDOException $e) {  }

$stmt = $db->prepare("SELECT * FROM student_projects WHERE student_id = ? ORDER BY id DESC");
$stmt->execute([$studentId]);
$myProjects = $stmt->fetchAll();

$activeTab = $_GET['tab'] ?? 'my-certs';
$validTabs = ['my-certs','catalog','add-cert','my-projects','add-project'];
if (!in_array($activeTab, $validTabs)) $activeTab = 'my-certs';

$activePage = 'certifications';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sertifikasi & Portofolio — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        
        .tab-groups { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; align-items: center; }
        .tab-group-label { font-size: 11px; color: #475569; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; margin-right: 4px; }
        .tab-strip { display: flex; gap: 3px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 4px; }
        .tab-strip + .tab-strip { margin-left: 8px; }
        .cert-tab { padding: 8px 18px; border-radius: 9px; font-size: 13px; font-weight: 500; cursor: pointer; color: var(--text-muted); transition: all .2s; background: transparent; border: none; font-family: 'Space Grotesk', sans-serif; white-space: nowrap; }
        .cert-tab.active { color: #fff; }
        .cert-tab.tab-cert.active { background: rgba(34,211,238,.15); color: #22d3ee; }
        .cert-tab.tab-proj.active { background: rgba(251,191,36,.12); color: #fbbf24; }
        .cert-tab:hover:not(.active) { background: rgba(255,255,255,.05); color: var(--text-secondary); }
        .tab-divider { width: 1px; height: 28px; background: var(--border); margin: 0 4px; align-self: center; }

       
        .cert-section { display: none; animation: fadeIn .2s ease; }
        .cert-section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

       
        .cert-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px,1fr)); gap: 16px; }
        .cert-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; transition: all .2s; }
        .cert-card:hover { border-color: var(--border-hover); transform: translateY(-2px); }
        .cert-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .cert-card-name { font-size: 14px; font-weight: 600; line-height: 1.4; flex: 1; }
        .tier-badge { flex-shrink: 0; font-size: 10px; padding: 3px 8px; border-radius: 999px; font-weight: 700; }
        .tier-1 { background: rgba(250,204,21,.15); color: #facc15; border: 1px solid rgba(250,204,21,.3); }
        .tier-2 { background: rgba(148,163,184,.15); color: #94a3b8; border: 1px solid rgba(148,163,184,.3); }
        .tier-3 { background: rgba(180,120,80,.15); color: #b47850; border: 1px solid rgba(180,120,80,.3); }
        .cert-provider { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
        .cert-relevance { font-size: 11px; color: var(--text-secondary); line-height: 1.5; }
        .cert-actions { margin-top: 14px; display: flex; gap: 8px; }
        .cert-score-bar { height: 4px; background: var(--border); border-radius: 999px; margin-top: 10px; overflow: hidden; }
        .cert-score-fill { height: 100%; border-radius: 999px; }

        .add-form-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 28px; margin-bottom: 24px; }
        .add-form-title { font-size: 15px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .add-form-title-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-row-3 { grid-template-columns: 1fr 1fr 1fr; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-label { font-size: 12px; color: var(--text-muted); font-weight: 600; letter-spacing: .04em; }
        .form-input { background: var(--bg-secondary); border: 1px solid var(--border); color: var(--text-primary); padding: 10px 13px; border-radius: 10px; font-size: 13px; font-family: 'Space Grotesk', sans-serif; transition: all .2s; }
        .form-input:focus { outline: none; border-color: var(--cyan); box-shadow: 0 0 0 3px rgba(34,211,238,.08); }
        .form-input-proj:focus { border-color: #fbbf24; box-shadow: 0 0 0 3px rgba(251,191,36,.08); }
        .form-textarea { min-height: 80px; resize: vertical; }

        .btn-add-cert { font-size: 12px; padding: 7px 16px; background: rgba(34,211,238,.08); border: 1px solid rgba(34,211,238,.2); color: var(--cyan); border-radius: 999px; cursor: pointer; transition: all .2s; font-family: 'Space Grotesk', sans-serif; font-weight: 600; }
        .btn-add-cert:hover { background: rgba(34,211,238,.15); border-color: rgba(34,211,238,.4); }
        .btn-submit-cert { padding: 11px 26px; background: linear-gradient(135deg,#22d3ee,#3b82f6); border: none; color: #000; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Space Grotesk', sans-serif; transition: all .2s; }
        .btn-submit-cert:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(34,211,238,.3); }
        .btn-submit-proj { padding: 11px 26px; background: linear-gradient(135deg,#fbbf24,#f59e0b); border: none; color: #000; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Space Grotesk', sans-serif; transition: all .2s; }
        .btn-submit-proj:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(251,191,36,.3); }
        .owned-badge { font-size: 11px; padding: 6px 12px; background: rgba(16,185,129,.1); color: #10b981; border-radius: 999px; border: 1px solid rgba(16,185,129,.2); }

        .my-row { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 16px 20px; margin-bottom: 10px; display: flex; align-items: center; gap: 16px; transition: all .2s; }
        .my-row:hover { border-color: var(--border-hover); transform: translateX(2px); }
        .my-row-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.1rem; }
        .my-row-info { flex: 1; min-width: 0; }
        .my-row-name { font-size: 14px; font-weight: 600; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .my-row-meta { font-size: 12px; color: var(--text-muted); }
        .my-row-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .score-pill-sm { padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
        .pill-cert { background: rgba(34,211,238,.1); color: #22d3ee; border: 1px solid rgba(34,211,238,.2); }
        .pill-proj { background: rgba(251,191,36,.1); color: #fbbf24; border: 1px solid rgba(251,191,36,.2); }
        .scale-pill { padding: 3px 9px; border-radius: 6px; font-size: 11px; font-weight: 600; }
        .scale-besar { background: rgba(251,191,36,.12); color: #fbbf24; }
        .scale-kecil { background: rgba(148,163,184,.1); color: #94a3b8; }

        .btn-delete { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.18); color: #ef4444; padding: 7px 14px; border-radius: 999px; font-size: 11px; cursor: pointer; font-family: 'Space Grotesk', sans-serif; font-weight: 600; transition: all .2s; display: inline-flex; align-items: center; gap: 5px; }
        .btn-delete:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.35); }

        .empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); }
        .empty-state svg { margin: 0 auto 14px; display: block; opacity: .25; }
        .empty-state p { font-size: 14px; margin-bottom: 16px; }

        .alert-success { background: rgba(16,185,129,.1); border: 1px solid rgba(16,185,129,.3); color: #10b981; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .alert-del { background: rgba(245,158,11,.08); border: 1px solid rgba(245,158,11,.25); color: #f59e0b; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .alert-proj { background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.25); color: #fbbf24; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }

        .stats-bar { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .stat-chip { padding: 8px 16px; border-radius: 10px; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 7px; }

        .modal-overlay { position: fixed; inset: 0; z-index: 9999; background: rgba(5,8,20,.75); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity .25s; }
        .modal-overlay.open { opacity: 1; pointer-events: all; }
        .modal-box { background: #0d1424; border: 1px solid rgba(239,68,68,.25); border-radius: 20px; padding: 32px 28px 24px; max-width: 400px; width: 90%; text-align: center; transform: scale(.92) translateY(12px); transition: transform .28s cubic-bezier(.34,1.56,.64,1); box-shadow: 0 24px 60px rgba(0,0,0,.6); }
        .modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
        .modal-icon { width: 58px; height: 58px; margin: 0 auto 16px; background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #ef4444; }
        .modal-title { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
        .modal-sub { font-size: 13px; color: var(--text-muted); line-height: 1.6; }
        .modal-item-name { font-size: 13px; font-weight: 700; color: #ef4444; background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.15); border-radius: 8px; padding: 8px 16px; margin: 12px 0 24px; display: inline-block; max-width: 100%; word-break: break-word; }
        .modal-actions { display: flex; gap: 10px; justify-content: center; }
        .modal-btn-cancel { padding: 10px 24px; background: rgba(255,255,255,.05); border: 1px solid var(--border); color: var(--text-secondary); border-radius: 999px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Space Grotesk', sans-serif; transition: all .2s; }
        .modal-btn-cancel:hover { background: rgba(255,255,255,.09); }
        .modal-btn-delete { padding: 10px 28px; background: linear-gradient(135deg,#ef4444,#dc2626); border: none; color: #fff; border-radius: 999px; font-size: 13px; font-weight: 700; cursor: pointer; font-family: 'Space Grotesk', sans-serif; transition: all .2s; box-shadow: 0 4px 15px rgba(239,68,68,.35); }
        .modal-btn-delete:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(239,68,68,.45); }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .section-title-sm { font-size: 13px; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .07em; }

        @media(max-width: 640px) { .form-row { grid-template-columns: 1fr; } .form-row-3 { grid-template-columns: 1fr; } }
    </style>
</head>
<body class="dashboard-body">

<?php include '../../includes/sidebar.php'; ?>

<div class="modal-overlay" id="deleteModal" role="dialog" aria-modal="true">
    <div class="modal-box">
        <div class="modal-icon">
            <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
        </div>
        <div class="modal-title" id="modalTitle">Hapus Item?</div>
        <div class="modal-sub" id="modalSub">Tindakan ini tidak bisa dibatalkan.</div>
        <div class="modal-item-name" id="modalItemName">—</div>
        <div class="modal-actions">
            <button class="modal-btn-cancel" id="modalCancel">Batal</button>
            <form method="POST" id="deleteForm" style="display:inline">
                <input type="hidden" name="cert_id"    id="modalCertId">
                <input type="hidden" name="project_id" id="modalProjectId">
                <button type="submit" id="modalDeleteBtn" class="modal-btn-delete">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="display:inline-block;vertical-align:middle;margin-right:4px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>
</div>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Sertifikasi & Portofolio</h1>
                <p class="page-sub">Kelola sertifikasi dan proyek portofoliomu</p>
            </div>
        </div>
        <div class="topbar-right">
            <span class="semester-badge"><?= count($myCerts) ?> Sertifikat · <?= count($myProjects) ?> Proyek</span>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ <?= $_GET['saved'] === 'cert' ? 'Sertifikasi' : 'Proyek' ?> berhasil ditambahkan!</div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert-del">🗑️ <?= $_GET['deleted'] === 'cert' ? 'Sertifikasi' : 'Proyek' ?> berhasil dihapus.</div>
    <?php endif; ?>

    <div class="tab-groups">
      
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span class="tab-group-label" style="color:#22d3ee">🏆 Sertifikasi</span>
            <div class="tab-strip">
                <button class="cert-tab tab-cert <?= $activeTab==='my-certs'?'active':'' ?>" onclick="switchTab('my-certs',this)">
                    Milikku <span style="font-size:10px;opacity:.7">(<?= count($myCerts) ?>)</span>
                </button>
                <?php if (!empty($catalog)): ?>
                <button class="cert-tab tab-cert <?= $activeTab==='catalog'?'active':'' ?>" onclick="switchTab('catalog',this)">Katalog</button>
                <?php endif; ?>
                <button class="cert-tab tab-cert <?= $activeTab==='add-cert'?'active':'' ?>" onclick="switchTab('add-cert',this)">+ Tambah</button>
            </div>
        </div>

        <div class="tab-divider"></div>

        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span class="tab-group-label" style="color:#fbbf24">📁 Portofolio</span>
            <div class="tab-strip">
                <button class="cert-tab tab-proj <?= $activeTab==='my-projects'?'active':'' ?>" onclick="switchTab('my-projects',this)">
                    Proyekku <span style="font-size:10px;opacity:.7">(<?= count($myProjects) ?>)</span>
                </button>
                <button class="cert-tab tab-proj <?= $activeTab==='add-project'?'active':'' ?>" onclick="switchTab('add-project',this)">+ Tambah</button>
            </div>
        </div>
    </div>

   <!-- my sertif-->
    <div class="cert-section <?= $activeTab==='my-certs'?'active':'' ?>" id="my-certs">
        <?php if (empty($myCerts)): ?>
        <div class="empty-state">
            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="6"/><path d="M9 12l2 2 4-4m-8 8h10"/></svg>
            <p>Belum ada sertifikasi yang ditambahkan.</p>
            <button onclick="switchTab('add-cert',document.querySelector('.tab-cert:last-child'))" class="btn-add-cert" style="font-size:13px;padding:10px 22px">+ Tambah Sertifikasi</button>
        </div>
        <?php else: ?>
        <div class="stats-bar">
            <?php
            $t1 = count(array_filter($myCerts, fn($c)=>$c['tier']==1));
            $t2 = count(array_filter($myCerts, fn($c)=>$c['tier']==2));
            $t3 = count(array_filter($myCerts, fn($c)=>$c['tier']==3));
            $totalScore = array_sum(array_column($myCerts,'score'));
            ?>
            <?php if($t1): ?><div class="stat-chip" style="background:rgba(250,204,21,.08);border:1px solid rgba(250,204,21,.2);color:#facc15">🏆 <?= $t1 ?> Internasional</div><?php endif; ?>
            <?php if($t2): ?><div class="stat-chip" style="background:rgba(148,163,184,.08);border:1px solid rgba(148,163,184,.2);color:#94a3b8">🎖️ <?= $t2 ?> Nasional BNSP</div><?php endif; ?>
            <?php if($t3): ?><div class="stat-chip" style="background:rgba(180,120,80,.08);border:1px solid rgba(180,120,80,.2);color:#b47850">📚 <?= $t3 ?> Kursus</div><?php endif; ?>
            <div class="stat-chip" style="background:rgba(34,211,238,.07);border:1px solid rgba(34,211,238,.2);color:#22d3ee;margin-left:auto">Total: <?= min(100, $totalScore) ?> / 100 poin simulasi</div>
        </div>
        <?php foreach ($myCerts as $cert):
            $tc  = $cert['tier']===1?'#facc15':($cert['tier']===2?'#94a3b8':'#b47850');
            $tl  = $cert['tier']===1?'Internasional':($cert['tier']===2?'Nasional BNSP':'Kursus Online');
            $tEmoji = $cert['tier']===1?'🌐':($cert['tier']===2?'🏅':'📜');
        ?>
        <div class="my-row">
            <div class="my-row-icon" style="background:<?= $tc ?>18;border:1px solid <?= $tc ?>33">
                <?= $tEmoji ?>
            </div>
            <div class="my-row-info">
                <div class="my-row-name"><?= htmlspecialchars($cert['cert_name']) ?></div>
                <div class="my-row-meta">
                    <?= htmlspecialchars($cert['provider'] ?? '-') ?> · <?= $tl ?>
                    <?php if (!empty($cert['obtained_date'])): ?> · <?= date('M Y', strtotime($cert['obtained_date'])) ?><?php endif; ?>
                    <?php if (($cert['status']??'owned')==='recommended'): ?>
                    · <span style="color:#f59e0b">Target</span>
                    <?php else: ?>
                    · <span style="color:#10b981">Dimiliki</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="my-row-right">
                <span class="tier-badge tier-<?= $cert['tier'] ?>">Tier <?= $cert['tier'] ?></span>
                <span class="score-pill-sm pill-cert">+<?= $cert['score'] ?> poin</span>
                <button type="button" class="btn-delete"
                    onclick="openDeleteModal('cert', <?= $cert['id'] ?>, <?= htmlspecialchars(json_encode($cert['cert_name']), ENT_QUOTES) ?>)">
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Hapus
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- KATALOG-->
    <?php if (!empty($catalog)): ?>
    <div class="cert-section <?= $activeTab==='catalog'?'active':'' ?>" id="catalog">
        <?php
        $tiers = [1=>['label'=>'🏆 Tier 1 — Internasional','color'=>'#facc15'], 2=>['label'=>'🎖️ Tier 2 — Nasional BNSP','color'=>'#94a3b8'], 3=>['label'=>'📚 Tier 3 — Kursus Online','color'=>'#b47850']];
        $catalogByTier = [];
        foreach ($catalog as $c) $catalogByTier[$c['tier']][] = $c;
        foreach ($tiers as $t => $info): ?>
        <div style="margin-bottom:28px">
            <div style="font-size:13px;font-weight:700;margin-bottom:14px;color:<?= $info['color'] ?>"><?= $info['label'] ?></div>
            <div class="cert-grid">
                <?php foreach ($catalogByTier[$t] ?? [] as $cert):
                    $isOwned = in_array($cert['cert_name'], $myOwnedNames); ?>
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
                            <input type="hidden" name="provider"  value="<?= htmlspecialchars($cert['provider']) ?>">
                            <input type="hidden" name="tier"      value="<?= $t ?>">
                            <input type="hidden" name="status"    value="owned">
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
    <?php endif; ?>

    <!-- ADD CERT-->
    <div class="cert-section <?= $activeTab==='add-cert'?'active':'' ?>" id="add-cert">
        <div class="add-form-card">
            <div class="add-form-title">
                <div class="add-form-title-icon" style="background:rgba(34,211,238,.1)">🏆</div>
                Tambah Sertifikasi
            </div>
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
                            <option value="1">🌐 Tier 1 — Internasional (100 poin)</option>
                            <option value="2">🏅 Tier 2 — Nasional BNSP (75 poin)</option>
                            <option value="3" selected>📜 Tier 3 — Kursus Online (50 poin)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-input" name="status">
                            <option value="owned">✅ Dimiliki</option>
                            <option value="recommended">🎯 Target / Rekomendasi</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:22px;max-width:260px">
                    <label class="form-label">Tanggal Diperoleh (opsional)</label>
                    <input class="form-input" type="date" name="obtained_date">
                </div>
                <button type="submit" name="add_cert" class="btn-submit-cert">+ Tambah Sertifikasi</button>
            </form>
        </div>
    </div>

    <!-- MY PROJECTS-->
    <div class="cert-section <?= $activeTab==='my-projects'?'active':'' ?>" id="my-projects">
        <?php if (empty($myProjects)): ?>
        <div class="empty-state">
            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="9" x2="15" y2="9"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="15" x2="12" y2="15"/></svg>
            <p>Belum ada proyek portofolio yang ditambahkan.</p>
            <button onclick="switchTab('add-project', document.querySelector('.tab-proj:last-child'))" class="btn-submit-proj" style="font-size:13px;padding:10px 22px">+ Tambah Proyek</button>
        </div>
        <?php else: ?>
        <?php
        $projBesar = count(array_filter($myProjects, fn($p)=>$p['scale']==='besar'));
        $projKecil = count(array_filter($myProjects, fn($p)=>$p['scale']==='kecil'));
        $projScore = min(100, array_sum(array_column($myProjects,'score')));
        ?>
        <div class="stats-bar">
            <?php if($projBesar): ?><div class="stat-chip" style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);color:#fbbf24">🏆 <?= $projBesar ?> Proyek Besar</div><?php endif; ?>
            <?php if($projKecil): ?><div class="stat-chip" style="background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.15);color:#94a3b8">📄 <?= $projKecil ?> Proyek Kecil</div><?php endif; ?>
            <div class="stat-chip" style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.2);color:#fbbf24;margin-left:auto">Total: <?= $projScore ?> / 100 poin simulasi</div>
        </div>
        <?php foreach ($myProjects as $p):
            $isBesar = $p['scale'] === 'besar';
            $pEmoji  = $isBesar ? '🏆' : '📄';
            $pColor  = $isBesar ? '#fbbf24' : '#94a3b8';
        ?>
        <div class="my-row">
            <div class="my-row-icon" style="background:<?= $pColor ?>18;border:1px solid <?= $pColor ?>33">
                <?= $pEmoji ?>
            </div>
            <div class="my-row-info">
                <div class="my-row-name"><?= htmlspecialchars($p['project_name']) ?></div>
                <div class="my-row-meta">
                    <?= $isBesar ? 'Proyek Besar — TA / Client / Teamwork' : 'Proyek Kecil — Tugas Harian' ?>
                    <?php if (!empty($p['description'])): ?> · <?= htmlspecialchars(mb_substr($p['description'], 0, 60)) . (mb_strlen($p['description']) > 60 ? '…' : '') ?><?php endif; ?>
                </div>
            </div>
            <div class="my-row-right">
                <span class="scale-pill <?= $isBesar?'scale-besar':'scale-kecil' ?>"><?= ucfirst($p['scale']) ?></span>
                <span class="score-pill-sm pill-proj">+<?= $p['score'] ?> poin</span>
                <button type="button" class="btn-delete"
                    onclick="openDeleteModal('project', <?= $p['id'] ?>, <?= htmlspecialchars(json_encode($p['project_name']), ENT_QUOTES) ?>)">
                    <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
                    Hapus
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ADD PROJECT-->
    <div class="cert-section <?= $activeTab==='add-project'?'active':'' ?>" id="add-project">
        <div class="add-form-card" style="border-color:rgba(251,191,36,.15)">
            <div class="add-form-title">
                <div class="add-form-title-icon" style="background:rgba(251,191,36,.1)">📁</div>
                Tambah Proyek Portofolio
            </div>
          
            <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap">
                <div style="padding:8px 14px;border-radius:9px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);font-size:12px;color:#94a3b8">
                    <strong style="color:#fbbf24">Besar</strong> — TA, Proyek Client, Teamwork = <strong style="color:#fbbf24">40 poin</strong>
                </div>
                <div style="padding:8px 14px;border-radius:9px;background:rgba(148,163,184,.06);border:1px solid rgba(148,163,184,.15);font-size:12px;color:#94a3b8">
                    <strong style="color:#94a3b8">Kecil</strong> — Tugas Harian, Latihan = <strong style="color:#94a3b8">20 poin</strong>
                </div>
            </div>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Nama Proyek *</label>
                        <input class="form-input form-input-proj" type="text" name="project_name" placeholder="e.g. Sistem Monitoring IoT ESP32" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skala Proyek</label>
                        <select class="form-input form-input-proj" name="project_scale">
                            <option value="besar">🏆 Besar — TA / Client / Teamwork (40 poin)</option>
                            <option value="kecil" selected>📄 Kecil — Tugas Harian (20 poin)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:16px">
                    <label class="form-label">Deskripsi Singkat (opsional)</label>
                    <textarea class="form-input form-input-proj form-textarea" name="project_desc" placeholder="Jelaskan singkat apa proyeknya, teknologi yang dipakai, atau hasil yang dicapai..."></textarea>
                </div>
                <div class="form-group" style="margin-bottom:22px;max-width:260px">
                    <label class="form-label">Tanggal Selesai (opsional)</label>
                    <input class="form-input form-input-proj" type="date" name="project_date">
                </div>
                <button type="submit" name="add_project" class="btn-submit-proj">+ Tambah Proyek</button>
            </form>
        </div>
    </div>

</main>

<script src="../../script/main.js"></script>
<script>

document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});

function switchTab(sectionId, btn) {
    document.querySelectorAll('.cert-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.cert-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(sectionId)?.classList.add('active');
    btn?.classList.add('active');
    
    const url = new URL(window.location);
    url.searchParams.set('tab', sectionId);
    window.history.replaceState({}, '', url);
}

const deleteModal     = document.getElementById('deleteModal');
const modalTitle      = document.getElementById('modalTitle');
const modalSub        = document.getElementById('modalSub');
const modalItemName   = document.getElementById('modalItemName');
const modalCertId     = document.getElementById('modalCertId');
const modalProjectId  = document.getElementById('modalProjectId');
const modalDeleteBtn  = document.getElementById('modalDeleteBtn');
const modalCancel     = document.getElementById('modalCancel');

function openDeleteModal(type, id, name) {
   
    modalCertId.value    = '';
    modalProjectId.value = '';
    modalDeleteBtn.name  = '';

    if (type === 'cert') {
        modalTitle.textContent   = 'Hapus Sertifikasi?';
        modalSub.textContent     = 'Sertifikasi berikut akan dihapus dari daftarmu:';
        modalCertId.value        = id;
        modalDeleteBtn.name      = 'delete_cert';
    } else {
        modalTitle.textContent   = 'Hapus Proyek?';
        modalSub.textContent     = 'Proyek berikut akan dihapus dari portofoliomu:';
        modalProjectId.value     = id;
        modalDeleteBtn.name      = 'delete_project';
    }
    modalItemName.textContent = name;
    deleteModal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    deleteModal.classList.remove('open');
    document.body.style.overflow = '';
}

modalCancel.addEventListener('click', closeDeleteModal);
deleteModal.addEventListener('click', e => { if (e.target === deleteModal) closeDeleteModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteModal(); });
</script>
</body>
</html>