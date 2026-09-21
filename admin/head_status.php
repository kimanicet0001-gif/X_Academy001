<?php
/**
 * admin/head_status.php
 * Active / désactive un compte Chef de Département.
 * Règle de conservation : les délégués, cours, séances et présences
 * rattachés au département restent intacts.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare("SELECT * FROM users WHERE id = ? AND role = 'head'");
$stmt->execute([$id]);
$headUser = $stmt->fetch();

if (!$headUser) {
    setFlash('error', 'Chef de département introuvable.');
    redirect('/admin/heads.php');
}

$newStatus = $headUser['status'] === 'active' ? 'inactive' : 'active';
getDB()->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
logAudit('head_status', "Chef de département #{$id} : statut -> $newStatus");

setFlash('success', $newStatus === 'inactive'
    ? 'Compte désactivé. Les données du département (délégués, cours, séances, PDF) restent conservées.'
    : 'Compte réactivé.');

redirect('/admin/heads.php');
