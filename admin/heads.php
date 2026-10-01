<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$heads = getDB()->query(
    "SELECT u.*, d.name AS department_name
     FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.role = 'head'
     ORDER BY u.full_name"
)->fetchAll();

$pageTitle = 'Chefs de Département';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Chefs de Département</h1>
    <a href="<?= BASE_URL ?>/admin/head_create.php"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
        + Nouveau Chef
    </a>
</div>

<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#headsTable" placeholder="Rechercher un chef de département..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm" id="headsTable">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Nom complet</th>
                <th class="py-3 px-4">Identifiant</th>
                <th class="py-3 px-4">Département</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($heads as $u): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($u['full_name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($u['username']) ?></td>
                <td class="py-3 px-4"><?= h($u['department_name'] ?? '—') ?></td>
                <td class="py-3 px-4">
                    <?php if ($u['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-right space-x-2">
                    <a href="<?= BASE_URL ?>/admin/head_edit.php?id=<?= $u['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Modifier</a>
                    <a href="<?= BASE_URL ?>/admin/head_password.php?id=<?= $u['id'] ?>" class="text-amber-700 hover:text-amber-800 font-medium">Mot de passe</a>
                    <a href="<?= BASE_URL ?>/admin/head_status.php?id=<?= $u['id'] ?>"
                       onclick="return confirm('<?= $u['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?> ce compte ?');"
                       class="text-red-600 hover:text-red-700 font-medium">
                        <?= $u['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($heads)): ?>
            <tr><td colspan="5" class="py-6 text-center text-slate-400">Aucun Chef de Département enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
