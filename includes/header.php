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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        display: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col">

<?php if ($user): ?>
    <?php require_once ROOT_PATH . '/includes/navbar.php'; ?>
    <div class="flex flex-1">
        <?php require_once ROOT_PATH . '/includes/sidebar.php'; ?>
        <main class="flex-1 p-4 sm:p-6">
            <div class="max-w-6xl mx-auto">
                <?php renderFlash(); ?>
<?php else: ?>
    <main class="flex-1 flex items-center justify-center p-6 bg-gradient-to-b from-slate-50 to-slate-100">
        <div class="w-full max-w-md">
            <?php renderFlash(); ?>
<?php endif; ?>
