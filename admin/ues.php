<?php
/**
 * admin/ues.php
 * Gestion des Unités d'Enseignement (UE) et des crédits de chaque cours.
 * Un cours (ECUE) peut être rattaché à une UE de sa promotion ; les
 * crédits servent de poids dans tous les calculs (GPA, moyennes).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$db = getDB();
$promotionId = (int) ($_GET['promotion_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $promotionId = (int) ($_POST['promotion_id'] ?? 0);

    if ($action === 'create_ue') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        if ($name === '' || $promotionId <= 0) {
            setFlash('error', "Le nom de l'UE est obligatoire.");
        } else {
            try {
                $db->prepare('INSERT INTO ues (promotion_id, name, code) VALUES (?, ?, ?)')
                   ->execute([$promotionId, $name, $code ?: null]);
                logAudit('ue_create', "UE « $name » créée (promotion #$promotionId)");
                setFlash('success', 'UE créée.');
            } catch (PDOException $e) {
                setFlash('error', 'Une UE portant ce nom existe déjà pour cette promotion.');
            }
        }
    } elseif ($action === 'delete_ue') {
        $ueId = (int) ($_POST['ue_id'] ?? 0);
        $db->prepare('DELETE FROM ues WHERE id = ? AND promotion_id = ?')->execute([$ueId, $promotionId]);
        logAudit('ue_delete', "UE #$ueId supprimée (les cours sont conservés, simplement détachés)");
        setFlash('success', 'UE supprimée. Ses cours sont conservés, sans UE.');
    } elseif ($action === 'update_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $credits  = (float) str_replace(',', '.', $_POST['credits'] ?? '0');
        $ueId     = (int) ($_POST['ue_id'] ?? 0);
        if ($credits < 0 || $credits > 30) {
            setFlash('error', 'Crédits invalides (entre 0 et 30).');
        } else {
            $db->prepare('UPDATE courses SET credits = ?, ue_id = ? WHERE id = ? AND promotion_id = ?')
               ->execute([$credits, $ueId > 0 ? $ueId : null, $courseId, $promotionId]);
            // Les points pondérés dépendent des crédits : on recalcule les notes du cours.
            $g = $db->prepare('SELECT id, score FROM student_grades WHERE course_id = ?');
            $g->execute([$courseId]);
            foreach ($g->fetchAll() as $row) {
                refreshGradeDerivedFields((int) $row['id'], (float) $row['score']);
            }
            logAudit('course_credits', "Cours #$courseId : crédits = $credits, UE #$ueId");
            setFlash('success', 'Cours mis à jour (notes recalculées).');
        }
    }
    redirect('/admin/ues.php?promotion_id=' . $promotionId);
}

$promotions = $db->query(
    "SELECT p.id, p.name, p.group_name, p.academic_year, p.semester, d.name AS department_name
     FROM promotions p JOIN departments d ON d.id = p.department_id
     ORDER BY d.name, p.academic_year DESC, p.name"
)->fetchAll();

$ues = [];
$courses = [];
if ($promotionId > 0) {
    $stmt = $db->prepare('SELECT * FROM ues WHERE promotion_id = ? ORDER BY name');
    $stmt->execute([$promotionId]);
    $ues = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM courses WHERE promotion_id = ? ORDER BY name');
    $stmt->execute([$promotionId]);
    $courses = $stmt->fetchAll();
}

$pageTitle = 'UE & crédits';
require_once ROOT_PATH . '/includes/header.php';
$input = 'border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">UE &amp; crédits</h1>
<p class="text-slate-500 mb-6">Regroupez les cours en Unités d'Enseignement et fixez leurs crédits (poids dans le GPA).</p>

<form method="get" class="mb-6 flex items-center gap-2">
    <label class="text-sm text-slate-600">Promotion :</label>
    <select name="promotion_id" onchange="this.form.submit()" class="<?= $input ?>">
        <option value="0">-- Choisir --</option>
        <?php foreach ($promotions as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $promotionId === (int) $p['id'] ? 'selected' : '' ?>>
                <?= h($p['department_name'] . ' · ' . $p['name'] . ($p['group_name'] ? ' ' . $p['group_name'] : '') . ' · ' . $p['academic_year'] . ' ' . $p['semester']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($promotionId > 0): ?>
<div class="grid lg:grid-cols-3 gap-6">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Unités d'Enseignement</h2>
        <form method="post" class="space-y-3 mb-5">
            <input type="hidden" name="action" value="create_ue">
            <input type="hidden" name="promotion_id" value="<?= $promotionId ?>">
            <input type="text" name="name" required placeholder="Nom de l'UE" class="w-full <?= $input ?>">
            <input type="text" name="code" placeholder="Code (facultatif)" class="w-full <?= $input ?>">
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">+ Ajouter l'UE</button>
        </form>
        <ul class="divide-y divide-slate-100 text-sm">
            <?php foreach ($ues as $u): ?>
                <li class="py-2 flex items-center justify-between">
                    <span><?= h($u['name']) ?><?= $u['code'] ? ' <span class="text-slate-400">(' . h($u['code']) . ')</span>' : '' ?></span>
                    <form method="post" onsubmit="return confirm('Supprimer cette UE ? Les cours restent, sans UE.');">
                        <input type="hidden" name="action" value="delete_ue">
                        <input type="hidden" name="promotion_id" value="<?= $promotionId ?>">
                        <input type="hidden" name="ue_id" value="<?= $u['id'] ?>">
                        <button class="text-red-600 hover:text-red-700 text-xs font-medium">Supprimer</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (empty($ues)): ?><li class="py-3 text-slate-400">Aucune UE.</li><?php endif; ?>
        </ul>
    </div>

    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="py-3 px-4">Cours (ECUE)</th>
                    <th class="py-3 px-4">UE</th>
                    <th class="py-3 px-4">Crédits</th>
                    <th class="py-3 px-4"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($courses as $c): ?>
                <tr class="border-t border-slate-100">
                    <form method="post">
                        <input type="hidden" name="action" value="update_course">
                        <input type="hidden" name="promotion_id" value="<?= $promotionId ?>">
                        <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                        <td class="py-2.5 px-4 font-medium"><?= h($c['name']) ?></td>
                        <td class="py-2.5 px-4">
                            <select name="ue_id" class="<?= $input ?>">
                                <option value="0">— aucune —</option>
                                <?php foreach ($ues as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= (int) $c['ue_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="py-2.5 px-4"><input type="number" step="0.5" min="0" max="30" name="credits" value="<?= h((string) $c['credits']) ?>" class="w-20 <?= $input ?>"></td>
                        <td class="py-2.5 px-4 text-right"><button class="text-indigo-600 hover:text-indigo-700 font-medium">Enregistrer</button></td>
                    </form>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($courses)): ?>
                <tr><td colspan="4" class="py-6 text-center text-slate-400">Aucun cours dans cette promotion.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
