<?php
/**
 * CALMS — Dosen Sidebar
 */

if (!isset($activePageDosen)) {
    $activePageDosen = '';
}

$dosenUser = getDosenUser();

$navDosen = [
    [
        'page' => 'dashboard',
        'href' => 'dashboard_dosen.php',
        'label' => 'Dashboard',
        'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'
    ],
    [
        'page' => 'monitoring',
        'href' => 'dosen_monitoring.php',
        'label' => 'Monitoring Mahasiswa',
        'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
    ],
    [
        'page' => 'skill_report',
        'href' => 'dosen_skill_report.php',
        'label' => 'Laporan Skill Gap',
        'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'
    ],
    [
        'page' => 'lab_review',
        'href' => 'dosen_lab_review.php',
        'label' => 'Kompatibilitas Lab',
        'icon' => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>'
    ],
    [
        'page' => 'simulation_report',
        'href' => 'dosen_simulation_report.php',
        'label' => 'Hasil Simulasi',
        'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'
    ]
];

function getInitials(string $name): string
{
    $parts = explode(' ', trim($name));
    $initials = '';

    foreach ($parts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials ?: 'DS';
}

function getDisplayName(string $name): string
{
    return trim($name);
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span>
        <span class="logo-dot">.</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"
             style="background:rgba(167,139,250,.12);
                    border:1px solid rgba(167,139,250,.25);
                    color:#a78bfa;">
            <?= getInitials($dosenUser['fullname']) ?>
        </div>

        <div class="user-info">
            <strong><?= htmlspecialchars(getDisplayName($dosenUser['fullname'])) ?></strong>
            <span>Dosen Wali</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Menu Dosen</div>

        <?php foreach ($navDosen as $item): ?>
            <a href="<?= $item['href'] ?>"
               <?= $activePageDosen === $item['page'] ? 'class="active"' : '' ?>>
                <svg width="16"
                     height="16"
                     fill="none"
                     stroke="currentColor"
                     stroke-width="2"
                     viewBox="0 0 24 24">
                    <?= $item['icon'] ?>
                </svg>
                <?= $item['label'] ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-group-label nav-group-label--mt">Akun</div>

        <a href="../../logout.php" class="nav-logout">
            <svg width="16"
                 height="16"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Keluar
        </a>
    </nav>
</aside>