<?php
session_start();
require_once '../../includes/auth_guard.php';
require_once '../../config/database.php';

requireRole('dosen');
$user = getCurrentUser();
$db   = getDB();

// Semua mahasiswa + readiness
$students = $db->query("
    SELECT u.fullname, mp.nim, mp.semester, mp.ipk, mp.target_career,
           ROUND(AVG(ss.student_level / s.industry_level * 100)) AS readiness,
           COUNT(ss.id) AS skill_count
    FROM mahasiswa_profiles mp
    JOIN users u ON u.id = mp.user_id
    LEFT JOIN student_skills ss ON ss.student_id = mp.id
    LEFT JOIN skills s ON s.id = ss.skill_id
    GROUP BY mp.id
    ORDER BY readiness DESC
")->fetchAll();

$activePageDosen = 'monitoring';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Mahasiswa — CALMS</title>
    <link rel="stylesheet" href="../../styles/style.css">
    <link rel="stylesheet" href="../../styles/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        .dosen-table { width:100%; border-collapse:collapse; }
        .dosen-table th { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--text-muted); font-weight:600; text-align:left; padding:10px 14px; border-bottom:1px solid var(--border); }
        .dosen-table td { font-size:13px; padding:12px 14px; border-bottom:1px solid rgba(255,255,255,0.04); color:var(--text-secondary); }
        .dosen-table tr:last-child td { border-bottom:none; }
        .dosen-table tr:hover td { background:rgba(255,255,255,0.02); }
        .readiness-wrap { display:flex; align-items:center; gap:8px; }
        .readiness-bar { width:80px; height:5px; background:#1e293b; border-radius:999px; overflow:hidden; flex-shrink:0; }
        .readiness-fill { height:100%; border-radius:999px; }
        .nim-text { font-family:var(--font-mono); font-size:12px; }
        .no-data { color:var(--text-muted); font-style:italic; }
        .filter-bar { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
        .filter-input { background:var(--bg-card); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); width:240px; }
        .filter-input:focus { outline:none; border-color:var(--border-hover); }
        .filter-select { background:var(--bg-card); border:1px solid var(--border); color:var(--text-primary); padding:8px 14px; border-radius:var(--radius-sm); font-size:13px; font-family:var(--font-sans); }
        .summary-chips { display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap; }
        .chip { font-size:12px; padding:5px 14px; border-radius:999px; border:1px solid var(--border); color:var(--text-secondary); }
        .chip strong { color:var(--text-primary); }
    </style>
</head>
<body class="dashboard-body">
<?php include '../../includes/sidebar_dosen.php'; ?>
<main class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <h1 class="page-title">Monitoring Mahasiswa</h1>
                <p class="page-sub">Data seluruh mahasiswa yang terdaftar di CALMS</p>
            </div>
        </div>
    </div>

    <div class="summary-chips">
        <div class="chip">Total: <strong><?= count($students) ?> mahasiswa</strong></div>
        <div class="chip">Sudah isi skill: <strong><?= count(array_filter($students, fn($s) => $s['skill_count'] > 0)) ?></strong></div>
        <div class="chip">Belum isi skill: <strong><?= count(array_filter($students, fn($s) => $s['skill_count'] == 0)) ?></strong></div>
    </div>

    <div class="filter-bar">
        <input type="text" class="filter-input" id="searchInput" placeholder="Cari nama atau NIM...">
        <select class="filter-select" id="filterSemester">
            <option value="">Semua Semester</option>
            <?php for ($i=1; $i<=8; $i++): ?>
            <option value="<?= $i ?>">Semester <?= $i ?></option>
            <?php endfor; ?>
        </select>
        <select class="filter-select" id="filterReadiness">
            <option value="">Semua Readiness</option>
            <option value="tinggi">Tinggi (≥70%)</option>
            <option value="sedang">Sedang (40–69%)</option>
            <option value="rendah">Rendah (<40%)</option>
        </select>
    </div>

    <div class="dash-panel" style="padding:0;overflow:hidden;">
        <table class="dosen-table" id="studentTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th>NIM</th>
                    <th>Semester</th>
                    <th>IPK</th>
                    <th>Target Karir</th>
                    <th>Skill Terdata</th>
                    <th>Readiness</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $i => $s):
                    $r = $s['readiness'] ?? 0;
                    $rc = $r >= 70 ? '#10b981' : ($r >= 40 ? '#f59e0b' : '#ef4444');
                ?>
                <tr data-semester="<?= $s['semester'] ?>" data-readiness="<?= $r ?>">
                    <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                    <td style="font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($s['fullname']) ?></td>
                    <td class="nim-text"><?= htmlspecialchars($s['nim']) ?></td>
                    <td><?= $s['semester'] ?></td>
                    <td style="font-family:var(--font-mono)"><?= number_format($s['ipk'], 2) ?></td>
                    <td><?= $s['target_career'] ? htmlspecialchars($s['target_career']) : '<span class="no-data">-</span>' ?></td>
                    <td style="font-family:var(--font-mono);text-align:center"><?= $s['skill_count'] ?></td>
                    <td>
                        <?php if ($s['skill_count'] > 0): ?>
                        <div class="readiness-wrap">
                            <div class="readiness-bar">
                                <div class="readiness-fill" style="width:<?= $r ?>%;background:<?= $rc ?>"></div>
                            </div>
                            <span style="font-family:var(--font-mono);font-size:12px;color:<?= $rc ?>"><?= $r ?>%</span>
                        </div>
                        <?php else: ?>
                        <span class="no-data">Belum diisi</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
const toggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');
toggle?.addEventListener('click', () => sidebar.classList.toggle('open'));

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const sem = document.getElementById('filterSemester').value;
    const ready = document.getElementById('filterReadiness').value;
    document.querySelectorAll('#studentTable tbody tr').forEach(row => {
        const name = row.cells[1].textContent.toLowerCase();
        const nim  = row.cells[2].textContent.toLowerCase();
        const rowSem = row.dataset.semester;
        const rowR   = parseInt(row.dataset.readiness) || 0;
        let show = true;
        if (search && !name.includes(search) && !nim.includes(search)) show = false;
        if (sem && rowSem !== sem) show = false;
        if (ready === 'tinggi' && rowR < 70) show = false;
        if (ready === 'sedang' && (rowR < 40 || rowR >= 70)) show = false;
        if (ready === 'rendah' && rowR >= 40) show = false;
        row.style.display = show ? '' : 'none';
    });
}
document.getElementById('searchInput').addEventListener('input', filterTable);
document.getElementById('filterSemester').addEventListener('change', filterTable);
document.getElementById('filterReadiness').addEventListener('change', filterTable);
</script>
</body>
</html>
