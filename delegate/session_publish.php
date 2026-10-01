<?php
/**
 * delegate/session_publish.php
 * Publie une séance en brouillon : elle devient visible aux étudiants
 * et déclenche une notification à toute la promotion.
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

if ($session['status'] !== 'draft') {
    setFlash('error', 'Seule une séance en brouillon peut être publiée.');
    redirect('/delegate/session_view.php?id=' . $id);
}

getDB()->prepare("UPDATE sessions SET status = 'published' WHERE id = ?")->execute([$id]);
logAudit('session_publish', "Séance #$id publiée");

notifyPromotion(
    $promotion['id'], 'session_published',
    "Nouvelle séance publiée : {$session['course_name']} le " . formatDateFr($session['session_date']) . " à " . formatTime($session['start_time']),
    BASE_URL . '/student/schedule.php', $id
);

setFlash('success', 'Séance publiée. Les étudiants ont été notifiés.');
redirect('/delegate/session_view.php?id=' . $id);
