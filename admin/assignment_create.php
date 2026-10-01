<?php
/**
 * admin/assignment_create.php
 * Affectation d'un professeur à un cours, en 2 étapes :
 *   1. select  : professeur + cours choisis (depuis assignments.php)
 *   2. confirm : UE et crédits du cours fixés dans la foulée, puis
 *      l'affectation est enregistrée. Les crédits déterminent les
 *      points pondérés : les notes déjà saisies sont recalculées.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/assignments.php');
}

$db = getDB();
$stage = $_POST['stage'] ?? 'select';
$professorId = (int) ($_POST['professor_id'] ?? 0);
$courseId    = (int) ($_POST['course_id'] ?? 0);

$stmt = $db->prepare(
    "SELECT c.*, p.name AS promotion_name, p.group_name, p.academic_year, p.id AS promotion_id,
            d.name AS department_name, f.name AS filiere_name
     FROM courses c JOIN promotions p ON p.id = c.promotion_id
     JOIN departments d ON d.id = p.department_id
     LEFT JOIN filieres f ON f.id = p.filiere_id
     WHERE c.id = ?"
);
$stmt->execute([$courseId]);
$course = $stmt->fetch();

$chkProf = $db->prepare("SELECT full_name FROM users WHERE id = ? AND role = 'professor'");
$chkProf->execute([$professorId]);
$professorName = $chkProf->fetchColumn();

if (!$course || !$professorName) {
    setFlash('error', 'Professeur ou cours invalide.');
    redirect('/admin/assignments.php');
}

if ($stage === 'confirm') {
    $credits = (float) str_replace(',', '.', $_POST['credits'] ?? '0');
    $ueId = (int) ($_POST['ue_id'] ?? 0);
    $newUeName = trim($_POST['new_ue_name'] ?? '');

    if ($credits < 0 || $credits > 30) {
        setFlash('error', 'Crédits invalides (entre 0 et 30).');
        redirect('/admin/assignments.php');
    }

    // Nouvelle UE créée à la volée, si demandé, plutôt que de choisir une existante.
    if ($newUeName !== '') {
        try {
            $db->prepare('INSERT INTO ues (promotion_id, name) VALUES (?, ?)')->execute([$course['promotion_id'], $newUeName]);
            $ueId = (int) $db->lastInsertId();
        } catch (PDOException $e) {
            $chk = $db->prepare('SELECT id FROM ues WHERE promotion_id = ? AND name = ?');
            $chk->execute([$course['promotion_id'], $newUeName]);
            $ueId = (int) $chk->fetchColumn();
        }
    }

    $db->prepare('UPDATE courses SET credits = ?, ue_id = ? WHERE id = ?')
       ->execute([$credits, $ueId > 0 ? $ueId : null, $courseId]);

    // Les points pondérés dépendent des crédits : recalcul des notes déjà saisies.
    $g = $db->prepare('SELECT id, score FROM student_grades WHERE course_id = ?');
    $g->execute([$courseId]);
    foreach ($g->fetchAll() as $row) {
        refreshGradeDerivedFields((int) $row['id'], (float) $row['score']);
    }

    $ins = $db->prepare(
        "INSERT INTO teaching_assignments (professor_id, course_id, status) VALUES (?, ?, 'active')
         ON DUPLICATE KEY UPDATE status = 'active'"
    );
    $ins->execute([$professorId, $courseId]);

    logAudit('assignment_create', "Professeur #$professorId affecté au cours #$courseId (UE #$ueId, crédits $credits)");
    setFlash('success', 'Affectation, UE et crédits enregistrés.');
    redirect('/admin/assignments.php?professor_id=' . $professorId);
}

// Étape 1 -> formulaire de l'étape 2 (UE + crédits de CE cours précis)
$ues = $db->prepare('SELECT * FROM ues WHERE promotion_id = ? ORDER BY name');
$ues->execute([$course['promotion_id']]);
$ues = $ues->fetchAll();

$pageTitle = "UE & crédits du cours";
require_once ROOT_PATH . '/includes/header.php';
$input = 'w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Finaliser l'affectation</h1>
<p class="text-slate-500 mb-6">
    <strong><?= h($professorName) ?></strong> →
    <strong><?= h($course['name']) ?></strong>
    (<?= h($course['department_name'] . ' / ' . ($course['filiere_name'] ?? '—') . ' · ' . $course['promotion_name'] . ($course['group_name'] ? ' — ' . $course['group_name'] : '') . ' · ' . $course['academic_year'] . ' ' . $course['semester']) ?>)
</p>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <p class="text-xs text-slate-500 mb-4">Ce cours devient l'ECUE d'une UE, avec un nombre de crédits qui servira au calcul du GPA et des points pondérés.</p>
    <form method="post" class="space-y-4">
        <input type="hidden" name="stage" value="confirm">
        <input type="hidden" name="professor_id" value="<?= $professorId ?>">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">

        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">UE existante de cette promotion</label>
            <select name="ue_id" class="<?= $input ?>">
                <option value="0">— aucune —</option>
                <?php foreach ($ues as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= (int) $course['ue_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= h($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Ou créer une nouvelle UE</label>
            <input type="text" name="new_ue_name" placeholder="Ex : UE Réseaux et Systèmes" class="<?= $input ?>">
            <p class="text-xs text-slate-400 mt-1">Si rempli, prend le pas sur l'UE existante sélectionnée.</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Crédits du cours</label>
            <input type="number" step="0.5" min="0" max="30" name="credits" required value="<?= h((string) $course['credits']) ?>" class="<?= $input ?>">
        </div>

        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">Valider l'affectation</button>
            <a href="<?= BASE_URL ?>/admin/assignments.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
