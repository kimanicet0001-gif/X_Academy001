<?php
/**
 * head/promotion_delete.php
 * "Suppression" = désactivation / réactivation (bascule de statut).
 * Les étudiants, cours, séances et présences liés restent conservés.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare('SELECT * FROM promotions WHERE id = ? AND department_id = ?');
$stmt->execute([$id, $deptId]);
$promotion = $stmt->fetch();

if (!$promotion) {
    setFlash('error', 'Promotion introuvable.');
    redirect('/head/promotions.php');
}

$newStatus = $promotion['status'] === 'active' ? 'inactive' : 'active';
getDB()->prepare('UPDATE promotions SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
logAudit('promotion_status', "Promotion #{$id} : statut -> $newStatus");

setFlash('success', $newStatus === 'inactive'
    ? 'Promotion désactivée. Étudiants, cours, séances et présences restent conservés.'
    : 'Promotion réactivée.');

redirect('/head/promotions.php');
