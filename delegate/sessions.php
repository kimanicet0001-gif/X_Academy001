<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_DELEGATE]);

$userId = currentUser()['id'];
$promotion = getDelegatePromotion($userId);
$courseFilter = (int) ($_GET['course_id'] ?? 0);

$courses = [];
$sessions = [];

if ($promotion) {
    $stmt = getDB()->prepare('SELECT * FROM courses WHERE promotion_id = ? ORDER BY name');
    $stmt->execute([$promotion['id']]);
    $courses = $stmt->fetchAll();

    $sql = "SELECT sess.*, c.name AS course_name
            FROM sessions sess
            JOIN courses c ON c.id = sess.course_id
            WHERE c.promotion_id = ?";
    $params = [$promotion['id']];
    if ($courseFilter > 0) {
        $sql .= " AND c.id = ?";
        $params[] = $courseFilter;
    }
    $sql .= " ORDER BY sess.session_date DESC, sess.start_time DESC";

    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();
}

$pageTitle = 'Séances';
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-6 tracking-tight">Séances</h1>

<?php if (!$promotion): ?>
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3">
        Aucune promotion assignée pour le moment.
    </div>
<?php else: ?>
    <form method="get" class="mb-4 flex items-center gap-2">
        <label class="text-sm text-slate-600">Filtrer par cours :</label>
        <select name="course_id" onchange="this.form.submit()" class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
            <option value="0">Tous</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $courseFilter === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="flex justify-end mb-3">
    <input type="text" data-table-search="#delegateSessionsTable" placeholder="Rechercher une séance..."
           class="w-full sm:w-64 border border-slate-300 rounded-lg px-3.5 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
</div>

<div class="overflow-x-auto border-t border-slate-200">
        <table class="w-full text-sm" id="delegateSessionsTable">
            <thead>
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold border-b border-slate-200">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Cours</th>
                    <th class="py-3 px-4">Horaire</th>
                    <th class="py-3 px-4">Matière étudiée</th>
                    <th class="py-3 px-4">Statut</th>
                    <th class="py-3 px-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($sessions as $s): $st = sessionStatusInfo($s['status']); ?>
                <tr class="border-t border-slate-100">
                    <td class="py-2 px-4"><?= formatDateFr($s['session_date']) ?></td>
                    <td class="py-2 px-4 font-medium"><?= h($s['course_name']) ?></td>
                    <td class="py-2 px-4"><?= formatTime($s['start_time']) ?> – <?= formatTime($s['end_time']) ?> (<?= $s['duration_hours'] ?> h)</td>
                    <td class="py-2 px-4"><?= h($s['topic']) ?></td>
                    <td class="py-2 px-4"><span class="border rounded-full px-2.5 py-1 text-xs font-medium <?= $st['color'] ?>"><?= h($st['label']) ?></span></td>
                    <td class="py-2 px-4 text-right">
                        <a href="<?= BASE_URL ?>/delegate/session_view.php?id=<?= $s['id'] ?>" class="text-indigo-600 hover:text-indigo-700 font-medium">Voir</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($sessions)): ?>
                <tr><td colspan="6" class="py-6 text-center text-slate-400">Aucune séance enregistrée.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
