<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];

$stmt = getDB()->prepare(
    "SELECT p.*, u.full_name AS delegate_name, f.name AS filiere_name,
            (SELECT COUNT(*) FROM students s WHERE s.promotion_id = p.id) AS student_count,
            (SELECT COUNT(*) FROM courses c WHERE c.promotion_id = p.id) AS course_count
     FROM promotions p
     LEFT JOIN users u ON u.id = p.delegate_id
     LEFT JOIN filieres f ON f.id = p.filiere_id
     WHERE p.department_id = ?
     ORDER BY p.academic_year DESC, p.name"
);
$stmt->execute([$deptId]);
$promotions = $stmt->fetchAll();

$pageTitle = 'Promotions / Groupes';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-display font-bold text-slate-900 tracking-tight">Promotions / Groupes</h1>
    <a href="<?= BASE_URL ?>/head/promotion_create.php"
       class="bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition">
        + Nouvelle promotion
    </a>
</div>

<div class="flex justify-end mb-3">
    <input type="text" data-table-search="#promotionsTable" placeholder="Rechercher une promotion..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm" id="promotionsTable">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Filière</th>
                <th class="py-3 px-4">Année / Semestre</th>
                <th class="py-3 px-4">Délégué</th>
                <th class="py-3 px-4">Étudiants</th>
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($promotions as $p): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($p['name']) ?><?= $p['group_name'] ? ' — ' . h($p['group_name']) : '' ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($p['filiere_name'] ?? '—') ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($p['academic_year']) ?> · <?= h($p['semester']) ?></td>
                <td class="py-3 px-4"><?= h($p['delegate_name'] ?? '— non assigné —') ?></td>
                <td class="py-3 px-4"><?= (int) $p['student_count'] ?></td>
                <td class="py-3 px-4"><?= (int) $p['course_count'] ?></td>
                <td class="py-3 px-4">
                    <?php if ($p['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Active</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivée</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                    <a href="<?= BASE_URL ?>/head/promotion_edit.php?id=<?= $p['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Modifier</a>
                    <a href="<?= BASE_URL ?>/head/promotion_delete.php?id=<?= $p['id'] ?>"
                       onclick="return confirm('<?= $p['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?> cette promotion ?');"
                       class="text-red-600 hover:text-red-700 font-medium">
                        <?= $p['status'] === 'active' ? 'Désactiver' : 'Réactiver' ?>
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($promotions)): ?>
            <tr><td colspan="8" class="py-6 text-center text-slate-400">Aucune promotion enregistrée.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
