<?php
/**
 * head/grades_lock.php
 * Le Chef valide les notes soumises d'un cours : SUBMITTED -> LOCKED.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$user = currentUser();
$courseId = (int) ($_POST['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/head/grades.php');
}

$stmt = getDB()->prepare(
    'SELECT p.department_id FROM courses c JOIN promotions p ON p.id = c.promotion_id WHERE c.id = ?'
);
$stmt->execute([$courseId]);
if ((int) $stmt->fetchColumn() !== (int) $user['department_id']) {
    setFlash('error', 'Action non autorisée.');
    redirect('/head/grades.php');
}

$upd = getDB()->prepare("UPDATE student_grades SET status = 'locked', locked_at = NOW() WHERE course_id = ? AND status = 'submitted'");
$upd->execute([$courseId]);
$n = $upd->rowCount();

logAudit('grades_lock', "Cours #$courseId : $n note(s) validée(s)");
setFlash('success', "$n note(s) validée(s).");
redirect('/head/grades_course.php?course_id=' . $courseId);
