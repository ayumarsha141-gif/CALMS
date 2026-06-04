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
$studentId = $profile['id'] ?? null;

$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname      = trim($_POST['fullname'] ?? '');
    $nim           = trim($_POST['nim'] ?? '');
    $semester      = (int)($_POST['semester'] ?? 1);
    $ipk           = (float)($_POST['ipk'] ?? 0);
    $targetCareer  = trim($_POST['target_career'] ?? '');
    $bio           = trim($_POST['bio'] ?? '');
    $linkedin      = trim($_POST['linkedin_url'] ?? '');
    $github        = trim($_POST['github_url'] ?? '');

    if (empty($fullname)) $errors[] = 'Nama lengkap tidak boleh kosong.';
    if ($ipk < 0 || $ipk > 4) $errors[] = 'IPK harus antara 0.00 – 4.00.';

    if (empty($errors)) {
        // Update users
        $stmt = $db->prepare("UPDATE users SET fullname = ? WHERE id = ?");
        $stmt->execute([$fullname, $user['id']]);

        // Update profile
        $stmt = $db->prepare("UPDATE mahasiswa_profiles SET nim=?, semester=?, ipk=?, target_career=?, bio=?, linkedin_url=?, github_url=? WHERE user_id=?");
        $stmt->execute([$nim, $semester, $ipk, $targetCareer, $bio, $linkedin, $github, $user['id']]);

        // Update session fullname
        $_SESSION['fullname'] = $fullname;

        header('Location: profile.php?saved=1');
        exit;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password'] ?? '';
    $confirmPw  = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $pwRow = $stmt->fetch();

    if (!password_verify($currentPw, $pwRow['password'])) {
        $errors[] = 'Password saat ini tidak sesuai.';
    } elseif (strlen($newPw) < 8) {
        $errors[] = 'Password baru minimal 8 karakter.';
    } elseif ($newPw !== $confirmPw) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    } else {
        $hash = password_hash($newPw, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hash, $user['id']]);
        header('Location: profile.php?pw=1');
        exit;
    }
}

// Stats
$stmt = $db->prepare("SELECT COUNT(*) FROM student_skills WHERE student_id = ? AND student_level > 0");
$stmt->execute([$studentId]);
$skillCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM student_certifications WHERE student_id = ? AND status = 'owned'");
$stmt->execute([$studentId]);
$certCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM simulations WHERE student_id = ?");
$stmt->execute([$studentId]);
$simCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT probability_score FROM simulations WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$studentId]);
$lastSim = $stmt->fetchColumn();
$lastSimPct = $lastSim ? round($lastSim * 100) : 0;

