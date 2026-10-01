<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$assignments = getProfessorAssignments((int) currentUser()['id']);

$pageTitle = 'Mes enseignements';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Mes enseignements</h1>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Filière</th>
                <th class="py-3 px-4">Crédits</th>
                <th class="py-3 px-4">Étudiants</th>
                <th class="py-3 px-4">Notes saisies</th>
                <th class="py-3 px-4 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($a['course_name']) ?></td>
                <td class="py-3 px-4"><?= h($a['promotion_name'] . ($a['group_name'] ? ' — ' . $a['group_name'] : '')) ?>
                    <span class="text-slate-400"><?= h($a['academic_year'] . ' ' . $a['semester']) ?></span></td>
                <td class="py-3 px-4"><?= h($a['filiere_name'] ?? '—') ?></td>
                <td class="py-3 px-4"><?= h((string) $a['credits']) ?></td>
                <td class="py-3 px-4"><?= (int) $a['student_count'] ?></td>
                <td class="py-3 px-4"><?= (int) $a['graded_count'] ?></td>
                <td class="py-3 px-4 text-right">
                    <a href="<?= BASE_URL ?>/professor/grades.php?course_id=<?= $a['course_id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Gérer les notes</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($assignments)): ?>
            <tr><td colspan="7" class="py-6 text-center text-slate-400">Aucun cours affecté.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
