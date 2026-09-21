<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'], $_POST['new_name'])) {
    $courseId = (int) $_POST['course_id'];
    $newName = trim($_POST['new_name']);
    if ($newName !== '') {
        $stmt = getDB()->prepare('SELECT name FROM courses WHERE id = ?');
        $stmt->execute([$courseId]);
        $oldName = $stmt->fetchColumn();

        try {
            $upd = getDB()->prepare('UPDATE courses SET name = ? WHERE id = ?');
            $upd->execute([$newName, $courseId]);
            logAudit('course_edit', "Nom uniformisé : « $oldName » -> « $newName » (cours #$courseId)");
            setFlash('success', 'Intitulé mis à jour.');
        } catch (PDOException $e) {
            setFlash('error', "Impossible d'uniformiser : ces deux cours seraient alors des doublons exacts dans la même promotion/semestre. Une fusion manuelle des séances est nécessaire dans ce cas.");
        }
        redirect('/admin/data_quality.php');
    }
}

$variants = detectCourseNameVariants();

$pageTitle = 'Qualité des données';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Qualité des données</h1>
<p class="text-slate-500 mb-6">
    Détection automatique de cours dont l'intitulé se ressemble fortement (variantes d'orthographe),
    ce qui peut fausser les statistiques par cours.
</p>

<?php if (empty($variants)): ?>
    <div class="bg-green-50 text-green-700 border border-green-200 rounded-xl px-4 py-4 text-sm">
        Aucune variante suspecte détectée pour le moment. Les intitulés de cours sont cohérents.
    </div>
<?php else: ?>
    <div class="space-y-4">
        <?php foreach ($variants as $pair): ?>
            <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs uppercase tracking-wide text-amber-700 font-semibold">Similarité : <?= $pair['similarity'] ?>%</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach (['a', 'b'] as $key): $c = $pair[$key]; ?>
                        <div class="border border-slate-200 rounded-xl p-4">
                            <p class="font-medium text-slate-800">« <?= h($c['name']) ?> »</p>
                            <p class="text-xs text-slate-500 mt-1">
                                <?= h($c['department_name']) ?> · <?= h($c['promotion_name']) ?><?= $c['group_name'] ? ' — ' . h($c['group_name']) : '' ?>
                            </p>
                            <form method="post" class="mt-3">
                                <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
                                <input type="hidden" name="new_name" value="<?= h($pair[$key === 'a' ? 'b' : 'a']['name']) ?>">
                                <button type="submit"
                                        onclick="return confirm('Renommer « <?= h(addslashes($c['name'])) ?> » en « <?= h(addslashes($pair[$key === 'a' ? 'b' : 'a']['name'])) ?> » ?');"
                                        class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">
                                    Uniformiser avec l'autre intitulé →
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
