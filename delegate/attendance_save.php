<?php
/**
 * delegate/attendance_save.php
 * Traite le formulaire d'appel : crée ou met à jour une ligne de
 * présence pour CHAQUE étudiant actif de la promotion (présent si coché,
 * absent sinon), puis fait passer la séance au statut "Terminée".
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/delegate/courses.php');
}

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$sessionId = (int) ($_POST['session_id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/courses.php');
}

// Vérifie que la séance appartient bien à un cours de la promotion du Délégué
$stmt = getDB()->prepare(
    "SELECT sess.* FROM sessions sess JOIN courses c ON c.id = sess.course_id
     WHERE sess.id = ? AND c.promotion_id = ?"
);
$stmt->execute([$sessionId, $promotion['id']]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Séance introuvable.');
    redirect('/delegate/courses.php');
}

if ($session['status'] === 'cancelled') {
    setFlash('error', "Impossible d'enregistrer l'appel : cette séance est annulée.");
    redirect('/delegate/course_view.php?id=' . $session['course_id']);
}

$presentIds = array_map('intval', $_POST['present'] ?? []);

$db = getDB();
$db->beginTransaction();
try {
    $studentsStmt = $db->prepare("SELECT id FROM students WHERE promotion_id = ? AND status = 'active'");
    $studentsStmt->execute([$promotion['id']]);
    $studentIds = $studentsStmt->fetchAll(PDO::FETCH_COLUMN);

    $upsert = $db->prepare(
        'INSERT INTO attendance (session_id, student_id, status) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status)'
    );
    foreach ($studentIds as $studentId) {
        $status = in_array($studentId, $presentIds, true) ? 'present' : 'absent';
        $upsert->execute([$sessionId, $studentId, $status]);
    }

    // L'appel étant fait, la séance passe (ou repasse) au statut "Terminée"
    // — c'est uniquement à partir de là qu'elle compte dans les heures prestées.
    $db->prepare("UPDATE sessions SET status = 'done' WHERE id = ?")->execute([$sessionId]);

    $db->commit();
    logAudit('attendance_save', "Appel enregistré pour la séance #$sessionId");
    setFlash('success', 'Présences enregistrées avec succès.');
} catch (Exception $e) {
    $db->rollBack();
    setFlash('error', "Erreur lors de l'enregistrement des présences.");
}

redirect('/delegate/session_view.php?id=' . $sessionId);
