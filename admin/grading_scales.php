<?php
/**
 * admin/grading_scales.php
 * Barème note -> lettre et seuil de validation d'UE (UV/UNV).
 * Valeurs par défaut à faire confirmer par la direction académique.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_scale') {
        $ids   = $_POST['id'] ?? [];
        $mins  = $_POST['min_score'] ?? [];
        $maxs  = $_POST['max_score'] ?? [];
        $grades = $_POST['grade'] ?? [];
        $valid = true;
        foreach ($ids as $i => $id) {
            $min = (float) str_replace(',', '.', $mins[$i] ?? '0');
            $max = (float) str_replace(',', '.', $maxs[$i] ?? '0');
            if ($min < 0 || $max > 100 || $min > $max || trim($grades[$i] ?? '') === '') {
                $valid = false;
                break;
            }
        }
        if (!$valid) {
            setFlash('error', 'Barème invalide : chaque tranche doit être comprise entre 0 et 100 (min ≤ max) et avoir une lettre.');
        } else {
            $upd = $db->prepare('UPDATE grading_scales SET min_score = ?, max_score = ?, grade = ? WHERE id = ?');
            foreach ($ids as $i => $id) {
                $upd->execute([
                    (float) str_replace(',', '.', $mins[$i]),
                    (float) str_replace(',', '.', $maxs[$i]),
                    trim($grades[$i]),
                    (int) $id,
                ]);
            }
            // Les lettres stockées dépendent du barème : on les recalcule.
            foreach ($db->query('SELECT id, score FROM student_grades')->fetchAll() as $row) {
                refreshGradeDerivedFields((int) $row['id'], (float) $row['score']);
            }
            logAudit('grading_scale_update', 'Barème de notation modifié (lettres recalculées)');
            setFlash('success', 'Barème enregistré ; les lettres des notes existantes ont été recalculées.');
        }
    } elseif ($action === 'save_threshold') {
        $threshold = (float) str_replace(',', '.', $_POST['threshold'] ?? '60');
        if ($threshold < 0 || $threshold > 100) {
            setFlash('error', 'Le seuil doit être compris entre 0 et 100.');
        } else {
            $db->prepare('UPDATE settings SET ue_validation_threshold = ? WHERE id = 1')->execute([$threshold]);
            logAudit('ue_threshold_update', "Seuil de validation d'UE = $threshold");
            setFlash('success', "Seuil de validation d'UE enregistré.");
        }
    }
    redirect('/admin/grading_scales.php');
}

$scales = $db->query('SELECT * FROM grading_scales ORDER BY min_score DESC')->fetchAll();
$threshold = getUeValidationThreshold();

$pageTitle = 'Barème & validation';
require_once ROOT_PATH . '/includes/header.php';
$input = 'border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Barème &amp; validation</h1>
<div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 mb-6 text-sm">
    Valeurs par défaut fournies à titre d'exemple : faites-les confirmer par la direction académique avant usage officiel.
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
        <h2 class="font-semibold text-slate-800 mb-4">Barème note → lettre</h2>
        <form method="post">
            <input type="hidden" name="action" value="save_scale">
            <table class="w-full text-sm mb-4">
                <thead><tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="pb-2">Note min</th><th class="pb-2">Note max</th><th class="pb-2">Lettre</th>
                </tr></thead>
                <tbody>
                <?php foreach ($scales as $s): ?>
                    <tr>
                        <td class="py-1 pr-2"><input type="hidden" name="id[]" value="<?= $s['id'] ?>">
                            <input type="number" step="0.01" name="min_score[]" value="<?= h((string) $s['min_score']) ?>" class="w-28 <?= $input ?>"></td>
                        <td class="py-1 pr-2"><input type="number" step="0.01" name="max_score[]" value="<?= h((string) $s['max_score']) ?>" class="w-28 <?= $input ?>"></td>
                        <td class="py-1"><input type="text" name="grade[]" value="<?= h($s['grade']) ?>" maxlength="5" class="w-20 <?= $input ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Enregistrer le barème</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 h-fit">
        <h2 class="font-semibold text-slate-800 mb-1">Validation d'une UE</h2>
        <p class="text-xs text-slate-500 mb-4">Une UE est « Validée » si sa moyenne pondérée atteint ce seuil, sinon « Non validée ».</p>
        <form method="post" class="space-y-3">
            <input type="hidden" name="action" value="save_threshold">
            <label class="block text-sm font-medium text-slate-600">Seuil (sur 100)</label>
            <input type="number" step="0.01" min="0" max="100" name="threshold" value="<?= h((string) $threshold) ?>" class="w-full <?= $input ?>">
            <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium py-2 rounded-lg transition">Enregistrer</button>
        </form>
    </div>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
