<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$id = (int) ($_GET['id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/courses.php');
}

$stmt = getDB()->prepare(
    "SELECT sess.*, c.name AS course_name, c.id AS course_id
     FROM sessions sess
     JOIN courses c ON c.id = sess.course_id
     WHERE sess.id = ? AND c.promotion_id = ?"
);
$stmt->execute([$id, $promotion['id']]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Séance introuvable.');
    redirect('/delegate/courses.php');
}

if ($session['status'] === 'done' || $session['status'] === 'cancelled') {
    setFlash('error', 'Une séance terminée ou annulée ne peut plus être modifiée.');
    redirect('/delegate/session_view.php?id=' . $id);
}

$wasPublished = $session['status'] === 'published';

$error = null;
$sessionDate = $session['session_date'];
$startTime = substr($session['start_time'], 0, 5);
$endTime = substr($session['end_time'], 0, 5);
$topic = $session['topic'];
$room = $session['room'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionDate = $_POST['session_date'] ?? '';
    $startTime   = $_POST['start_time'] ?? '';
    $endTime     = $_POST['end_time'] ?? '';
    $topic       = trim($_POST['topic'] ?? '');
    $room        = trim($_POST['room'] ?? '');

    $duration = computeDurationHours($startTime, $endTime);

    if ($sessionDate === '' || $startTime === '' || $endTime === '' || $topic === '') {
        $error = 'Tous les champs sont obligatoires (sauf la salle).';
    } elseif ($duration <= 0) {
        $error = "L'heure de fin doit être postérieure à l'heure de début.";
    } else {
        $upd = getDB()->prepare(
            'UPDATE sessions SET session_date = ?, start_time = ?, end_time = ?, duration_hours = ?, topic = ?, room = ? WHERE id = ?'
        );
        $upd->execute([$sessionDate, $startTime, $endTime, $duration, $topic, $room ?: null, $id]);
        logAudit('session_edit', "Séance #$id modifiée");

        if ($wasPublished) {
            notifyPromotion(
                $promotion['id'], 'session_updated',
                "Séance modifiée : {$session['course_name']} — désormais le " . formatDateFr($sessionDate) . " à " . formatTime($startTime),
                BASE_URL . '/student/schedule.php', $id
            );
        }

        setFlash('success', 'Séance mise à jour.');
        redirect('/delegate/session_view.php?id=' . $id);
    }
}

$pageTitle = 'Modifier la séance';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Modifier la séance</h1>
<p class="text-slate-500 mb-6">Cours : <strong><?= h($session['course_name']) ?></strong></p>

<?php if ($wasPublished): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mb-4 max-w-lg text-sm">
        Cette séance est déjà publiée : toute modification enverra automatiquement une notification aux étudiants concernés.
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Date de la séance</label>
            <input type="date" name="session_date" required value="<?= h($sessionDate) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Heure de début</label>
                <input type="time" name="start_time" required value="<?= h($startTime) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Heure de fin</label>
                <input type="time" name="end_time" required value="<?= h($endTime) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
        </div>
        <p class="text-xs text-slate-400 -mt-2">1 heure académique = <?= MINUTES_PER_ACADEMIC_HOUR ?> minutes réelles.</p>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Matière étudiée</label>
            <input type="text" name="topic" required value="<?= h($topic) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Salle (optionnel)</label>
            <input type="text" name="room" value="<?= h($room) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/delegate/session_view.php?id=<?= $id ?>" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
