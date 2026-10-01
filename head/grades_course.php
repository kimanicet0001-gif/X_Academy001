<?php
/**
 * head/grades_course.php
 * Notes d'un cours du département : validation (verrouillage) et
 * accès à la correction de chaque note.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$user = currentUser();
$courseId = (int) ($_GET['course_id'] ?? 0);

$stmt = getDB()->prepare(
    "SELECT c.*, p.name AS promotion_name, p.group_name, p.academic_year, p.department_id
     FROM courses c JOIN promotions p ON p.id = c.promotion_id WHERE c.id = ?"
);
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course || (int) $course['department_id'] !== (int) $user['department_id']) {
    setFlash('error', 'Cours introuvable dans votre département.');
    redirect('/head/grades.php');
}

$students = getCourseStudentsWithGrades($courseId);
$submitted = count(array_filter($students, fn($s) => $s['grade_status'] === 'submitted'));

$pageTitle = 'Notes — ' . $course['name'];
require_once ROOT_PATH . '/includes/header.php';
?>

<a href="<?= BASE_URL ?>/head/grades.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-3">
    <?= icon('arrow-left', 'w-4 h-4') ?> Notes du département
</a>
<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight"><?= h($course['name']) ?></h1>
        <p class="text-slate-500"><?= h($course['promotion_name'] . ($course['group_name'] ? ' — ' . $course['group_name'] : '') . ' · ' . $course['academic_year'] . ' ' . $course['semester']) ?> · <?= h((string) $course['credits']) ?> crédit(s)</p>
    </div>
    <form method="post" action="<?= BASE_URL ?>/head/grades_lock.php"
          onsubmit="return confirm('Valider définitivement les notes soumises de ce cours ?');">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        <button <?= $submitted === 0 ? 'disabled' : '' ?>
                class="bg-green-600 hover:bg-green-700 disabled:opacity-40 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Valider les notes soumises (<?= $submitted ?>)
        </button>
    </form>
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Matricule</th>
                <th class="py-3 px-4">Étudiant</th>
                <th class="py-3 px-4">Note</th>
                <th class="py-3 px-4">GPA</th>
                <th class="py-3 px-4">Lettre</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s): ?>
            <tr class="border-t border-slate-100">
                <td class="py-2.5 px-4 text-slate-500"><?= h($s['matricule'] ?? '—') ?></td>
                <td class="py-2.5 px-4 font-medium"><?= h($s['full_name']) ?></td>
                <td class="py-2.5 px-4"><?= $s['score'] !== null ? h((string) $s['score']) : '—' ?></td>
                <td class="py-2.5 px-4"><?= $s['gpa'] !== null ? h(number_format((float) $s['gpa'], 2)) : '—' ?></td>
                <td class="py-2.5 px-4"><?= h($s['grade'] ?? '—') ?></td>
                <td class="py-2.5 px-4">
                    <?php if ($s['grade_status'] === null): ?><span class="text-slate-400 text-xs">Non saisie</span>
                    <?php elseif ($s['grade_status'] === 'draft'): ?><span class="text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full text-xs font-medium">Brouillon</span>
                    <?php elseif ($s['grade_status'] === 'submitted'): ?><span class="text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-full text-xs font-medium">Soumise</span>
                    <?php else: ?><span class="text-green-700 bg-green-50 border border-green-200 px-2 py-0.5 rounded-full text-xs font-medium">Validée</span>
                    <?php endif; ?>
                </td>
                <td class="py-2.5 px-4 text-right">
                    <?php if (in_array($s['grade_status'], ['submitted', 'locked'], true)): ?>
                        <a href="<?= BASE_URL ?>/head/grade_edit.php?id=<?= $s['grade_id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Corriger / historique</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
