<?php
/**
 * includes/header.php
 * En-tête HTML commun. À inclure après config/config.php (et auth/auth.php
 * pour les pages protégées). Ouvre <html>, affiche la barre de navigation
 * et le menu latéral si l'utilisateur est connecté.
 */
$user = currentUser();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= h(APP_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">

    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/favicon.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/images/icon-192.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Palette de marque X_Academy, dérivée du logo (bleu électrique sur
        // fond bleu nuit). Remplacer l'échelle "indigo" ici recolore
        // automatiquement TOUTE l'application (boutons, liens, badges,
        // focus...) sans avoir à toucher chaque page une par une.
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        // Serif éditoriale réservée aux titres de page et aux grands
                        // chiffres (GPA, stats) — évoque le relevé de notes plutôt
                        // que le tableau de bord SaaS générique.
                        display: ['Fraunces', 'ui-serif', 'Georgia', 'serif'],
                    },
                    colors: {
                        indigo: {
                            50:  '#eef4ff',
                            100: '#dce9ff',
                            200: '#b8d2ff',
                            300: '#86b3ff',
                            400: '#4a86fa',
                            500: '#1c5fe0',
                            600: '#0d49c9',
                            700: '#0a3897',
                            800: '#0a2b6e',
                            900: '#071f4f',
                            950: '#04122e',
                        },
                        // Accent "sceau" — réservé aux éléments à valeur officielle
                        // (GPA, mention validée). Un seul accent fort, utilisé avec
                        // parcimonie, jamais comme deuxième bleu générique.
                        gold: {
                            50:  '#fdf8ec',
                            100: '#faedc9',
                            300: '#eec065',
                            500: '#c8901f',
                            600: '#a1691b',
                            700: '#7c5116',
                        },
                    },
                    borderRadius: {
                        '2xl': '0.875rem',
                    },
                    boxShadow: {
                        sm: '0 1px 2px 0 rgba(10, 30, 61, 0.04)',
                        DEFAULT: '0 1px 3px 0 rgba(10, 30, 61, 0.06), 0 1px 2px -1px rgba(10, 30, 61, 0.06)',
                    },
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col overflow-x-hidden">

<?php if ($user): ?>
    <?php require_once ROOT_PATH . '/includes/navbar.php'; ?>
    <div class="flex flex-1 min-w-0">
        <?php require_once ROOT_PATH . '/includes/sidebar.php'; ?>
        <main class="flex-1 min-w-0 p-4 sm:p-6">
            <div class="max-w-6xl mx-auto">
                <?php renderFlash(); ?>
<?php else: ?>
    <main class="flex-1 flex items-center justify-center p-6 bg-indigo-950 bg-[radial-gradient(ellipse_at_top,_theme(colors.indigo.900),_theme(colors.indigo.950)_60%)]">
        <div class="w-full max-w-md">
            <?php renderFlash(); ?>
<?php endif; ?>
