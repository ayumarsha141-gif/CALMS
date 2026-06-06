<?php

if (session_status() === PHP_SESSION_NONE) session_start();

function requireDosen(): void {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dosen') {
        header('Location: login.php');
        exit;
    }
}

function getDosenUser(): array {
    return [
        'fullname' => $_SESSION['fullname'] ?? 'Dosen',
        'email'    => $_SESSION['email']    ?? '',
        'role'     => 'dosen',
    ];
}
