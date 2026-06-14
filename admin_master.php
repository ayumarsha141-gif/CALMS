<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('admin');
$user = getCurrentUser();
$db   = getDB();

$tab = $_GET['tab'] ?? 'career';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

if ($tab === 'career' && $action === 'update_career') {

    $id   = (int)$_POST['career_id'];
    $name = trim($_POST['position_name']);

    $stmt = $db->prepare("
        UPDATE career_positions
        SET position_name = ?
        WHERE id = ?
    ");

    $stmt->execute([$name, $id]);

    header("Location: admin_master.php?tab=career&success=1");
    exit;
}

        if ($tab === 'career' && $action === 'delete_career') {

            $stmt = $db->prepare("
                DELETE FROM career_positions
                WHERE id = ?
            ");

            $stmt->execute([
                $_POST['career_id']
            ]);

            header("Location: admin_master.php?tab=career&success=1");
            exit;
        }
        
        if ($tab === 'saw' && $action === 'update_saw') {
            $configs = [
                'saw_weight_skill'      => $_POST['w1'],
                'saw_weight_portfolio'     => $_POST['w2'],
                'saw_weight_certification'     => $_POST['w3'],
                'saw_tier1_min'            => $_POST['tier1'],
                'saw_tier2_min'            => $_POST['tier2'],
                'saw_tier3_min'            => $_POST['tier3'],
            ];
            $stmt = $db->prepare("UPDATE system_config SET config_val = ? WHERE config_key = ?");
            foreach ($configs as $k => $v) $stmt->execute([$v, $k]);
            header("Location: admin_master.php?tab=saw&success=1"); exit;
        }


        if ($tab === 'career' && $action === 'add_career') {
            $stmt = $db->prepare("INSERT INTO career_positions (position_name) VALUES (?)");
            $stmt->execute([$_POST['position_name']]);
            header("Location: admin_master.php?tab=career&success=1"); exit;
        }

                // UPDATE CAREER
        if ($tab === 'career' && $action === 'update_career') {

            $id   = (int)$_POST['career_id'];
            $name = trim($_POST['position_name']);

            $stmt = $db->prepare("
                UPDATE career_positions
                SET position_name = ?
                WHERE id = ?
            ");

            $stmt->execute([$name, $id]);

            header("Location: admin_master.php?tab=career&success=1");
            exit;
        }

        // DELETE CAREER
        if ($tab === 'career' && $action === 'delete_career') {

            $id = (int)$_POST['career_id'];

            $stmt = $db->prepare("
                DELETE FROM career_positions
                WHERE id = ?
            ");

            $stmt->execute([$id]);

            header("Location: admin_master.php?tab=career&success=1");
            exit;
        }

        if ($tab === 'roadmap' && $action === 'add_roadmap') {

            $stmt = $db->prepare("
                INSERT INTO roadmap_steps
                (
                    career_id,
                    step_name,
                    course_id,
                    type_matkul,
                    saran_matkul,
                    saran_kursus,
                    saran_kursus_url
                )
                VALUES (?,?,?,?,?,?,?)
            ");

            $stmt->execute([
                $_POST['career_id'],
                $_POST['step_name'],
                $_POST['course_id'] ?: null,
                $_POST['type_matkul'],
                $_POST['saran_matkul'],
                $_POST['saran_kursus'],
                $_POST['saran_kursus_url']
            ]);

            header("Location: admin_master.php?tab=roadmap&success=1");
            exit;
        }
            if ($tab === 'roadmap' && $action === 'update_roadmap') {

                $stmt = $db->prepare("
                    UPDATE roadmap_steps
                    SET
                        career_id = ?,
                        step_name = ?,
                        type_matkul = ?,
                        saran_matkul = ?,
                        saran_kursus = ?,
                        saran_kursus_url = ?
                    WHERE id = ?
                ");

                $stmt->execute([
                    $_POST['career_id'],
                    $_POST['step_name'],
                    $_POST['type_matkul'],
                    $_POST['saran_matkul'],
                    $_POST['saran_kursus'],
                    $_POST['saran_kursus_url'],
                    $_POST['roadmap_id']
                ]);

                header("Location: admin_master.php?tab=roadmap&success=1");
                exit;
            }

            if ($tab === 'roadmap' && $action === 'delete_roadmap') {

                $stmt = $db->prepare("
                    DELETE FROM roadmap_steps
                    WHERE id = ?
                ");

                $stmt->execute([
                    $_POST['roadmap_id']
                ]);

                header("Location: admin_master.php?tab=roadmap&success=1");
                exit;
            }

            if ($tab === 'lab' && $action === 'add_lab_mapping') {

    $stmt = $db->prepare("
        INSERT INTO lab_course_mapping
        (
            lab_id,
            course_id,
            weight
        )
        VALUES (?,?,?)
    ");

    $stmt->execute([
        $_POST['lab_id'],
        $_POST['course_id'],
        $_POST['weight']
    ]);

    header("Location: admin_master.php?tab=lab&success=1");
    exit;
}

if ($tab === 'lab' && $action === 'update_lab_mapping') {

    $stmt = $db->prepare("
        UPDATE lab_course_mapping
        SET
            lab_id=?,
            course_id=?,
            weight=?
        WHERE id=?
    ");

    $stmt->execute([
        $_POST['lab_id'],
        $_POST['course_id'],
        $_POST['weight'],
        $_POST['mapping_id']
    ]);

    header("Location: admin_master.php?tab=lab&success=1");
    exit;
}

if ($tab === 'lab' && $action === 'delete_lab_mapping') {

    $stmt = $db->prepare("
        DELETE FROM lab_course_mapping
        WHERE id=?
    ");

    $stmt->execute([
        $_POST['mapping_id']
    ]);

    header("Location: admin_master.php?tab=lab&success=1");
    exit;
}
    }
}

$sysConfig = [];
$stmt = $db->query("SELECT config_key, config_val FROM system_config WHERE config_key LIKE 'saw_%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $sysConfig[$row['config_key']] = $row['config_val'];

$careers    = $db->query("SELECT * FROM career_positions")->fetchAll();
$labs = $db->query("
    SELECT *
    FROM labs
    ORDER BY lab_name
")->fetchAll();

$labMappings = $db->query("
    SELECT
        lcm.id,
        lcm.lab_id,
        lcm.course_id,
        lcm.weight,
        l.lab_name,
        c.course_code,
        c.course_name_id
    FROM lab_course_mapping lcm
    JOIN labs l ON l.id = lcm.lab_id
    JOIN courses c ON c.id = lcm.course_id
    ORDER BY l.lab_name
")->fetchAll();

$roadmaps = $db->query("
    SELECT rs.*, cp.position_name
    FROM roadmap_steps rs
    LEFT JOIN career_positions cp
        ON cp.id = rs.career_id
    ORDER BY cp.position_name, rs.id
")->fetchAll();
$courses    = $db->query("SELECT * FROM courses ORDER BY semester, course_code")->fetchAll();

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
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="style_patch.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0;
        }
        .tab-btn {
            padding: 10px 18px;
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
        }
        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active {
            color: var(--cyan);
            border-bottom-color: var(--cyan);
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: var(--font-main);
            transition: var(--transition);
            outline: none;
        }
        .form-control:focus { border-color: var(--cyan); }
        .btn-submit {
            background: var(--cyan);
            color: #0a0f1a;
            padding: 10px 24px;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            font-family: var(--font-main);
            transition: var(--transition);
        }
        .btn-submit:hover { opacity: 0.85; }
        .master-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-top: 24px;
        }
        .master-table th {
            text-align: left;
            padding: 8px 12px;
            font-size: 11px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .master-table td {
            padding: 11px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-secondary);
        }
        .master-table tr:last-child td { border-bottom: none; }
        .master-table tr:hover td { background: rgba(255,255,255,0.02); }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 24px 0 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
        }
        .section-title:first-child { margin-top: 0; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .alert-ok  { padding: 12px 16px; background: rgba(16,185,129,0.08); color: #10b981; border: 1px solid rgba(16,185,129,0.25); border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
        .alert-err { padding: 12px 16px; background: rgba(239,68,68,0.08); color: #ef4444; border: 1px solid rgba(239,68,68,0.25); border-radius: 8px; margin-bottom: 20px; font-size: 13px; }
    </style>
</head>
<body class="dashboard-body admin-body">

<?php include 'includes/sidebar_admin.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Master Data Administrator</h1>
                <p class="page-sub">Kelola kriteria, bobot SAW, dan referensi sistem</p>
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
        <a href="?tab=career"  class="tab-btn <?= $tab === 'career'  ? 'active' : '' ?>">Posisi Karir & Skill</a>
        <a href="?tab=roadmap" class="tab-btn <?= $tab === 'roadmap' ? 'active' : '' ?>">Setup Roadmap</a>
        <a href="?tab=lab"     class="tab-btn <?= $tab === 'lab'     ? 'active' : '' ?>">Matriks Bobot LAB</a>
        <a href="?tab=saw"     class="tab-btn <?= $tab === 'saw'     ? 'active' : '' ?>">Pengaturan SAW</a>
    </div>

    <div class="dash-panel">

        <?php if ($tab === 'career'): ?>
            <div class="section-title">Tambah Posisi Karir Baru</div>
            <form method="POST" style="max-width:440px;margin-bottom:8px;">
                <input type="hidden" name="action" value="add_career">
                <div class="form-group">
                    <label>Nama Posisi Karir</label>
                    <input type="text" name="position_name" class="form-control" placeholder="contoh: Data Engineer" required>
                </div>
                <button type="submit" class="btn-submit">+ Tambah Posisi</button>
            </form>
            <div class="section-title">Daftar Posisi Karir</div>
            <table class="master-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Posisi Karir</th>
                    <th>Aksi</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach($careers as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>

                    <td>
                        <form method="POST" action = "admin_master.php?tab=career" style="display:flex;gap:8px;">
                            <input type="hidden" name="action" value="update_career">
                            <input type="hidden" name="career_id" value="<?= $c['id'] ?>">

                            <input
                                type="text"
                                name="position_name"
                                value="<?= htmlspecialchars($c['position_name']) ?>"
                                class="form-control"
                            >

                            <button type="submit" class="btn-submit">
                                Simpan
                            </button>
                        </form>
                    </td>

                    <td>
                        <form method="POST"
                            action="admin_master.php?tab=career"
                            onsubmit="return confirm('Hapus posisi ini?')">
                            <input type="hidden" name="action" value="delete_career">
                            <input type="hidden" name="career_id" value="<?= $c['id'] ?>">

                            <button
                                type="submit"
                                style="
                                    background:#ef4444;
                                    color:white;
                                    border:none;
                                    padding:8px 12px;
                                    border-radius:8px;
                                    cursor:pointer;
                                ">
                                Hapus
                            </button>

                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($careers)): ?>
                <tr><td colspan="2" style="text-align:center;color:var(--text-muted);padding:20px">Belum ada data.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php elseif ($tab === 'roadmap'): ?>

            <div class="section-title">
                Tambah Langkah Roadmap
            </div>

            <form method="POST">

                <input type="hidden"
                    name="action"
                    value="add_roadmap">

                <div class="form-group">
                    <label>Posisi Karir</label>

                    <select name="career_id"
                            class="form-control"
                            required>

                        <?php foreach($careers as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['position_name']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Nama Step</label>

                    <input type="text"
                        name="step_name"
                        class="form-control"
                        required>
                </div>

                <div class="form-group">
                    <label>Jenis Matkul</label>

                    <select name="type_matkul"
                            class="form-control">

                        <option value="Wajib">Wajib</option>
                        <option value="Pilihan">Pilihan</option>

                    </select>
                </div>

                <div class="form-group">
                    <label>Saran Matkul</label>

                    <textarea name="saran_matkul"
                            class="form-control"></textarea>
                </div>

                <div class="form-group">
                    <label>Saran Kursus</label>

                    <input type="text"
                        name="saran_kursus"
                        class="form-control">
                </div>

                <div class="form-group">
                    <label>URL Kursus</label>

                    <input type="text"
                        name="saran_kursus_url"
                        class="form-control">
                </div>

                <button type="submit"
                        class="btn-submit">
                    Simpan Roadmap
                </button>

            </form>

            <div class="section-title">
                Data Roadmap
            </div>

            <table class="master-table">

                <thead>
                    <tr>
                        <th>Karir</th>
                        <th>Step</th>
                        <th>Jenis</th>
                        <th>Kursus</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach($roadmaps as $r): ?>
                    <tr>

                    <td>
                    <form method="POST" action="admin_master.php?tab=roadmap">

                        <input type="hidden"
                            name="action"
                            value="update_roadmap">

                        <input type="hidden"
                            name="roadmap_id"
                            value="<?= $r['id'] ?>">

                        <select name="career_id"
                                class="form-control">

                            <?php foreach($careers as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= $c['id']==$r['career_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['position_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </td>

                    <td>
                        <input type="text"
                            name="step_name"
                            value="<?= htmlspecialchars($r['step_name']) ?>"
                            class="form-control">
                    </td>

                    <td>
                        <select name="type_matkul"
                                class="form-control">

                            <option value="Wajib"
                                <?= $r['type_matkul']=='Wajib' ? 'selected' : '' ?>>
                                Wajib
                            </option>

                            <option value="Pilihan"
                                <?= $r['type_matkul']=='Pilihan' ? 'selected' : '' ?>>
                                Pilihan
                            </option>

                        </select>
                    </td>

                    <td>
                        <input type="text"
                            name="saran_kursus"
                            value="<?= htmlspecialchars($r['saran_kursus']) ?>"
                            class="form-control">
                    </td>

                    <td>

                        <input type="hidden"
                            name="saran_matkul"
                            value="<?= htmlspecialchars($r['saran_matkul']) ?>">

                        <input type="hidden"
                            name="saran_kursus_url"
                            value="<?= htmlspecialchars($r['saran_kursus_url']) ?>">

                        <button type="submit"
                                class="btn-submit">
                            Simpan
                        </button>

                    </form>

                    <form method="POST"
                        action="admin_master.php?tab=roadmap"
                        style="margin-top:5px"
                        onsubmit="return confirm('Hapus roadmap ini?')">

                        <input type="hidden"
                            name="action"
                            value="delete_roadmap">

                        <input type="hidden"
                            name="roadmap_id"
                            value="<?= $r['id'] ?>">

                        <button type="submit"
                                style="background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:8px;">
                            Hapus
                        </button>

                    </form>

                    </td>

                    </tr>
                    <?php endforeach; ?>

                </tbody>

            </table>
<?php elseif ($tab === 'lab'): ?>

<div class="section-title">
    Tambah Mapping LAB
</div>

<form method="POST">

    <input type="hidden"
           name="action"
           value="add_lab_mapping">

    <div class="form-group">
        <label>Lab</label>

        <select name="lab_id" class="form-control">

            <?php foreach($labs as $lab): ?>

            <option value="<?= $lab['id'] ?>">
                <?= htmlspecialchars($lab['lab_name']) ?>
            </option>

            <?php endforeach; ?>

        </select>
    </div>

    <div class="form-group">
        <label>Mata Kuliah</label>

        <select name="course_id" class="form-control">

            <?php foreach($courses as $co): ?>

            <option value="<?= $co['id'] ?>">
                <?= $co['course_code'] ?>
                -
                <?= htmlspecialchars($co['course_name_id']) ?>
            </option>

            <?php endforeach; ?>

        </select>
    </div>

    <div class="form-group">
        <label>Weight</label>

        <input type="number"
               step="0.1"
               name="weight"
               class="form-control"
               required>
    </div>

    <button type="submit"
            class="btn-submit">
        Tambah Mapping
    </button>

</form>

<div class="section-title">
    Data Mapping LAB
</div>

<table class="master-table">

<thead>
<tr>
    <th>Lab</th>
    <th>Kode</th>
    <th>Mata Kuliah</th>
    <th>Weight</th>
    <th>Aksi</th>
</tr>
</thead>

<tbody>

<?php foreach($labMappings as $m): ?>

<tr>

<form method="POST"
      action="admin_master.php?tab=lab">

    <input type="hidden"
           name="action"
           value="update_lab_mapping">

    <input type="hidden"
           name="mapping_id"
           value="<?= $m['id'] ?>">

    <td>

        <select name="lab_id"
                class="form-control">

            <?php foreach($labs as $lab): ?>

            <option value="<?= $lab['id'] ?>"
                <?= ($lab['lab_name']==$m['lab_name']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($lab['lab_name']) ?>
            </option>

            <?php endforeach; ?>

        </select>

    </td>

    <td>
        <?= htmlspecialchars($m['course_code']) ?>
    </td>

    <td>
        <?= htmlspecialchars($m['course_name_id']) ?>
    </td>

    <td>

        <input type="number"
               step="0.1"
               name="weight"
               value="<?= $m['weight'] ?>"
               class="form-control">

        <input type="hidden"
               name="course_id"
               value="<?= $m['course_id'] ?>">

    </td>

    <td>

        <button type="submit"
                class="btn-submit">
            Simpan
        </button>

</form>

<form method="POST"
      action="admin_master.php?tab=lab"
      style="margin-top:5px"
      onsubmit="return confirm('Hapus mapping ini?')">

    <input type="hidden"
           name="action"
           value="delete_lab_mapping">

    <input type="hidden"
           name="mapping_id"
           value="<?= $m['id'] ?>">

    <button type="submit"
            style="background:#ef4444;color:#fff;border:none;padding:8px 12px;border-radius:8px;">
        Hapus
    </button>

</form>

    </td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

        <?php elseif ($tab === 'saw'): ?>
            <form method="POST" style="max-width:640px;">
                <input type="hidden" name="action" value="update_saw">
                <div class="section-title">
                    Bobot SAW (Total = 1.0)
                </div>

                <div class="grid-3">

                    <div class="form-group">
                        <label>C1 - Skill Praktis</label>
                        <input
                            type="number"
                            step="0.01"
                            name="w1"
                            value="<?= $sysConfig['saw_weight_skill'] ?? 0.50 ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>C2 - Portofolio</label>
                        <input
                            type="number"
                            step="0.01"
                            name="w2"
                            value="<?= $sysConfig['saw_weight_portfolio'] ?? 0.30 ?>"
                            class="form-control">
                    </div>

                    <div class="form-group">
                        <label>C3 - Sertifikasi</label>
                        <input
                            type="number"
                            step="0.01"
                            name="w3"
                            value="<?= $sysConfig['saw_weight_certification'] ?? 0.20 ?>"
                            class="form-control">
                    </div>

                </div>
                
                <div class="section-title">Ambang Batas Kelulusan (KKM)</div>
                <div class="grid-3">
                    <div class="form-group">
                        <label>Tier 1 — Internasional</label>
                        <input type="number" step="1" name="tier1" value="<?= $sysConfig['saw_tier1_min'] ?? 85 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tier 2 — Nasional</label>
                        <input type="number" step="1" name="tier2" value="<?= $sysConfig['saw_tier2_min'] ?? 70 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tier 3 — Lokal</label>
                        <input type="number" step="1" name="tier3" value="<?= $sysConfig['saw_tier3_min'] ?? 55 ?>" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top:8px">💾 Simpan Pengaturan SAW</button>
            </form>
        <?php endif; ?>

    </div>
</main>

<script>
document.getElementById('sidebarToggle')
    ?.addEventListener('click', () => document.getElementById('sidebar')?.classList.toggle('open'));
</script>
</body>
</html>