<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$deptFilter = (int) ($_GET['department_id'] ?? 0);

$sql = "SELECT c.*, d.name AS department_name, p.name AS promotion_name, p.group_name
        FROM courses c
        JOIN promotions p ON p.id = c.promotion_id
        JOIN departments d ON d.id = p.department_id";
$params = [];
if ($deptFilter > 0) {
    $sql .= " WHERE d.id = ?";
    $params[] = $deptFilter;
}
$sql .= " ORDER BY d.name, p.name, c.name";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);

$rows = [];
foreach ($stmt->fetchAll() as $c) {
    $completed = getCompletedHours((int) $c['id']);
    $rows[] = [
        $c['name'], $c['teacher_name'], $c['department_name'],
        $c['promotion_name'] . ($c['group_name'] ? ' - ' . $c['group_name'] : ''),
        $c['semester'], (float) $c['planned_hours'], $completed,
        calculateProgress((float) $c['planned_hours'], $completed),
    ];
}

logAudit('export_csv', 'Export CSV des cours (université)');

exportCsv('cours_universite.csv',
    ['Cours', 'Enseignant', 'Département', 'Promotion', 'Semestre', 'Heures prévues', 'Heures prestées', 'Progression (%)'],
    $rows
);
