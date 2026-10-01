<?php
/**
 * admin/professor_status.php
 * Active / désactive un compte professeur. Ses notes, affectations et
 * l'historique de modifications restent intacts.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'professor'");
$stmt->execute([$id]);
$prof = $stmt->fetch();

if (!$prof) {
    setFlash('error', 'Professeur introuvable.');
    redirect('/admin/professors.php');
}

$newStatus = $prof['status'] === 'active' ? 'inactive' : 'active';
getDB()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
logAudit('professor_status', "Professeur #{$id} : statut -> $newStatus");

setFlash('success', $newStatus === 'inactive'
    ? 'Compte désactivé. Ses affectations et ses notes restent conservées.'
    : 'Compte réactivé.');
redirect('/admin/professors.php');
