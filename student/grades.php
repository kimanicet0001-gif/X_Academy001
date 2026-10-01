<?php
/**
 * student/grades.php
 * Résultats de l'étudiant : détail par semestre (cours, UE), GPA et
 * moyennes, puis cumul de l'année et du cycle. Seules les notes
 * soumises/validées sont visibles (jamais les brouillons).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();
$studentId = (int) $user['id'];
$db = getDB();

$stmt = $db->prepare(
    "SELECT sg.score, sg.gpa, sg.weighted_points, sg.grade, c.name AS course_name, c.credits,
            p.academic_year, p.semester, u.name AS ue_name
     FROM student_grades sg
     JOIN courses c ON c.id = sg.course_id
     JOIN promotions p ON p.id = c.promotion_id
     LEFT JOIN ues u ON u.id = c.ue_id
     WHERE sg.student_id = ? AND sg.status <> 'draft'
     ORDER BY p.academic_year DESC, p.semester, u.name, c.name"
);
$stmt->execute([$studentId]);

$periods = [];
foreach ($stmt->fetchAll() as $row) {
    $periods[$row['academic_year']][$row['semester']][] = $row;
}

$cumulative = calculateCumulativeResult($studentId);

$pageTitle = 'Mes résultats';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Mes résultats</h1>

<?php if (empty($periods)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 text-sm">
        Aucun résultat publié pour le moment.
    </div>
<?php else: ?>

<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
    <div class="bg-indigo-950 text-white rounded-2xl shadow-sm p-5 relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-gold-500/10"></div>
        <p class="text-xs uppercase tracking-wide text-indigo-300">GPA cumulé</p>
        <p class="text-4xl font-display font-semibold text-gold-300"><?= number_format($cumulative['gpa'], 2) ?><span class="text-lg text-indigo-300 font-sans"> / 4.00</span></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">Moyenne cumulée</p>
        <p class="text-3xl font-display font-bold text-slate-900"><?= number_format($cumulative['mean_percent'], 2) ?><span class="text-lg text-slate-400"> / 100</span></p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs uppercase tracking-wide text-slate-500">Crédits pris en compte</p>
        <p class="text-3xl font-display font-bold text-slate-900"><?= rtrim(rtrim(number_format($cumulative['credits'], 1), '0'), '.') ?></p>
    </div>
</div>

<?php foreach ($periods as $year => $semesters):
    $annual = calculateAnnualResult($studentId, $year); ?>
    <div class="mb-8">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="text-lg font-display font-bold text-slate-900">Année <?= h($year) ?></h2>
            <p class="text-sm text-slate-500">GPA annuel : <strong class="text-slate-900"><?= number_format($annual['gpa'], 2) ?></strong> · <?= rtrim(rtrim(number_format($annual['credits'], 1), '0'), '.') ?> crédits</p>
        </div>

        <?php ksort($semesters); foreach ($semesters as $sem => $rows):
            $result = calculateSemesterResult($studentId, $year, $sem); ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
                <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-slate-800">Semestre <?= h($sem) ?></span>
                    <span class="text-sm text-slate-500">
                        Moyenne <strong class="text-slate-900"><?= number_format($result['mean'], 2) ?></strong> ·
                        GPA <strong class="text-slate-900"><?= number_format($result['gpa'], 2) ?></strong>
                        <?php if ($result['ue_total'] > 0): ?>· UE validées <strong class="text-slate-900"><?= $result['ue_validated'] ?>/<?= $result['ue_total'] ?></strong><?php endif; ?>
                    </span>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                            <th class="py-2.5 px-4">UE</th><th class="py-2.5 px-4">Cours</th><th class="py-2.5 px-4">Crédits</th>
                            <th class="py-2.5 px-4">Note</th><th class="py-2.5 px-4">GPA</th><th class="py-2.5 px-4">Lettre</th><th class="py-2.5 px-4">Points pondérés</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr class="border-t border-slate-100">
                            <td class="py-2.5 px-4 text-slate-500"><?= h($r['ue_name'] ?? '—') ?></td>
                            <td class="py-2.5 px-4 font-medium"><?= h($r['course_name']) ?></td>
                            <td class="py-2.5 px-4"><?= h((string) $r['credits']) ?></td>
                            <td class="py-2.5 px-4"><?= h((string) $r['score']) ?></td>
                            <td class="py-2.5 px-4"><?= h(number_format((float) $r['gpa'], 2)) ?></td>
                            <td class="py-2.5 px-4 font-semibold"><?= h($r['grade'] ?? '—') ?></td>
                            <td class="py-2.5 px-4"><?= h(number_format((float) $r['weighted_points'], 2)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if (!empty($result['ue_results'])): ?>
                    <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap gap-2">
                        <?php foreach ($result['ue_results'] as $ue):
                            $name = $db->prepare('SELECT name FROM ues WHERE id = ?'); $name->execute([$ue['ue_id']]); ?>
                            <span class="text-xs border rounded-full px-2.5 py-1 <?= !empty($ue['validated']) ? 'text-green-700 bg-green-50 border-green-200' : 'text-red-700 bg-red-50 border-red-200' ?>">
                                <?= h((string) $name->fetchColumn()) ?> : <?= number_format($ue['mean'] ?? 0, 2) ?> — <?= !empty($ue['validated']) ? 'Validée' : 'Non validée' ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
