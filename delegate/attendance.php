<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$sessionId = (int) ($_GET['session_id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/courses.php');
}

$stmt = getDB()->prepare(
    "SELECT sess.*, c.name AS course_name
     FROM sessions sess
     JOIN courses c ON c.id = sess.course_id
     WHERE sess.id = ? AND c.promotion_id = ?"
);
$stmt->execute([$sessionId, $promotion['id']]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Séance introuvable.');
    redirect('/delegate/courses.php');
}

if ($session['status'] === 'cancelled') {
    setFlash('error', "Impossible de faire l'appel : cette séance est annulée.");
    redirect('/delegate/course_view.php?id=' . $session['course_id']);
}
if ($session['status'] === 'draft') {
    setFlash('error', "Cette séance est encore en brouillon. Publiez-la avant de faire l'appel.");
    redirect('/delegate/session_view.php?id=' . $sessionId);
}

// On repart toujours de la liste actuelle des étudiants actifs de la
// promotion (pas d'un pré-remplissage figé), avec le statut déjà
// enregistré s'il existe (cas d'une modification d'un appel déjà fait).
$stmt = getDB()->prepare(
    "SELECT st.id AS student_id, st.full_name, a.status
     FROM students st
     LEFT JOIN attendance a ON a.student_id = st.id AND a.session_id = ?
     WHERE st.promotion_id = ? AND st.status = 'active'
     ORDER BY st.full_name"
);
$stmt->execute([$sessionId, $promotion['id']]);
$attendance = $stmt->fetchAll();

$pageTitle = "Faire l'appel";
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Appel — <?= h($session['course_name']) ?></h1>
<p class="text-slate-500 mb-6"><?= formatDateFr($session['session_date']) ?> · <?= h($session['topic']) ?><?= $session['room'] ? ' · Salle ' . h($session['room']) : '' ?></p>

<form method="post" action="<?= BASE_URL ?>/delegate/attendance_save.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <input type="hidden" name="session_id" value="<?= $sessionId ?>">

    <div class="flex justify-end gap-3 mb-3 text-xs">
        <button type="button" onclick="document.querySelectorAll('.att-checkbox').forEach(c => c.checked = true)" class="text-indigo-600 hover:text-indigo-700 font-medium">Tout cocher (présents)</button>
        <button type="button" onclick="document.querySelectorAll('.att-checkbox').forEach(c => c.checked = false)" class="text-slate-500 hover:text-slate-700">Tout décocher</button>
    </div>

    <div class="divide-y">
        <?php foreach ($attendance as $a): ?>
            <label class="flex items-center justify-between py-2.5 cursor-pointer">
                <span class="text-sm text-slate-800"><?= h($a['full_name']) ?></span>
                <input type="checkbox" class="att-checkbox w-5 h-5 accent-indigo-600"
                       name="present[]" value="<?= $a['student_id'] ?>"
                       <?= $a['status'] === 'present' ? 'checked' : '' ?>>
            </label>
        <?php endforeach; ?>
        <?php if (empty($attendance)): ?>
            <p class="py-4 text-center text-slate-400 text-sm">Aucun étudiant dans cette promotion.</p>
        <?php endif; ?>
    </div>

    <div class="flex gap-3 mt-6">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
            Enregistrer l'appel
        </button>
        <a href="<?= BASE_URL ?>/delegate/session_view.php?id=<?= $sessionId ?>" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
    </div>
</form>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
