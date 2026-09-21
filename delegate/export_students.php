<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$promotion = getDelegatePromotion(currentUser()['id']);
if (!$promotion) {
    redirect('/delegate/students.php');
}

$stmt = getDB()->prepare('SELECT full_name, matricule, status FROM students WHERE promotion_id = ? ORDER BY full_name');
$stmt->execute([$promotion['id']]);

$rows = [];
foreach ($stmt->fetchAll() as $s) {
    $rows[] = [$s['full_name'], $s['matricule'] ?? '', $s['status'] === 'active' ? 'Actif' : 'Désactivé'];
}

logAudit('export_csv', 'Export CSV des étudiants de la promotion');

exportCsv('etudiants_promotion.csv', ['Nom complet', 'Matricule', 'Statut'], $rows);
