<?php
/**
 * process_transcript.php — AJAX endpoint
 * Menerima hasil parsing transkrip PDF dari client-side JS
 * Menyimpan: IPK → mahasiswa_profiles, courses → student_courses, skills → student_skills
 */
session_start();
require_once 'includes/auth_guard.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'error'=>'Unauthenticated']); exit;
}

$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare("SELECT id FROM mahasiswa_profiles WHERE user_id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();
if (!$profile) {
    echo json_encode(['success'=>false,'error'=>'Profile not found']); exit;
}
$studentId = $profile['id'];

$data    = json_decode(file_get_contents('php://input'), true);
$ipk     = isset($data['ipk']) ? round((float)$data['ipk'], 2) : null;
$courses = $data['courses'] ?? [];   // [{course_id, course_name, grade, score}]
$skills  = $data['skills']  ?? [];   // [{skill_id, level}]

$updatedIPK      = false;
$savedCourseCnt  = 0;
$updatedSkillCnt = 0;

try {
    // ── 1. Save IPK to profile ──
    if ($ipk !== null && $ipk > 0 && $ipk <= 4.0) {
        $stmt = $db->prepare("UPDATE mahasiswa_profiles SET ipk = ? WHERE id = ?");
        $stmt->execute([$ipk, $studentId]);
        $updatedIPK = true;
    }

    // ── 2. Save taken courses (clear old transcript entries first) ──
    if (!empty($courses)) {
        try {
            $stmt = $db->prepare("DELETE FROM student_courses WHERE student_id = ? AND source = 'transcript'");
            $stmt->execute([$studentId]);

            foreach ($courses as $c) {
                $courseId   = isset($c['course_id']) && $c['course_id'] ? (int)$c['course_id'] : null;
                $courseName = substr(trim($c['course_name'] ?? ''), 0, 200);
                $grade      = strtoupper(trim($c['grade'] ?? ''));
                $score      = max(0, min(10, (int)($c['score'] ?? 0)));

                if (!$courseName || !$grade) continue;

                $stmt = $db->prepare("
                    INSERT INTO student_courses (student_id, course_id, course_name, grade, score, source)
                    VALUES (?, ?, ?, ?, ?, 'transcript')
                ");
                $stmt->execute([$studentId, $courseId, $courseName, $grade, $score]);
                $savedCourseCnt++;
            }
        } catch (PDOException $e) {
            // Table might not exist yet — skip gracefully
        }
    }

    // ── 3. Save skill levels ──
    if (!empty($skills)) {
        foreach ($skills as $s) {
            $skillId = (int)($s['skill_id'] ?? 0);
            $level   = max(0, min(10, (int)($s['level'] ?? 0)));
            if ($skillId <= 0) continue;

            $stmt = $db->prepare("
                INSERT INTO student_skills (student_id, skill_id, student_level)
                VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE student_level = VALUES(student_level)
            ");
            $stmt->execute([$studentId, $skillId, $level]);
            $updatedSkillCnt++;
        }
    }

    echo json_encode([
        'success'         => true,
        'ipk_saved'       => $updatedIPK,
        'courses_saved'   => $savedCourseCnt,
        'skills_updated'  => $updatedSkillCnt,
        'message'         => "IPK " . ($updatedIPK ? "({$ipk}) " : '') .
                             "tersimpan. {$updatedSkillCnt} skill & {$savedCourseCnt} matkul diperbarui.",
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
