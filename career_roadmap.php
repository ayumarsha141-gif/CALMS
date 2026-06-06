<?php
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

requireRole('mahasiswa');
$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
$studentId = $profile['id'];

// Get target career ID
$targetCareerName = $profile['target_career'] ?? '';

// Handle career change from this page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_career'])) {
    $newCareer = trim($_POST['target_career'] ?? '');
    if ($newCareer) {
        $db->prepare("UPDATE mahasiswa_profiles SET target_career = ? WHERE id = ?")->execute([$newCareer, $studentId]);
        $targetCareerName = $newCareer;
        header('Location: career_roadmap.php?career_updated=1');
        exit;
    }
}

// All available roles from career_positions table
$stmt = $db->query("SELECT id, position_name FROM career_positions");
$allRoles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (!$targetCareerName && !empty($allRoles)) {
    // default
    $targetCareerName = current($allRoles);
}

$careerId = array_search($targetCareerName, $allRoles);

// Fetch Roadmap Steps and Student Grades
$roadmapSteps = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT rs.*, c.course_name_id, c.course_code, sc.grade
        FROM roadmap_steps rs
        LEFT JOIN courses c ON c.id = rs.course_id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        WHERE rs.career_id = ?
        ORDER BY rs.id ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $roadmapSteps = $stmt->fetchAll();
}

$activePage = 'roadmap';

