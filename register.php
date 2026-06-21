<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: pages/user/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $nim      = trim($_POST['nim'] ?? '');
    $semester = intval($_POST['semester'] ?? 1);
    $target   = trim($_POST['target_career'] ?? '');

    if (empty($fullname) || empty($email) || empty($password) || empty($nim)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } elseif ($semester < 1 || $semester > 8) {
        $error = 'Semester harus antara 1-8.';
    } else {
        $db = getDB();

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah terdaftar. Gunakan email lain.';
        } else {
            $stmt = $db->prepare("SELECT id FROM mahasiswa_profiles WHERE nim = ?");
            $stmt->execute([$nim]);
            if ($stmt->fetch()) {
                $error = 'NIM sudah terdaftar.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                $stmt = $db->prepare("INSERT INTO users (fullname, email, password, role) VALUES (?, ?, ?, 'mahasiswa')");
                $stmt->execute([$fullname, $email, $hashedPassword]);
                $userId = $db->lastInsertId();

                $stmt = $db->prepare("INSERT INTO mahasiswa_profiles (user_id, nim, semester, target_career) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $nim, $semester, $target]);

                $success = 'Akun berhasil dibuat! Silakan masuk.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar — CALMS</title>
    <link rel="stylesheet" href="styles/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="auth-box auth-box--wide">
        <div class="auth-logo">
            <div><span class="logo-text">CALMS</span><span class="logo-dot">.</span></div>
            <p>Career Adaptive Learning Management System</p>
        </div>

        <h1 class="auth-title">Buat Akun</h1>
        <p class="auth-subtitle">Daftar sebagai mahasiswa Informatika Unram</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <br><a href="login.php" class="alert-link">→ Masuk sekarang</a>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" action="register.php" novalidate id="registerForm">
            <div class="form-group">
                <label for="fullname">Nama Lengkap</label>
                <input type="text" id="fullname" name="fullname"
                    placeholder="Nama lengkap sesuai KTM"
                    value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="nim">NIM</label>
                    <input type="text" id="nim" name="nim"
                        placeholder="F1D024XXXXX"
                        value="<?= htmlspecialchars($_POST['nim'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester">
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (($_POST['semester'] ?? 1) == $i) ? 'selected' : '' ?>>
                                Semester <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    placeholder="nama@gmail.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="target_career">Target Karir (Opsional)</label>
                <select id="target_career" name="target_career">
                    <option value="">-- Pilih target karir --</option>
                    <?php
                    $careers = ['Backend Developer','Frontend Developer','Full Stack Developer',
                                'Data Scientist','Data Analyst','ML Engineer','Cloud Engineer',
                                'DevOps Engineer','Cybersecurity Analyst','Mobile Developer','UI/UX Designer'];
                    foreach ($careers as $c):
                        $sel = (($_POST['target_career'] ?? '') === $c) ? 'selected' : '';
                    ?>
                    <option value="<?= $c ?>" <?= $sel ?>><?= $c ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                    placeholder="Minimal 8 karakter"
                    required autocomplete="new-password">
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                    placeholder="Ulangi password"
                    required autocomplete="new-password">
            </div>

            <button type="submit" class="btn-primary btn-full">Daftar →</button>
        </form>
        <?php endif; ?>

        <p class="auth-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </p>
        <p class="auth-footer auth-footer--mt">
            <a href="index.php">← Kembali ke Beranda</a>
        </p>
    </div>
</div>

<script>
document.getElementById('registerForm')?.addEventListener('submit', function(e) {
    const pw  = document.getElementById('password').value;
    const cpw = document.getElementById('confirm_password').value;
    if (pw.length < 8) {
        e.preventDefault();
        alert('Password minimal 8 karakter.');
        return;
    }
    if (pw !== cpw) {
        e.preventDefault();
        alert('Konfirmasi password tidak cocok.');
    }
});
</script>
</body>
</html>
