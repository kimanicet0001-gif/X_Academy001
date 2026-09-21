<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

if (!$promotion) {
    setFlash('error', 'Aucune promotion assignée.');
    redirect('/delegate/courses.php');
}

$courseId = (int) ($_GET['course_id'] ?? $_POST['course_id'] ?? 0);

$stmt = getDB()->prepare("SELECT * FROM courses WHERE id = ? AND promotion_id = ? AND status = 'active'");
$stmt->execute([$courseId, $promotion['id']]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Cours introuvable ou inactif.');
    redirect('/delegate/courses.php');
}

$error = null;
$sessionDate = date('Y-m-d');
$startTime = '08:00';
$endTime = '09:40';
$topic = '';
$room = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionDate = $_POST['session_date'] ?? '';
    $startTime   = $_POST['start_time'] ?? '';
    $endTime     = $_POST['end_time'] ?? '';
    $topic       = trim($_POST['topic'] ?? '');
    $room        = trim($_POST['room'] ?? '');
    $publishNow  = isset($_POST['publish_now']);

    $duration = computeDurationHours($startTime, $endTime);

    if ($sessionDate === '' || $startTime === '' || $endTime === '' || $topic === '') {
        $error = 'Tous les champs sont obligatoires (sauf la salle).';
    } elseif ($duration <= 0) {
        $error = "L'heure de fin doit être postérieure à l'heure de début.";
    } else {
        $status = $publishNow ? 'published' : 'draft';

        $ins = getDB()->prepare(
            'INSERT INTO sessions (course_id, session_date, start_time, end_time, duration_hours, topic, room, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([$courseId, $sessionDate, $startTime, $endTime, $duration, $topic, $room ?: null, $status]);
        $sessionId = (int) getDB()->lastInsertId();

        logAudit('session_create', "Séance créée pour le cours #$courseId le $sessionDate (statut : $status)");

        if ($publishNow) {
            notifyPromotion(
                $promotion['id'], 'session_published',
                "Nouvelle séance publiée : {$course['name']} le " . formatDateFr($sessionDate) . " à " . formatTime($startTime),
                BASE_URL . '/student/schedule.php', $sessionId
            );
            setFlash('success', 'Séance créée et publiée aux étudiants.');
        } else {
            setFlash('success', 'Séance enregistrée en brouillon. Publiez-la quand elle sera prête.');
        }
        redirect('/delegate/course_view.php?id=' . $courseId);
    }
}

$pageTitle = 'Nouvelle séance';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Nouvelle séance</h1>
<p class="text-slate-500 mb-6">Cours : <strong><?= h($course['name']) ?></strong></p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
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
            <input type="text" name="topic" required value="<?= h($topic) ?>" placeholder="Ex : Introduction à la sécurité Web"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Salle (optionnel)</label>
            <input type="text" name="room" value="<?= h($room) ?>" placeholder="Ex : Amphi B"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="publish_now" class="w-4 h-4 accent-indigo-600">
            Publier immédiatement (les étudiants seront notifiés)
        </label>
        <p class="text-xs text-slate-400">Sans cette case, la séance reste en brouillon — visible par vous seul, modifiable librement.</p>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $courseId ?>" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
