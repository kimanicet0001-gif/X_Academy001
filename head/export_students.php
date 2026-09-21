<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];

$stmt = getDB()->prepare(
    "SELECT s.full_name, s.matricule, s.status, p.name AS promotion_name, p.group_name
     FROM students s
     JOIN promotions p ON p.id = s.promotion_id
     WHERE p.department_id = ?
     ORDER BY p.name, s.full_name"
);
$stmt->execute([$deptId]);

$rows = [];
foreach ($stmt->fetchAll() as $s) {
    $rows[] = [
        $s['full_name'], $s['matricule'] ?? '',
        $s['promotion_name'] . ($s['group_name'] ? ' - ' . $s['group_name'] : ''),
        $s['status'] === 'active' ? 'Actif' : 'Désactivé',
    ];
}

logAudit('export_csv', 'Export CSV des étudiants du département');

exportCsv('etudiants_departement.csv', ['Nom complet', 'Matricule', 'Promotion', 'Statut'], $rows);
