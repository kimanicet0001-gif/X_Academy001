<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$deptFilter = (int) ($_GET['department_id'] ?? 0);

$sql = "SELECT s.full_name, s.matricule, s.status, d.name AS department_name, p.name AS promotion_name, p.group_name
        FROM students s
        JOIN promotions p ON p.id = s.promotion_id
        JOIN departments d ON d.id = p.department_id";
$params = [];
if ($deptFilter > 0) {
    $sql .= " WHERE d.id = ?";
    $params[] = $deptFilter;
}
$sql .= " ORDER BY d.name, p.name, s.full_name";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);

$rows = [];
foreach ($stmt->fetchAll() as $s) {
    $rows[] = [
        $s['full_name'], $s['matricule'] ?? '', $s['department_name'],
        $s['promotion_name'] . ($s['group_name'] ? ' - ' . $s['group_name'] : ''),
        $s['status'] === 'active' ? 'Actif' : 'Désactivé',
    ];
}

logAudit('export_csv', 'Export CSV des étudiants (université)');

exportCsv('etudiants_universite.csv',
    ['Nom complet', 'Matricule', 'Département', 'Promotion', 'Statut'],
    $rows
);
