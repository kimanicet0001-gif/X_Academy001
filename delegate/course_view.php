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

$stmt = getDB()->prepare('SELECT * FROM courses WHERE id = ? AND promotion_id = ?');
$stmt->execute([$id, $promotion['id']]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Cours introuvable.');
    redirect('/delegate/courses.php');
}

$completed = getCompletedHours($id);
$remaining = calculateRemainingHours((float) $course['planned_hours'], $completed);
$progress  = calculateProgress((float) $course['planned_hours'], $completed);

$stmt = getDB()->prepare('SELECT * FROM sessions WHERE course_id = ? ORDER BY session_date DESC, start_time DESC');
$stmt->execute([$id]);
$sessions = $stmt->fetchAll();

$estimatedCompletion = estimateCompletionDate((float) $course['planned_hours'], $completed, array_reverse($sessions));

$pageTitle = $course['name'];
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-start justify-between mb-6 flex-wrap gap-3">
    <div>
        <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight"><?= h($course['name']) ?></h1>
        <p class="text-slate-500">Enseignant : <?= h($course['teacher_name']) ?> · <?= h($course['semester']) ?></p>
    </div>
    <div class="space-x-2">
        <a href="<?= BASE_URL ?>/delegate/course_edit.php?id=<?= $id ?>" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Modifier</a>
        <a href="<?= BASE_URL ?>/delegate/session_create.php?course_id=<?= $id ?>" class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">+ Nouvelle séance</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
    <div class="w-full bg-slate-200 rounded-full h-3 mb-2">
        <div class="bg-indigo-600 h-3 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
    </div>
    <div class="grid grid-cols-4 gap-4 text-center text-sm mt-4">
        <div><div class="text-xl font-display font-bold text-slate-900"><?= number_format((float) $course['planned_hours'], 1) ?> h</div><div class="text-slate-500">Prévues</div></div>
        <div><div class="text-xl font-display font-bold text-green-600"><?= number_format($completed, 1) ?> h</div><div class="text-slate-500">Prestées</div></div>
        <div><div class="text-xl font-display font-bold text-amber-600"><?= number_format($remaining, 1) ?> h</div><div class="text-slate-500">Restantes</div></div>
        <div><div class="text-xl font-display font-bold text-slate-900"><?= $progress ?>%</div><div class="text-slate-500">Progression</div></div>
    </div>
    <?php if ($estimatedCompletion): ?>
        <p class="text-xs text-slate-500 text-center mt-4 pt-4 border-t border-slate-100">
            Au rythme actuel des séances, ce cours devrait être terminé vers le
            <strong class="text-slate-700"><?= formatDateFr($estimatedCompletion) ?></strong>.
        </p>
    <?php endif; ?>
</div>

<div class="flex items-center justify-between mb-3">
    <h2 class="font-semibold text-slate-700">Registre PDF du cours</h2>
</div>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6 flex items-center justify-between">
    <span class="text-sm text-slate-500">Un seul PDF, enrichi à chaque nouvelle séance.</span>
    <div class="space-x-3">
        <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $id ?>" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium text-sm"><?= icon('eye', 'w-4 h-4') ?> Voir</a>
        <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $id ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium text-sm"><?= icon('download', 'w-4 h-4') ?> Télécharger</a>
    </div>
</div>

<h2 class="font-semibold text-slate-700 mb-3">Séances</h2>
<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Date</th>
                <th class="py-3 px-4">Horaire</th>
                <th class="py-3 px-4">Durée</th>
                <th class="py-3 px-4">Matière étudiée</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sessions as $s): $st = sessionStatusInfo($s['status']); ?>
            <tr class="border-t border-slate-100">
                <td class="py-2 px-4"><?= formatDateFr($s['session_date']) ?></td>
                <td class="py-2 px-4"><?= formatTime($s['start_time']) ?> – <?= formatTime($s['end_time']) ?></td>
                <td class="py-2 px-4"><?= $s['duration_hours'] ?> h</td>
                <td class="py-2 px-4"><?= h($s['topic']) ?></td>
                <td class="py-2 px-4"><span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $st['color'] ?>"><?= h($st['label']) ?></span></td>
                <td class="py-2 px-4 text-right">
                    <a href="<?= BASE_URL ?>/delegate/session_view.php?id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Voir</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($sessions)): ?>
            <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucune séance enregistrée pour ce cours.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
