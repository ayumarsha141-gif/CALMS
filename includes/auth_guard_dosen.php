<?php
/**
 * Backward-compatible dosen auth guard.
 * Delegates to the main auth_guard.php to avoid code duplication.
 */
require_once __DIR__ . '/auth_guard.php';

function requireDosen(): void {
    requireRole('dosen');
}

function getDosenUser(): array {
    return getCurrentUser();
}
