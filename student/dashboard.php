<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();
$db = getDB();

$stmt = $db->prepare(
    "SELECT s.*, p.name AS promotion_name, p.group_name
     FROM students s JOIN promotions p ON p.id = s.promotion_id
     WHERE s.id = ?"
);
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$stats = getStudentAttendanceStats((int) $user['id']);
$risk = classifyRisk($stats['rate'], $stats['total_sessions']);
$badges = getAttendanceBadges($stats);

$stmt = $db->prepare(
    "SELECT sess.*, c.name AS course_name
     FROM sessions sess JOIN courses c ON c.id = sess.course_id
     WHERE c.promotion_id = ? AND sess.status = 'published' AND sess.session_date >= CURDATE()
     ORDER BY sess.session_date, sess.start_time LIMIT 5"
);
$stmt->execute([$student['promotion_id']]);
$upcoming = $stmt->fetchAll();

$pageTitle = 'Tableau de bord';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Bonjour <?= h(explode(' ', $student['full_name'])[0]) ?></h1>
<p class="text-slate-500 mb-6"><?= h($student['promotion_name']) ?><?= $student['group_name'] ? ' — ' . h($student['group_name']) : '' ?></p>

<div class="flex flex-wrap items-center gap-2 mb-6">
    <span class="border rounded-full px-3 py-1 text-sm font-medium <?= $risk['color'] ?>"><?= h($risk['label']) ?></span>
    <?php foreach ($badges as $badge): ?>
        <span class="border rounded-full px-3 py-1 text-sm font-medium <?= $badge['color'] ?>"><?= h($badge['label']) ?></span>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-3 gap-4 mb-6 max-w-lg">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-slate-900 js-counter" data-target="<?= $stats['total_sessions'] ?>">0</div>
        <div class="text-xs text-slate-500">Séances suivies</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-green-600 js-counter" data-target="<?= $stats['present'] ?>">0</div>
        <div class="text-xs text-slate-500">Présences</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 text-center">
        <div class="text-2xl font-display font-bold text-indigo-600"><?= $stats['rate'] ?>%</div>
        <div class="text-xs text-slate-500">Taux de présence</div>
    </div>
</div>

<h2 class="font-semibold text-slate-800 mb-3">Prochaines séances</h2>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Cours</th>
                    <th class="py-3 px-4">Horaire</th>
                    <th class="py-3 px-4">Salle</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming as $s): ?>
                <tr class="border-t border-slate-100">
                    <td class="py-3 px-4"><?= formatDateFr($s['session_date']) ?></td>
                    <td class="py-3 px-4 font-medium"><?= h($s['course_name']) ?></td>
                    <td class="py-3 px-4"><?= formatTime($s['start_time']) ?> – <?= formatTime($s['end_time']) ?></td>
                    <td class="py-3 px-4 text-slate-500"><?= h($s['room'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($upcoming)): ?>
                <tr><td colspan="4" class="py-10 text-center text-slate-400 text-sm">Aucune séance publiée pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
