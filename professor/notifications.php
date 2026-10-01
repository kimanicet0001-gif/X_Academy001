<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_PROFESSOR]);

$user = currentUser();
$db = getDB();

$db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);

$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$typeIcons = [
    'grade_change' => ['icon' => 'edit', 'color' => 'bg-amber-50 text-amber-600'],
];

$pageTitle = 'Notifications';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Notifications</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm divide-y divide-slate-100">
    <?php foreach ($notifications as $n):
        $style = $typeIcons[$n['type']] ?? ['icon' => 'bell', 'color' => 'bg-slate-100 text-slate-600'];
    ?>
        <div class="flex items-start gap-3 p-4">
            <span class="w-9 h-9 rounded-lg shrink-0 flex items-center justify-center <?= $style['color'] ?>">
                <?= icon($style['icon'], 'w-4 h-4') ?>
            </span>
            <div class="flex-1">
                <p class="text-sm text-slate-800"><?= h($n['message']) ?></p>
                <p class="text-xs text-slate-400 mt-0.5"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (empty($notifications)): ?>
        <div class="p-10 text-center text-slate-400 text-sm">Aucune notification pour le moment.</div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
