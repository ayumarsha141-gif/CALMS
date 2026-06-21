<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('admin');
$user = getCurrentUser();
$db   = getDB();

$tab = $_GET['tab'] ?? 'career';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($tab === 'career' && $action === 'add_career') {
        $db->prepare("INSERT INTO career_positions (position_name) VALUES (?)")
           ->execute([trim($_POST['position_name'])]);
        header("Location: admin_master.php?tab=career&success=1"); exit;
    }

    if ($tab === 'career' && $action === 'update_career') {
        $db->prepare("UPDATE career_positions SET position_name = ? WHERE id = ?")
           ->execute([trim($_POST['position_name']), (int)$_POST['career_id']]);
        header("Location: admin_master.php?tab=career&success=1"); exit;
    }

    if ($tab === 'career' && $action === 'delete_career') {
        $id = (int)$_POST['career_id'];
        $db->prepare("DELETE FROM career_skills   WHERE career_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM roadmap_steps   WHERE career_id = ?")->execute([$id]);
        $db->prepare("DELETE FROM career_positions WHERE id = ?")->execute([$id]);
        header("Location: admin_master.php?tab=career&success=1"); exit;
    }

    if ($tab === 'roadmap' && $action === 'add_roadmap') {
        $db->prepare("INSERT INTO roadmap_steps (career_id,step_name,course_id,type_matkul,saran_matkul,saran_kursus,saran_kursus_url) VALUES (?,?,?,?,?,?,?)")
           ->execute([$_POST['career_id'],$_POST['step_name'],$_POST['course_id']?:null,$_POST['type_matkul'],$_POST['saran_matkul'],$_POST['saran_kursus'],$_POST['saran_kursus_url']]);
        header("Location: admin_master.php?tab=roadmap&success=1"); exit;
    }

    if ($tab === 'roadmap' && $action === 'update_roadmap') {
        $db->prepare("UPDATE roadmap_steps SET career_id=?,step_name=?,type_matkul=?,saran_matkul=?,saran_kursus=?,saran_kursus_url=? WHERE id=?")
           ->execute([$_POST['career_id'],$_POST['step_name'],$_POST['type_matkul'],$_POST['saran_matkul'],$_POST['saran_kursus'],$_POST['saran_kursus_url'],$_POST['roadmap_id']]);
        header("Location: admin_master.php?tab=roadmap&success=1"); exit;
    }

    if ($tab === 'roadmap' && $action === 'delete_roadmap') {
        $db->prepare("DELETE FROM roadmap_steps WHERE id=?")->execute([$_POST['roadmap_id']]);
        header("Location: admin_master.php?tab=roadmap&success=1"); exit;
    }

    if ($tab === 'lab' && $action === 'add_lab_mapping') {
        $db->prepare("INSERT INTO lab_course_mapping (lab_id,course_id,weight) VALUES (?,?,?)")
           ->execute([$_POST['lab_id'],$_POST['course_id'],$_POST['weight']]);
        header("Location: admin_master.php?tab=lab&success=1"); exit;
    }

    if ($tab === 'lab' && $action === 'update_lab_mapping') {
        $db->prepare("UPDATE lab_course_mapping SET lab_id=?,course_id=?,weight=? WHERE id=?")
           ->execute([$_POST['lab_id'],$_POST['course_id'],$_POST['weight'],$_POST['mapping_id']]);
        header("Location: admin_master.php?tab=lab&success=1"); exit;
    }

    if ($tab === 'lab' && $action === 'delete_lab_mapping') {
        $db->prepare("DELETE FROM lab_course_mapping WHERE id=?")->execute([$_POST['mapping_id']]);
        header("Location: admin_master.php?tab=lab&success=1"); exit;
    }

    if ($tab === 'saw' && $action === 'update_saw') {
        $configs = [
            'saw_weight_skill'         => $_POST['w1'],
            'saw_weight_portfolio'     => $_POST['w2'],
            'saw_weight_certification' => $_POST['w3'],
            'saw_tier1_min'            => $_POST['tier1'],
            'saw_tier2_min'            => $_POST['tier2'],
            'saw_tier3_min'            => $_POST['tier3'],
        ];
        $stmt = $db->prepare("UPDATE system_config SET config_val = ? WHERE config_key = ?");
        foreach ($configs as $k => $v) $stmt->execute([$v, $k]);
        header("Location: admin_master.php?tab=saw&success=1"); exit;
    }
}

// ── Data queries ──
$sysConfig = [];
$stmt = $db->query("SELECT config_key, config_val FROM system_config WHERE config_key LIKE 'saw_%'");
if ($stmt) while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $sysConfig[$row['config_key']] = $row['config_val'];

