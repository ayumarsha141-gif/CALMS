<?php
<<<<<<< HEAD
/**
 * CALMS — Dosen Sidebar
 * Usage: set $activePage before including
 * $activePage: 'dosen_dashboard' | 'dosen_mahasiswa' | 'dosen_notifications'
 */
if (!isset($activePage)) $activePage = '';

$nav = [
    ['page'=>'dosen_dashboard',      'href'=>'dosen_dashboard.php',      'label'=>'Dashboard',       'icon'=>'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
    ['page'=>'dosen_mahasiswa',      'href'=>'dosen_mahasiswa.php',      'label'=>'Monitor Mahasiswa','icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['page'=>'dosen_notifications',  'href'=>'dosen_notifications.php',  'label'=>'Notifikasi At-Risk','icon'=>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
];

// Count unread notifications
$unreadCount = 0;
try {
    $stmtN = $db->prepare("SELECT COUNT(*) FROM dosen_notifications WHERE dosen_id = ? AND is_read = 0");
    $stmtN->execute([$user['id']]);
    $unreadCount = (int)$stmtN->fetchColumn();
} catch (Exception $e) {}
=======

if (!isset($activePageDosen)) $activePageDosen = '';

$navDosen = [
    ['page' => 'dashboard',         'href' => 'dashboard_dosen.php',        'label' => 'Dashboard',           'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
    ['page' => 'monitoring',        'href' => 'dosen_monitoring.php',        'label' => 'Monitoring Mahasiswa', 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['page' => 'skill_report',      'href' => 'dosen_skill_report.php',      'label' => 'Laporan Skill Gap',    'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ['page' => 'lab_review',        'href' => 'dosen_lab_review.php',        'label' => 'Kompatibilitas Lab',   'icon' => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>'],
    ['page' => 'simulation_report', 'href' => 'dosen_simulation_report.php', 'label' => 'Hasil Simulasi',       'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
];
$dosenUser = getDosenUser();

function getInitials(string $name): string {
    $parts = explode(' ', $name);
    $initials = '';
    foreach ($parts as $part) {
        $clean = rtrim($part, '.,');
        if (strlen($clean) <= 3 || strpos($part, '.') !== false) continue;
        $initials .= strtoupper(substr($clean, 0, 1));
        if (strlen($initials) >= 2) break;
    }
    return $initials ?: strtoupper(substr($name, 0, 2));
}

function getDisplayName(string $name): string {
    $name = preg_replace('/^(Dr\.Eng\.|Dr\.|Ir\.|Prof\.)\s*/i', '', $name);
    $parts = explode(' ', trim($name));
    return implode(' ', array_slice($parts, 0, 2));
}
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
<<<<<<< HEAD
        <span style="font-size:10px;background:rgba(167,139,250,.15);color:#a78bfa;padding:2px 8px;border-radius:999px;margin-left:4px;border:1px solid rgba(167,139,250,.3);">Dosen</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['fullname'] ?? 'D', 0, 2)) ?></div>
        <div class="user-info">
            <strong><?= htmlspecialchars(explode(' ', $user['fullname'] ?? 'Dosen')[0]) ?></strong>
            <span><?= htmlspecialchars($dosenProfile['nidn'] ?? 'NIDN -') ?></span>
=======
    </div>

    <div class="sidebar-user">
        <div class="user-avatar" style="background:rgba(167,139,250,0.12);border:1px solid rgba(167,139,250,0.25);color:#a78bfa;">
            <?= getInitials($dosenUser['fullname']) ?>
        </div>
        <div class="user-info">
            <strong title="<?= htmlspecialchars($dosenUser['fullname']) ?>"><?= htmlspecialchars(getDisplayName($dosenUser['fullname'])) ?></strong>
            <span style="color:#a78bfa;font-size:10px;font-weight:600;">DOSEN WALI</span>
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Menu Dosen</div>
<<<<<<< HEAD
        <?php foreach ($nav as $item): ?>
        <a href="<?= $item['href'] ?>" <?= $activePage === $item['page'] ? 'class="active"' : '' ?> style="position:relative;">
=======
        <?php foreach ($navDosen as $item): ?>
        <a href="<?= $item['href'] ?>" <?= $activePageDosen === $item['page'] ? 'class="active"' : '' ?>>
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <?= $item['icon'] ?>
            </svg>
            <?= $item['label'] ?>
<<<<<<< HEAD
            <?php if ($item['page'] === 'dosen_notifications' && $unreadCount > 0): ?>
            <span style="position:absolute;right:12px;background:#ef4444;color:#fff;font-size:9px;font-weight:700;padding:1px 6px;border-radius:999px;"><?= $unreadCount ?></span>
            <?php endif; ?>
=======
>>>>>>> b7b294ad7a0bb0880777640ce9324cbf85b5bf87
        </a>
        <?php endforeach; ?>

        <div class="nav-group-label nav-group-label--mt">Akun</div>
        <a href="logout.php" class="nav-logout">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </nav>
</aside>
