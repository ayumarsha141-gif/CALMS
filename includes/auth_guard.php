<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . getBaseUrl() . '/login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden — CALMS</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body class="error-page">
    <div class="error-box">
        <h1 class="error-code">403</h1>
        <p class="error-msg">Akses ditolak. Kamu tidak memiliki izin ke halaman ini.</p>
        <a href="javascript:history.back()" class="btn-primary">← Kembali</a>
    </div>
</body>
</html>';
        exit;
    }
}

function getBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host;
}

function getCurrentUser(): array {
    return [
        'id'       => $_SESSION['user_id']  ?? null,
        'fullname' => $_SESSION['fullname'] ?? '',
        'email'    => $_SESSION['email']    ?? '',
        'role'     => $_SESSION['role']     ?? '',
    ];
}
