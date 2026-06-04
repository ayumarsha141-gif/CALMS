<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: dashboard.php');
    elseif ($role === 'dosen') header('Location: dashboard.php');
    else header('Location: dashboard.php');
    exit;
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
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

            if ($user['role'] === 'admin') header('Location: dashboard.php');
            elseif ($user['role'] === 'dosen') header('Location: dashboard.php');
            else header('Location: dashboard.php');
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
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    </div>
</div>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const emailRe  = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email || !emailRe.test(email)) {
        e.preventDefault();
        alert('Masukkan email yang valid.');
        return;
    }
    if (password.length < 6) {
        e.preventDefault();
        alert('Password minimal 6 karakter.');
    }
});
</script>
</body>
</html>
