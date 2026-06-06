<?php
session_start();
require_once '../includes/auth_guard.php';
require_once '../config/database.php';

requireRole('admin');
$user = getCurrentUser();
$db   = getDB();

$tab = $_GET['tab'] ?? 'career';

// Handle form submissions based on tab
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($tab === 'saw' && $action === 'update_saw') {
            // Update SAW config
            $configs = [
                'saw_weight_academic' => $_POST['w1'],
                'saw_weight_practical' => $_POST['w2'],
                'saw_weight_portfolio' => $_POST['w3'],
                'saw_weight_certification' => $_POST['w4'],
                'saw_sub_weight_course' => $_POST['c1_course'],
                'saw_sub_weight_ipk' => $_POST['c1_ipk'],
                'saw_tier1_min' => $_POST['tier1'],
                'saw_tier2_min' => $_POST['tier2'],
                'saw_tier3_min' => $_POST['tier3'],
            ];
            $stmt = $db->prepare("UPDATE system_config SET config_val = ? WHERE config_key = ?");
            foreach ($configs as $k => $v) {
                $stmt->execute([$v, $k]);
            }
            header("Location: admin_master.php?tab=saw&success=1");
            exit;
        }

        if ($tab === 'lab' && $action === 'update_lab') {
            $course_id = $_POST['course_id'];
            $rpl = $_POST['bobot_rpl'];
            $hpc = $_POST['bobot_hpc'];
            $ai = $_POST['bobot_ai'];
            
            if (($rpl + $hpc + $ai) == 1.0) {
                $stmt = $db->prepare("INSERT INTO course_lab_weights (course_id, bobot_rpl, bobot_hpc, bobot_ai) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE bobot_rpl=VALUES(bobot_rpl), bobot_hpc=VALUES(bobot_hpc), bobot_ai=VALUES(bobot_ai)");
                $stmt->execute([$course_id, $rpl, $hpc, $ai]);
                header("Location: admin_master.php?tab=lab&success=1");
            } else {
                header("Location: admin_master.php?tab=lab&error=Total+bobot+harus+1.0");
            }
            exit;
        }
        
        if ($tab === 'career' && $action === 'add_career') {
            $name = $_POST['position_name'];
            $stmt = $db->prepare("INSERT INTO career_positions (position_name) VALUES (?)");
            $stmt->execute([$name]);
            header("Location: admin_master.php?tab=career&success=1");
            exit;
        }
    }
}

// Fetch data for tabs
$sysConfig = [];
$stmt = $db->query("SELECT config_key, config_val FROM system_config WHERE config_key LIKE 'saw_%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sysConfig[$row['config_key']] = $row['config_val'];
}

