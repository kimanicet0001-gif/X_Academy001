<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM promotions WHERE delegate_id = ? AND status = 'active' LIMIT 1");
$stmt->execute([$userId]);
$promotion = $stmt->fetch();

$courseCount = 0;
$sessionCount = 0;
$plannedTotal = 0.0;
$completedTotal = 0.0;

if ($promotion) {
    $stmt = $db->prepare("SELECT * FROM courses WHERE promotion_id = ?");
    $stmt->execute([$promotion['id']]);
    $courses = $stmt->fetchAll();
    $courseCount = count($courses);

    foreach ($courses as $c) {
        $plannedTotal += (float) $c['planned_hours'];
        $completedTotal += getCompletedHours((int) $c['id']);
    }

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM sessions se JOIN courses c ON c.id = se.course_id WHERE c.promotion_id = ?"
    );
    $stmt->execute([$promotion['id']]);
    $sessionCount = (int) $stmt->fetchColumn();
}

$alerts = $promotion ? array_slice(getCourseAlerts($courses ?? []), 0, 5) : [];

$pageTitle = 'Tableau de bord — Délégué';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Tableau de bord</h1>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mt-4">
        Aucune promotion ne vous est encore assignée. Contactez votre Chef de Département.
    </div>
<?php else: ?>
    <p class="text-slate-500 mb-6">
        Promotion : <strong><?= h($promotion['name']) ?><?= $promotion['group_name'] ? ' — ' . h($promotion['group_name']) : '' ?></strong>
        (<?= h($promotion['academic_year']) ?> · <?= h($promotion['semester']) ?>)
    </p>

    <?php if (!empty($alerts)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 mb-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><?= icon('shield-alert', 'w-4 h-4') ?></span>
                <h2 class="font-semibold text-slate-800 text-sm">Alertes</h2>
            </div>
            <div class="space-y-2">
                <?php foreach ($alerts as $alert):
                    $styles = $alert['type'] === 'warning'
                        ? 'bg-amber-50 text-amber-800 border-amber-200'
                        : 'bg-slate-50 text-slate-600 border-slate-200';
                ?>
                    <p class="text-sm border rounded-lg px-3 py-2 <?= $styles ?>"><?= h($alert['text']) ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-slate-500 text-sm">Mes cours</div>
            <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $courseCount ?>">0</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-slate-500 text-sm">Séances réalisées</div>
            <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $sessionCount ?>">0</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-slate-500 text-sm">Heures prévues</div>
            <div class="text-3xl font-display font-bold text-slate-900"><?= number_format($plannedTotal, 1) ?> h</div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="text-slate-500 text-sm">Heures prestées</div>
            <div class="text-3xl font-display font-bold text-slate-900"><?= number_format($completedTotal, 1) ?> h</div>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="<?= BASE_URL ?>/delegate/courses.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('book-open', 'w-5 h-5') ?></div>
            <div class="font-semibold text-slate-800">Mes cours</div>
        </a>
        <a href="<?= BASE_URL ?>/delegate/students.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('users', 'w-5 h-5') ?></div>
            <div class="font-semibold text-slate-800">Mes étudiants</div>
        </a>
        <a href="<?= BASE_URL ?>/delegate/journal.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('book', 'w-5 h-5') ?></div>
            <div class="font-semibold text-slate-800">Journal complet</div>
        </a>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
