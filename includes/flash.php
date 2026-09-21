<?php
/**
 * includes/flash.php
 * Messages de notification "flash" (succès / erreur) affichés une seule fois.
 */

/**
 * Enregistre un message flash à afficher à la prochaine page chargée.
 * @param string $type 'success' | 'error' | 'info'
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Affiche (en HTML) puis vide tous les messages flash en attente.
 */
function renderFlash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    foreach ($_SESSION['flash'] as $flash) {
        $type = $flash['type'];
        [$colors, $iconName] = match ($type) {
            'success' => ['bg-green-50 text-green-700 border-green-200', 'check-circle'],
            'error'   => ['bg-red-50 text-red-700 border-red-200', 'x-circle'],
            default   => ['bg-indigo-50 text-indigo-700 border-indigo-200', 'shield-alert'],
        };
        echo '<div class="js-flash flex items-start gap-2.5 border rounded-xl px-4 py-3 mb-4 text-sm font-medium ' . $colors . '">'
            . icon($iconName, 'w-5 h-5 shrink-0 mt-0.5')
            . '<span>' . h($flash['message']) . '</span>'
            . '</div>';
    }

    unset($_SESSION['flash']);
}
