<?php
/**
 * head/rankings.php
 * Classements du département à partir des notes soumises/verrouillées :
 * meilleure / moins bonne filière-promotion, meilleurs / moins bons
 * étudiants — pour repérer vite où concentrer l'accompagnement.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = (int) currentUser()['department_id'];
$promotions = getPromotionGradeRanking($deptId);
$topStudents = getStudentGradeRanking($deptId, 'DESC', 10);
$bottomStudents = getStudentGradeRanking($deptId, 'ASC', 10);

$pageTitle = 'Classements';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Classements</h1>
<p class="text-slate-500 mb-6">Basés uniquement sur les notes soumises ou validées.</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800">Promotions — de la meilleure à la moins bonne</div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-2.5 px-4">#</th><th class="py-2.5 px-4">Promotion</th><th class="py-2.5 px-4">Filière</th>
                <th class="py-2.5 px-4">Moyenne</th><th class="py-2.5 px-4">GPA</th><th class="py-2.5 px-4">Étudiants notés</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($promotions as $i => $p): ?>
            <tr class="border-t border-slate-100 <?= $i === 0 ? 'bg-green-50/40' : ($i === count($promotions) - 1 ? 'bg-red-50/40' : '') ?>">
                <td class="py-2.5 px-4 text-slate-400"><?= $i + 1 ?></td>
                <td class="py-2.5 px-4 font-medium"><?= h($p['promotion_name'] . ($p['group_name'] ? ' — ' . $p['group_name'] : '')) ?>
                    <span class="text-slate-400 text-xs"><?= h($p['academic_year'] . ' ' . $p['semester']) ?></span></td>
                <td class="py-2.5 px-4"><?= h($p['filiere_name'] ?? '—') ?></td>
                <td class="py-2.5 px-4 font-semibold"><?= number_format((float) $p['mean'], 2) ?></td>
                <td class="py-2.5 px-4"><?= number_format((float) $p['gpa'], 2) ?></td>
                <td class="py-2.5 px-4"><?= (int) $p['student_count'] ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($promotions)): ?>
            <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucune note soumise pour le moment.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800 flex items-center gap-2">
            <?= icon('sparkles', 'w-4 h-4 text-amber-500') ?> Meilleurs étudiants
        </div>
        <ul class="divide-y divide-slate-100 text-sm">
            <?php foreach ($topStudents as $i => $s): ?>
                <li class="flex items-center justify-between px-5 py-2.5">
                    <span><span class="text-slate-400 mr-2"><?= $i + 1 ?>.</span><?= h($s['full_name']) ?>
                        <span class="text-slate-400 text-xs"><?= h($s['promotion_name'] . ($s['group_name'] ? ' — ' . $s['group_name'] : '')) ?></span></span>
                    <strong><?= number_format((float) $s['mean'], 2) ?></strong>
                </li>
            <?php endforeach; ?>
            <?php if (empty($topStudents)): ?><li class="px-5 py-4 text-slate-400">Aucune donnée.</li><?php endif; ?>
        </ul>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800 flex items-center gap-2">
            <?= icon('shield-alert', 'w-4 h-4 text-red-500') ?> Étudiants à accompagner en priorité
        </div>
        <ul class="divide-y divide-slate-100 text-sm">
            <?php foreach ($bottomStudents as $i => $s): ?>
                <li class="flex items-center justify-between px-5 py-2.5">
                    <span><span class="text-slate-400 mr-2"><?= $i + 1 ?>.</span><?= h($s['full_name']) ?>
                        <span class="text-slate-400 text-xs"><?= h($s['promotion_name'] . ($s['group_name'] ? ' — ' . $s['group_name'] : '')) ?></span></span>
                    <strong class="text-red-600"><?= number_format((float) $s['mean'], 2) ?></strong>
                </li>
            <?php endforeach; ?>
            <?php if (empty($bottomStudents)): ?><li class="px-5 py-4 text-slate-400">Aucune donnée.</li><?php endif; ?>
        </ul>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
