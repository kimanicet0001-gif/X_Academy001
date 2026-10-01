<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$db = getDB();
$assignments = getProfessorAssignments((int) $user['id']);

$stmt = $db->prepare(
    "SELECT sg.status, COUNT(*) AS n
     FROM student_grades sg
     JOIN teaching_assignments ta ON ta.course_id = sg.course_id AND ta.professor_id = ? AND ta.status = 'active'
     GROUP BY sg.status"
);
$stmt->execute([$user['id']]);
$byStatus = array_column($stmt->fetchAll(), 'n', 'status');

$unread = getUnreadNotificationCount((int) $user['id'], 'professor');

$pageTitle = 'Tableau de bord';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Bonjour, <?= h($user['full_name']) ?></h1>
<p class="text-slate-500 mb-6">Vos enseignements et l'avancement de la saisie des notes.</p>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php
    $cards = [
        ['Enseignements', count($assignments), 'book-open', 'bg-indigo-50 text-indigo-600'],
        ['Notes en brouillon', (int) ($byStatus['draft'] ?? 0), 'edit', 'bg-amber-50 text-amber-600'],
        ['Notes soumises', (int) ($byStatus['submitted'] ?? 0) + (int) ($byStatus['locked'] ?? 0), 'check-circle', 'bg-green-50 text-green-600'],
        ['Notifications non lues', $unread, 'bell', 'bg-rose-50 text-rose-600'],
    ];
    foreach ($cards as [$label, $value, $ic, $color]): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <span class="w-10 h-10 rounded-xl flex items-center justify-center mb-3 <?= $color ?>"><?= icon($ic, 'w-5 h-5') ?></span>
            <p class="text-2xl font-display font-bold text-slate-900"><?= $value ?></p>
            <p class="text-xs text-slate-500"><?= h($label) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 font-semibold text-slate-800">Mes enseignements</div>
    <div class="divide-y divide-slate-100">
        <?php foreach ($assignments as $a):
            $pct = $a['student_count'] > 0 ? min(100, round($a['graded_count'] / $a['student_count'] * 100)) : 0; ?>
            <a href="<?= BASE_URL ?>/professor/grades.php?course_id=<?= $a['course_id'] ?>" class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition">
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-slate-900 truncate"><?= h($a['course_name']) ?></p>
                    <p class="text-xs text-slate-500"><?= h($a['promotion_name'] . ($a['group_name'] ? ' — ' . $a['group_name'] : '') . ' · ' . $a['academic_year'] . ' ' . $a['semester']) ?></p>
                </div>
                <div class="w-40 hidden sm:block">
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-indigo-500" style="width: <?= $pct ?>%"></div></div>
                    <p class="text-xs text-slate-400 mt-1"><?= (int) $a['graded_count'] ?>/<?= (int) $a['student_count'] ?> notes saisies</p>
                </div>
                <span class="text-indigo-600 text-sm font-medium">Saisir →</span>
            </a>
        <?php endforeach; ?>
        <?php if (empty($assignments)): ?>
            <div class="p-10 text-center text-slate-400 text-sm">Aucun cours ne vous est encore affecté. Contactez l'administration.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
