<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$db = getDB();
$deptCount = (int) $db->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$activeDeptCount = (int) $db->query("SELECT COUNT(*) FROM departments WHERE status='active'")->fetchColumn();
$headCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='head'")->fetchColumn();
$delegateCount = (int) $db->query("SELECT COUNT(*) FROM users WHERE role='delegate'")->fetchColumn();

$recentDepartments = $db->query(
    "SELECT * FROM departments ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$allCourses = $db->query(
    "SELECT c.*, p.name AS promotion_name
     FROM courses c JOIN promotions p ON p.id = c.promotion_id
     WHERE c.status = 'active'"
)->fetchAll();
$alerts = array_slice(getCourseAlerts($allCourses), 0, 5);

$pageTitle = 'Tableau de bord — Administrateur';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Tableau de bord</h1>

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
        <a href="<?= BASE_URL ?>/admin/analytics.php" class="inline-block mt-3 text-xs text-indigo-600 hover:text-indigo-700 font-medium">Voir toutes les statistiques →</a>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Départements</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $deptCount ?>">0</div>
        <div class="text-xs text-slate-400"><?= $activeDeptCount ?> actif(s)</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Chefs de Département</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $headCount ?>">0</div>
    </div>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <div class="text-slate-500 text-sm">Délégués (total)</div>
        <div class="text-3xl font-display font-bold text-slate-900 js-counter" data-target="<?= $delegateCount ?>">0</div>
    </div>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-slate-700">Départements récents</h2>
        <a href="<?= BASE_URL ?>/admin/departments.php" class="text-indigo-600 text-sm font-medium hover:text-indigo-700">Voir tout</a>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-2">Nom</th>
                <th class="py-2">Statut</th>
                <th class="py-2">Créé le</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentDepartments as $d): ?>
            <tr class="border-b last:border-0">
                <td class="py-2"><?= h($d['name']) ?></td>
                <td class="py-2">
                    <?php if ($d['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td class="py-2 text-slate-500"><?= formatDateFr($d['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentDepartments)): ?>
            <tr><td colspan="3" class="py-4 text-center text-slate-400">Aucun département pour le moment.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
    <a href="<?= BASE_URL ?>/admin/courses.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('book-open', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Tous les cours</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/students.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('users', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Tous les étudiants</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/analytics.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('chart', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Statistiques</div>
    </a>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 hover:border-indigo-300 hover:shadow-md transition">
        <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3"><?= icon('cog', 'w-5 h-5') ?></div>
        <div class="font-semibold text-slate-800">Paramètres université</div>
    </a>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
