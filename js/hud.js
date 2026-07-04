/*
 * Nouveau HUD (option newHud) — Phase 1.
 * Onglets et chargement des flux du panneau latéral.
 *
 * Stratégie de rafraîchissement volontairement simple : chargement au
 * DOM ready + rechargement au clic d'onglet + bouton manuel. Pas de
 * polling — Log::get est coûteux côté serveur et un déplacement
 * recharge déjà la page entière (js/view.js). À revoir avec le vrai
 * chat (Phase 4).
 */

(function () {

    var FEEDS = {
        mdj: { url: 'load_chat.php', pane: '#hud-feed-mdj' },
        events: { url: 'load_events.php', pane: '#hud-feed-events' }
    };

    function activeTab() {
        return $('.hud-tab--active').data('tab') || 'mdj';
    }

    function loadFeed(name) {
        var feed = FEEDS[name];
        if (!feed) {
            return;
        }
        $.post(feed.url)
            .done(function (data) {
                $(feed.pane).html(data);
                if (name === 'events') {
                    /* Onglet ouvert : tout est vu ; sinon, compter. */
                    if (activeTab() === 'events') {
                        markEventsSeen();
                    } else {
                        updateEventsBadge();
                    }
                }
            })
            .fail(function () {
                $(feed.pane).html('<p class="hud-feed-empty">Impossible de charger le flux.</p>');
            });
    }

    /*
     * Compteur d'évènements non lus : compare les data-time du flux au
     * dernier passage sur l'onglet (localStorage). Purement frontend,
     * pas de marqueur "lu" côté serveur en Phase 1.
     */
    var SEEN_KEY = 'hudEventsSeen';

    function updateEventsBadge() {
        var lastSeen = parseInt(localStorage.getItem(SEEN_KEY), 10) || 0;
        var unread = $('#hud-feed-events .hud-feed-item').filter(function () {
            return parseInt($(this).data('time'), 10) > lastSeen;
        }).length;

        $('#hud-events-badge')
            .text(unread)
            .toggle(unread > 0 && activeTab() !== 'events');
    }

    function markEventsSeen() {
        var latest = 0;
        $('#hud-feed-events .hud-feed-item').each(function () {
            latest = Math.max(latest, parseInt($(this).data('time'), 10) || 0);
        });
        if (latest > 0) {
            localStorage.setItem(SEEN_KEY, String(latest));
        }
        $('#hud-events-badge').hide();
    }

    /*
     * Minimap : dimensionne le wrapper au ratio de l'image (data-ratio,
     * posé par MinimapView) pour tenir dans la case — équivalent
     * d'object-fit: contain, mais le marqueur en pourcentages reste
     * aligné sur l'image.
     */
    function fitMinimap() {
        var $box = $('#hud-minimap');
        var $map = $box.find('.hud-minimap-map');
        var ratio = parseFloat($map.data('ratio'));

        if (!$map.length || !ratio) {
            return;
        }

        var boxW = $box.innerWidth() - 8;
        var boxH = $box.innerHeight() - 8;
        var w = Math.min(boxW, boxH * ratio);

        $map.css({ width: w + 'px', height: (w / ratio) + 'px' });
    }

    /*
     * Panneau d'actions : observe.php injecte la carte (#ui-card,
     * .card-actions incluse) dans #ajax-data ; on déplace la
     * .card-actions vers #hud-actions. Déplacer les éléments (et non
     * les cloner) préserve les handlers directs posés par observe.js.
     *
     * Hors de #ui-card, les .action-name deviennent visibles (la règle
     * de main.css ne matche plus) : on force window.visible pour que le
     * premier clic exécute l'action au lieu de "révéler" les noms.
     *
     * Pendant le tutoriel on ne déplace rien : ses surlignages ciblent
     * #ui-card .card-actions dans la carte.
     */
    function relocateCardActions() {
        if (sessionStorage.getItem('tutorial_active') === 'true') {
            return;
        }

        var $actions = $('#ajax-data .card-actions');
        var $panel = $('#hud-actions');

        $panel.empty();

        if ($actions.length) {
            $panel.append($actions);
            window.visible = true;
        }
    }

    /*
     * Routeur de panneaux (Phase 2) : charge les sous-pages en
     * fragments dans le panneau glissant #hud-panel, sans navigation.
     *
     * Les liens gardent leurs URLs plein-page (repli sans JS,
     * clic-molette intact) ; panelUrl() les réécrit vers les endpoints
     * fragments load_*.php. Pendant le tutoriel, aucune interception :
     * ses steps ciblent les vraies pages (inventory.php…).
     */
    function tutorialActive() {
        return sessionStorage.getItem('tutorial_active') === 'true';
    }

    /* Réécrit une URL plein-page vers son fragment ; null = pas de
     * version panneau (laisser la navigation normale). */
    function panelUrl(href) {
        if (/^inventory\.php/.test(href)) {
            return href.replace(/^inventory\.php/, 'load_inventory.php');
        }
        /* Fiche perso : uniquement la vue de base (réputation et
         * récompenses restent des pages complètes). */
        if (/^infos\.php\?targetId=\d+$/.test(href)) {
            return href.replace(/^infos\.php/, 'load_infos.php');
        }
        return null;
    }

    function panelTitle(href) {
        if (href.indexOf('craft') !== -1) {
            return 'Artisanat';
        }
        if (href.indexOf('bank') !== -1) {
            return 'Banque';
        }
        if (href.indexOf('inventory') !== -1) {
            return 'Inventaire';
        }
        if (href.indexOf('infos') !== -1) {
            return 'Personnage';
        }
        return '';
    }

    function openPanel(url, title) {
        $('#hud-panel-title').text(title || '');
        $('#hud-panel-content').html('Chargement…');
        $('#hud').addClass('hud--panel-open');
        $('#hud-panel').attr('aria-hidden', 'false');

        $.get(url)
            .done(function (data) {
                $('#hud-panel-content').html(data);
            })
            .fail(function () {
                $('#hud-panel-content').html('<p class="hud-feed-empty">Impossible de charger cette page.</p>');
            });
    }

    function closePanel() {
        $('#hud').removeClass('hud--panel-open');
        $('#hud-panel').attr('aria-hidden', 'true');
    }

    $(document).ready(function () {

        loadFeed('mdj');
        loadFeed('events');

        /* Rail : Inventaire en panneau (hors tutoriel) */
        $(document).on('click', '#show-inventory', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            openPanel('load_inventory.php', 'Inventaire');
        });

        /* Chip joueur : fiche perso en panneau */
        $(document).on('click', '#hud-chip-name', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            openPanel(panelUrl($(this).attr('href')), 'Personnage');
        });

        /* Navigation interne au panneau : réécrire les liens
         * panneau-compatibles, fermer sur « Retour » (index.php),
         * laisser passer le reste (réputation, faction, wiki…). */
        $('#hud-panel-content').on('click', 'a[href]', function (e) {
            var href = $(this).attr('href');

            if (/^index\.php/.test(href)) {
                e.preventDefault();
                closePanel();
                return;
            }

            var fragment = panelUrl(href);
            if (fragment) {
                e.preventDefault();
                openPanel(fragment, panelTitle(href));
            }
        });

        $('#hud-panel-close').on('click', closePanel);

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#hud').hasClass('hud--panel-open')) {
                closePanel();
            }
        });

        /* childList sans subtree : seul le remplacement du contenu de
         * #ajax-data (view.js .html()) déclenche — ni notre propre
         * déplacement (nœud profond), ni les mises à jour de .card-text
         * après une action. */
        var ajaxData = document.getElementById('ajax-data');
        if (ajaxData) {
            new MutationObserver(relocateCardActions)
                .observe(ajaxData, { childList: true });
            relocateCardActions();
        }

        /* Fermer depuis le panneau : observe.js (handler direct) cache
         * déjà #ui-card ; on vide aussi le panneau d'actions. */
        $('#hud-actions').on('click', '.close-card', function () {
            $('#hud-actions').empty();
        });

        $('.hud-tab').on('click', function () {
            var tab = $(this).data('tab');

            $('.hud-tab').removeClass('hud-tab--active');
            $(this).addClass('hud-tab--active');

            $('#hud-feed-mdj').toggle(tab === 'mdj');
            $('#hud-feed-events').toggle(tab === 'events');

            if (tab === 'events') {
                markEventsSeen();
            }

            loadFeed(tab);
        });

        $('#hud-feed-refresh').on('click', function () {
            loadFeed(activeTab());
        });

        fitMinimap();
        $(window).on('resize', fitMinimap);
    });

})();
