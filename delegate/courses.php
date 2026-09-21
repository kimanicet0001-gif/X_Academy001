<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

$courses = [];
if ($promotion) {
    $stmt = getDB()->prepare('SELECT * FROM courses WHERE promotion_id = ? ORDER BY status DESC, name');
    $stmt->execute([$promotion['id']]);
    $courses = $stmt->fetchAll();
}

$pageTitle = 'Mes cours';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Mes cours</h1>
    <?php if ($promotion): ?>
        <a href="<?= BASE_URL ?>/delegate/course_create.php"
           class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
            + Nouveau cours
        </a>
    <?php endif; ?>
</div>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3">
        Aucune promotion assignée pour le moment. Contactez votre Chef de Département.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($courses as $c):
            $completed = getCompletedHours((int) $c['id']);
            $remaining = calculateRemainingHours((float) $c['planned_hours'], $completed);
            $progress  = calculateProgress((float) $c['planned_hours'], $completed);
        ?>
            <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $c['id'] ?>"
               class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition block">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="font-semibold text-slate-800"><?= h($c['name']) ?></h2>
                    <?php if ($c['status'] !== 'active'): ?>
                        <span class="text-xs bg-slate-100 text-slate-600 border border-slate-200 px-2 py-0.5 rounded-full font-medium">Inactif</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-slate-500 mb-3">Enseignant : <?= h($c['teacher_name']) ?> · <?= h($c['semester']) ?></p>
                <div class="w-full bg-slate-200 rounded-full h-2 mb-1">
                    <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-slate-500">
                    <span><?= number_format($completed, 1) ?> h prestées / <?= number_format((float) $c['planned_hours'], 1) ?> h</span>
                    <span><?= number_format($remaining, 1) ?> h restantes</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($courses)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 text-center text-slate-400 mt-4">
            Aucun cours enregistré pour le moment.
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
