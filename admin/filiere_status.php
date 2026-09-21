<?php
/**
 * admin/filiere_status.php
 * Active / désactive une filière. Les promotions déjà rattachées ne
 * sont jamais supprimées ni détachées.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT * FROM filieres WHERE id = ?');
$stmt->execute([$id]);
$filiere = $stmt->fetch();

if (!$filiere) {
    setFlash('error', 'Filière introuvable.');
    redirect('/admin/filieres.php');
}

$newStatus = $filiere['status'] === 'active' ? 'inactive' : 'active';
getDB()->prepare('UPDATE filieres SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
logAudit('filiere_status', "Filière #{$id} : statut -> $newStatus");

setFlash('success', $newStatus === 'inactive' ? 'Filière désactivée.' : 'Filière réactivée.');
redirect('/admin/filieres.php');
