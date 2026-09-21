<?php
/**
 * includes/sidebar.php
 * Menu latéral, dont le contenu dépend du rôle de l'utilisateur connecté.
 */
$user = currentUser();
$role = $user['role'] ?? null;

function navLink(string $url, string $label, string $iconName = 'dashboard'): string
{
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $active = (strpos($current, $url) !== false);
    $classes = $active
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'text-slate-300 hover:bg-slate-800 hover:text-white';
    return '<a href="' . BASE_URL . $url . '" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition ' . $classes . '">'
        . icon($iconName, 'w-5 h-5 shrink-0') . '<span>' . h($label) . '</span></a>';
}
?>
<aside id="sidebar" class="w-64 bg-slate-900 text-white min-h-[calc(100vh-56px)] p-4 hidden md:block">
    <nav class="space-y-1">
    <?php if ($role === ROLE_ADMIN): ?>
        <p class="px-3 pt-1 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Administration</p>
        <?= navLink('/admin/dashboard.php', 'Tableau de bord', 'dashboard') ?>
        <?= navLink('/admin/departments.php', 'Départements', 'building') ?>
        <?= navLink('/admin/filieres.php', 'Filières', 'academic-cap') ?>
        <?= navLink('/admin/heads.php', 'Chefs de Département', 'briefcase') ?>
        <p class="px-3 pt-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Toute l'université</p>
        <?= navLink('/admin/courses.php', 'Cours', 'book-open') ?>
        <?= navLink('/admin/students.php', 'Étudiants', 'users') ?>
        <?= navLink('/admin/analytics.php', 'Statistiques', 'chart') ?>
        <p class="px-3 pt-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Configuration</p>
        <?= navLink('/admin/settings.php', "Paramètres de l'université", 'cog') ?>
        <?= navLink('/admin/data_quality.php', 'Qualité des données', 'shield') ?>
        <?= navLink('/admin/audit_log.php', "Journal d'audit", 'document') ?>
    <?php elseif ($role === ROLE_HEAD): ?>
        <p class="px-3 pt-1 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Département</p>
        <?= navLink('/head/dashboard.php', 'Tableau de bord', 'dashboard') ?>
        <?= navLink('/head/delegates.php', 'Délégués', 'users') ?>
        <?= navLink('/head/promotions.php', 'Promotions / Groupes', 'academic-cap') ?>
        <?= navLink('/head/students.php', 'Étudiants', 'users') ?>
        <?= navLink('/head/courses.php', 'Cours', 'book-open') ?>
        <?= navLink('/head/sessions.php', 'Séances', 'calendar') ?>
        <?= navLink('/head/reports.php', 'Statistiques & PDF', 'document') ?>
    <?php elseif ($role === ROLE_DELEGATE): ?>
        <p class="px-3 pt-1 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Ma promotion</p>
        <?= navLink('/delegate/dashboard.php', 'Tableau de bord', 'dashboard') ?>
        <?= navLink('/delegate/setup.php', 'Ma promotion', 'cog') ?>
        <?= navLink('/delegate/students.php', 'Étudiants', 'users') ?>
        <?= navLink('/delegate/courses.php', 'Mes cours', 'book-open') ?>
        <?= navLink('/delegate/journal.php', 'Journal', 'book') ?>
    <?php elseif ($role === ROLE_STUDENT): ?>
        <p class="px-3 pt-1 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Mon espace</p>
        <?= navLink('/student/dashboard.php', 'Tableau de bord', 'dashboard') ?>
        <?= navLink('/student/schedule.php', 'Mon horaire', 'calendar') ?>
        <?= navLink('/student/courses.php', 'Mes cours', 'book-open') ?>
        <?= navLink('/student/attendance.php', 'Mes présences', 'check-circle') ?>
        <?= navLink('/student/notifications.php', 'Notifications', 'bell') ?>
        <?= navLink('/student/profile.php', 'Mon profil', 'cog') ?>
    <?php endif; ?>
    </nav>
</aside>
