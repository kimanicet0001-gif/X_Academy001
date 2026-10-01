<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$db = getDB();
$profFilter = (int) ($_GET['professor_id'] ?? 0);

$professors = $db->query("SELECT id, full_name FROM users WHERE role = 'professor' AND status = 'active' ORDER BY full_name")->fetchAll();

// Cours groupés par Département > Filière, pour éviter toute confusion
// entre deux cours de même nom donnés dans des filières différentes.
$courses = $db->query(
    "SELECT c.id, c.name, c.semester, c.credits, p.name AS promotion_name, p.group_name, p.academic_year,
            d.name AS department_name, f.name AS filiere_name
     FROM courses c
     JOIN promotions p ON p.id = c.promotion_id
     JOIN departments d ON d.id = p.department_id
     LEFT JOIN filieres f ON f.id = p.filiere_id
     WHERE c.status = 'active'
     ORDER BY d.name, f.name, p.name, c.name"
)->fetchAll();

$grouped = [];
foreach ($courses as $c) {
    $groupLabel = $c['department_name'] . ' — ' . ($c['filiere_name'] ?? 'Sans filière');
    $grouped[$groupLabel][] = $c;
}

$sql = "SELECT ta.id, ta.status, u.full_name AS professor_name, c.name AS course_name, c.semester, c.credits, u2.name AS ue_name,
               p.name AS promotion_name, p.group_name, p.academic_year, d.name AS department_name, f.name AS filiere_name
        FROM teaching_assignments ta
        JOIN users u ON u.id = ta.professor_id
        JOIN courses c ON c.id = ta.course_id
        JOIN promotions p ON p.id = c.promotion_id
        JOIN departments d ON d.id = p.department_id
        LEFT JOIN filieres f ON f.id = p.filiere_id
        LEFT JOIN ues u2 ON u2.id = c.ue_id";
$params = [];
if ($profFilter > 0) {
    $sql .= ' WHERE ta.professor_id = ?';
    $params[] = $profFilter;
}
$sql .= ' ORDER BY u.full_name, p.academic_year DESC, c.name';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

$pageTitle = 'Affectations';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Affectations pédagogiques</h1>
<p class="text-slate-500 mb-6">Un professeur ne voit et ne note que les cours qui lui sont affectés ici. L'UE et les crédits du cours sont fixés à cette étape.</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
    <h2 class="font-semibold text-slate-800 mb-4">Affecter un professeur à un cours</h2>
    <form method="post" action="<?= BASE_URL ?>/admin/assignment_create.php" class="grid md:grid-cols-3 gap-3 items-end">
        <input type="hidden" name="stage" value="select">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Professeur</label>
            <select name="professor_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">-- Choisir --</option>
                <?php foreach ($professors as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $profFilter === (int) $p['id'] ? 'selected' : '' ?>><?= h($p['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-1">
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Cours</label>
            <select name="course_id" required class="w-full border border-slate-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                <option value="">-- Choisir --</option>
                <?php foreach ($grouped as $label => $list): ?>
                    <optgroup label="<?= h($label) ?>">
                        <?php foreach ($list as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= h($c['promotion_name'] . ($c['group_name'] ? ' ' . $c['group_name'] : '') . ' · ' . $c['academic_year'] . ' ' . $c['semester'] . ' · ' . $c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Continuer →</button>
    </form>
</div>

<div class="overflow-x-auto border-t border-slate-200">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                <th class="py-3 px-4">Professeur</th>
                <th class="py-3 px-4">Cours</th>
                <th class="py-3 px-4">Promotion</th>
                <th class="py-3 px-4">Département / Filière</th>
                <th class="py-3 px-4">UE</th>
                <th class="py-3 px-4">Crédits</th>
                <th class="py-3 px-4 text-right">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
            <tr class="border-t border-slate-100">
                <td class="py-3 px-4 font-medium"><?= h($a['professor_name']) ?></td>
                <td class="py-3 px-4"><?= h($a['course_name']) ?> <span class="text-slate-400">(<?= h($a['semester']) ?>)</span></td>
                <td class="py-3 px-4"><?= h($a['promotion_name'] . ($a['group_name'] ? ' — ' . $a['group_name'] : '')) ?> <span class="text-slate-400"><?= h($a['academic_year']) ?></span></td>
                <td class="py-3 px-4"><?= h($a['department_name'] . ' / ' . ($a['filiere_name'] ?? '—')) ?></td>
                <td class="py-3 px-4"><?= h($a['ue_name'] ?? '—') ?></td>
                <td class="py-3 px-4"><?= h((string) $a['credits']) ?></td>
                <td class="py-3 px-4 text-right">
                    <a href="<?= BASE_URL ?>/admin/assignment_delete.php?id=<?= $a['id'] ?>"
                       onclick="return confirm('Retirer cette affectation ? Les notes déjà saisies sont conservées.');"
                       class="text-red-600 hover:text-red-700 font-medium">Retirer</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($assignments)): ?>
            <tr><td colspan="7" class="py-6 text-center text-slate-400">Aucune affectation.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
