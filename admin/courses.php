<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$deptFilter = (int) ($_GET['department_id'] ?? 0);

$departments = getDB()->query('SELECT * FROM departments ORDER BY name')->fetchAll();

$sql = "SELECT c.*, p.name AS promotion_name, p.group_name, d.name AS department_name
        FROM courses c
        JOIN promotions p ON p.id = c.promotion_id
        JOIN departments d ON d.id = p.department_id";
$params = [];
if ($deptFilter > 0) {
    $sql .= " WHERE d.id = ?";
    $params[] = $deptFilter;
}
$sql .= " ORDER BY d.name, p.name, c.name";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$pageTitle = 'Tous les cours';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Cours — toute l'université</h1>
<p class="text-slate-500 mb-6">Vue consolidée de tous les cours, toutes facultés confondues.</p>

<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="get" class="flex items-center gap-2">
        <label class="text-sm text-slate-600">Département :</label>
        <select name="department_id" onchange="this.form.submit()"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
            <option value="0">Tous</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= $d['id'] ?>" <?= $deptFilter === (int) $d['id'] ? 'selected' : '' ?>><?= h($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <a href="<?= BASE_URL ?>/admin/export_courses.php<?= $deptFilter ? '?department_id=' . $deptFilter : '' ?>"
       class="inline-flex items-center gap-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 text-sm font-medium px-3.5 py-2 rounded-lg transition">
        <?= icon('download', 'w-4 h-4') ?> Exporter CSV
    </a>
    <input type="text" data-table-search="#coursesTable" placeholder="Rechercher un cours, un enseignant..."
           class="w-full sm:w-64 sm:ml-auto border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm" id="coursesTable">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Enseignant</th>
                <th class="py-3 px-4">Département</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Heures</th>
                <th class="py-3 px-4">Progression</th>
                <th class="py-3 px-4 text-right">Registre</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($courses as $c):
            $completed = getCompletedHours((int) $c['id']);
            $progress  = calculateProgress((float) $c['planned_hours'], $completed);
        ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($c['name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($c['teacher_name']) ?></td>
                <td class="py-3 px-4"><?= h($c['department_name']) ?></td>
                <td class="py-3 px-4 text-slate-500"><?= h($c['promotion_name']) ?><?= $c['group_name'] ? ' — ' . h($c['group_name']) : '' ?></td>
                <td class="py-3 px-4"><?= number_format($completed, 1) ?> / <?= number_format((float) $c['planned_hours'], 1) ?> h</td>
                <td class="py-3 px-4">
                    <div class="w-28 bg-slate-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all" style="width: <?= $progress ?>%"></div>
                    </div>
                    <span class="text-xs text-slate-500"><?= $progress ?>%</span>
                </td>
                <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                    <a href="<?= BASE_URL ?>/pdf/view_register.php?course_id=<?= $c['id'] ?>" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('eye', 'w-4 h-4') ?></a>
                    <a href="<?= BASE_URL ?>/pdf/download_register.php?course_id=<?= $c['id'] ?>" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-700 font-medium"><?= icon('download', 'w-4 h-4') ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($courses)): ?>
            <tr><td colspan="7" class="py-10 text-center text-slate-400 text-sm">Aucun cours enregistré.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
