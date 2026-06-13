<?php

if (!isset($activePage)) $activePage = '';

$nav = [
    ['page' => 'dashboard',      'href' => 'dashboard.php',        'label' => 'Dashboard',          'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>'],
    ['page' => 'input_nilai',    'href' => 'input_nilai.php',      'label' => 'Input Nilai Mata Kuliah',   'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 0 4 24V4.5A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['page' => 'skill_gap',      'href' => 'skill_gap.php',        'label' => 'Skill Gap',           'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
    ['page' => 'roadmap',        'href' => 'career_roadmap.php',   'label' => 'Career Roadmap',      'icon' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'],
    ['page' => 'simulation',     'href' => 'simulation.php',       'label' => 'Simulasi Rekrutmen',  'icon' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>'],
    ['page' => 'certifications', 'href' => 'certifications.php',   'label' => 'Sertifikasi dan Portofolio',         'icon' => '<circle cx="12" cy="8" r="6"/><path d="M9 12l2 2 4-4m-8 8h10"/>'],
    ['page' => 'lab',            'href' => 'lab_recommendation.php','label' => 'Lab Recommendation', 'icon' => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/>'],
    ['page' => 'industry',       'href' => 'industry_insight.php', 'label' => 'Industry Insight',    'icon' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="logo-text">CALMS</span><span class="logo-dot">.</span>
    </div>

    <div class="sidebar-user">
        <?php
        $avatarPath = $profile['avatar_path'] ?? '';
        $avatarFile = $avatarPath ? __DIR__ . '/../' . $avatarPath : '';
        $hasSidebarAvatar = $avatarFile && file_exists($avatarFile);
        ?>
        <?php if ($hasSidebarAvatar): ?>
        <img src="../<?= htmlspecialchars($avatarPath) ?>" alt="Avatar" class="user-avatar" style="object-fit:cover;">
        <?php else: ?>
        <div class="user-avatar"><?= strtoupper(substr($user['fullname'] ?? 'U', 0, 2)) ?></div>
        <?php endif; ?>
        <div class="user-info">
            <strong><?= htmlspecialchars(explode(' ', $user['fullname'] ?? 'User')[0]) ?></strong>
            <span><?= htmlspecialchars($profile['nim'] ?? '-') ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Menu Utama</div>
        <?php foreach ($nav as $item): ?>
        <a href="<?= $item['href'] ?>" <?= $activePage === $item['page'] ? 'class="active"' : '' ?>>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <?= $item['icon'] ?>
            </svg>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>

        <div class="nav-group-label nav-group-label--mt">Akun</div>
        <a href="profile.php" <?= $activePage === 'profile' ? 'class="active"' : '' ?>>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            Profil
        </a>
        <a href="logout.php" class="nav-logout">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Keluar
        </a>
    </nav>
</aside>
