<?php
/**
 * pdf/view_register.php
 * Affiche le registre PDF d'un cours directement dans le navigateur.
 * Accessible à l'Admin, au Chef du département concerné, au Délégué
 * responsable de la promotion du cours, et aux étudiants de cette promotion
 * (lecture seule).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN, ROLE_HEAD, ROLE_DELEGATE, ROLE_STUDENT]);

$courseId = (int) ($_GET['course_id'] ?? 0);

$stmt = getDB()->prepare(
    "SELECT c.*, p.department_id, p.delegate_id, p.id AS promotion_id
     FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     WHERE c.id = ?"
);
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    http_response_code(404);
    die('Cours introuvable.');
}

$user = currentUser();
$allowed = match ($user['role']) {
    ROLE_ADMIN    => true,
    ROLE_HEAD     => (int) $course['department_id'] === (int) $user['department_id'],
    ROLE_DELEGATE => (int) $course['delegate_id'] === (int) $user['id'],
    ROLE_STUDENT  => (int) $course['promotion_id'] === (int) $user['promotion_id'],
    default       => false,
};

if (!$allowed) {
    http_response_code(403);
    die("Vous n'avez pas accès à ce registre.");
}

logAudit('pdf_view', "Consultation du registre du cours #$courseId");
require_once __DIR__ . '/generate_register.php';
$result = buildRegisterPdf($courseId);

if (!$result) {
    http_response_code(404);
    die('Registre introuvable.');
}

$result['pdf']->output('I', $result['filename']);
