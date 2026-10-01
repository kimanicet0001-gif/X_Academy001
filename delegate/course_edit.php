<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$id = (int) ($_GET['id'] ?? 0);

if (!$promotion) {
    redirect('/delegate/courses.php');
}

$stmt = getDB()->prepare('SELECT * FROM courses WHERE id = ? AND promotion_id = ?');
$stmt->execute([$id, $promotion['id']]);
$course = $stmt->fetch();

if (!$course) {
    setFlash('error', 'Cours introuvable.');
    redirect('/delegate/courses.php');
}

$error = null;
$name = $course['name'];
$teacherName = $course['teacher_name'];
$semester = $course['semester'];
$plannedHours = $course['planned_hours'];
$completed = getCompletedHours($id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $teacherName  = trim($_POST['teacher_name'] ?? '');
    $semester     = $_POST['semester'] ?? 'S1';
    $plannedHours = trim($_POST['planned_hours'] ?? '');
    $status       = isset($_POST['status']) ? 'active' : 'inactive';

    if ($name === '' || $teacherName === '' || $plannedHours === '' || !is_numeric($plannedHours) || (float) $plannedHours <= 0) {
        $error = 'Tous les champs sont obligatoires. Le volume horaire doit être un nombre positif.';
    } elseif ((float) $plannedHours < $completed) {
        $error = 'Le volume horaire prévu ne peut pas être inférieur aux heures déjà prestées (' . $completed . ' h).';
    } else {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM courses WHERE promotion_id = ? AND name = ? AND semester = ? AND id != ?');
        $chk->execute([$promotion['id'], $name, $semester, $id]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Un autre cours porte déjà ce nom pour ce semestre.';
        } else {
            $upd = getDB()->prepare('UPDATE courses SET name=?, teacher_name=?, semester=?, planned_hours=?, status=? WHERE id=?');
            $upd->execute([$name, $teacherName, $semester, $plannedHours, $status, $id]);
            logAudit('course_edit', "Cours #$id modifié");
            setFlash('success', 'Cours mis à jour.');
            redirect('/delegate/course_view.php?id=' . $id);
        }
    }
}

$pageTitle = 'Modifier le cours';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Modifier le cours</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom du cours</label>
            <input type="text" name="name" required value="<?= h($name) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Enseignant</label>
            <input type="text" name="teacher_name" required value="<?= h($teacherName) ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Semestre</label>
                <select name="semester" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                    <option value="S1" <?= $semester === 'S1' ? 'selected' : '' ?>>S1</option>
                    <option value="S2" <?= $semester === 'S2' ? 'selected' : '' ?>>S2</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Volume horaire prévu (h)</label>
                <input type="number" step="0.5" min="0.5" name="planned_hours" required value="<?= h($plannedHours) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
        </div>
        <p class="text-xs text-slate-500">Heures déjà prestées : <?= number_format($completed, 2) ?> h (non modifiable ici — provient des séances enregistrées).</p>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="status" <?= $course['status'] === 'active' ? 'checked' : '' ?>>
            Cours actif
        </label>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Enregistrer
            </button>
            <a href="<?= BASE_URL ?>/delegate/course_view.php?id=<?= $id ?>" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
