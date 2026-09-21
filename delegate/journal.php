<?php
/**
 * delegate/journal.php
 * Vue consolidée : tous les cours de la promotion avec leur avancement,
 * un peu comme un "carnet de bord" imprimable pour le Délégué.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

$courses = [];
$plannedTotal = 0.0;
$completedTotal = 0.0;
$sessionTotal = 0;

if ($promotion) {
    $stmt = getDB()->prepare(
        "SELECT c.*,
                (SELECT COUNT(*) FROM sessions se WHERE se.course_id = c.id) AS session_count,
                (SELECT MAX(session_date) FROM sessions se WHERE se.course_id = c.id) AS last_session
         FROM courses c
         WHERE c.promotion_id = ?
         ORDER BY c.name"
    );
    $stmt->execute([$promotion['id']]);
    $courses = $stmt->fetchAll();

    foreach ($courses as $c) {
        $completed = getCompletedHours((int) $c['id']);
        $plannedTotal += (float) $c['planned_hours'];
        $completedTotal += $completed;
        $sessionTotal += (int) $c['session_count'];
    }
}

$pageTitle = 'Journal';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Journal de suivi</h1>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mt-4">
        Aucune promotion assignée pour le moment.
    </div>
<?php else: ?>
    <p class="text-slate-500 mb-6">
        Promotion : <strong><?= h($promotion['name']) ?><?= $promotion['group_name'] ? ' — ' . h($promotion['group_name']) : '' ?></strong>
        — <?= h($promotion['academic_year']) ?> · <?= h($promotion['semester']) ?>
    </p>

    <div class="grid grid-cols-3 gap-4 mb-6 max-w-lg">
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
            <div class="text-xl font-display font-bold text-slate-900"><?= count($courses) ?></div>
            <div class="text-xs text-slate-500">Cours</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
            <div class="text-xl font-display font-bold text-slate-900"><?= $sessionTotal ?></div>
            <div class="text-xs text-slate-500">Séances</div>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
            <div class="text-xl font-display font-bold text-slate-900"><?= number_format($completedTotal, 1) ?> / <?= number_format($plannedTotal, 1) ?> h</div>
            <div class="text-xs text-slate-500">Heures prestées</div>
        </div>
    </div>

    <div class="flex justify-end mb-3">
    <input type="text" data-table-search="#journalTable" placeholder="Rechercher un cours..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm" id="journalTable">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                    <th class="py-3 px-4">Cours</th>
                    <th class="py-3 px-4">Enseignant</th>
                    <th class="py-3 px-4">Séances</th>
                    <th class="py-3 px-4">Dernière séance</th>
                    <th class="py-3 px-4">Heures</th>
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
                    <td class="py-2 px-4 font-medium">
                        <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $c['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium"><?= h($c['name']) ?></a>
                    </td>
                    <td class="py-2 px-4 text-slate-500"><?= h($c['teacher_name']) ?></td>
                    <td class="py-2 px-4"><?= (int) $c['session_count'] ?></td>
                    <td class="py-2 px-4"><?= $c['last_session'] ? formatDateFr($c['last_session']) : '-' ?></td>
                    <td class="py-2 px-4"><?= number_format($completed, 1) ?> / <?= number_format((float) $c['planned_hours'], 1) ?> h</td>
                    <td class="py-2 px-4"><?= $progress ?>%</td>
                    <td class="py-2 px-4 text-right space-x-2 whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $c['id'] ?>" target="_blank" class="inline-flex text-indigo-600 hover:text-indigo-700 font-medium" title="Voir"><?= icon('eye', 'w-4 h-4') ?></a>
                        <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $c['id'] ?>" class="inline-flex text-indigo-600 hover:text-indigo-700 font-medium" title="Télécharger"><?= icon('download', 'w-4 h-4') ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($courses)): ?>
                <tr><td colspan="7" class="py-6 text-center text-slate-400">Aucun cours enregistré.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
