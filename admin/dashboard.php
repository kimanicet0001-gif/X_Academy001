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

<div class="flex items-baseline justify-between mb-8">
    <div>
        <h1 class="text-3xl font-display font-semibold text-slate-900 tracking-tight">Tableau de bord</h1>
        <p class="text-slate-500 text-sm mt-1">Vue d'ensemble de l'établissement</p>
    </div>
</div>

<?php if (!empty($alerts)): ?>
    <div class="border-l-2 border-amber-400 bg-amber-50/60 rounded-r-lg px-4 py-3 mb-8">
        <p class="text-xs font-semibold text-amber-800 mb-1.5">À signaler</p>
        <div class="space-y-1">
            <?php foreach ($alerts as $alert): ?>
                <p class="text-sm text-amber-900"><?= h($alert['text']) ?></p>
            <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/admin/analytics.php" class="inline-block mt-2 text-xs text-amber-800 hover:text-amber-900 font-medium underline underline-offset-2">Voir toutes les statistiques</a>
    </div>
<?php endif; ?>

<!-- Indicateurs clés : une bande unique avec séparateurs fins, plutôt que
     trois cartes identiques flottantes. -->
<div class="grid grid-cols-3 divide-x divide-slate-200 border border-slate-200 rounded-2xl bg-white mb-8">
    <div class="px-6 py-5">
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1.5">Départements</p>
        <p class="text-4xl font-display font-semibold text-slate-900"><?= $deptCount ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= $activeDeptCount ?> actif(s)</p>
    </div>
    <div class="px-6 py-5">
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1.5">Chefs de Département</p>
        <p class="text-4xl font-display font-semibold text-slate-900"><?= $headCount ?></p>
    </div>
    <div class="px-6 py-5">
        <p class="text-xs uppercase tracking-wide text-slate-400 mb-1.5">Délégués</p>
        <p class="text-4xl font-display font-semibold text-slate-900"><?= $delegateCount ?></p>
    </div>
</div>

<!-- Table posée directement sur le fond, filet fin — pas de carte flottante
     autour d'une simple liste. -->
<div class="mb-10">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-slate-800">Départements récents</h2>
        <a href="<?= BASE_URL ?>/admin/departments.php" class="text-indigo-600 text-sm font-medium hover:text-indigo-700">Voir tout →</a>
    </div>
    <table class="w-full text-sm border-t border-slate-200">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-2.5">Nom</th>
                <th class="py-2.5">Statut</th>
                <th class="py-2.5">Créé le</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($recentDepartments as $d): ?>
            <tr class="border-b border-slate-100">
                <td class="py-2.5 font-medium text-slate-800"><?= h($d['name']) ?></td>
                <td class="py-2.5">
                    <?php if ($d['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td class="py-2.5 text-slate-500"><?= formatDateFr($d['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($recentDepartments)): ?>
            <tr><td colspan="3" class="py-6 text-center text-slate-400">Aucun département pour le moment.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Accès rapides : une rangée sobre plutôt que 4 cartes identiques. -->
<div>
    <h2 class="font-semibold text-slate-800 mb-3">Accès rapides</h2>
    <div class="flex flex-wrap gap-2">
        <?php
        $quickLinks = [
            ['url' => '/admin/courses.php', 'label' => 'Tous les cours', 'icon' => 'book-open'],
            ['url' => '/admin/students.php', 'label' => 'Tous les étudiants', 'icon' => 'users'],
            ['url' => '/admin/analytics.php', 'label' => 'Statistiques', 'icon' => 'chart'],
            ['url' => '/admin/settings.php', 'label' => 'Paramètres université', 'icon' => 'cog'],
        ];
        foreach ($quickLinks as $q): ?>
            <a href="<?= BASE_URL . $q['url'] ?>"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-full border border-slate-200 text-sm font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700 hover:bg-indigo-50/50 transition">
                <?= icon($q['icon'], 'w-4 h-4') ?><?= h($q['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
