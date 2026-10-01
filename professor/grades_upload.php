<?php
/**
 * professor/grades_upload.php
 * Étape 1 de l'import : analyse du fichier CSV, SANS rien enregistrer.
 * Le résultat est gardé en session pour la prévisualisation.
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

$file = $_FILES['grades_file'] ?? null;
$back = '/professor/grades.php?course_id=' . $courseId;

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', "Le fichier n'a pas pu être envoyé.");
    redirect($back);
}
if ($file['size'] > 1024 * 1024) {
    setFlash('error', 'Fichier trop volumineux (1 Mo maximum).');
    redirect($back);
}
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt', 'pdf'], true)) {
    setFlash('error', 'Format non pris en charge : CSV, TXT ou PDF uniquement.');
    redirect($back);
}

$rows = $ext === 'pdf'
    ? parseGradesPdf($file['tmp_name'], $courseId)
    : parseGradesCsv($file['tmp_name'], $courseId);

if (empty($rows)) {
    setFlash('error', $ext === 'pdf'
        ? "Aucune ligne exploitable dans ce PDF. Fonctionne avec un PDF texte (Word/Excel exporté en PDF) — pas avec un document scanné/image."
        : 'Aucune ligne exploitable dans ce fichier (colonnes attendues : Matricule ; Note).');
    redirect($back);
}

$_SESSION['grade_import'][$courseId] = $rows;
redirect($back);
