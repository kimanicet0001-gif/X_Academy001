<?php
/**
 * professor/grades_save.php
 * Enregistre en BROUILLON les notes saisies dans la grille manuelle.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$courseId = (int) ($_POST['course_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isProfessorAssignedToCourse((int) $user['id'], $courseId)) {
    setFlash('error', 'Action non autorisée.');
    redirect('/professor/courses.php');
}

// Seuls les étudiants de la promotion du cours sont acceptés
$allowed = array_column(getCourseStudentsWithGrades($courseId), 'student_id');
$saved = 0;
$errors = 0;

foreach (($_POST['score'] ?? []) as $studentId => $raw) {
    $raw = trim(str_replace(',', '.', (string) $raw));
    if ($raw === '') {
        continue;
    }
    if (!in_array((int) $studentId, array_map('intval', $allowed), true) || !is_numeric($raw)) {
        $errors++;
        continue;
    }
    saveDraftGrade((int) $studentId, $courseId, (float) $raw) ? $saved++ : $errors++;
}

if ($saved > 0) {
    logAudit('grades_save', "Cours #$courseId : $saved note(s) enregistrée(s) en brouillon");
}
setFlash($errors > 0 ? 'error' : 'success',
    "$saved note(s) enregistrée(s) en brouillon" . ($errors > 0 ? " — $errors ligne(s) ignorée(s) (note invalide ou déjà soumise)." : '.'));
redirect('/professor/grades.php?course_id=' . $courseId);
