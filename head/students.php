<?php
/**
 * head/students.php
 * Vue en lecture seule de tous les étudiants du département.
 * La gestion (ajout, modification, désactivation) est réservée au
 * Délégué responsable de chaque promotion.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$promotionFilter = (int) ($_GET['promotion_id'] ?? 0);

$promotions = getDB()->prepare('SELECT * FROM promotions WHERE department_id = ? ORDER BY name');
$promotions->execute([$deptId]);
$promotions = $promotions->fetchAll();

$sql = "SELECT s.*, p.name AS promotion_name, p.group_name
        FROM students s
        JOIN promotions p ON p.id = s.promotion_id
        WHERE p.department_id = ?";
$params = [$deptId];

if ($promotionFilter > 0) {
    $sql .= " AND p.id = ?";
    $params[] = $promotionFilter;
}
$sql .= " ORDER BY p.name, s.full_name";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$pageTitle = 'Étudiants';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Étudiants</h1>
<p class="text-slate-500 mb-6">Vue en lecture seule. La gestion des étudiants se fait au niveau de chaque Délégué.</p>

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-slate-600">Promotion :</label>
        <select name="promotion_id" onchange="this.form.submit()"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
            <option value="0">Toutes</option>
            <?php foreach ($promotions as $p): ?>
                <option value="<?= $p['id'] ?>" <?= $promotionFilter === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= h($p['name']) ?><?= $p['group_name'] ? ' — ' . h($p['group_name']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="<?= BASE_URL ?>/head/export_students.php"
       class="inline-flex items-center gap-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">
        <?= icon('download', 'w-4 h-4') ?> Exporter CSV
    </a>
    <input type="text" data-table-search="#studentsTable" placeholder="Rechercher un étudiant..."
           class="w-full sm:w-64 sm:ml-auto border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
    <table class="w-full text-sm" id="studentsTable">
        <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                <th class="py-3 px-4">Nom complet</th>
                <th class="py-3 px-4">Matricule</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Assiduité</th>
                <th class="py-3 px-4">Statut</th>
                <th class="py-3 px-4 text-right">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s):
            $stats = getStudentAttendanceStats((int) $s['id']);
            $risk = classifyRisk($stats['rate'], $stats['total_sessions']);
        ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($s['full_name']) ?></td>
                <td class="py-3 px-4 text-slate-500 font-mono text-xs"><?= h($s['matricule'] ?? '—') ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($s['promotion_name']) ?><?= $s['group_name'] ? ' — ' . h($s['group_name']) : '' ?></td>
                <td class="py-3 px-4">
                    <span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $risk['color'] ?>"><?= h($risk['label']) ?></span>
                </td>
                <td class="py-3 px-4">
                    <?php if ($s['status'] === 'active'): ?>
                        <span class="text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full text-xs font-medium">Actif</span>
                    <?php else: ?>
                        <span class="text-slate-600 bg-slate-100 border border-slate-200 px-2.5 py-1 rounded-full text-xs font-medium">Désactivé</span>
                    <?php endif; ?>
                </td>
                <td class="py-3 px-4 text-right">
                    <a href="<?= BASE_URL ?>/head/student_matricule_edit.php?id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Corriger le matricule</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
            <tr><td colspan="6" class="py-10 text-center text-slate-400 text-sm">Aucun étudiant enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
