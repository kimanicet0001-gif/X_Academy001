<?php
/**
 * professor/grades_submit.php
 * Soumet les notes d'un cours : DRAFT -> SUBMITTED. Irréversible pour
 * le professeur (seul le Chef de Département peut ensuite corriger).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$courseId = (int) ($_POST['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/professor/courses.php');
}

$result = submitGrades((int) $user['id'], $courseId);
setFlash($result['success'] ? 'success' : 'error',
    $result['success'] ? "{$result['count']} note(s) soumise(s). Elles ne sont plus modifiables par vous." : $result['error']);
redirect('/professor/grades.php?course_id=' . $courseId);
