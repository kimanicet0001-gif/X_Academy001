<?php
/**
 * professor/grades.php
 * Gestion des notes d'un cours : saisie manuelle, import CSV avec
 * prévisualisation, soumission. Le professeur ne peut agir que sur
 * un cours qui lui est affecté (vérifié côté serveur).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$courseId = (int) ($_GET['course_id'] ?? 0);

if (!isProfessorAssignedToCourse((int) $user['id'], $courseId)) {
    setFlash('error', "Ce cours ne vous est pas affecté.");
    redirect('/professor/courses.php');
}

$stmt = getDB()->prepare(
    "SELECT c.*, p.name AS promotion_name, p.group_name, p.academic_year
     FROM courses c JOIN promotions p ON p.id = c.promotion_id WHERE c.id = ?"
);
$stmt->execute([$courseId]);
$course = $stmt->fetch();

$students = getCourseStudentsWithGrades($courseId);
$draftCount = 0;
$submittedCount = 0;
foreach ($students as $s) {
    if ($s['grade_status'] === 'draft') { $draftCount++; }
    elseif ($s['grade_status'] !== null) { $submittedCount++; }
}

$preview = $_SESSION['grade_import'][$courseId] ?? null;

$pageTitle = 'Notes — ' . $course['name'];
require_once ROOT_PATH . '/includes/header.php';
$input = 'border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';
?>

<a href="<?= BASE_URL ?>/professor/courses.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-3">
    <?= icon('arrow-left', 'w-4 h-4') ?> Mes enseignements
</a>
<h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight"><?= h($course['name']) ?></h1>
<p class="text-slate-500 mb-6">
    <?= h($course['promotion_name'] . ($course['group_name'] ? ' — ' . $course['group_name'] : '')) ?> ·
    <?= h($course['academic_year'] . ' ' . $course['semester']) ?> ·
    <?= h((string) $course['credits']) ?> crédit(s)
</p>

<?php if ((float) $course['credits'] <= 0): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mb-6 text-sm">
        Les crédits de ce cours ne sont pas encore définis : les points pondérés seront à 0 tant que l'administration ne les a pas renseignés.
    </div>
<?php endif; ?>

<?php if ($preview): ?>
<div class="bg-white rounded-2xl border border-indigo-200 shadow-sm p-6 mb-6">
    <h2 class="font-semibold text-slate-800 mb-1">Prévisualisation de l'import</h2>
    <?php $okCount = count(array_filter($preview, fn($r) => $r['ok'])); ?>
    <p class="text-sm text-slate-500 mb-4"><?= $okCount ?> ligne(s) valide(s) sur <?= count($preview) ?>. Seules les lignes valides seront enregistrées (en brouillon).</p>
    <div class="overflow-x-auto mb-4">
    <table class="w-full text-sm">
        <thead class="bg-slate-50"><tr class="text-left text-slate-500 text-xs uppercase font-semibold">
            <th class="py-2 px-3">Matricule</th><th class="py-2 px-3">Étudiant</th><th class="py-2 px-3">Note</th><th class="py-2 px-3">Statut</th>
        </tr></thead>
        <tbody>
        <?php foreach ($preview as $r): ?>
            <tr class="border-t border-slate-100">
                <td class="py-2 px-3"><?= h($r['matricule']) ?></td>
                <td class="py-2 px-3"><?= h($r['name'] ?: '—') ?></td>
                <td class="py-2 px-3"><?= h((string) $r['score']) ?></td>
                <td class="py-2 px-3">
                    <?php if ($r['ok']): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full text-xs font-medium">OK</span>
                    <?php else: ?>
                        <span class="text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full text-xs font-medium"><?= h($r['message']) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <form method="post" action="<?= BASE_URL ?>/professor/grades_upload_confirm.php" class="flex gap-3">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <button name="decision" value="confirm" <?= $okCount === 0 ? 'disabled' : '' ?>
                class="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Confirmer l'import</button>
        <button name="decision" value="cancel" class="text-slate-500 hover:text-slate-700 text-sm">Annuler</button>
    </form>
</div>
<?php endif; ?>

<div class="grid lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-1">Importer un fichier CSV ou PDF</h2>
        <p class="text-xs text-slate-500 mb-4">
            <strong>CSV</strong> : deux colonnes, Matricule ; Note (sur 100) — enregistrez votre Excel en « CSV ».<br>
            <strong>PDF</strong> : le matricule et la note sont détectés automatiquement sur chaque ligne. Fonctionne avec un PDF texte (export Word/Excel) ; pas avec un document scanné.<br>
            L'étudiant est identifié uniquement par son matricule.
        </p>
        <form method="post" action="<?= BASE_URL ?>/professor/grades_upload.php" enctype="multipart/form-data" class="flex flex-wrap gap-3 items-center">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <input type="file" name="grades_file" accept=".csv,.txt,.pdf" required class="text-sm">
            <button class="bg-slate-800 hover:bg-slate-900 text-white text-sm font-medium px-4 py-2 rounded-lg transition">Analyser le fichier</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-3">Soumission</h2>
        <p class="text-sm text-slate-600 mb-1"><?= $draftCount ?> note(s) en brouillon</p>
        <p class="text-sm text-slate-600 mb-4"><?= $submittedCount ?> note(s) soumise(s)</p>
        <form method="post" action="<?= BASE_URL ?>/professor/grades_submit.php"
              onsubmit="return confirm('Soumettre les notes ? Vous ne pourrez plus les modifier ; seul le Chef de Département pourra les corriger.');">
            <input type="hidden" name="course_id" value="<?= $courseId ?>">
            <button <?= $draftCount === 0 ? 'disabled' : '' ?>
                    class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-40 text-white font-medium py-2.5 rounded-lg shadow-sm transition">Soumettre les notes</button>
        </form>
    </div>
</div>

<form method="post" action="<?= BASE_URL ?>/professor/grades_save.php">
    <input type="hidden" name="course_id" value="<?= $courseId ?>">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-4">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="py-3 px-4">Matricule</th>
                    <th class="py-3 px-4">Étudiant</th>
                    <th class="py-3 px-4">Note /100</th>
                    <th class="py-3 px-4">GPA</th>
                    <th class="py-3 px-4">Lettre</th>
                    <th class="py-3 px-4">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($students as $s):
                $editable = $s['grade_status'] === null || $s['grade_status'] === 'draft'; ?>
                <tr class="border-t border-slate-100">
                    <td class="py-2.5 px-4 text-slate-500"><?= h($s['matricule'] ?? '—') ?></td>
                    <td class="py-2.5 px-4 font-medium"><?= h($s['full_name']) ?></td>
                    <td class="py-2.5 px-4">
                        <?php if ($editable): ?>
                            <input type="number" step="0.01" min="0" max="100" name="score[<?= $s['student_id'] ?>]"
                                   value="<?= $s['score'] !== null ? h((string) $s['score']) : '' ?>" class="w-24 <?= $input ?>">
                        <?php else: ?>
                            <?= h((string) $s['score']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="py-2.5 px-4"><?= $s['gpa'] !== null ? h(number_format((float) $s['gpa'], 2)) : '—' ?></td>
                    <td class="py-2.5 px-4"><?= h($s['grade'] ?? '—') ?></td>
                    <td class="py-2.5 px-4">
                        <?php if ($s['grade_status'] === null): ?><span class="text-slate-400 text-xs">Non saisie</span>
                        <?php elseif ($s['grade_status'] === 'draft'): ?><span class="text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full text-xs font-medium">Brouillon</span>
                        <?php else: ?><span class="text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full text-xs font-medium">Soumise</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($students)): ?>
                <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucun étudiant actif dans cette promotion.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Enregistrer en brouillon</button>
</form>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