$careers  = $db->query("SELECT * FROM career_positions ORDER BY position_name")->fetchAll();
$courses  = $db->query("SELECT * FROM courses ORDER BY semester, course_code")->fetchAll();

$labs = [];
$labMappings = [];
$roadmaps = [];
try {
    $labs        = $db->query("SELECT * FROM labs ORDER BY lab_name")->fetchAll();
    $labMappings = $db->query("SELECT lcm.*,l.lab_name,c.course_code,c.course_name_id FROM lab_course_mapping lcm JOIN labs l ON l.id=lcm.lab_id JOIN courses c ON c.id=lcm.course_id ORDER BY l.lab_name")->fetchAll();
    $roadmaps    = $db->query("SELECT rs.*,cp.position_name FROM roadmap_steps rs LEFT JOIN career_positions cp ON cp.id=rs.career_id ORDER BY cp.position_name,rs.id")->fetchAll();
} catch (PDOException $e) {}

switch ($tab) {
    case 'career':  $activePage = 'admin_career';  break;
    case 'roadmap': $activePage = 'admin_roadmap'; break;
    case 'lab':     $activePage = 'admin_lab';     break;
    case 'saw':     $activePage = 'admin_saw';     break;
    default:        $activePage = 'admin_career';  break;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Admin — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link rel="stylesheet" href="../styles/style_patch.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        /* ── Main content layout fix ── */
        .main-content {
            margin-left: 260px;
            padding: 32px 32px 48px;
            min-height: 100vh;
            box-sizing: border-box;
            width: calc(100% - 260px);
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; width: 100%; padding: 20px 16px 40px; }
        }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
        }
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            color: var(--text-muted);
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            font-family: var(--font-main);
            text-decoration: none;
            transition: var(--transition);
            margin-bottom: -1px;
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { color: var(--cyan); border-bottom-color: var(--cyan); }

        /* ── Forms ── */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .form-control {
            width: 100%;
            padding: 9px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-family: var(--font-main);
            transition: var(--transition);
            outline: none;
            box-sizing: border-box;
        }
        .form-control:focus { border-color: var(--cyan); }
        textarea.form-control { resize: vertical; min-height: 72px; }

        .btn-submit {
            background: var(--cyan);
            color: #0a0f1a;
            padding: 9px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            font-family: var(--font-main);
            transition: var(--transition);
            white-space: nowrap;
        }
        .btn-submit:hover { opacity: 0.85; }

        .btn-danger {
            background: rgba(239,68,68,.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,.25);
            padding: 9px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            font-family: var(--font-main);
            transition: var(--transition);
            white-space: nowrap;
        }
        .btn-danger:hover { background: rgba(239,68,68,.2); }

        /* ── Section title ── */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 28px 0 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .section-title:first-child { margin-top: 0; }

        /* ── Grid helpers ── */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        @media (max-width: 640px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }

        /* ── Master table ── */
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 8px;
        }
        .master-table th {
            text-align: left;
            padding: 8px 12px;
            font-size: 11px;
            letter-spacing: .8px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        .master-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        .master-table tr:last-child td { border-bottom: none; }
        .master-table tr:hover td { background: rgba(255,255,255,.02); }

        /* ── Inline edit row ── */
        .inline-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .inline-form .form-control {
            flex: 1;
            min-width: 0;
        }

        /* ── Add form box ── */
        .add-box {
            background: var(--bg-secondary);
            border: 1px dashed rgba(34,211,238,.25);
            border-radius: 12px;
            padding: 20px 22px;
            max-width: 520px;
            margin-bottom: 8px;
        }

        /* ── Alerts ── */
        .alert-ok  { padding: 11px 16px; background: rgba(16,185,129,.08); color: #10b981; border: 1px solid rgba(16,185,129,.25); border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .alert-err { padding: 11px 16px; background: rgba(239,68,68,.08); color: #ef4444; border: 1px solid rgba(239,68,68,.25); border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
    
           /* ── Responsive ── */
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
<body class="dashboard-body admin-body">

<?php include '../../includes/sidebar_admin.php'; ?>

<main class="main-content">
    <!-- Topbar -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6"/>
                    <line x1="3" y1="12" x2="21" y2="12"/>
                    <line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Master Data Administrator</h1>
                <p class="page-sub">Kelola posisi karir, roadmap, bobot LAB, dan pengaturan SAW</p>
            </div>
        </div>
        <div class="topbar-right">
            <a href="dashboardAdmin.php" class="btn-sm-cyan">← Dashboard</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="alert-ok">✅ Perubahan berhasil disimpan!</div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert-err">⚠️ <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <a href="?tab=career"  class="tab-btn <?= $tab==='career'  ? 'active':'' ?>">🎯 Posisi Karir & Skill</a>
        <a href="?tab=roadmap" class="tab-btn <?= $tab==='roadmap' ? 'active':'' ?>">🗺️ Setup Roadmap</a>
        <a href="?tab=lab"     class="tab-btn <?= $tab==='lab'     ? 'active':'' ?>">🔬 Matriks Bobot LAB</a>
        <a href="?tab=saw"     class="tab-btn <?= $tab==='saw'     ? 'active':'' ?>">⚖️ Pengaturan SAW</a>
    </div>

    <div class="dash-panel">

    <?php if ($tab === 'career'): ?>

        <!-- Tambah posisi -->
        <div class="section-title">Tambah Posisi Karir Baru</div>
        <div class="add-box">
            <form method="POST">
                <input type="hidden" name="action" value="add_career">
                <div class="form-group">
                    <label>Nama Posisi Karir</label>
                    <input type="text" name="position_name" class="form-control" placeholder="contoh: Data Engineer" required>
                </div>
                <button type="submit" class="btn-submit">+ Tambah Posisi</button>
            </form>
        </div>

        <!-- Daftar posisi -->
        <div class="section-title">Daftar Posisi Karir</div>
        <?php if (empty($careers)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:16px 0">Belum ada data posisi karir.</p>
        <?php else: ?>
        <table class="master-table">
            <thead>
                <tr>
                    <th style="width:48px">ID</th>
                    <th>Nama Posisi Karir</th>
                    <th style="width:80px;text-align:center">Hapus</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($careers as $c): ?>
            <tr>
                <td style="font-family:var(--font-mono);color:var(--text-muted);font-size:12px"><?= $c['id'] ?></td>
                <td>
                    <form method="POST" class="inline-form">
                        <input type="hidden" name="action" value="update_career">
                        <input type="hidden" name="career_id" value="<?= $c['id'] ?>">
                        <input type="text" name="position_name" value="<?= htmlspecialchars($c['position_name']) ?>" class="form-control" required>
                        <button type="submit" class="btn-submit">Simpan</button>
                    </form>
                </td>
                <td style="text-align:center">
                    <form method="POST" onsubmit="return confirm('Hapus posisi ini beserta semua skill & roadmap-nya?')">
                        <input type="hidden" name="action" value="delete_career">
                        <input type="hidden" name="career_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'roadmap'): ?>

        <div class="section-title">Tambah Langkah Roadmap</div>
        <div class="add-box" style="max-width:640px">
            <form method="POST">
                <input type="hidden" name="action" value="add_roadmap">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Posisi Karir</label>
                        <select name="career_id" class="form-control" required>
                            <?php foreach ($careers as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Step</label>
                        <input type="text" name="step_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Matkul</label>
                        <select name="type_matkul" class="form-control">
                            <option value="Wajib">Wajib</option>
                            <option value="Pilihan">Pilihan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Saran Kursus</label>
                        <input type="text" name="saran_kursus" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Saran Matkul</label>
                    <textarea name="saran_matkul" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>URL Kursus</label>
                    <input type="text" name="saran_kursus_url" class="form-control" placeholder="https://...">
                </div>
                <input type="hidden" name="course_id" value="">
                <button type="submit" class="btn-submit">Simpan Roadmap</button>
            </form>
        </div>

        <div class="section-title">Data Roadmap (<?= count($roadmaps) ?> langkah)</div>
        <?php if (empty($roadmaps)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:16px 0">Belum ada data roadmap.</p>
        <?php else: ?>
        <table class="master-table">
            <thead>
                <tr>
                    <th>Karir</th>
                    <th>Step</th>
                    <th>Jenis</th>
                    <th>Kursus</th>
                    <th style="width:140px">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($roadmaps as $r): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="action" value="update_roadmap">
                    <input type="hidden" name="roadmap_id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="saran_matkul" value="<?= htmlspecialchars($r['saran_matkul'] ?? '') ?>">
                    <input type="hidden" name="saran_kursus_url" value="<?= htmlspecialchars($r['saran_kursus_url'] ?? '') ?>">
                    <td>
                        <select name="career_id" class="form-control">
                            <?php foreach ($careers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id']==$r['career_id']?'selected':'' ?>><?= htmlspecialchars($c['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" name="step_name" value="<?= htmlspecialchars($r['step_name']) ?>" class="form-control"></td>
                    <td>
                        <select name="type_matkul" class="form-control">
                            <option value="Wajib"   <?= $r['type_matkul']==='Wajib'   ?'selected':'' ?>>Wajib</option>
                            <option value="Pilihan" <?= $r['type_matkul']==='Pilihan' ?'selected':'' ?>>Pilihan</option>
                        </select>
                    </td>
                    <td><input type="text" name="saran_kursus" value="<?= htmlspecialchars($r['saran_kursus'] ?? '') ?>" class="form-control"></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="submit" class="btn-submit">Simpan</button>
                </form>
                        <form method="POST" onsubmit="return confirm('Hapus langkah ini?')" style="margin:0">
                            <input type="hidden" name="action" value="delete_roadmap">
                            <input type="hidden" name="roadmap_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-danger">Hapus</button>
                        </form>
                        </div>
                    </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'lab'): ?>

        <div class="section-title">Tambah Mapping LAB</div>
        <div class="add-box" style="max-width:520px">
            <form method="POST">
                <input type="hidden" name="action" value="add_lab_mapping">
                <div class="form-group">
                    <label>Lab</label>
                    <select name="lab_id" class="form-control">
                        <?php foreach ($labs as $lab): ?>
                        <option value="<?= $lab['id'] ?>"><?= htmlspecialchars($lab['lab_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mata Kuliah</label>
                    <select name="course_id" class="form-control">
                        <?php foreach ($courses as $co): ?>
                        <option value="<?= $co['id'] ?>"><?= $co['course_code'] ?> — <?= htmlspecialchars($co['course_name_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Weight</label>
                    <input type="number" step="0.1" name="weight" class="form-control" required>
                </div>
                <button type="submit" class="btn-submit">Tambah Mapping</button>
            </form>
        </div>

        <div class="section-title">Data Mapping LAB</div>
        <?php if (empty($labMappings)): ?>
        <p style="color:var(--text-muted);font-size:13px;padding:16px 0">Belum ada data mapping LAB.</p>
        <?php else: ?>
        <table class="master-table">
            <thead>
                <tr><th>Lab</th><th>Kode</th><th>Mata Kuliah</th><th>Weight</th><th style="width:140px">Aksi</th></tr>
            </thead>
            <tbody>
            <?php foreach ($labMappings as $m): ?>
            <tr>
                <form method="POST">
                    <input type="hidden" name="action" value="update_lab_mapping">
                    <input type="hidden" name="mapping_id" value="<?= $m['id'] ?>">
                    <input type="hidden" name="course_id" value="<?= $m['course_id'] ?>">
                    <td>
                        <select name="lab_id" class="form-control">
                            <?php foreach ($labs as $lab): ?>
                            <option value="<?= $lab['id'] ?>" <?= $lab['id']==$m['lab_id']?'selected':'' ?>><?= htmlspecialchars($lab['lab_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($m['course_code']) ?></td>
                    <td><?= htmlspecialchars($m['course_name_id']) ?></td>
                    <td><input type="number" step="0.1" name="weight" value="<?= $m['weight'] ?>" class="form-control"></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap">
                            <button type="submit" class="btn-submit">Simpan</button>
                </form>
                        <form method="POST" onsubmit="return confirm('Hapus mapping ini?')" style="margin:0">
                            <input type="hidden" name="action" value="delete_lab_mapping">
                            <input type="hidden" name="mapping_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn-danger">Hapus</button>
                        </form>
                        </div>
                    </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

    <?php elseif ($tab === 'saw'): ?>

        <form method="POST" style="max-width:560px">
            <input type="hidden" name="action" value="update_saw">

            <div class="section-title">Bobot SAW (Total = 1.0)</div>
            <div class="grid-3">
                <div class="form-group">
                    <label>C1 — Skill Praktis</label>
                    <input type="number" step="0.01" name="w1" value="<?= $sysConfig['saw_weight_skill'] ?? 0.50 ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>C2 — Portofolio</label>
                    <input type="number" step="0.01" name="w2" value="<?= $sysConfig['saw_weight_portfolio'] ?? 0.30 ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>C3 — Sertifikasi</label>
                    <input type="number" step="0.01" name="w3" value="<?= $sysConfig['saw_weight_certification'] ?? 0.20 ?>" class="form-control">
                </div>
            </div>

            <div class="section-title">Ambang Batas Kelulusan (KKM)</div>
            <div class="grid-3">
                <div class="form-group">
                    <label>Tier 1 — Sangat Siap</label>
                    <input type="number" step="1" name="tier1" value="<?= $sysConfig['saw_tier1_min'] ?? 85 ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tier 2 — Siap</label>
                    <input type="number" step="1" name="tier2" value="<?= $sysConfig['saw_tier2_min'] ?? 70 ?>" class="form-control">
                </div>
                <div class="form-group">
                    <label>Tier 3 — Pengembangan</label>
                    <input type="number" step="1" name="tier3" value="<?= $sysConfig['saw_tier3_min'] ?? 55 ?>" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn-submit">💾 Simpan Pengaturan SAW</button>
        </form>

    <?php endif; ?>

    </div><!-- .dash-panel -->
</main>

<script>
document.getElementById('sidebarToggle')
    ?.addEventListener('click', () => document.getElementById('sidebar')?.classList.toggle('open'));
</script>
</body>
</html>