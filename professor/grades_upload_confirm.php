<?php
/**
 * professor/grades_upload_confirm.php
 * Étape 2 de l'import : après prévisualisation, enregistre les lignes
 * valides en BROUILLON (ou annule l'import).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$courseId = (int) ($_POST['course_id'] ?? 0);
$back = '/professor/grades.php?course_id=' . $courseId;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isProfessorAssignedToCourse((int) $user['id'], $courseId)) {
    setFlash('error', 'Action non autorisée.');
    redirect('/professor/courses.php');
}

$rows = $_SESSION['grade_import'][$courseId] ?? [];
unset($_SESSION['grade_import'][$courseId]);

if (($_POST['decision'] ?? '') !== 'confirm' || empty($rows)) {
    setFlash('info', 'Import annulé.');
    redirect($back);
}

$saved = 0;
foreach ($rows as $r) {
    if (!empty($r['ok']) && saveDraftGrade((int) $r['student_id'], $courseId, (float) $r['score'])) {
        $saved++;
    }
}

logAudit('grades_import', "Cours #$courseId : $saved note(s) importée(s) depuis CSV (brouillon)");
setFlash('success', "$saved note(s) importée(s) en brouillon. Vérifiez-les puis soumettez.");
redirect($back);
