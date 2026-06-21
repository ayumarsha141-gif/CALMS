<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'dosen') { header('Location: pages/dosen/dashboard_dosen.php'); exit; }
    if ($role === 'admin') { header('Location: pages/admin/dashboardAdmin.php'); exit; }
    header('Location: pages/user/dashboard.php'); exit;
}

$DOSEN_ACCOUNTS = [
    ['email' => 'dosen1@calms.ac.id', 'password' => 'dosen123', 'name' => 'Royana Afwani, ST., MT.'],
    ['email' => 'dosen2@calms.ac.id', 'password' => 'dosen123', 'name' => 'Dwi Ratnasari, S.Kom., M.T.'],
    ['email' => 'dosen3@calms.ac.id', 'password' => 'dosen123', 'name' => 'Ir. Sri Endang Anjarwani, M.Kom'],
    ['email' => 'dosen4@calms.ac.id', 'password' => 'dosen123', 'name' => 'Herliana Rosika, S.Kom., M.Kom.'],
    ['email' => 'dosen5@calms.ac.id', 'password' => 'dosen123', 'name' => 'Santi Ika Murpratiwi, S. Kom., M.T.'],
    ['email' => 'dosen6@calms.ac.id', 'password' => 'dosen123', 'name' => 'I Wayan Agus Arimbawa, S.T., M.Eng., Ph.D.'],
    ['email' => 'dosen7@calms.ac.id', 'password' => 'dosen123', 'name' => 'Dr.Eng. I Gde Putu Wirarama WW., ST., MT.'],
    ['email' => 'dosen8@calms.ac.id', 'password' => 'dosen123', 'name' => 'Fitri Bimantoro, ST.,M.Kom.'],
];

// Akun admin hardcoded
$ADMIN_ACCOUNTS = [
    ['email' => 'admin@calms.ac.id', 'password' => 'admin123', 'name' => 'Administrator CALMS'],
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        foreach ($ADMIN_ACCOUNTS as $admin) {

            if (
                $admin['email'] === $email &&
                $admin['password'] === $password
            ) {

                session_regenerate_id(true);

                $_SESSION['user_id']  = 'admin';
                $_SESSION['fullname'] = $admin['name'];
                $_SESSION['email']    = $email;
                $_SESSION['role']     = 'admin';

                header('Location: pages/admin/dashboardAdmin.php');
                exit;
            }
        }

        // Cek dosen
        foreach ($DOSEN_ACCOUNTS as $dosen) {
            if ($dosen['email'] === $email && $dosen['password'] === $password) {
                $_SESSION['user_id']  = 'dosen_' . md5($email);
                $_SESSION['fullname'] = $dosen['name'];
                $_SESSION['email']    = $email;
                $_SESSION['role']     = 'dosen';
                header('Location: pages/dosen/dashboard_dosen.php');
                exit;
            }
        }

        // Cek mahasiswa di DB
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, fullname, email, password, role FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['email']    = $user['email'];
            $_SESSION['role']     = $user['role'];
            session_regenerate_id(true);
            header('Location: pages/user/dashboard.php');
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — CALMS</title>
    <link rel="stylesheet" href="styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .login-hint { margin-top: 24px; padding: 16px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: var(--radius-md); }
        .hint-title { font-size: 10px; letter-spacing: 1.2px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
        .hint-row { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid var(--border); }
        .hint-row:last-child { border-bottom: none; padding-bottom: 0; }
        .hint-badge { font-size: 10px; padding: 3px 10px; border-radius: 999px; font-weight: 600; white-space: nowrap; flex-shrink: 0; }
        .badge-mhs { background: rgba(16,185,129,0.12); color: #10b981; }
        .badge-dos { background: rgba(167,139,250,0.12); color: #a78bfa; }
        .badge-adm { background: rgba(239,68,68,0.12);  color: #ef4444; }
        .hint-desc { font-size: 11px; color: var(--text-muted); line-height: 1.4; }
    </style>
</head>
<body>
<div class="auth-page">
    <div class="auth-box">
        <div class="auth-logo">
            <div><span class="logo-text">CALMS</span><span class="logo-dot">.</span></div>
            <p>Career Adaptive Learning Management System</p>
        </div>

        <h1 class="auth-title">Selamat Datang</h1>
        <p class="auth-subtitle">Masuk ke akun CALMS kamu</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" novalidate>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    placeholder="nama@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autocomplete="email">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                    placeholder="Masukkan password"
                    required autocomplete="current-password">
            </div>
            <button type="submit" class="btn-primary btn-full">Masuk →</button>
        </form>

        <p class="auth-footer">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </p>
        <p class="auth-footer auth-footer--mt">
            <a href="index.php">← Kembali ke Beranda</a>
        </p>

        <div class="login-hint">
            <div class="hint-title">Info Login</div>
            <div class="hint-row">
                <span class="hint-badge badge-mhs">Mahasiswa</span>
                <span class="hint-desc">Daftar akun sendiri via halaman registrasi</span>
            </div>
            <div class="hint-row">
                <span class="hint-badge badge-dos">Dosen Wali</span>
                <span class="hint-desc">Gunakan email &amp; password khusus dosen</span>
            </div>
            <div class="hint-row">
                <span class="hint-badge badge-adm">Admin</span>
                <span class="hint-desc">Gunakan email &amp; password khusus admin</span>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const emailRe  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRe.test(email)) { e.preventDefault(); alert('Masukkan email yang valid.'); return; }
    if (password.length < 6) { e.preventDefault(); alert('Password minimal 6 karakter.'); }
});
</script>
</body>
</html>