$careers = $db->query("SELECT * FROM career_positions")->fetchAll();
$courses = $db->query("SELECT * FROM courses WHERE is_wajib = 1")->fetchAll();
$labWeights = $db->query("
    SELECT cl.*, c.course_name_id, c.course_code 
    FROM course_lab_weights cl 
    JOIN courses c ON c.id = cl.course_id
")->fetchAll();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Admin — CALMS</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;}
        .tab-btn { padding: 8px 16px; background: none; border: none; color: var(--text-muted); cursor: pointer; font-weight: 500; border-radius: 4px; }
        .tab-btn.active { background: rgba(34,211,238,0.1); color: var(--cyan); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; color: var(--text-secondary); font-size: 13px; }
        .form-control { width: 100%; padding: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff; border-radius: 6px; }
        .btn-submit { background: var(--cyan); color: #000; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px;}
        .table th, .table td { padding: 10px; border-bottom: 1px solid var(--border); text-align: left; }
    </style>
</head>
<body class="dashboard-body">

<!-- SIDEBAR -->
<?php // For brevity, we'll assume sidebar is included or we just provide back link ?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
    </div>
    <nav class="sidebar-nav">
        <a href="dashboardAdmin.php">← Kembali ke Dashboard</a>
    </nav>
</aside>

<main class="main-content">
    <div class="topbar">
        <div>
            <div class="page-title">Master Data Administrator</div>
            <div class="page-sub">Kelola kriteria, bobot SAW, dan referensi sistem</div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div style="padding:15px; background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid #22c55e; margin-bottom:20px; border-radius:6px;">
        Perubahan berhasil disimpan!
    </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
    <div style="padding:15px; background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid #ef4444; margin-bottom:20px; border-radius:6px;">
        <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <div class="tabs">
        <a href="?tab=career" class="tab-btn <?= $tab == 'career' ? 'active' : '' ?>">Posisi Karir & Skill</a>
        <a href="?tab=roadmap" class="tab-btn <?= $tab == 'roadmap' ? 'active' : '' ?>">Setup Roadmap</a>
        <a href="?tab=lab" class="tab-btn <?= $tab == 'lab' ? 'active' : '' ?>">Matriks Bobot LAB</a>
        <a href="?tab=saw" class="tab-btn <?= $tab == 'saw' ? 'active' : '' ?>">Pengaturan SAW</a>
    </div>

    <div class="dash-panel">
        <?php if ($tab === 'career'): ?>
            <h2 class="panel-title">Manajemen Posisi Karir</h2>
            <form method="POST" style="max-width: 400px; margin-bottom: 30px;">
                <input type="hidden" name="action" value="add_career">
                <div class="form-group">
                    <label>Tambah Posisi Karir Baru</label>
                    <input type="text" name="position_name" class="form-control" required>
                </div>
                <button type="submit" class="btn-submit">Simpan</button>
            </form>

            <table class="table">
                <tr><th>ID</th><th>Posisi Karir</th></tr>
                <?php foreach($careers as $c): ?>
                <tr>
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['position_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($tab === 'roadmap'): ?>
            <h2 class="panel-title">Setup Roadmap Master Data</h2>
            <p style="color:var(--text-muted)">Pengaturan roadmap dinamis.</p>

        <?php elseif ($tab === 'lab'): ?>
            <h2 class="panel-title">Matriks Bobot LAB Dosen (SAW)</h2>
            <form method="POST" style="max-width: 600px; margin-bottom:30px;">
                <input type="hidden" name="action" value="update_lab">
                <div class="form-group">
                    <label>Mata Kuliah Wajib</label>
                    <select name="course_id" class="form-control" required>
                        <?php foreach($courses as $co): ?>
                        <option value="<?= $co['id'] ?>"><?= $co['course_code'] ?> - <?= htmlspecialchars($co['course_name_id']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1">
                        <label>Bobot RPL (0.0-1.0)</label>
                        <input type="number" step="0.01" name="bobot_rpl" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Bobot HPC (0.0-1.0)</label>
                        <input type="number" step="0.01" name="bobot_hpc" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Bobot AI (0.0-1.0)</label>
                        <input type="number" step="0.01" name="bobot_ai" class="form-control" required>
                    </div>
                </div>
                <p style="font-size:12px; color:var(--text-muted)">Total ketiga bobot harus 1.0 (100%)</p>
                <button type="submit" class="btn-submit">Simpan Bobot LAB</button>
            </form>

            <table class="table">
                <tr><th>Mata Kuliah</th><th>RPL</th><th>HPC</th><th>AI</th></tr>
                <?php foreach($labWeights as $lw): ?>
                <tr>
                    <td><?= $lw['course_code'] ?> - <?= htmlspecialchars($lw['course_name_id']) ?></td>
                    <td><?= $lw['bobot_rpl'] ?></td>
                    <td><?= $lw['bobot_hpc'] ?></td>
                    <td><?= $lw['bobot_ai'] ?></td>
                </tr>
                <?php endforeach; ?>
            </table>

        <?php elseif ($tab === 'saw'): ?>
            <h2 class="panel-title">Konfigurasi Pembobotan & Ambang Batas SAW</h2>
            <form method="POST" style="max-width: 600px;">
                <input type="hidden" name="action" value="update_saw">
                
                <h3 style="margin:20px 0 10px; color:#fff">Porsi Bobot Utama (Total 1.0)</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Skill Akademik (W1)</label>
                        <input type="number" step="0.01" name="w1" value="<?= $sysConfig['saw_weight_academic'] ?? 0.40 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Skill Praktis (W2)</label>
                        <input type="number" step="0.01" name="w2" value="<?= $sysConfig['saw_weight_practical'] ?? 0.30 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Portofolio (W3)</label>
                        <input type="number" step="0.01" name="w3" value="<?= $sysConfig['saw_weight_portfolio'] ?? 0.20 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Sertifikasi (W4)</label>
                        <input type="number" step="0.01" name="w4" value="<?= $sysConfig['saw_weight_certification'] ?? 0.10 ?>" class="form-control">
                    </div>
                </div>

                <h3 style="margin:20px 0 10px; color:#fff">Sub-Bobot Akademik C1 (Total 1.0)</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Rata-rata Matkul Spesifik</label>
                        <input type="number" step="0.01" name="c1_course" value="<?= $sysConfig['saw_sub_weight_course'] ?? 0.70 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Skor Konversi IPK</label>
                        <input type="number" step="0.01" name="c1_ipk" value="<?= $sysConfig['saw_sub_weight_ipk'] ?? 0.30 ?>" class="form-control">
                    </div>
                </div>

                <h3 style="margin:20px 0 10px; color:#fff">Ambang Batas Kelulusan (KKM)</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Tier 1 (Internasional)</label>
                        <input type="number" step="1" name="tier1" value="<?= $sysConfig['saw_tier1_min'] ?? 85 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tier 2 (Nasional)</label>
                        <input type="number" step="1" name="tier2" value="<?= $sysConfig['saw_tier2_min'] ?? 70 ?>" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Tier 3 (Lokal)</label>
                        <input type="number" step="1" name="tier3" value="<?= $sysConfig['saw_tier3_min'] ?? 55 ?>" class="form-control">
                    </div>
                </div>

                <button type="submit" class="btn-submit" style="margin-top:20px;">Simpan Pengaturan SAW</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
