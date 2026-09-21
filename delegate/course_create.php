<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);

if (!$promotion) {
    setFlash('error', 'Aucune promotion assignée. Contactez votre Chef de Département.');
    redirect('/delegate/courses.php');
}

$error = null;
$name = '';
$teacherName = '';
$semester = $promotion['semester'];
$plannedHours = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $teacherName  = trim($_POST['teacher_name'] ?? '');
    $semester     = $_POST['semester'] ?? 'S1';
    $plannedHours = trim($_POST['planned_hours'] ?? '');

    if ($name === '' || $teacherName === '' || $plannedHours === '' || !is_numeric($plannedHours) || (float) $plannedHours <= 0) {
        $error = 'Tous les champs sont obligatoires. Le volume horaire doit être un nombre positif.';
    } else {
        // Règle de gestion : pas de doublon incohérent (même cours / même promotion / même semestre)
        $chk = getDB()->prepare('SELECT COUNT(*) FROM courses WHERE promotion_id = ? AND name = ? AND semester = ?');
        $chk->execute([$promotion['id'], $name, $semester]);
        if ($chk->fetchColumn() > 0) {
            $error = 'Ce cours existe déjà pour cette promotion et ce semestre.';
        } else {
            $ins = getDB()->prepare(
                'INSERT INTO courses (promotion_id, name, teacher_name, semester, planned_hours, status)
                 VALUES (?, ?, ?, ?, ?, "active")'
            );
            $ins->execute([$promotion['id'], $name, $teacherName, $semester, $plannedHours]);
            logAudit('course_create', "Cours créé : $name");
            setFlash('success', 'Cours créé avec succès.');
            redirect('/delegate/courses.php');
        }
    }
}

$pageTitle = 'Nouveau cours';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Nouveau cours</h1>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom du cours</label>
            <input type="text" name="name" required value="<?= h($name) ?>" placeholder="Ex : Sécurité Web"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Enseignant</label>
            <input type="text" name="teacher_name" required value="<?= h($teacherName) ?>" placeholder="Ex : Prof. Jean"
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
        <p class="text-xs text-slate-500">Promotion : <?= h($promotion['name']) ?><?= $promotion['group_name'] ? ' — ' . h($promotion['group_name']) : '' ?></p>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer le cours
            </button>
            <a href="<?= BASE_URL ?>/delegate/courses.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