function convertGradeToScore($grade) {
    $map = ['A' => 100, 'B+' => 90, 'B' => 80, 'C+' => 70, 'C' => 60, 'D+' => 50, 'D' => 0, 'E' => 0];
    return $map[strtoupper($grade ?? '')] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Roadmap — CALMS</title>
    <meta name="description" content="Peta jalan karir personalmu berdasarkan target posisi.">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .roadmap-hero { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-lg); padding:28px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; gap:20px; flex-wrap:wrap; }
        .roadmap-hero-left h2 { font-size:20px; font-weight:700; margin-bottom:4px; }
        .roadmap-hero-left p { font-size:13px; color:var(--text-secondary); }
        .readiness-pill { display:inline-flex; align-items:center; gap:6px; padding:8px 18px; border-radius:999px; background:rgba(34,211,238,0.1); border:1px solid rgba(34,211,238,0.25); color:var(--cyan); font-weight:700; font-size:14px; }
        .roadmap-timeline { position:relative; display: flex; flex-direction: column; gap: 15px;}
        .step-card { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:20px; display:flex; flex-direction:column; gap:10px; }
        .step-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px;}
        .step-title { font-size: 16px; font-weight: 700; color: var(--cyan); }
        .step-course { font-size: 14px; font-weight: 600; color: #fff; }
        .status-badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-review { background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid #ef4444; }
        .status-plan { background: rgba(245,158,11,0.2); color: #f59e0b; border: 1px solid #f59e0b; }
        .status-good { background: rgba(16,185,129,0.2); color: #10b981; border: 1px solid #10b981; }
        .saran-box { background: rgba(34,211,238,0.05); border-left: 3px solid var(--cyan); padding: 10px; font-size: 13px; color: var(--text-secondary); margin-top: 5px;}
        .saran-box a { color: var(--cyan); text-decoration: underline; }
        
        /* Career Selector */
        .career-selector { background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius-md); padding:18px 22px; margin-bottom:24px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
        .career-selector label { font-size:13px; color:var(--text-secondary); font-weight:500; flex-shrink:0; }
        .career-select { background:var(--bg-secondary); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; cursor:pointer; font-family:var(--font-sans); flex:1; min-width:200px; }
        .career-select:focus { outline:none; border-color:var(--cyan); }
        .career-change-btn { padding:8px 18px; background:var(--cyan); color:#0a0f1a; border:none; border-radius:var(--radius-sm); font-size:13px; font-weight:700; cursor:pointer; transition:var(--transition); }
        .career-change-btn:hover { opacity:.85; }

        .timeline-footer { background:var(--bg-card); border:1px solid rgba(34,211,238,0.2); border-radius:var(--radius-md); padding:20px; text-align:center; margin-top:8px; }
        .timeline-footer p { font-size:13px; color:var(--text-secondary); }
        .timeline-footer strong { color:var(--cyan); }
        .alert-success-sm { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#10b981; padding:10px 16px; border-radius:var(--radius-sm); margin-bottom:18px; font-size:13px; }
    </style>
</head>
<body class="dashboard-body">
<?php include 'includes/sidebar.php'; ?>

<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Career Roadmap</h1>
                <p class="page-sub">Peta jalan karir personal berdasarkan nilai akademikmu</p>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['career_updated'])): ?>
    <div class="alert-success-sm">✅ Target karir berhasil diperbarui!</div>
    <?php endif; ?>

    <!-- Career Selector -->
    <form method="POST" class="career-selector">
        <label>🎯 Target Karir:</label>
        <select name="target_career" class="career-select">
            <?php foreach ($allRoles as $role): ?>
            <option value="<?= htmlspecialchars($role) ?>" <?= $targetCareerName === $role ? 'selected' : '' ?>><?= htmlspecialchars($role) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="change_career" class="career-change-btn">Ubah Roadmap</button>
        <span style="font-size:12px;color:var(--text-muted)">Juga tersimpan ke profil kamu</span>
    </form>

    <!-- Hero -->
    <div class="roadmap-hero">
        <div class="roadmap-hero-left">
            <h2>Roadmap: <?= htmlspecialchars($targetCareerName) ?></h2>
            <p>Peta jalan berbasis evaluasi nilai transkrip dan mata kuliah terkait.</p>
        </div>
    </div>

    <!-- Timeline / Steps -->
    <div class="roadmap-timeline">
        <?php if (empty($roadmapSteps)): ?>
            <div style="text-align:center; padding: 40px; background: rgba(255,255,255,0.05); border-radius: 8px;">
                <p>Belum ada roadmap yang dikonfigurasi untuk karir ini.</p>
                <p style="font-size:12px; color:var(--text-muted);">Admin dapat menambahkannya di halaman Master Data Setup Roadmap.</p>
            </div>
        <?php else: ?>
            <?php foreach ($roadmapSteps as $step): 
                $statusClass = '';
                $statusText = '';
                $showSaran = false;
                
                if ($step['type_matkul'] === 'Wajib') {
                    $score = convertGradeToScore($step['grade']);
                    if (!$step['grade'] || $score <= 60) {
                        $statusClass = 'status-review';
                        $statusText = 'Perlu Ditinjau';
                        $showSaran = true;
                    } else {
                        $statusClass = 'status-good';
                        $statusText = 'Dikuasai (Nilai '.$step['grade'].')';
                    }
                } else if ($step['type_matkul'] === 'Pilihan') {
                    if (!$step['grade']) {
                        $statusClass = 'status-plan';
                        $statusText = 'Rencana Masa Depan';
                        $showSaran = true;
                    } else {
                        $statusClass = 'status-good';
                        $statusText = 'Telah Diambil (Nilai '.$step['grade'].')';
                    }
                }
            ?>
            <div class="step-card">
                <div class="step-header">
                    <span class="step-title"><?= htmlspecialchars($step['step_name']) ?></span>
                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                </div>
                <div class="step-course">
                    📖 Mata Kuliah: <?= htmlspecialchars($step['course_name_id'] ?? 'N/A') ?> 
                    <span style="font-size:12px; color:var(--text-muted); font-weight:normal;">(<?= $step['type_matkul'] ?>)</span>
                </div>
                
                <?php if ($showSaran): ?>
                <div class="saran-box">
                    <strong>💡 Saran Matkul:</strong> <?= nl2br(htmlspecialchars($step['saran_matkul'] ?? '')) ?><br><br>
                    <?php if ($step['saran_kursus']): ?>
                    <strong>🌐 Rekomendasi Eksternal:</strong> 
                    <?php if ($step['saran_kursus_url']): ?>
                        <a href="<?= htmlspecialchars($step['saran_kursus_url']) ?>" target="_blank"><?= htmlspecialchars($step['saran_kursus']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($step['saran_kursus']) ?>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="timeline-footer" style="margin-top:16px;">
        <p>Roadmap ini dirancang secara dinamis berdasarkan <strong>Nilai Transkrip Kamu</strong>.</p>
    </div>
</main>

<script src="main.js"></script>
<script>
const toggle  = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));
</script>
</body>
</html>
