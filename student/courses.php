<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_STUDENT]);

$user = currentUser();

$stmt = getDB()->prepare("SELECT * FROM courses WHERE promotion_id = ? AND status = 'active' ORDER BY name");
$stmt->execute([$user['promotion_id']]);
$courses = $stmt->fetchAll();

$pageTitle = 'Mes cours';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Mes cours</h1>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <?php foreach ($courses as $c):
        $completed = getCompletedHours((int) $c['id']);
        $progress = calculateProgress((float) $c['planned_hours'], $completed);
    ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
            <h2 class="font-semibold text-slate-800 mb-1"><?= h($c['name']) ?></h2>
            <p class="text-sm text-slate-500 mb-3">Enseignant : <?= h($c['teacher_name']) ?></p>
            <div class="w-full bg-slate-200 rounded-full h-2 mb-1">
                <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
            </div>
            <div class="flex justify-between text-xs text-slate-500 mb-3">
                <span><?= number_format($completed, 1) ?> h / <?= number_format((float) $c['planned_hours'], 1) ?> h</span>
                <span><?= $progress ?>%</span>
            </div>
            <div class="flex gap-3 text-sm">
                <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $c['id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('eye', 'w-4 h-4') ?> Voir le registre</a>
                <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('download', 'w-4 h-4') ?> Télécharger</a>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if (empty($courses)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-10 text-center text-slate-400 text-sm">
        Aucun cours enregistré pour votre promotion.
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
