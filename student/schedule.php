<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();

$stmt = getDB()->prepare(
    "SELECT sess.*, c.name AS course_name, c.teacher_name
     FROM sessions sess
     JOIN courses c ON c.id = sess.course_id
     WHERE c.promotion_id = ? AND sess.status IN ('published', 'done')
     ORDER BY sess.session_date DESC, sess.start_time DESC"
);
$stmt->execute([$user['promotion_id']]);
$sessions = $stmt->fetchAll();

$pageTitle = 'Mon horaire';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Mon horaire</h1>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Cours</th>
                    <th class="py-3 px-4">Enseignant</th>
                    <th class="py-3 px-4">Horaire</th>
                    <th class="py-3 px-4">Salle</th>
                    <th class="py-3 px-4">Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $s): $st = sessionStatusInfo($s['status']); ?>
                <tr class="border-t border-slate-100">
                    <td class="py-3 px-4"><?= formatDateFr($s['session_date']) ?></td>
                    <td class="py-3 px-4 font-medium"><?= h($s['course_name']) ?></td>
                    <td class="py-3 px-4 text-slate-500"><?= h($s['teacher_name']) ?></td>
                    <td class="py-3 px-4"><?= formatTime($s['start_time']) ?> – <?= formatTime($s['end_time']) ?></td>
                    <td class="py-3 px-4 text-slate-500"><?= h($s['room'] ?? '—') ?></td>
                    <td class="py-3 px-4"><span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $st['color'] ?>"><?= h($st['label']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sessions)): ?>
                <tr><td colspan="6" class="py-10 text-center text-slate-400 text-sm">Aucune séance publiée pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
