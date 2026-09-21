<?php
/**
 * head/student_matricule_edit.php
 * Le Chef de Département ne gère pas les étudiants au quotidien, mais
 * peut corriger un matricule sur demande — seul champ verrouillé pour
 * l'étudiant et le Délégué.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = getDB()->prepare(
    "SELECT s.* FROM students s
     JOIN promotions p ON p.id = s.promotion_id
     WHERE s.id = ? AND p.department_id = ?"
);
$stmt->execute([$id, $deptId]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Étudiant introuvable.');
    redirect('/head/students.php');
}

$error = null;
$matricule = $student['matricule'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricule = trim($_POST['matricule'] ?? '');

    if ($matricule === '') {
        $error = 'Le matricule est obligatoire.';
    } elseif (isMatriculeTaken($matricule, $id)) {
        $error = 'Ce matricule est déjà utilisé par un autre étudiant.';
    } else {
        $old = $student['matricule'];
        getDB()->prepare('UPDATE students SET matricule = ? WHERE id = ?')->execute([$matricule, $id]);
        logAudit('student_matricule_edit', "Matricule corrigé pour l'étudiant #$id : « $old » -> « $matricule »");
        setFlash('success', 'Matricule mis à jour.');
        redirect('/head/students.php');
    }
}

$pageTitle = 'Corriger le matricule';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Corriger le matricule</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <p class="mb-4 text-slate-600 text-sm">
        Étudiant : <strong><?= h($student['full_name']) ?></strong>
    </p>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Matricule</label>
            <input type="text" name="matricule" required value="<?= h($matricule) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/head/students.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
