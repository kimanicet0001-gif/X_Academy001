<?php
/**
 * admin/assignment_delete.php
 * Retire une affectation. Les notes déjà saisies par le professeur
 * restent en base (règle de conservation des données).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$id = (int) ($_GET['id'] ?? 0);
$stmt = getDB()->prepare('SELECT professor_id, course_id FROM teaching_assignments WHERE id = ?');
$stmt->execute([$id]);
$a = $stmt->fetch();

if ($a) {
    getDB()->prepare('DELETE FROM teaching_assignments WHERE id = ?')->execute([$id]);
    logAudit('assignment_delete', "Affectation retirée : professeur #{$a['professor_id']}, cours #{$a['course_id']}");
    setFlash('success', 'Affectation retirée.');
}
redirect('/admin/assignments.php');
