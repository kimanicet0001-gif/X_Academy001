<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_HEAD]);

$deptId = currentUser()['department_id'];

$stmt = getDB()->prepare("SELECT * FROM users WHERE department_id = ? AND role = 'delegate' AND status = 'active' ORDER BY full_name");
$stmt->execute([$deptId]);
$delegates = $stmt->fetchAll();

$filieres = getFilieres($deptId);

$error = null;
$name = '';
$groupName = '';
$academicYear = date('Y') . '-' . (date('Y') + 1);
$semester = 'S1';
$delegateId = '';
$filiereId = (int) ($_GET['filiere_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $groupName    = trim($_POST['group_name'] ?? '');
    $academicYear = trim($_POST['academic_year'] ?? '');
    $semester     = $_POST['semester'] ?? 'S1';
    $delegateId   = $_POST['delegate_id'] !== '' ? (int) $_POST['delegate_id'] : null;
    $filiereId    = $_POST['filiere_id'] !== '' ? (int) $_POST['filiere_id'] : null;

    // Vérifie que la filière choisie appartient bien au département du Chef
    if ($filiereId) {
        $chk = getDB()->prepare('SELECT COUNT(*) FROM filieres WHERE id = ? AND department_id = ?');
        $chk->execute([$filiereId, $deptId]);
        if ($chk->fetchColumn() == 0) {
            $filiereId = null;
        }
    }

    if ($name === '' || $academicYear === '' || !$filiereId) {
        $error = 'Le nom de la promotion, la filière et l\'année académique sont obligatoires.';
    } else {
        $ins = getDB()->prepare(
            'INSERT INTO promotions (department_id, filiere_id, delegate_id, name, group_name, academic_year, semester, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "active")'
        );
        $ins->execute([$deptId, $filiereId, $delegateId, $name, $groupName ?: null, $academicYear, $semester]);
        logAudit('promotion_create', "Promotion créée : $name");
        setFlash('success', 'Promotion créée avec succès.');
        redirect('/head/promotions.php');
    }
}

$pageTitle = 'Nouvelle promotion';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Nouvelle promotion / groupe</h1>

<?php if (empty($filieres)): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 max-w-lg">
        Aucune filière active n'est encore configurée pour votre département.
        Demandez à l'Administrateur d'en créer une avant de pouvoir créer une promotion.
    </div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8 max-w-lg">
    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Filière</label>
            <select name="filiere_id" required
                    class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                <option value="">-- Choisir --</option>
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $filiereId === (int) $f['id'] ? 'selected' : '' ?>><?= h($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Nom de la promotion</label>
            <input type="text" name="name" required value="<?= h($name) ?>" placeholder="Ex : L3 SSR"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Groupe (optionnel)</label>
            <input type="text" name="group_name" value="<?= h($groupName) ?>" placeholder="Ex : Groupe A"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Année académique</label>
                <input type="text" name="academic_year" required value="<?= h($academicYear) ?>"
                       class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1.5">Semestre</label>
                <select name="semester" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                    <option value="S1" <?= $semester === 'S1' ? 'selected' : '' ?>>S1</option>
                    <option value="S2" <?= $semester === 'S2' ? 'selected' : '' ?>>S2</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Délégué assigné</label>
            <select name="delegate_id" class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
                <option value="">-- Aucun pour le moment --</option>
                <?php foreach ($delegates as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= (string) $delegateId === (string) $d['id'] ? 'selected' : '' ?>>
                        <?= h($d['full_name']) ?> (<?= h($d['username']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium px-5 py-2.5 rounded-lg shadow-sm transition">
                Créer
            </button>
            <a href="<?= BASE_URL ?>/head/promotions.php" class="text-slate-500 hover:text-slate-700 self-center text-sm transition">Annuler</a>
        </div>
    </form>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
