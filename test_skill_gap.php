<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
require_once 'config/database.php';

$db = getDB();

$user = ['id' => 1];

// Profile
$stmt = $db->prepare("SELECT mp.*, u.fullname, u.email FROM mahasiswa_profiles mp JOIN users u ON u.id = mp.user_id WHERE mp.user_id = ?");
$stmt->execute([$user['id']]);
$profile   = $stmt->fetch();
$studentId = $profile['id'];

$targetCareerName = $profile['target_career'] ?? '';

// All available roles
$stmt = $db->query("SELECT id, position_name FROM career_positions");
$allRoles = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (!$targetCareerName && !empty($allRoles)) {
    $targetCareerName = current($allRoles);
}
$careerId = array_search($targetCareerName, $allRoles);

// Fetch required academic courses
$academicCourses = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT c.id, c.course_code, c.course_name_id, sc.grade
        FROM career_courses cc
        JOIN courses c ON c.id = cc.course_id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        WHERE cc.career_id = ?
        ORDER BY c.semester ASC, c.course_name_id ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $academicCourses = $stmt->fetchAll();
}

// Fetch required independent skills
$independentSkills = [];
if ($careerId) {
    $stmt = $db->prepare("
        SELECT s.id, s.skill_name, COALESCE(ss.student_level, 0) as is_mastered
        FROM career_skills cs
        JOIN skills s ON s.id = cs.skill_id
        LEFT JOIN student_skills ss ON ss.skill_id = s.id AND ss.student_id = ?
        WHERE cs.career_id = ?
        ORDER BY s.skill_name ASC
    ");
    $stmt->execute([$studentId, $careerId]);
    $independentSkills = $stmt->fetchAll();
}

echo "Success. CareerID: $careerId\n";
var_dump($academicCourses);
var_dump($independentSkills);
