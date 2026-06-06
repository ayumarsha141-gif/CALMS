<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

// Ambil profil
$stmt = $db->prepare("
    SELECT mp.*, u.fullname, u.email
    FROM mahasiswa_profiles mp
    JOIN users u ON u.id = mp.user_id
    WHERE mp.user_id = ?
");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

// Ambil skill mahasiswa
$stmt = $db->prepare("
    SELECT ss.skill_id, ss.student_level, s.skill_name, s.category, s.industry_level
    FROM student_skills ss
    JOIN skills s ON s.id = ss.skill_id
    JOIN mahasiswa_profiles mp ON mp.id = ss.student_id
    WHERE mp.user_id = ?
    ORDER BY s.category, s.skill_name
");
$stmt->execute([$user['id']]);
$mySkills = $stmt->fetchAll();

// Ambil sertifikasi milik mahasiswa
$stmt = $db->prepare("
    SELECT * FROM student_certifications
    WHERE student_id = ? AND status = 'owned'
    ORDER BY tier ASC, created_at DESC
");
$stmt->execute([$profile['id']]);
$myCerts = $stmt->fetchAll();

// Ambil proyek mahasiswa
$stmt = $db->prepare("
    SELECT * FROM student_projects
    WHERE student_id = ?
    ORDER BY created_year DESC, created_at DESC
");
$stmt->execute([$profile['id']]);
$myProjects = $stmt->fetchAll();

$success = '';
$error   = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Silakan refresh halaman.';
    } else {
        $action = $_POST['action'] ?? '';

        // ── Update info dasar ──
        if ($action === 'update_profile') {
            $fullname     = substr(trim($_POST['fullname'] ?? ''), 0, 255);
            $semester     = intval($_POST['semester'] ?? 1);
            $ipk          = floatval($_POST['ipk'] ?? 0);
            $target       = substr(trim($_POST['target_career'] ?? ''), 0, 100);
            $bio          = substr(trim($_POST['bio'] ?? ''), 0, 500);
            $linkedin     = substr(trim($_POST['linkedin_url'] ?? ''), 0, 500);
            $github       = substr(trim($_POST['github_url'] ?? ''), 0, 500);

            if (empty($fullname)) {
                $error = 'Nama tidak boleh kosong.';
            } elseif ($semester < 1 || $semester > 8) {
                $error = 'Semester harus antara 1-8.';
            } elseif ($ipk < 0 || $ipk > 4) {
                $error = 'IPK harus antara 0.00 – 4.00.';
            } else {
                $stmt = $db->prepare("UPDATE users SET fullname = ? WHERE id = ?");
                $stmt->execute([$fullname, $user['id']]);

                $stmt = $db->prepare("
                    UPDATE mahasiswa_profiles
                    SET semester = ?, ipk = ?, target_career = ?, bio = ?, linkedin_url = ?, github_url = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$semester, $ipk, $target, $bio, $linkedin, $github, $user['id']]);

                $_SESSION['fullname'] = $fullname;
                $success = 'Profil berhasil diperbarui.';

                // Refresh data
                $stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
                $stmt->execute([$user['id']]);
                $profile = $stmt->fetch();
            }
        }

        // ── Upload foto profil ──
        elseif ($action === 'upload_avatar') {
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file    = $_FILES['avatar'];
                $allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
                $maxSize = 5 * 1024 * 1024; // 5MB

                if (!in_array($file['type'], $allowed)) {
                    $error = 'Format foto tidak didukung. Gunakan JPG, PNG, atau WebP.';
                } elseif ($file['size'] > $maxSize) {
                    $error = 'Ukuran foto maksimal 5MB.';
                } else {
                    $uploadDir  = __DIR__ . '/uploads/avatars/';
                    $filename   = $user['id'] . '_' . time() . '.jpg';
                    $targetPath = $uploadDir . $filename;

                    // Resize & convert to JPEG using GD
                    $src = null;
                    if ($file['type'] === 'image/png') {
                        $src = imagecreatefrompng($file['tmp_name']);
                    } elseif ($file['type'] === 'image/gif') {
                        $src = imagecreatefromgif($file['tmp_name']);
                    } elseif ($file['type'] === 'image/webp') {
                        $src = imagecreatefromwebp($file['tmp_name']);
                    } else {
                        $src = imagecreatefromjpeg($file['tmp_name']);
                    }

                    if ($src) {
                        $w = imagesx($src); $h = imagesy($src);
                        $size  = min($w, $h);
                        $dst   = imagecreatetruecolor(200, 200);
                        // Center crop
                        $srcX = ($w - $size) / 2;
                        $srcY = ($h - $size) / 2;
                        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, 200, 200, $size, $size);
                        imagejpeg($dst, $targetPath, 85);
                        imagedestroy($src); imagedestroy($dst);

                        // Delete old avatar
                        if (!empty($profile['avatar_path'])) {
                            @unlink($uploadDir . basename($profile['avatar_path']));
                        }

                        $avatarPath = 'uploads/avatars/' . $filename;
                        try {
                            $db->prepare("UPDATE mahasiswa_profiles SET avatar_path = ? WHERE user_id = ?")->execute([$avatarPath, $user['id']]);
                            $success = 'Foto profil berhasil diperbarui!';
                            $profile['avatar_path'] = $avatarPath;
                        } catch (PDOException $e) {
                            $success = 'Foto tersimpan tapi kolom DB belum ada. Jalankan calms_db_update2.sql terlebih dahulu.';
                        }
                    } else {
                        $error = 'Gagal memproses gambar. Pastikan file tidak corrupt.';
                    }
                }
            } else {
                $error = 'Tidak ada file yang diunggah atau terjadi error upload.';
            }
        }

        // ── Simpan skill ──
        elseif ($action === 'update_skills') {
            $skillLevels = $_POST['skill_level'] ?? [];
            $db->beginTransaction();
            try {
                // Hapus skill lama lalu insert ulang
                $stmt = $db->prepare("DELETE FROM student_skills WHERE student_id = ?");
                $stmt->execute([$profile['id']]);

                $stmt = $db->prepare("INSERT INTO student_skills (student_id, skill_id, student_level) VALUES (?, ?, ?)");
                foreach ($skillLevels as $skillId => $level) {
                    $level = intval($level);
                    if ($level > 0) { // hanya simpan skill yang dikuasai
                        $stmt->execute([$profile['id'], intval($skillId), min(10, $level)]);
                    }
                }
                $db->commit();
                $success = 'Skill berhasil disimpan.';

                // Refresh
                $stmt = $db->prepare("SELECT ss.skill_id, ss.student_level, s.skill_name, s.category, s.industry_level FROM student_skills ss JOIN skills s ON s.id = ss.skill_id JOIN mahasiswa_profiles mp ON mp.id = ss.student_id WHERE mp.user_id = ? ORDER BY s.category, s.skill_name");
                $stmt->execute([$user['id']]);
                $mySkills = $stmt->fetchAll();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal menyimpan skill. Coba lagi.';
            }
        }

        // ── Tambah sertifikasi ──
        elseif ($action === 'add_cert') {
            $certName = substr(trim($_POST['cert_name'] ?? ''), 0, 255);
            $provider = substr(trim($_POST['cert_provider'] ?? ''), 0, 100);
            $tier     = intval($_POST['cert_tier'] ?? 3);
            $obtained = $_POST['obtained_date'] ?? null;
            $score    = $tier === 1 ? 100 : ($tier === 2 ? 75 : 50);

            if (empty($certName)) {
                $error = 'Nama sertifikasi tidak boleh kosong.';
            } elseif (!in_array($tier, [1,2,3])) {
                $error = 'Tier tidak valid.';
            } else {
                $stmt = $db->prepare("INSERT INTO student_certifications (student_id, cert_name, provider, tier, score, status, obtained_date) VALUES (?, ?, ?, ?, ?, 'owned', ?)");
                $stmt->execute([$profile['id'], $certName, $provider, $tier, $score, $obtained ?: null]);
                $success = 'Sertifikasi berhasil ditambahkan.';

                $stmt = $db->prepare("SELECT * FROM student_certifications WHERE student_id = ? AND status = 'owned' ORDER BY tier ASC, created_at DESC");
                $stmt->execute([$profile['id']]);
                $myCerts = $stmt->fetchAll();
            }
        }

        // ── Hapus sertifikasi ──
        elseif ($action === 'delete_cert') {
            $certId = intval($_POST['cert_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM student_certifications WHERE id = ? AND student_id = ?");
            $stmt->execute([$certId, $profile['id']]);
            $success = 'Sertifikasi dihapus.';

            $stmt = $db->prepare("SELECT * FROM student_certifications WHERE student_id = ? AND status = 'owned' ORDER BY tier ASC, created_at DESC");
            $stmt->execute([$profile['id']]);
            $myCerts = $stmt->fetchAll();
        }

        // ── Tambah proyek ──
        elseif ($action === 'add_project') {
            $projName = substr(trim($_POST['project_name'] ?? ''), 0, 255);
            $desc     = substr(trim($_POST['description'] ?? ''), 0, 1000);
            $scale    = $_POST['scale'] ?? 'kecil';
            $tech     = substr(trim($_POST['tech_stack'] ?? ''), 0, 255);
            $url      = substr(trim($_POST['project_url'] ?? ''), 0, 500);
            $year     = intval($_POST['created_year'] ?? date('Y'));
            $score    = $scale === 'besar' ? 40 : 20;

            if (empty($projName)) {
                $error = 'Nama proyek tidak boleh kosong.';
            } elseif (!in_array($scale, ['besar','kecil'])) {
                $error = 'Skala proyek tidak valid.';
            } else {
                $stmt = $db->prepare("INSERT INTO student_projects (student_id, project_name, description, scale, score, tech_stack, project_url, created_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$profile['id'], $projName, $desc, $scale, $score, $tech, $url ?: null, $year]);
                $success = 'Proyek berhasil ditambahkan.';

                $stmt = $db->prepare("SELECT * FROM student_projects WHERE student_id = ? ORDER BY created_year DESC, created_at DESC");
                $stmt->execute([$profile['id']]);
                $myProjects = $stmt->fetchAll();
            }
        }

        // ── Hapus proyek ──
        elseif ($action === 'delete_project') {
            $projId = intval($_POST['project_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM student_projects WHERE id = ? AND student_id = ?");
            $stmt->execute([$projId, $profile['id']]);
            $success = 'Proyek dihapus.';

            $stmt = $db->prepare("SELECT * FROM student_projects WHERE student_id = ? ORDER BY created_year DESC, created_at DESC");
            $stmt->execute([$profile['id']]);
            $myProjects = $stmt->fetchAll();
        }
    }
}

// Ambil semua skill katalog untuk form
$skillsAll = $db->query("SELECT * FROM skills ORDER BY category, skill_name")->fetchAll();
$savedSkillMap = array_column($mySkills, 'student_level', 'skill_id');

$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — CALMS</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .profile-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } }

        .profile-avatar-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .profile-avatar-lg {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(34,211,238,0.12);
            border: 2px solid rgba(34,211,238,0.3);
            color: var(--cyan);
            font-size: 28px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-name { font-size: 17px; font-weight: 600; text-align: center; }
        .profile-nim  { font-size: 12px; color: var(--text-muted); font-family: var(--font-mono); }

        .tab-bar {
            display: flex;
            gap: 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: -1px;
        }

        .tab-btn:hover { color: var(--text-primary); }
        .tab-btn.active { color: var(--cyan); border-bottom-color: var(--cyan); }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .form-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }

        .field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 16px;
        }

        .field-group label {
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .field-group input,
        .field-group select,
        .field-group textarea {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            padding: 9px 12px;
            font-size: 13px;
            font-family: var(--font-sans);
            transition: var(--transition);
        }

        .field-group input:focus,
        .field-group select:focus,
        .field-group textarea:focus {
            outline: none;
            border-color: var(--cyan);
        }

        .field-group textarea { resize: vertical; min-height: 80px; }

        .btn-save {
            background: var(--cyan);
            color: #000;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 24px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-save:hover { opacity: 0.85; }

        .btn-danger-sm {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
            font-size: 11px;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-danger-sm:hover { background: rgba(239,68,68,0.2); }

        .skill-catalog-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .skill-catalog-table th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .skill-catalog-table td {
            padding: 8px 10px;
            border-bottom: 1px solid rgba(255,255,255,0.03);
        }
        .skill-catalog-table tr:last-child td { border-bottom: none; }

        .skill-range {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 6px;
            border-radius: 3px;
            background: var(--border);
            outline: none;
            cursor: pointer;
            accent-color: #22d3ee;
        }

        .skill-val {
            font-family: var(--font-mono);
            font-size: 12px;
            color: var(--cyan);
            min-width: 24px;
            text-align: center;
            display: inline-block;
        }

        .cat-filter {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        .cat-btn {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            transition: var(--transition);
        }
        .cat-btn.active, .cat-btn:hover {
            background: rgba(34,211,238,0.1);
            color: var(--cyan);
            border-color: rgba(34,211,238,0.3);
        }

        .cert-card, .proj-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            margin-bottom: 10px;
        }

        .tier-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .tier-1 { background: rgba(34,211,238,0.15); color: #22d3ee; }
        .tier-2 { background: rgba(96,165,250,0.15);  color: #60a5fa; }
        .tier-3 { background: rgba(148,163,184,0.12); color: #94a3b8; }

        .scale-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            flex-shrink: 0;
        }
        .scale-besar { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .scale-kecil { background: rgba(148,163,184,0.12); color: #94a3b8; }

        .cert-info, .proj-info { flex: 1; overflow: hidden; }
        .cert-info strong, .proj-info strong { display: block; font-size: 13px; font-weight: 600; margin-bottom: 2px; }
        .cert-info span, .proj-info span { font-size: 11px; color: var(--text-muted); }

        .add-form-box {
            background: var(--bg-secondary);
            border: 1px dashed var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            margin-top: 16px;
        }

        .add-form-title {
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--cyan);
        }

        .alert {
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981; }
        .alert-error   { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.2);  color: #ef4444; }

        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 500;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
        }
    </style>
</head>
<body class="dashboard-body">

<?php $activePage = 'profile'; include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
            <div>
                <h1 class="page-title">Profil Saya</h1>
                <p class="page-sub">Kelola data diri, skill, sertifikasi, dan portofolio</p>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="profile-grid">

        <!-- ── Kolom Kiri: Info Ringkas ── -->
        <div>
            <div class="dash-panel">
                <div class="profile-avatar-wrap">
                    <?php
                    $avatarPath = $profile['avatar_path'] ?? '';
                    $avatarFile = $avatarPath ? __DIR__ . '/' . $avatarPath : '';
                    $hasAvatar  = $avatarFile && file_exists($avatarFile);
                    ?>
                    <?php if ($hasAvatar): ?>
                    <img src="<?= htmlspecialchars($avatarPath) ?>?v=<?= filemtime($avatarFile) ?>" alt="Foto Profil" style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:2px solid rgba(34,211,238,0.4);">
                    <?php else: ?>
                    <div class="profile-avatar-lg">
                        <?= strtoupper(substr($profile['fullname'] ?? 'U', 0, 2)) ?>
                    </div>
                    <?php endif; ?>
                    <div class="profile-name"><?= htmlspecialchars($profile['fullname']) ?></div>
                    <div class="profile-nim"><?= htmlspecialchars($profile['nim'] ?? '-') ?></div>
                    <!-- Upload foto -->
                    <form method="POST" enctype="multipart/form-data" style="margin-top:8px;text-align:center;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="upload_avatar">
                        <label for="avatarFile" style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.25);color:var(--cyan);border-radius:999px;font-size:11px;font-weight:600;cursor:pointer;transition:.2s;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <?= $hasAvatar ? 'Ganti Foto' : 'Upload Foto' ?>
                        </label>
                        <input type="file" id="avatarFile" name="avatar" accept="image/*" style="display:none;" onchange="this.form.submit()">
                    </form>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:var(--text-muted)">Email</span>
                        <span><?= htmlspecialchars($profile['email']) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:var(--text-muted)">Semester</span>
                        <span><?= $profile['semester'] ?? '-' ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:var(--text-muted)">IPK</span>
                        <span style="font-family:var(--font-mono); color:var(--cyan)"><?= number_format($profile['ipk'] ?? 0, 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; font-size:13px;">
                        <span style="color:var(--text-muted)">Target Karir</span>
                        <span style="color:var(--cyan)"><?= htmlspecialchars($profile['target_career'] ?? '-') ?></span>
                    </div>
                    <?php if ($profile['linkedin_url']): ?>
                    <div style="font-size:13px;">
                        <a href="<?= htmlspecialchars($profile['linkedin_url']) ?>" target="_blank" rel="noopener" style="color:var(--cyan);">🔗 LinkedIn</a>
                    </div>
                    <?php endif; ?>
                    <?php if ($profile['github_url']): ?>
                    <div style="font-size:13px;">
                        <a href="<?= htmlspecialchars($profile['github_url']) ?>" target="_blank" rel="noopener" style="color:var(--cyan);">🐙 GitHub</a>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border); display:flex; gap:8px; flex-wrap:wrap;">
                    <span class="stat-pill">
                        📚 <?= count($mySkills) ?> Skill
                    </span>
                    <span class="stat-pill">
                        🏅 <?= count($myCerts) ?> Sertifikasi
                    </span>
                    <span class="stat-pill">
                        💼 <?= count($myProjects) ?> Proyek
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Kolom Kanan: Tab Panel ── -->
        <div class="dash-panel">

            <div class="tab-bar">
                <button class="tab-btn active" data-tab="info">Info Dasar</button>
                <button class="tab-btn" data-tab="skills">Skill</button>
                <button class="tab-btn" data-tab="certs">Sertifikasi</button>
                <button class="tab-btn" data-tab="projects">Portofolio</button>
            </div>

            <!-- Tab: Info Dasar -->
            <div class="tab-panel active" id="tab-info">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="form-grid-2">
                        <div class="field-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="fullname" value="<?= htmlspecialchars($profile['fullname']) ?>" required maxlength="255">
                        </div>
                        <div class="field-group">
                            <label>NIM</label>
                            <input type="text" value="<?= htmlspecialchars($profile['nim'] ?? '') ?>" disabled style="opacity:0.5; cursor:not-allowed;">
                        </div>
                        <div class="field-group">
                            <label>Semester</label>
                            <select name="semester">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($profile['semester'] ?? 1) == $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field-group">
                            <label>IPK (skala 4.00)</label>
                            <input type="number" name="ipk" min="0" max="4" step="0.01" value="<?= number_format($profile['ipk'] ?? 0, 2) ?>">
                        </div>
                    </div>

                    <div class="field-group">
                        <label>Target Karir</label>
                        <select name="target_career">
                            <option value="">-- Pilih target karir --</option>
                            <?php
                            $careers = [
                                'Backend Developer','Frontend Developer','Full Stack Developer',
                                'Data Scientist','Data Analyst','ML Engineer','Cloud Engineer',
                                'DevOps Engineer','Cybersecurity Analyst','Mobile Developer',
                                'UI/UX Designer','Network Engineer','QA Engineer',
                                'Database Administrator','Software Architect'
                            ];
                            foreach ($careers as $c):
                                $sel = ($profile['target_career'] === $c) ? 'selected' : '';
                            ?>
                            <option value="<?= $c ?>" <?= $sel ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field-group">
                        <label>Bio Singkat</label>
                        <textarea name="bio" maxlength="500" placeholder="Ceritakan sedikit tentang dirimu..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>

                    <div class="form-grid-2">
                        <div class="field-group">
                            <label>URL LinkedIn</label>
                            <input type="url" name="linkedin_url" placeholder="https://linkedin.com/in/username" value="<?= htmlspecialchars($profile['linkedin_url'] ?? '') ?>" maxlength="500">
                        </div>
                        <div class="field-group">
                            <label>URL GitHub</label>
                            <input type="url" name="github_url" placeholder="https://github.com/username" value="<?= htmlspecialchars($profile['github_url'] ?? '') ?>" maxlength="500">
                        </div>
                    </div>

                    <button type="submit" class="btn-save">Simpan Perubahan</button>
                </form>
            </div>

            <!-- Tab: Skill -->
            <div class="tab-panel" id="tab-skills">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="update_skills">

                    <div class="cat-filter" id="catFilter">
                        <button type="button" class="cat-btn active" data-cat="all">Semua</button>
                        <?php
                        $cats = array_unique(array_column($skillsAll, 'category'));
                        foreach ($cats as $cat):
                        ?>
                        <button type="button" class="cat-btn" data-cat="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></button>
                        <?php endforeach; ?>
                    </div>

                    <table class="skill-catalog-table" id="skillTable">
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Kategori</th>
                                <th>Standar Industri</th>
                                <th>Level Kamu (0–10)</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($skillsAll as $sk):
                            $saved = $savedSkillMap[$sk['id']] ?? 0;
                        ?>
                        <tr data-cat="<?= htmlspecialchars($sk['category']) ?>">
                            <td><?= htmlspecialchars($sk['skill_name']) ?></td>
                            <td><span style="font-size:11px; padding:2px 8px; border-radius:999px; background:rgba(255,255,255,0.06); color:var(--text-muted);"><?= htmlspecialchars($sk['category']) ?></span></td>
                            <td style="font-family:var(--font-mono); color:var(--text-muted); font-size:12px;"><?= $sk['industry_level'] ?>/10</td>
                            <td>
                                <input type="hidden" name="skill_industry[<?= $sk['id'] ?>]" value="<?= $sk['industry_level'] ?>">
                                <input type="range" class="skill-range" name="skill_level[<?= $sk['id'] ?>]"
                                    min="0" max="10" step="1" value="<?= $saved ?>"
                                    oninput="this.closest('tr').querySelector('.skill-val').textContent=this.value">
                            </td>
                            <td><span class="skill-val"><?= $saved ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="margin-top:16px;">
                        <button type="submit" class="btn-save">Simpan Skill</button>
                    </div>
                </form>
            </div>

            <!-- Tab: Sertifikasi -->
            <div class="tab-panel" id="tab-certs">
                <?php if (empty($myCerts)): ?>
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Belum ada sertifikasi. Tambahkan sertifikasi yang kamu miliki.</p>
                <?php else: ?>
                    <?php foreach ($myCerts as $cert):
                        $tierLabel = $cert['tier'] == 1 ? 'Internasional' : ($cert['tier'] == 2 ? 'BNSP' : 'Kursus');
                    ?>
                    <div class="cert-card">
                        <span class="tier-badge tier-<?= $cert['tier'] ?>">Tier <?= $cert['tier'] ?> — <?= $tierLabel ?></span>
                        <div class="cert-info">
                            <strong><?= htmlspecialchars($cert['cert_name']) ?></strong>
                            <span><?= htmlspecialchars($cert['provider'] ?? '-') ?>
                                <?= $cert['obtained_date'] ? ' · ' . date('M Y', strtotime($cert['obtained_date'])) : '' ?>
                            </span>
                        </div>
                        <form method="POST" style="flex-shrink:0;" onsubmit="return confirm('Hapus sertifikasi ini?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete_cert">
                            <input type="hidden" name="cert_id" value="<?= $cert['id'] ?>">
                            <button type="submit" class="btn-danger-sm">Hapus</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="add-form-box">
                    <div class="add-form-title">+ Tambah Sertifikasi</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="add_cert">
                        <div class="form-grid-2">
                            <div class="field-group">
                                <label>Nama Sertifikasi</label>
                                <input type="text" name="cert_name" placeholder="cth: AWS Cloud Practitioner" required maxlength="255">
                            </div>
                            <div class="field-group">
                                <label>Penerbit</label>
                                <input type="text" name="cert_provider" placeholder="cth: Amazon Web Services" maxlength="100">
                            </div>
                            <div class="field-group">
                                <label>Tier</label>
                                <select name="cert_tier">
                                    <option value="1">Tier 1 — Internasional (100 poin)</option>
                                    <option value="2">Tier 2 — Nasional BNSP (75 poin)</option>
                                    <option value="3" selected>Tier 3 — Kursus (50 poin)</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Tanggal Diperoleh</label>
                                <input type="date" name="obtained_date">
                            </div>
                        </div>
                        <button type="submit" class="btn-save">Tambah</button>
                    </form>
                </div>
            </div>

            <!-- Tab: Portofolio -->
            <div class="tab-panel" id="tab-projects">
                <?php if (empty($myProjects)): ?>
                    <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Belum ada proyek. Tambahkan portofolio proyekmu.</p>
                <?php else: ?>
                    <?php foreach ($myProjects as $proj): ?>
                    <div class="proj-card">
                        <span class="scale-badge scale-<?= $proj['scale'] ?>"><?= $proj['scale'] === 'besar' ? 'Besar' : 'Kecil' ?></span>
                        <div class="proj-info">
                            <strong><?= htmlspecialchars($proj['project_name']) ?></strong>
                            <span>
                                <?= htmlspecialchars($proj['tech_stack'] ?? '') ?>
                                <?= $proj['created_year'] ? ' · ' . $proj['created_year'] : '' ?>
                            </span>
                            <?php if ($proj['description']): ?>
                                <p style="font-size:12px; color:var(--text-secondary); margin-top:4px; line-height:1.5;">
                                    <?= htmlspecialchars(substr($proj['description'], 0, 120)) ?><?= strlen($proj['description']) > 120 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            <?php if ($proj['project_url']): ?>
                                <a href="<?= htmlspecialchars($proj['project_url']) ?>" target="_blank" rel="noopener" style="font-size:12px; color:var(--cyan);">🔗 Lihat Proyek</a>
                            <?php endif; ?>
                        </div>
                        <form method="POST" style="flex-shrink:0;" onsubmit="return confirm('Hapus proyek ini?')">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete_project">
                            <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                            <button type="submit" class="btn-danger-sm">Hapus</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="add-form-box">
                    <div class="add-form-title">+ Tambah Proyek</div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="add_project">
                        <div class="form-grid-2">
                            <div class="field-group">
                                <label>Nama Proyek</label>
                                <input type="text" name="project_name" placeholder="cth: Sistem Informasi Akademik" required maxlength="255">
                            </div>
                            <div class="field-group">
                                <label>Skala Proyek</label>
                                <select name="scale">
                                    <option value="besar">Besar — TA/Client/Teamwork (40 poin)</option>
                                    <option value="kecil" selected>Kecil — Tugas Harian (20 poin)</option>
                                </select>
                            </div>
                            <div class="field-group">
                                <label>Tech Stack</label>
                                <input type="text" name="tech_stack" placeholder="cth: Laravel, React, MySQL" maxlength="255">
                            </div>
                            <div class="field-group">
                                <label>Tahun</label>
                                <input type="number" name="created_year" min="2000" max="<?= date('Y') ?>" value="<?= date('Y') ?>">
                            </div>
                        </div>
                        <div class="field-group">
                            <label>Deskripsi Singkat</label>
                            <textarea name="description" maxlength="1000" placeholder="Jelaskan singkat apa yang dibangun..."></textarea>
                        </div>
                        <div class="field-group">
                            <label>URL Proyek (GitHub / Demo)</label>
                            <input type="url" name="project_url" placeholder="https://github.com/..." maxlength="500">
                        </div>
                        <button type="submit" class="btn-save">Tambah</button>
                    </form>
                </div>
            </div>

        </div><!-- .dash-panel -->
    </div><!-- .profile-grid -->
</main>

<script src="main.js"></script>
<script>
// Sidebar toggle
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('tab-' + this.dataset.tab).classList.add('active');
    });
});

// Category filter untuk tabel skill
document.querySelectorAll('.cat-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const cat = this.dataset.cat;
        document.querySelectorAll('#skillTable tbody tr').forEach(row => {
            row.style.display = (cat === 'all' || row.dataset.cat === cat) ? '' : 'none';
        });
    });
});
</script>
</body>
</html>