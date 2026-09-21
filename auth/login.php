<?php
/**
 * auth/login.php
 * Formulaire et traitement de la connexion.
 */
require_once __DIR__ . '/../config/config.php';

// Si déjà connecté, on redirige directement vers le bon tableau de bord
if (isset($_SESSION['user'])) {
    redirect(dashboardPath($_SESSION['user']['role']));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = "Veuillez renseigner l'identifiant et le mot de passe.";
    } else {
        // On cherche d'abord côté personnel (Admin/Chef/Délégué), puis côté étudiants.
        $stmt = getDB()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $staffUser = $stmt->fetch();

        $stmt = getDB()->prepare('SELECT * FROM students WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $studentUser = $stmt->fetch();

        $account = $staffUser ?: $studentUser;
        $isStudent = !$staffUser && (bool) $studentUser;

        if (!$account || empty($account['password_hash']) || !password_verify($password, $account['password_hash'])) {
            logAudit('login_failed', "Tentative avec l'identifiant : $username");
            $error = "Identifiant ou mot de passe incorrect.";
        } elseif ($account['status'] !== 'active') {
            $error = "Ce compte est désactivé. Contactez votre administrateur.";
        } else {
            logAudit('login', 'Connexion réussie');

            if ($isStudent) {
                $stmt = getDB()->prepare(
                    "SELECT p.department_id FROM promotions p WHERE p.id = ?"
                );
                $stmt->execute([$account['promotion_id']]);
                $departmentId = $stmt->fetchColumn();

                $_SESSION['user'] = [
                    'id'                   => $account['id'],
                    'username'             => $account['username'],
                    'full_name'            => $account['full_name'],
                    'role'                 => ROLE_STUDENT,
                    'department_id'        => $departmentId ?: null,
                    'promotion_id'         => $account['promotion_id'],
                    'must_change_password' => (bool) $account['must_change_password'],
                    'profile_completed'    => (bool) $account['profile_completed'],
                ];
            } else {
                $_SESSION['user'] = [
                    'id'                   => $account['id'],
                    'username'             => $account['username'],
                    'full_name'            => $account['full_name'],
                    'role'                 => $account['role'],
                    'department_id'        => $account['department_id'],
                    'must_change_password' => (bool) $account['must_change_password'],
                ];
            }

            if ($account['must_change_password']) {
                redirect('/auth/change_password.php');
            }
            if ($isStudent && !$account['profile_completed']) {
                redirect('/student/complete_profile.php');
            }
            redirect(dashboardPath($_SESSION['user']['role']));
        }
    }
}

$pageTitle = 'Connexion';
require_once ROOT_PATH . '/includes/header.php';
?>

<div class="bg-white rounded-2xl shadow-xl shadow-slate-200/60 p-8">
    <div class="text-center mb-6">
        <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-600 flex items-center justify-center mb-3 shadow-lg shadow-indigo-200">
            <?= icon('academic-cap', 'w-7 h-7 text-white') ?>
        </div>
        <h1 class="text-xl font-display font-bold text-slate-900"><?= h(APP_NAME) ?></h1>
        <p class="text-slate-500 text-sm">Journal académique numérique</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 mb-4 text-sm">
            <?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Identifiant</label>
            <input type="text" name="username" required autofocus
                   value="<?= h($_POST['username'] ?? '') ?>"
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-600 mb-1.5">Mot de passe</label>
            <input type="password" name="password" required
                   class="w-full border border-slate-300 rounded-lg px-3.5 py-2.5 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:outline-none transition">
        </div>
        <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-2.5 rounded-lg shadow-sm transition">
            Se connecter
        </button>
    </form>
</div>

<?php require_once ROOT_PATH . '/includes/footer.php'; ?>
