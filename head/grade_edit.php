<?php
/**
 * head/grade_edit.php
 * Correction d'une note soumise/validée par le Chef de Département :
 * motif obligatoire, historique conservé, professeur notifié, GPA et
 * lettre recalculés automatiquement.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$user = currentUser();
$db = getDB();
$gradeId = (int) ($_GET['id'] ?? 0);

$stmt = $db->prepare(
    "SELECT sg.*, st.full_name, st.matricule, c.name AS course_name, c.id AS course_id, c.credits
     FROM student_grades sg
     JOIN students st ON st.id = sg.student_id
     JOIN courses c ON c.id = sg.course_id
     WHERE sg.id = ?"
);
$stmt->execute([$gradeId]);
$grade = $stmt->fetch();

if (!$grade || $grade['status'] === 'draft' || !validateGradeAccess((int) $user['id'], $gradeId)) {
    setFlash('error', 'Note introuvable ou non modifiable.');
    redirect('/head/grades.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw    = trim(str_replace(',', '.', $_POST['new_score'] ?? ''));
    $reason = trim($_POST['reason'] ?? '');

    if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw > 100) {
        $error = 'La note doit être un nombre entre 0 et 100.';
    } elseif ($reason === '') {
        $error = 'Le motif de la correction est obligatoire.';
    } elseif (round((float) $raw, 2) === round((float) $grade['score'], 2)) {
        $error = "La nouvelle note est identique à l'ancienne.";
    } else {
        $newScore = round((float) $raw, 2);
        $oldScore = (float) $grade['score'];

        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO grade_change_history (grade_id, old_score, new_score, changed_by, reason) VALUES (?, ?, ?, ?, ?)')
               ->execute([$gradeId, $oldScore, $newScore, $user['id'], mb_substr($reason, 0, 255)]);
            $db->prepare('UPDATE student_grades SET score = ? WHERE id = ?')->execute([$newScore, $gradeId]);
            refreshGradeDerivedFields($gradeId, $newScore); // GPA, points pondérés, lettre
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $error = "Erreur lors de l'enregistrement de la correction.";
        }

        if (!$error) {
            if (!empty($grade['submitted_by'])) {
                notifyProfessorOfGradeChange((int) $grade['submitted_by'], (string) $grade['matricule'], $grade['course_name'], $oldScore, $newScore);
            }
            notifyStudentOfGrade((int) $grade['student_id'], $grade['course_name'], $newScore, true);
            logAudit('grade_change', "Note #$gradeId : $oldScore -> $newScore (motif : $reason)");
            setFlash('success', 'Note corrigée ; les résultats sont recalculés et le professeur a été notifié.');
            redirect('/head/grades_course.php?course_id=' . $grade['course_id']);
        }
    }
}

$hs = $db->prepare(
    "SELECT h.*, u.full_name AS changed_by_name FROM grade_change_history h
     LEFT JOIN users u ON u.id = h.changed_by WHERE h.grade_id = ? ORDER BY h.created_at DESC"
);
$hs->execute([$gradeId]);
$history = $hs->fetchAll();

$pageTitle = 'Corriger une note';
require_once ROOT_PATH . '/includes/header.php';
$input = 'w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';
?>

<a href="<?= BASE_URL ?>/head/grades_course.php?course_id=<?= $grade['course_id'] ?>" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 mb-3">
    <?= icon('arrow-left', 'w-4 h-4') ?> Retour au cours
</a>
<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Corriger une note</h1>
<p class="text-slate-500 mb-6"><?= h($grade['full_name']) ?> (<?= h($grade['matricule'] ?? '—') ?>) · <?= h($grade['course_name']) ?></p>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <p class="text-sm text-slate-500 mb-4">Note actuelle : <strong class="text-slate-900"><?= h((string) $grade['score']) ?></strong> — GPA <?= h(number_format((float) $grade['gpa'], 2)) ?> — <?= h($grade['grade'] ?? '—') ?></p>
        <?php if ($error): ?>
            <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Nouvelle note (/100)</label>
                <input type="number" step="0.01" min="0" max="100" name="new_score" required value="<?= h($_POST['new_score'] ?? '') ?>" class="<?= $input ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Motif de la correction</label>
                <input type="text" name="reason" required maxlength="255" value="<?= h($_POST['reason'] ?? '') ?>" class="<?= $input ?>" placeholder="Ex : erreur de saisie constatée sur la copie">
            </div>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Enregistrer la correction</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-3">Historique des corrections</h2>
        <ul class="divide-y divide-slate-100 text-sm">
            <?php foreach ($history as $h): ?>
                <li class="py-2.5">
                    <p><strong><?= h((string) $h['old_score']) ?></strong> → <strong><?= h((string) $h['new_score']) ?></strong>
                        <span class="text-slate-400">· <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?> · <?= h($h['changed_by_name'] ?? '—') ?></span></p>
                    <p class="text-slate-500 text-xs"><?= h($h['reason'] ?? '') ?></p>
                </li>
            <?php endforeach; ?>
            <?php if (empty($history)): ?><li class="py-3 text-slate-400">Aucune correction.</li><?php endif; ?>
        </ul>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
