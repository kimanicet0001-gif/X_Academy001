<?php
/**
 * head/delegate_status.php
 * Active / désactive un compte Délégué.
 * Règle de conservation : cours, séances, présences et PDF restent
 * conservés et consultables même si le compte est désactivé.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'delegate' AND department_id = ?");
$stmt->execute([$id, $deptId]);
$delegate = $stmt->fetch();

if (!$delegate) {
    setFlash('error', 'Délégué introuvable.');
    redirect('/head/delegates.php');
}

$newStatus = $delegate['status'] === 'active' ? 'inactive' : 'active';
getDB()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
logAudit('delegate_status', "Délégué #{$id} : statut -> $newStatus");

setFlash('success', $newStatus === 'inactive'
    ? 'Compte désactivé. Cours, séances, présences et PDF restent conservés et consultables.'
    : 'Compte réactivé.');

redirect('/head/delegates.php');
