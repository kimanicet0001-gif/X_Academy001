<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../auth/auth.php';
requireRole([ROLE_ADMIN]);

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

$actionFilter = trim($_GET['action'] ?? '');

$sql = "SELECT * FROM audit_logs";
$params = [];
if ($actionFilter !== '') {
    $sql .= " WHERE action = ?";
    $params[] = $actionFilter;
}
$sql .= " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$totalStmt = getDB()->query('SELECT COUNT(*) FROM audit_logs');
$total = (int) $totalStmt->fetchColumn();
$totalPages = max(1, (int) ceil($total / $perPage));

$actions = getDB()->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);

$actionLabels = [
    'login' => 'Connexion', 'login_failed' => 'Échec de connexion', 'logout' => 'Déconnexion',
    'password_change' => 'Changement de mot de passe',
    'department_create' => 'Création département', 'department_edit' => 'Modification département', 'department_status' => 'Statut département',
    'head_create' => 'Création Chef', 'head_edit' => 'Modification Chef', 'head_status' => 'Statut Chef',
    'delegate_create' => 'Création Délégué', 'delegate_edit' => 'Modification Délégué',
    'delegate_password_reset' => 'Réinitialisation mot de passe Délégué', 'delegate_status' => 'Statut Délégué',
    'promotion_create' => 'Création promotion', 'promotion_edit' => 'Modification promotion', 'promotion_status' => 'Statut promotion',
    'course_create' => 'Création cours', 'course_edit' => 'Modification cours',
    'session_create' => 'Création séance', 'attendance_save' => 'Enregistrement présences',
    'pdf_view' => 'Consultation registre PDF', 'pdf_download' => 'Téléchargement registre PDF',
];

$pageTitle = "Journal d'audit";
require_once ROOT_PATH . '/includes/header.php';
?>

<h1 class="text-2xl font-display font-bold text-slate-900 mb-1 tracking-tight">Journal d'audit</h1>
<p class="text-slate-500 mb-6">Trace des actions sensibles effectuées sur la plateforme (<?= $total ?> entrées au total).</p>

<form method="get" class="mb-4 flex items-center gap-2">
    <label class="text-sm text-slate-600">Filtrer par action :</label>
    <select name="action" onchange="this.form.submit()"
            class="border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none">
        <option value="">Toutes</option>
        <?php foreach ($actions as $a): ?>
            <option value="<?= h($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= h($actionLabels[$a] ?? $a) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-left text-slate-500 text-xs uppercase tracking-wide font-semibold">
                    <th class="py-3 px-4">Date</th>
                    <th class="py-3 px-4">Utilisateur</th>
                    <th class="py-3 px-4">Rôle</th>
                    <th class="py-3 px-4">Action</th>
                    <th class="py-3 px-4">Détails</th>
                    <th class="py-3 px-4">IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr class="border-t border-slate-100">
                    <td class="py-2.5 px-4 text-slate-500 whitespace-nowrap"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                    <td class="py-2.5 px-4 font-medium"><?= h($log['username'] ?? '—') ?></td>
                    <td class="py-2.5 px-4 text-slate-500"><?= h($log['role'] ? roleLabel($log['role']) : '—') ?></td>
                    <td class="py-2.5 px-4">
                        <span class="bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-full text-xs font-medium">
                            <?= h($actionLabels[$log['action']] ?? $log['action']) ?>
                        </span>
                    </td>
                    <td class="py-2.5 px-4 text-slate-500"><?= h($log['details'] ?? '—') ?></td>
                    <td class="py-2.5 px-4 text-slate-400 text-xs"><?= h($log['ip_address'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="py-10 text-center text-slate-400 text-sm">Aucune entrée pour le moment.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-4 text-sm">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?page=<?= $p ?><?= $actionFilter ? '&action=' . urlencode($actionFilter) : '' ?>"
               class="px-3 py-1.5 rounded-lg <?= $p === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-slate-300 text-slate-600 hover:bg-slate-50' ?>">
                <?= $p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