// Readiness
$stmt = $db->prepare("
    SELECT s.industry_level, COALESCE(ss.student_level,0) AS student_level
    FROM skills s
    LEFT JOIN student_skills ss ON ss.skill_id = s.id AND ss.student_id = ?
");
$stmt->execute([$studentId]);
$allSk = $stmt->fetchAll();
$rSum = 0; $rCount = 0;
foreach ($allSk as $sk) {
    if ($sk['student_level'] > 0) { $rSum += ($sk['student_level']/$sk['industry_level'])*100; $rCount++; }
}
$readiness = $rCount > 0 ? round($rSum/$rCount) : 0;

$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil — CALMS</title>
    <meta name="description" content="Kelola profil akademik dan karir kamu.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .profile-layout { display:grid; grid-template-columns:300px 1fr; gap:24px; align-items:start; }
        .profile-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; text-align:center; }
        .profile-avatar-big { width:90px; height:90px; border-radius:50%; background:rgba(34,211,238,0.12); border:2px solid rgba(34,211,238,0.35); color:var(--cyan); font-size:30px; font-weight:800; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
        .profile-name { font-size:18px; font-weight:700; margin-bottom:2px; }
        .profile-nim { font-size:13px; color:var(--text-muted); font-family:var(--font-mono); margin-bottom:4px; }
        .profile-career-badge { display:inline-block; font-size:12px; padding:4px 12px; border-radius:999px; background:rgba(34,211,238,0.08); border:1px solid rgba(34,211,238,0.2); color:var(--cyan); margin-bottom:20px; }
        .profile-stats-mini { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px; }
        .profile-stat-mini { background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-sm); padding:12px; }
        .profile-stat-mini-num { font-size:24px; font-weight:700; font-family:var(--font-mono); color:var(--cyan); }
        .profile-stat-mini-label { font-size:10px; color:var(--text-muted); margin-top:2px; }
        .profile-links { display:flex; flex-direction:column; gap:8px; margin-top:16px; }
        .profile-link-btn { display:flex; align-items:center; gap:8px; padding:9px 14px; background:var(--bg-secondary); border:1px solid var(--border); border-radius:var(--radius-sm); font-size:12px; color:var(--text-secondary); text-decoration:none; transition:var(--transition); }
        .profile-link-btn:hover { border-color:var(--border-hover); color:var(--text-primary); }
        .profile-link-btn svg { flex-shrink:0; }
        .form-card { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px; margin-bottom:20px; }
        .form-card-title { font-size:15px; font-weight:700; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--border); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-label { font-size:12px; color:var(--text-muted); font-weight:500; }
        .form-input { background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:9px 12px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); transition:var(--transition); }
        .form-input:focus { outline:none; border-color:var(--cyan); }
        textarea.form-input { resize:vertical; min-height:80px; }
        .alert-success { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); color:#10b981; padding:12px 18px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:13px; }
        .alert-error { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:#ef4444; padding:12px 18px; border-radius:var(--radius-sm); margin-bottom:20px; font-size:13px; }
        .readiness-mini { background:linear-gradient(135deg,rgba(34,211,238,0.08),rgba(167,139,250,0.08)); border:1px solid rgba(34,211,238,0.15); border-radius:var(--radius-md); padding:14px; margin-top:16px; }
        .readiness-mini-label { font-size:11px; color:var(--text-muted); margin-bottom:6px; }
        .readiness-mini-bar { height:8px; background:var(--border); border-radius:999px; overflow:hidden; }
        .readiness-mini-fill { height:100%; background:var(--cyan); border-radius:999px; transition:width 1s ease; }
        .readiness-mini-pct { font-size:20px; font-weight:700; font-family:var(--font-mono); color:var(--cyan); margin-bottom:2px; }
        @media(max-width:900px){ .profile-layout{grid-template-columns:1fr;} }
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
                <h1 class="page-title">Profil</h1>
                <p class="page-sub">Kelola informasi akademik dan target karirmu</p>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-success">✅ Profil berhasil disimpan!</div>
    <?php endif; ?>
    <?php if (isset($_GET['pw'])): ?>
    <div class="alert-success">🔒 Password berhasil diubah!</div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
    <div class="alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <div class="profile-layout">

        <!-- Left: Profile Card -->
        <div>
            <div class="profile-card">
                <div class="profile-avatar-big"><?= strtoupper(substr($profile['fullname'] ?? 'U', 0, 2)) ?></div>
                <div class="profile-name"><?= htmlspecialchars($profile['fullname'] ?? '-') ?></div>
                <div class="profile-nim"><?= htmlspecialchars($profile['nim'] ?? '-') ?></div>
                <?php if ($profile['target_career']): ?>
                <div class="profile-career-badge">🎯 <?= htmlspecialchars($profile['target_career']) ?></div>
                <?php endif; ?>

                <div class="profile-stats-mini">
                    <div class="profile-stat-mini">
                        <div class="profile-stat-mini-num"><?= $skillCount ?></div>
                        <div class="profile-stat-mini-label">Skills</div>
                    </div>
                    <div class="profile-stat-mini">
                        <div class="profile-stat-mini-num"><?= $certCount ?></div>
                        <div class="profile-stat-mini-label">Sertifikasi</div>
                    </div>
                    <div class="profile-stat-mini">
                        <div class="profile-stat-mini-num"><?= $simCount ?></div>
                        <div class="profile-stat-mini-label">Simulasi</div>
                    </div>
                    <div class="profile-stat-mini">
                        <div class="profile-stat-mini-num" style="font-size:18px"><?= number_format((float)($profile['ipk'] ?? 0), 2) ?></div>
                        <div class="profile-stat-mini-label">IPK</div>
                    </div>
                </div>

                <div class="readiness-mini">
                    <div class="readiness-mini-pct"><?= $readiness ?>%</div>
                    <div class="readiness-mini-label">Career Readiness Score</div>
                    <div class="readiness-mini-bar">
                        <div class="readiness-mini-fill" data-width="<?= $readiness ?>"></div>
                    </div>
                </div>

                <?php if ($profile['linkedin_url'] || $profile['github_url']): ?>
                <div class="profile-links">
                    <?php if ($profile['linkedin_url']): ?>
                    <a href="<?= htmlspecialchars($profile['linkedin_url']) ?>" target="_blank" class="profile-link-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="color:#0a66c2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                        LinkedIn Profile
                    </a>
                    <?php endif; ?>
                    <?php if ($profile['github_url']): ?>
                    <a href="<?= htmlspecialchars($profile['github_url']) ?>" target="_blank" class="profile-link-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>
                        GitHub Profile
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($profile['bio']): ?>
            <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px;margin-top:16px;">
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px;font-weight:500;">BIO</div>
                <p style="font-size:13px;color:var(--text-secondary);line-height:1.6;"><?= nl2br(htmlspecialchars($profile['bio'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Edit Forms -->
        <div>
            <!-- Edit Profile -->
            <div class="form-card">
                <div class="form-card-title">✏️ Edit Profil</div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap *</label>
                            <input class="form-input" type="text" name="fullname" value="<?= htmlspecialchars($profile['fullname'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">NIM</label>
                            <input class="form-input" type="text" name="nim" value="<?= htmlspecialchars($profile['nim'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Semester</label>
                            <select class="form-input" name="semester">
                                <?php for ($s=1;$s<=8;$s++): ?>
                                <option value="<?= $s ?>" <?= ($profile['semester'] ?? 1) == $s ? 'selected' : '' ?>>Semester <?= $s ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">IPK (0.00 – 4.00)</label>
                            <input class="form-input" type="number" name="ipk" step="0.01" min="0" max="4" value="<?= number_format((float)($profile['ipk'] ?? 0), 2) ?>">
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Target Karir</label>
                        <select class="form-input" name="target_career">
                            <?php
                            $careers = ['Data Scientist','Full Stack Developer','Cybersecurity Analyst','Cloud Engineer','Mobile Developer','Backend Developer','Frontend Developer','DevOps Engineer','UI/UX Designer','Data Engineer','ML Engineer','Network Engineer'];
                            foreach ($careers as $c): ?>
                            <option value="<?= $c ?>" <?= ($profile['target_career'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">Bio Singkat</label>
                        <textarea class="form-input" name="bio" placeholder="Ceritakan sedikit tentang dirimu..."><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">LinkedIn URL</label>
                            <input class="form-input" type="url" name="linkedin_url" placeholder="https://linkedin.com/in/..." value="<?= htmlspecialchars($profile['linkedin_url'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">GitHub URL</label>
                            <input class="form-input" type="url" name="github_url" placeholder="https://github.com/..." value="<?= htmlspecialchars($profile['github_url'] ?? '') ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="save-btn">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Simpan Perubahan
                    </button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="form-card">
                <div class="form-card-title">🔒 Ganti Password</div>
                <form method="POST">
                    <div class="form-group" style="margin-bottom:14px;">
                        <label class="form-label">Password Saat Ini</label>
                        <input class="form-input" type="password" name="current_password" placeholder="••••••••" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Password Baru (min. 8 karakter)</label>
                            <input class="form-input" type="password" name="new_password" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input class="form-input" type="password" name="confirm_password" placeholder="••••••••" required>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="save-btn" style="background:#1e293b;color:var(--text-primary);border:1px solid var(--border);">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        Ganti Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

document.querySelectorAll('[data-width]').forEach(el => {
    el.style.width = el.dataset.width + '%';
});
</script>
</body>
</html>
