<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$db = getDB();

$deptStmt = $db->prepare('SELECT * FROM departments WHERE id = ?');
$deptStmt->execute([$deptId]);
$department = $deptStmt->fetch();

$stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'delegate'");
$stmt->execute([$deptId]);
$delegateCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM promotions WHERE department_id = ?");
$stmt->execute([$deptId]);
$promotionCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT COUNT(*) FROM students s
     JOIN promotions p ON p.id = s.promotion_id
     WHERE p.department_id = ?"
);
$stmt->execute([$deptId]);
$studentCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT COUNT(*) FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     WHERE p.department_id = ?"
);
$stmt->execute([$deptId]);
$courseCount = (int) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT c.*, p.name AS promotion_name
     FROM courses c JOIN promotions p ON p.id = c.promotion_id
     WHERE p.department_id = ? AND c.status = 'active'"
);
$stmt->execute([$deptId]);
$deptCourses = $stmt->fetchAll();
$alerts = array_slice(getCourseAlerts($deptCourses), 0, 5);

$pageTitle = 'Tableau de bord — Chef de Département';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Tableau de bord</h1>
<p class="text-slate-500 mb-6">Département : <strong><?= h($department['name'] ?? '—') ?></strong></p>

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
        <a href="<?= BASE_URL ?>/head/reports.php" class="inline-block mt-3 text-xs text-indigo-600 hover:text-indigo-700 font-medium">Voir toutes les statistiques →</a>
    </div>
<?php endif; ?>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Délégués</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $delegateCount ?>">0</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Promotions</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $promotionCount ?>">0</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Étudiants</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $studentCount ?>">0</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Cours</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $courseCount ?>">0</div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <a href="<?= BASE_URL ?>/head/delegates.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('users', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Gérer les Délégués</div>
    </a>
    <a href="<?= BASE_URL ?>/head/promotions.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('academic-cap', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Gérer les Promotions</div>
    </a>
    <a href="<?= BASE_URL ?>/head/reports.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('document', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Statistiques &amp; Registres PDF</div>
    </a>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
