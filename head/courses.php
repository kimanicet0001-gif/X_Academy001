<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];

$stmt = getDB()->prepare(
    "SELECT c.*, p.name AS promotion_name, p.group_name
     FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     WHERE p.department_id = ?
     ORDER BY p.name, c.name"
);
$stmt->execute([$deptId]);
$courses = $stmt->fetchAll();

$pageTitle = 'Cours du département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Cours du département</h1>

<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#headCoursesTable" placeholder="Rechercher un cours..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm" id="headCoursesTable">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Enseignant</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Volume horaire</th>
                <th class="py-3 px-4">Progression</th>
                <th class="py-3 px-4 text-right">Registre</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $c):
            $completed = getCompletedHours((int) $c['id']);
            $progress  = calculateProgress((float) $c['planned_hours'], $completed);
        ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($c['name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($c['teacher_name']) ?></td>
                <td class="py-3 px-4"><?= h($c['promotion_name']) ?><?= $c['group_name'] ? ' — ' . h($c['group_name']) : '' ?></td>
                <td class="py-3 px-4"><?= number_format($completed, 2) ?> h / <?= number_format((float) $c['planned_hours'], 2) ?> h</td>
                <td class="py-3 px-4">
                    <div class="w-32 bg-slate-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                    </div>
                    <span class="text-xs text-slate-500"><?= $progress ?>%</span>
                </td>
                <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                    <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $c['id'] ?>" target="_blank" class="text-indigo-600 hover:text-indigo-700 font-medium">Voir</a>
                    <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Télécharger</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
            <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucun cours enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
