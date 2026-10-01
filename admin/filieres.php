<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$deptFilter = (int) ($_GET['department_id'] ?? 0);
$departments = getDB()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$sql = "SELECT f.*, d.name AS department_name,
               (SELECT COUNT(*) FROM promotions p WHERE p.filiere_id = f.id) AS promotion_count
        FROM filieres f JOIN departments d ON d.id = f.department_id";
$params = [];
if ($deptFilter > 0) {
    $sql .= " WHERE f.department_id = ?";
    $params[] = $deptFilter;
}
$sql .= " ORDER BY d.name, f.name";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$filieres = $stmt->fetchAll();

$pageTitle = 'Filières';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Filières</h1>
    <a href="<?= BASE_URL ?>/admin/filiere_create.php"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
        + Nouvelle filière
    </a>
</div>

<form method="get" class="mb-4 flex items-center gap-2">
    <label class="text-sm text-slate-600">Département :</label>
    <select name="department_id" onchange="this.form.submit()"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
        <option value="0">Tous</option>
        <?php foreach ($departments as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="py-3 px-4">Filière</th>
                    <th class="py-3 px-4">Département</th>
                    <th class="py-3 px-4">Promotions</th>
                    <th class="py-3 px-4">Statut</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filieres as $f): ?>
                <tr class="border-t border-slate-100">
                    <td class="py-3 px-4 font-medium"><?= h($f['name']) ?></td>
                    <td class="py-3 px-4 text-slate-500"><?= h($f['department_name']) ?></td>
                    <td class="py-3 px-4"><?= (int) $f['promotion_count'] ?></td>
                    <td class="py-3 px-4">
                        <?php if ($f['status'] === 'active'): ?>
                            <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Active</span>
                        <?php else: ?>
                            <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivée</span>
                        <?php endif; ?>
                    </td>
                    <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/admin/filiere_edit.php?id=<?= $f['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Modifier</a>
                        <a href="<?= BASE_URL ?>/admin/filiere_status.php?id=<?= $f['id'] ?>"
                           onclick="return confirm('<?= $f['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?> cette filière ?');"
                           class="text-red-600 hover:text-red-700 font-medium">
                            <?= $f['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($filieres)): ?>
                <tr><td colspan="5" class="py-10 text-center text-slate-400 text-sm">Aucune filière enregistrée.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
