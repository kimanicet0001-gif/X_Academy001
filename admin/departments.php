<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$departments = getDB()->query(
    "SELECT d.*,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'head') AS head_count
     FROM departments d
     ORDER BY d.name"
)->fetchAll();

$pageTitle = 'Départements';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Départements</h1>
    <a href="<?= BASE_URL ?>/admin/department_create.php"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
        + Nouveau département
    </a>
</div>

<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#departmentsTable" placeholder="Rechercher un département..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm" id="departmentsTable">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                <th class="py-3 px-4">Nom</th>
                <th class="py-3 px-4">Chef(s) de département</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($departments as $d): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($d['name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= (int) $d['head_count'] ?></td>
                <td class="py-3 px-4">
                    <?php if ($d['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-right space-x-2">
                    <a href="<?= BASE_URL ?>/admin/department_edit.php?id=<?= $d['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Modifier</a>
                    <a href="<?= BASE_URL ?>/admin/department_delete.php?id=<?= $d['id'] ?>"
                       onclick="return confirm('<?= $d['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?> ce département ?');"
                       class="text-red-600 hover:text-red-700 font-medium">
                        <?= $d['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($departments)): ?>
            <tr><td colspan="4" class="py-6 text-center text-slate-400">Aucun département enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
