<?php
/**
 * includes/permissions.php
 * Constantes de rôles et fonctions de contrôle d'accès.
 * Ce fichier ne contient que des fonctions - la vérification effective
 * se fait via auth/auth.php, inclus en tête de chaque page protégée.
 */

const ROLE_ADMIN     = 'admin';
const ROLE_HEAD      = 'head';
const ROLE_DELEGATE  = 'delegate';
const ROLE_STUDENT   = 'student';
const ROLE_PROFESSOR = 'professor';

/**
 * Retourne le rôle de l'utilisateur actuellement connecté, ou null.
 */
function currentRole(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Retourne l'utilisateur actuellement connecté (tableau associatif), ou null.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Vérifie si l'utilisateur connecté possède l'un des rôles donnés.
 */
function hasRole(array $roles): bool
{
    return in_array(currentRole(), $roles, true);
}

/**
 * Libellé affichable d'un rôle.
 */
function roleLabel(string $role): string
{
    return match ($role) {
        ROLE_ADMIN    => 'Administrateur',
        ROLE_HEAD     => 'Chef de Département',
        ROLE_DELEGATE => 'Délégué',
        ROLE_STUDENT   => 'Étudiant',
        ROLE_PROFESSOR => 'Professeur',
        default        => 'Utilisateur',
    };
}

/**
 * Chemin (relatif, sans BASE_URL) du tableau de bord correspondant à un rôle.
 * À utiliser avec redirect(), qui ajoute déjà BASE_URL lui-même.
 */
function dashboardPath(string $role): string
{
    return match ($role) {
        ROLE_ADMIN    => '/admin/dashboard.php',
        ROLE_HEAD     => '/head/dashboard.php',
        ROLE_DELEGATE => '/delegate/dashboard.php',
        ROLE_STUDENT   => '/student/dashboard.php',
        ROLE_PROFESSOR => '/professor/dashboard.php',
        default        => '/auth/login.php',
    };
}

/**
 * URL complète (avec BASE_URL) du tableau de bord correspondant à un rôle.
 * À utiliser dans un attribut href="...", PAS avec redirect() (sinon
 * BASE_URL se retrouve ajouté deux fois).
 */
function dashboardUrlForRole(string $role): string
{
    return BASE_URL . dashboardPath($role);
}
