<?php
/**
 * includes/navbar.php
 * Barre de navigation supérieure : nom de l'app, utilisateur connecté, déconnexion.
 */
$user = currentUser();
?>
<header class="bg-indigo-950 text-white sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button id="sidebarToggle" class="md:hidden text-slate-300 hover:text-white" aria-label="Menu">
                <?= icon('menu', 'w-6 h-6') ?>
            </button>
            <a href="<?= dashboardUrlForRole($user['role']) ?>" class="flex items-center gap-2.5">
                <img src="<?= BASE_URL ?>/assets/images/logo-64.png" alt="" class="w-8 h-8 rounded-lg object-cover">
                <span class="font-display font-bold text-lg tracking-tight hidden sm:inline"><?= h(APP_NAME) ?></span>
            </a>
            <form action="<?= BASE_URL ?>/search.php" method="get" class="hidden md:block relative">
                <input type="text" name="q" placeholder="Rechercher..."
                       class="bg-white/10 focus:bg-white text-white focus:text-slate-800 placeholder-slate-400 rounded-lg pl-9 pr-3 py-1.5 text-sm w-56 focus:w-72 transition-all focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                    <?= icon('search', 'w-4 h-4') ?>
                </span>
            </form>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <div class="hidden sm:flex flex-col items-end leading-tight">
                <span class="text-white font-medium"><?= h($user['full_name']) ?></span>
                <span class="text-slate-400 text-xs"><?= h(roleLabel($user['role'])) ?></span>
            </div>
            <?php if (in_array($user['role'], [ROLE_STUDENT, ROLE_PROFESSOR], true)): ?>
                <?php
                    $isProf = $user['role'] === ROLE_PROFESSOR;
                    $unread = getUnreadNotificationCount((int) $user['id'], $isProf ? 'professor' : 'student');
                    $notifUrl = $isProf ? '/professor/notifications.php' : '/student/notifications.php';
                ?>
                <a href="<?= BASE_URL . $notifUrl ?>" title="Notifications"
                   class="relative w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                    <?= icon('bell', 'w-5 h-5') ?>
                    <?php if ($unread > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold rounded-full w-4 h-4 flex items-center justify-center">
                            <?= $unread > 9 ? '9+' : $unread ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/auth/change_password.php" title="Changer le mot de passe"
               class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                <?= icon('key', 'w-5 h-5') ?>
            </a>
            <a href="<?= BASE_URL ?>/auth/logout.php" title="Déconnexion"
               class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                <?= icon('logout', 'w-5 h-5') ?>
            </a>
        </div>
    </div>
</header>
