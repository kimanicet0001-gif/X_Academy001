<?php
/**
 * auth/auth.php
 * Fonctions de contrôle d'accès. À inclure (require_once) en tête de
 * chaque page protégée, juste après config/config.php.
 * Ce fichier ne produit aucun affichage.
 */

/**
 * Exige que l'utilisateur soit connecté, sinon redirige vers le login.
 */
function requireLogin(): void
{
    if (!isset($_SESSION['user'])) {
        redirect('/auth/login.php');
    }
}

/**
 * Exige que l'utilisateur soit connecté ET possède l'un des rôles donnés.
 * Vérifie aussi que le compte est toujours actif (sécurité si désactivé
 * pendant qu'une session est ouverte), et force les étapes obligatoires
 * (changement de mot de passe, complétion du profil étudiant).
 *
 * @param string[] $roles Ex: [ROLE_ADMIN] ou [ROLE_HEAD, ROLE_DELEGATE]
 */
function requireRole(array $roles): void
{
    requireLogin();

    if (!hasRole($roles)) {
        redirect('/auth/unauthorized.php');
    }

    $isStudent = currentRole() === ROLE_STUDENT;
    $table = $isStudent ? 'students' : 'users';

    // Vérification fraîche du statut du compte en base (sécurité)
    $stmt = getDB()->prepare("SELECT status FROM $table WHERE id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $status = $stmt->fetchColumn();

    if ($status !== 'active') {
        session_destroy();
        redirect('/auth/login.php');
    }

    $current = basename($_SERVER['SCRIPT_NAME']);

    // Si le mot de passe doit être changé, on bloque tout sauf la page dédiée
    if (!empty($_SESSION['user']['must_change_password']) && $current !== 'change_password.php') {
        redirect('/auth/change_password.php');
    }

    // Étudiant : profil obligatoire à compléter avant tout autre accès
    // (une fois le mot de passe déjà changé, sinon on laisse d'abord
    // passer par la page de changement de mot de passe ci-dessus).
    if ($isStudent
        && empty($_SESSION['user']['profile_completed'])
        && empty($_SESSION['user']['must_change_password'])
        && $current !== 'complete_profile.php') {
        redirect('/student/complete_profile.php');
    }
}
