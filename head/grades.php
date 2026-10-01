<?php
/**
 * head/grades.php
 * Suivi des notes de tous les cours du département du Chef.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$user = currentUser();
$stmt = getDB()->prepare(
    "SELECT c.id, c.name, c.credits, p.name AS promotion_name, p.group_name, p.academic_year, p.semester,
            (SELECT GROUP_CONCAT(u.full_name SEPARATOR ', ') FROM teaching_assignments ta
               JOIN users u ON u.id = ta.professor_id WHERE ta.course_id = c.id AND ta.status = 'active') AS professors,
            (SELECT COUNT(*) FROM students st WHERE st.promotion_id = p.id AND st.status = 'active') AS student_count,
            (SELECT COUNT(*) FROM student_grades sg WHERE sg.course_id = c.id AND sg.status = 'draft') AS n_draft,
            (SELECT COUNT(*) FROM student_grades sg WHERE sg.course_id = c.id AND sg.status = 'submitted') AS n_submitted,
            (SELECT COUNT(*) FROM student_grades sg WHERE sg.course_id = c.id AND sg.status = 'locked') AS n_locked
     FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     WHERE p.department_id = ?
     ORDER BY p.academic_year DESC, p.name, c.name"
);
$stmt->execute([$user['department_id']]);
$courses = $stmt->fetchAll();

$pageTitle = 'Notes du département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Notes du département</h1>
<p class="text-slate-500 mb-6">Consultez, validez et corrigez les notes soumises par les professeurs.</p>

<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#gradesTable" placeholder="Rechercher un cours, un professeur..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm" id="gradesTable">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Professeur(s)</th>
                <th class="py-3 px-4">Brouillon</th>
                <th class="py-3 px-4">Soumises</th>
                <th class="py-3 px-4">Validées</th>
                <th class="py-3 px-4 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $c): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($c['name']) ?></td>
                <td class="py-3 px-4"><?= h($c['promotion_name'] . ($c['group_name'] ? ' — ' . $c['group_name'] : '')) ?>
                    <span class="text-slate-400"><?= h($c['academic_year'] . ' ' . $c['semester']) ?></span></td>
                <td class="py-3 px-4"><?= h($c['professors'] ?? '—') ?></td>
                <td class="py-3 px-4"><?= (int) $c['n_draft'] ?></td>
                <td class="py-3 px-4"><?= (int) $c['n_submitted'] ?></td>
                <td class="py-3 px-4"><?= (int) $c['n_locked'] ?> / <?= (int) $c['student_count'] ?></td>
                <td class="py-3 px-4 text-right">
                    <a href="<?= BASE_URL ?>/head/grades_course.php?course_id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Ouvrir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
            <tr><td colspan="7" class="py-6 text-center text-slate-400">Aucun cours dans ce département.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
