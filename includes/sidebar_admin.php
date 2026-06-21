<?php
$_activePage = $activePage ?? '';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar" style="background:rgba(245,158,11,0.15);border-color:rgba(245,158,11,0.3);color:#f59e0b;">
            <?= strtoupper(substr($user['fullname'] ?? 'A', 0, 2)) ?>
        </div>
        <div class="user-info">
            <strong><?= htmlspecialchars(explode(' ', $user['fullname'] ?? 'Admin')[0]) ?></strong>
            <span style="color:#f59e0b;font-size:10px;font-weight:600;letter-spacing:0.5px">ADMINISTRATOR</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Menu Admin</div>

        <a href="dashboardAdmin.php" class="<?= $_activePage === 'admin_dashboard' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
            </svg>
            Dashboard
        </a>

        <a href="admin_master.php?tab=career" class="<?= $_activePage === 'admin_career' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Posisi Karir & Skill
        </a>

        <a href="admin_master.php?tab=roadmap" class="<?= $_activePage === 'admin_roadmap' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            Setup Roadmap
        </a>

        <a href="admin_master.php?tab=lab" class="<?= $_activePage === 'admin_lab' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>
            </svg>
            Matriks Bobot LAB
        </a>

        <a href="admin_master.php?tab=saw" class="<?= $_activePage === 'admin_saw' ? 'active' : '' ?>">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Pengaturan SAW
        </a>

        <a href="../../logout.php" class="nav-logout">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <polyline points="16 17 21 12 16 7"/>
                <line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Keluar
        </a>
    </nav>
</aside>