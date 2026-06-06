<?php
require_once 'config/database.php';
$db = getDB();

try {
    echo "Testing career_positions...\n";
    $stmt = $db->query("SELECT id, position_name FROM career_positions");
    echo "OK\n";

    echo "Testing skill_gap queries...\n";
    $stmt = $db->prepare("
        SELECT c.id, c.course_code, c.course_name_id, sc.grade
        FROM career_courses cc
        JOIN courses c ON c.id = cc.course_id
        LEFT JOIN student_courses sc ON sc.course_id = c.id AND sc.student_id = ?
        WHERE cc.career_id = ?
    ");
    $stmt->execute([1, 1]);
    echo "skill_gap queries OK\n";

    echo "Testing simulation queries...\n";
    $stmt = $db->query("SELECT config_key, config_val FROM system_config WHERE config_key LIKE 'saw_%'");
    echo "system_config OK\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
