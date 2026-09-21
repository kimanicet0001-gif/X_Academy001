<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();
$stats = getStudentAttendanceStats((int) $user['id']);
$risk = classifyRisk($stats['rate'], $stats['total_sessions']);
$badges = getAttendanceBadges($stats);

$pageTitle = 'Mes présences';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Mes présences</h1>
<p class="text-slate-500 mb-6">Consultation uniquement — les présences sont enregistrées par votre Délégué lors de l'appel.</p>

<div class="flex flex-wrap items-center gap-2 mb-6">
    <span class="border rounded-full px-3 py-1 text-sm font-medium <?= $risk['color'] ?>"><?= h($risk['label']) ?></span>
    <?php foreach ($badges as $badge): ?>
        <span class="border rounded-full px-3 py-1 text-sm font-medium <?= $badge['color'] ?>"><?= h($badge['label']) ?></span>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-3 gap-4 mb-6 max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-slate-900"><?= $stats['total_sessions'] ?></div>
        <div class="text-xs text-slate-500">Séances suivies</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-green-600"><?= $stats['present'] ?></div>
        <div class="text-xs text-slate-500">Présences</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-indigo-600"><?= $stats['rate'] ?>%</div>
        <div class="text-xs text-slate-500">Taux de présence</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Assiduité par cours</h2>
        <?php if (empty($stats['by_course'])): ?>
            <p class="text-sm text-slate-400">Aucune séance enregistrée pour le moment.</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($stats['by_course'] as $c): ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-slate-700"><?= h($c['name']) ?></span>
                            <span class="text-slate-500"><?= $c['present'] ?>/<?= $c['total'] ?> (<?= $c['rate'] ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div class="<?= $c['rate'] < 65 ? 'bg-red-500' : ($c['rate'] < 85 ? 'bg-amber-500' : 'bg-green-500') ?> h-2 rounded-full transition-all" style="width: <?= $c['rate'] ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Historique des absences</h2>
        <?php if (empty($stats['absences'])): ?>
            <p class="text-sm text-slate-400">Aucune absence enregistrée.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3">Cours</th>
                            <th class="py-2 px-3">Matière</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (array_slice($stats['absences'], 0, 20) as $a): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2 px-3 whitespace-nowrap"><?= formatDateFr($a['date']) ?></td>
                            <td class="py-2 px-3"><?= h($a['course']) ?></td>
                            <td class="py-2 px-3 text-slate-500"><?= h($a['topic']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
