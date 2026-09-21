<?php
/**
 * admin/department_delete.php
 * "Suppression" = désactivation / réactivation (bascule de statut).
 * Conformément à la règle de conservation des données, on ne supprime
 * jamais réellement les enregistrements liés.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM departments WHERE id = ?');
$stmt->execute([$id]);
$department = $stmt->fetch();

if (!$department) {
    setFlash('error', 'Département introuvable.');
    redirect('/admin/departments.php');
}

$newStatus = $department['status'] === 'active' ? 'inactive' : 'active';
$upd = getDB()->prepare('UPDATE departments SET status = ? WHERE id = ?');
$upd->execute([$newStatus, $id]);

// Les chefs de département rattachés suivent le même sort d'accès si on désactive
logAudit('department_status', "Département #{$id} : statut -> $newStatus");
if ($newStatus === 'inactive') {
    getDB()->prepare("UPDATE users SET status = 'inactive' WHERE department_id = ? AND role = 'head'")->execute([$id]);
    setFlash('success', 'Département désactivé. Les cours, séances et présences déjà enregistrés restent conservés.');
} else {
    setFlash('success', 'Département réactivé.');
}

redirect('/admin/departments.php');
