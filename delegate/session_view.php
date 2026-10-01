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

$stmt = getDB()->prepare(
    "SELECT st.full_name, a.status
     FROM attendance a
     JOIN students st ON st.id = a.student_id
     WHERE a.session_id = ?
     ORDER BY st.full_name"
);
$stmt->execute([$id]);
$attendance = $stmt->fetchAll();

$present = count(array_filter($attendance, fn($a) => $a['status'] === 'present'));
$total = count($attendance);
$rate = calculateAttendanceRate($present, $total);
$statusInfo = sessionStatusInfo($session['status']);

$pageTitle = 'Détail de la séance';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-start justify-between mb-3 flex-wrap gap-3">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight"><?= h($session['course_name']) ?> — <?= formatDateFr($session['session_date']) ?></h1>
            <span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $statusInfo['color'] ?>"><?= h($statusInfo['label']) ?></span>
        </div>
        <p class="text-slate-500">
            <?= formatTime($session['start_time']) ?> – <?= formatTime($session['end_time']) ?> (<?= $session['duration_hours'] ?> h académiques) · <?= h($session['topic']) ?><?= $session['room'] ? ' · Salle ' . h($session['room']) : '' ?>
        </p>
    </div>
    <div class="flex gap-2">
        <?php if ($session['status'] === 'draft'): ?>
            <a href="<?= BASE_URL ?>/delegate/session_edit.php?id=<?= $id ?>" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Modifier</a>
            <a href="<?= BASE_URL ?>/delegate/session_publish.php?id=<?= $id ?>"
               onclick="return confirm('Publier cette séance ? Les étudiants seront notifiés.');"
               class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">Publier</a>
        <?php elseif ($session['status'] === 'published'): ?>
            <a href="<?= BASE_URL ?>/delegate/session_edit.php?id=<?= $id ?>" class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Modifier</a>
            <a href="<?= BASE_URL ?>/delegate/attendance.php?session_id=<?= $id ?>"
               class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">Faire l'appel</a>
        <?php elseif ($session['status'] === 'done'): ?>
            <a href="<?= BASE_URL ?>/delegate/attendance.php?session_id=<?= $id ?>"
               class="bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-4 py-2.5 rounded-lg transition">Modifier l'appel</a>
        <?php endif; ?>
        <?php if (in_array($session['status'], ['draft', 'published'], true)): ?>
            <a href="<?= BASE_URL ?>/delegate/session_cancel.php?id=<?= $id ?>"
               onclick="return confirm('Annuler cette séance ?');"
               class="text-red-600 hover:text-red-700 text-sm font-medium self-center">Annuler</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-3 gap-4 mb-6 max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-slate-900"><?= $total ?></div>
        <div class="text-xs text-slate-500">Total étudiants</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-green-600"><?= $present ?></div>
        <div class="text-xs text-slate-500">Présents</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-indigo-600"><?= $rate ?>%</div>
        <div class="text-xs text-slate-500">Taux de présence</div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden max-w-lg">
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200"><th class="py-2 px-4">Étudiant</th><th class="py-2 px-4">Statut</th></tr>
        </thead>
        <tbody>
        <?php foreach ($attendance as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="py-2 px-4"><?= h($a['full_name']) ?></td>
                <td class="py-2 px-4">
                    <?php if ($a['status'] === 'present'): ?>
                        <span class="inline-flex items-center gap-1 text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Présent</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-red-600 bg-red-50 border border-red-200 px-2.5 py-1 rounded-full text-xs font-medium">Absent</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<div class="mt-4">
    <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $session['course_id'] ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium text-sm"><?= icon('arrow-left', 'w-4 h-4') ?> Retour au cours</a>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
