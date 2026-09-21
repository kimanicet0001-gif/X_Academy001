/**
 * assets/js/app.js
 *
 * Comportements JS légers, sans dépendance externe :
 *   1. Menu latéral mobile (ouverture/fermeture)
 *   2. Fermeture automatique des notifications flash (.js-flash)
 *   3. Compteurs animés sur les chiffres clés des tableaux de bord
 *   4. Recherche instantanée dans les tableaux (data-table-search)
 *
 * Règle de sécurité appliquée partout dans ce fichier :
 * le contenu doit TOUJOURS être visible et correct par défaut, avec ou
 * sans JavaScript. Ce script n'a le droit d'AJOUTER que du confort visuel
 * (animation, filtrage) — il ne doit jamais être la seule condition qui
 * rend un contenu visible ou lisible.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initSidebarToggle();
        initFlashAutoDismiss();
        initAnimatedCounters();
        initTableSearch();
    });

    // =====================================================================
    // 0. Filet de sécurité contre le cache de navigation (bfcache)
    // Quand on revient sur une page via le bouton "Précédent", certains
    // navigateurs la restaurent depuis leur mémoire SANS ré-exécuter tout
    // le JS — avec l'état d'un ancien filtre de recherche encore appliqué
    // (lignes masquées par display:none). Ça donne l'impression que des
    // données ont disparu. On réinitialise systématiquement l'affichage
    // dans ce cas précis.
    // =====================================================================
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            resetAllTableFilters();
        }
    });

    function resetAllTableFilters() {
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            input.value = '';
        });
        document.querySelectorAll('table tbody tr').forEach(function (row) {
            row.style.display = '';
        });

        // Referme aussi le menu latéral mobile s'il était resté ouvert
        // en overlay dans l'état restauré depuis le cache.
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            sidebar.classList.add('hidden');
        }
    }

    // =====================================================================
    // 1. Menu latéral mobile
    // =====================================================================
    function initSidebarToggle() {
        var toggle = document.getElementById('sidebarToggle');
        var sidebar = document.getElementById('sidebar');
        if (!toggle || !sidebar) {
            return;
        }

        toggle.addEventListener('click', function (event) {
            event.stopPropagation();
            var isOpen = sidebar.classList.toggle('mobile-open');
            sidebar.classList.toggle('hidden', !isOpen);
        });

        // Ferme le menu si on clique en dehors, sur mobile
        document.addEventListener('click', function (event) {
            var clickedInside = sidebar.contains(event.target) || toggle.contains(event.target);
            if (!clickedInside && sidebar.classList.contains('mobile-open')) {
                closeSidebar();
            }
        });

        // Ferme le menu avec la touche Échap
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                closeSidebar();
            }
        });

        function closeSidebar() {
            sidebar.classList.remove('mobile-open');
            sidebar.classList.add('hidden');
        }
    }

    // =====================================================================
    // 2. Fermeture automatique des notifications flash
    // Cible uniquement .js-flash (voir includes/flash.php) — jamais une
    // carte de contenu ordinaire, même si elle porte aussi une classe "border".
    // =====================================================================
    function initFlashAutoDismiss() {
        var AUTO_DISMISS_DELAY = 4500;
        var TRANSITION_DURATION = 400;

        var flashes = document.querySelectorAll('.js-flash');
        flashes.forEach(function (el) {
            setTimeout(function () {
                dismissFlash(el, TRANSITION_DURATION);
            }, AUTO_DISMISS_DELAY);
        });

        function dismissFlash(el, duration) {
            var startHeight = el.offsetHeight;

            el.style.overflow = 'hidden';
            el.style.maxHeight = startHeight + 'px';
            el.style.transition = [
                'opacity ' + duration + 'ms ease',
                'transform ' + duration + 'ms ease',
                'max-height ' + duration + 'ms ease',
                'margin ' + duration + 'ms ease',
                'padding ' + duration + 'ms ease',
            ].join(', ');

            // On force un premier rendu avec max-height figée avant de l'animer
            // vers 0, sinon la transition CSS ne se déclenche pas correctement.
            requestAnimationFrame(function () {
                requestAnimationFrame(function () {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(-8px)';
                    el.style.maxHeight = '0px';
                    el.style.marginBottom = '0px';
                    el.style.paddingTop = '0px';
                    el.style.paddingBottom = '0px';
                });
            });

            setTimeout(function () {
                if (el.parentNode) {
                    el.remove();
                }
            }, duration + 50);
        }
    }

    // =====================================================================
    // 3. Compteurs animés sur les tableaux de bord
    // La vraie valeur est toujours dans data-target. Si l'animation ne se
    // déclenche jamais (JS bloqué, IntersectionObserver absent, onglet en
    // arrière-plan...), un filet de sécurité affiche quand même le bon
    // chiffre au bout de 2 secondes maximum.
    // =====================================================================
    function initAnimatedCounters() {
        var SAFETY_TIMEOUT = 2000;
        var ANIMATION_DURATION = 700;

        var counters = document.querySelectorAll('.js-counter');
        if (!counters.length) {
            return;
        }

        setTimeout(function () {
            counters.forEach(function (el) {
                if (el.dataset.counterDone !== 'true') {
                    setFinalValue(el);
                }
            });
        }, SAFETY_TIMEOUT);

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        return;
                    }
                    animateCounter(entry.target);
                    obs.unobserve(entry.target);
                });
            }, { threshold: 0.2 });

            counters.forEach(function (el) { observer.observe(el); });
        } else {
            counters.forEach(animateCounter);
        }

        function setFinalValue(el) {
            var target = parseInt(el.getAttribute('data-target') || '0', 10);
            el.textContent = isNaN(target) ? '0' : String(target);
            el.dataset.counterDone = 'true';
        }

        function animateCounter(el) {
            var target = parseInt(el.getAttribute('data-target') || '0', 10);
            if (isNaN(target) || target <= 0) {
                setFinalValue(el);
                return;
            }

            var start = null;

            function step(timestamp) {
                if (start === null) {
                    start = timestamp;
                }
                var progress = Math.min((timestamp - start) / ANIMATION_DURATION, 1);
                var eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic
                el.textContent = String(Math.floor(eased * target));

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    setFinalValue(el);
                }
            }

            requestAnimationFrame(step);
        }
    }

    // =====================================================================
    // 4. Recherche instantanée dans les tableaux
    // Usage : <input data-table-search="#idDuTableau">
    // =====================================================================
    function initTableSearch() {
        document.querySelectorAll('[data-table-search]').forEach(function (input) {
            var selector = input.getAttribute('data-table-search');
            var table = selector ? document.querySelector(selector) : null;
            var tbody = table ? table.querySelector('tbody') : null;
            if (!tbody) {
                return;
            }

            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));

            input.addEventListener('input', function () {
                var query = input.value.trim().toLowerCase();
                rows.forEach(function (row) {
                    var matches = query === '' || row.textContent.toLowerCase().indexOf(query) !== -1;
                    row.style.display = matches ? '' : 'none';
                });
            });
        });
    }
})();
