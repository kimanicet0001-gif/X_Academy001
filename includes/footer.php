<?php
/**
 * includes/footer.php
 * Pied de page HTML commun. Ferme les balises ouvertes dans header.php.
 */
$user = currentUser();
?>
<?php if ($user): ?>
            </div>
        </main>
    </div>
<?php else: ?>
        </div>
    </main>
<?php endif; ?>

<footer class="text-center text-xs text-slate-400 py-6 border-t border-slate-200 mt-auto">
    &copy; <?= date('Y') ?> <?= h(APP_NAME) ?> — Journal académique numérique
</footer>

<script>window.APP_BASE_URL = <?= json_encode(BASE_URL) ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
