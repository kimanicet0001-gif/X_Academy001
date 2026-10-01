<?php
/**
 * delegate/session_cancel.php
 * Annule une séance (brouillon ou publiée). Une séance annulée ne compte
 * jamais dans les heures prestées et ne peut plus être modifiée.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$id = (int) ($_GET['id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/courses.php');
}

$stmt = getDB()->prepare(
    "SELECT sess.*, c.name AS course_name
     FROM sessions sess JOIN courses c ON c.id = sess.course_id
     WHERE sess.id = ? AND c.promotion_id = ?"
);
$stmt->execute([$id, $promotion['id']]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Séance introuvable.');
    redirect('/delegate/courses.php');
}

if (!in_array($session['status'], ['draft', 'published'], true)) {
    setFlash('error', 'Cette séance ne peut plus être annulée.');
    redirect('/delegate/session_view.php?id=' . $id);
}

$wasPublished = $session['status'] === 'published';

getDB()->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?")->execute([$id]);
logAudit('session_cancel', "Séance #$id annulée");

if ($wasPublished) {
    notifyPromotion(
        $promotion['id'], 'session_cancelled',
        "Séance annulée : {$session['course_name']} du " . formatDateFr($session['session_date']),
        BASE_URL . '/student/schedule.php', $id
    );
}

setFlash('success', 'Séance annulée.' . ($wasPublished ? ' Les étudiants ont été notifiés.' : ''));
redirect('/delegate/course_view.php?id=' . $session['course_id']);
