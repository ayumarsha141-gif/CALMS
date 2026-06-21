<?php
$baseDir = __DIR__;

// Fix depth 2 (pages/user, pages/admin, pages/dosen)
$depth2Dirs = [
    $baseDir . '/pages/user',
    $baseDir . '/pages/admin',
    $baseDir . '/pages/dosen'
];

foreach ($depth2Dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = scandir($dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $path = $dir . '/' . $file;
            $content = file_get_contents($path);
            $orig = $content;

            // PHP requires
            $content = preg_replace("/require_once 'config\/database\.php';/", "require_once '../../config/database.php';", $content);
            $content = preg_replace("/require_once 'includes\/(.*?)';/", "require_once '../../includes/$1';", $content);
            $content = preg_replace("/include 'includes\/(.*?)';/", "include '../../includes/$1';", $content);

            // CSS Links
            $content = preg_replace('/href="style\.css"/', 'href="../../styles/style.css"', $content);
            $content = preg_replace('/href="dashboard\.css"/', 'href="../../styles/dashboard.css"', $content);
            $content = preg_replace('/href="style_patch\.css"/', 'href="../../styles/style_patch.css"', $content);

            // JS Links
            $content = preg_replace('/src="main\.js"/', 'src="../../script/main.js"', $content);

            if ($content !== $orig) {
                file_put_contents($path, $content);
                echo "Fixed $path\n";
            }
        }
    }
}

// Fix includes (depth 1)
$includesDir = $baseDir . '/includes';
if (is_dir($includesDir)) {
    $files = scandir($includesDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $path = $includesDir . '/' . $file;
            $content = file_get_contents($path);
            $orig = $content;

            if ($file === 'auth_guard.php') {
                $content = preg_replace("/header\('Location: login\.php'\);/", "header('Location: ../../login.php');", $content);
                $content = preg_replace('/href="style\.css"/', 'href="../../styles/style.css"', $content);
            }
            if ($file === 'sidebar.php' || $file === 'sidebar_admin.php' || $file === 'sidebar_dosen.php') {
                $content = preg_replace('/href="logout\.php"/', 'href="../../logout.php"', $content);
                $content = preg_replace('/src="\.\.\/<\?= htmlspecialchars\(\$avatarPath\) \?>"/ ', 'src="../../<?= htmlspecialchars($avatarPath) ?>" ', $content);
            }

            if ($content !== $orig) {
                file_put_contents($path, $content);
                echo "Fixed $path\n";
            }
        }
    }
}

// Fix root (depth 0)
$rootFiles = ['index.php', 'login.php', 'register.php', 'logout.php'];
foreach ($rootFiles as $file) {
    $path = $baseDir . '/' . $file;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $orig = $content;

        // Redirects
        $content = preg_replace("/header\('Location: dashboard_dosen\.php'\);/", "header('Location: pages/dosen/dashboard_dosen.php');", $content);
        $content = preg_replace("/header\('Location: dashboardAdmin\.php'\);/", "header('Location: pages/admin/dashboardAdmin.php');", $content);
        $content = preg_replace("/header\('Location: dashboard\.php'\);/", "header('Location: pages/user/dashboard.php');", $content);

        // CSS and JS
        $content = preg_replace('/href="style\.css"/', 'href="styles/style.css"', $content);
        $content = preg_replace('/src="main\.js"/', 'src="script/main.js"', $content);

        if ($content !== $orig) {
            file_put_contents($path, $content);
            echo "Fixed $path\n";
        }
    }
}
echo "Done.\n";
