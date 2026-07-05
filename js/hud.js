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
                if (name === 'mdj') {
                    updateBubbleText();
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

        var show = unread > 0 && activeTab() !== 'events';

        $('#hud-events-badge').text(unread).toggle(show);
        /* Écho sur la bulle mobile : le badge de l'onglet est invisible
         * tant que la sheet est fermée. */
        $('#hud-bubble-badge').text(unread).toggle(show);
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
        $('#hud-bubble-badge').hide();
    }

    /*
     * Centre la vue sur le joueur : le SVG place toujours le joueur au
     * centre géométrique, il suffit de centrer le défilement du
     * conteneur (utile dès que la carte déborde — mobile surtout).
     */
    function centerMap() {
        var el = document.querySelector('#hud #game-map');
        if (el) {
            el.scrollLeft = (el.scrollWidth - el.clientWidth) / 2;
            el.scrollTop = (el.scrollHeight - el.clientHeight) / 2;
        }
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

        adaptPvFilter();
        composeSelection();
    }

    /*
     * Recompose la réponse d'observe.php en vue de sélection occupant
     * tout le bandeau (wireframe) : portrait + voile de blessures à
     * gauche, nom / statut / effets / description au centre, contenu
     * de la case et coordonnées à droite. L'habillage carte hérité
     * (.card-wrapper, cadre, fond de race) disparaît ; les nœuds sont
     * déplacés, jamais recréés. La coquille #ui-card est conservée car
     * « Fermer » (observe.js) la masque — le contenu de la case et les
     * coordonnées restent alors visibles. Doit passer APRÈS
     * relocateCardActions/adaptPvFilter (actions déjà sorties,
     * #red-filter déjà rapatrié dans .card-image).
     */
    /* La recomposition modifie les enfants directs de #ajax-data, ce
     * qui redéclenche l'observer ; sans ce drapeau, la seconde passe
     * revidait #hud-actions (panneau d'actions cassé). */
    var selfCompose = false;

    function composeSelection() {
        var $d = $('#ajax-data');
        if ($d.children('.hud-sel').length) {
            return;
        }

        var $card = $d.children('#ui-card');
        var $infos = $d.children('.case-infos');
        if (!$card.length && !$infos.length) {
            return; /* réponse brute (go.php, erreurs) : ne pas toucher */
        }

        selfCompose = true;

        var $sel = $('<div class="hud-sel"></div>');

        if ($card.length) {
            var $w = $card.find('.card-wrapper');
            var $left = $('<div class="hud-sel-left"></div>')
                .append($w.children('.card-image'));
            var $main = $('<div class="hud-sel-main"></div>').append(
                $w.children('.card-name'),
                $w.children('.card-type'),
                $w.children('.card-faction'),
                $w.children('.card-text')
            );
            $card.empty().append($left, $main);
            $sel.append($card);
        } else {
            $sel.addClass('hud-sel--nocard');
        }

        if ($infos.length) {
            $sel.append(
                $('<div class="hud-sel-tile"></div>')
                    .append('<div class="hud-sel-tile-title">Sur la case</div>')
                    .append($infos)
            );
        }

        $sel.append($d.children('#case-coords'));

        $d.prepend($sel);
    }

    /*
     * Le #red-filter hérité (PV perdus) est calé en pixels sur
     * l'ancien portrait 225px ; dans la fiche recomposée du bandeau,
     * on le convertit en voile pourcentage ancré en haut du portrait
     * — même information, indépendante de la taille du portrait.
     */
    function adaptPvFilter() {
        var $filter = $('#ajax-data #red-filter');
        if (!$filter.length) {
            return;
        }

        var match = ($filter.attr('style') || '').match(/height:\s*([\d.]+)px/);
        var lostPct = match ? Math.min(100, Math.max(0, parseFloat(match[1]) / 225 * 100)) : 0;

        $filter.removeAttr('style')
            .addClass('hud-pv-lost')
            .css('height', lostPct + '%')
            .appendTo($('#ajax-data .card-image'));
    }

    /*
     * Routeur de panneaux (Phase 2) : charge les sous-pages en
     * fragments dans les panneaux glissants (.hud-panel), sans navigation.
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
        /* Améliorations et Sorts (upgrades.php seul ou ?spells ;
         * ?caracTables reste un export plein-page). */
        if (/^upgrades\.php(\?spells)?$/.test(href)) {
            return href.replace(/^upgrades\.php/, 'load_upgrades.php');
        }
        /* Profil : la racine seulement, les sous-pages (portraits,
         * mdj, histoire, mails…) restent plein-page. */
        if (href === 'account.php') {
            return 'load_account.php';
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
        if (href.indexOf('spells') !== -1) {
            return 'Sorts & Techniques';
        }
        if (href.indexOf('upgrades') !== -1) {
            return 'Améliorations';
        }
        if (href.indexOf('account') !== -1) {
            return 'Profil';
        }
        return '';
    }

    /*
     * Panneaux : chaque URL de fragment est un panneau (Inventaire,
     * Artisanat, Banque, Caractéristiques, fiche…). UN SEUL panneau à
     * la fois — ouvrir une autre entrée remplace le contenu courant
     * (décision UX : deux panneaux côte à côte étaient pénibles).
     * L'état est persisté en sessionStorage pour survivre aux
     * rechargements (déplacement, action). La mécanique reste en
     * liste/slots pour pouvoir rouvrir la question plus tard.
     */
    var openPanels = []; /* du plus ancien au plus récent : {url, title} */

    function maxPanels() {
        return 1;
    }

    function loadPanelContent(slot, url) {
        var $content = $('#hud-panel-' + slot + ' .hud-panel-content');
        $content.html('Chargement…');
        $.get(url)
            .done(function (data) {
                $content.html(data);
            })
            .fail(function () {
                $content.html('<p class="hud-feed-empty">Impossible de charger cette page.</p>');
            });
    }

    /* Projette openPanels sur les slots DOM et persiste l'état. */
    function syncPanels() {
        $('.hud-panel').each(function () {
            var entry = openPanels[$(this).data('slot')];
            $(this).toggleClass('hud-panel--open', !!entry)
                .attr('aria-hidden', entry ? 'false' : 'true');
            if (entry) {
                $(this).find('.hud-panel-title').text(entry.title || '');
            }
        });
        $('#hud').toggleClass('hud--panel-open', openPanels.length > 0);
        sessionStorage.setItem('hudPanels', JSON.stringify(openPanels));
    }

    function reloadAllPanels() {
        openPanels.forEach(function (entry, slot) {
            loadPanelContent(slot, entry.url);
        });
    }

    function openPanel(url, title) {
        var idx = openPanels.findIndex(function (p) { return p.url === url; });

        if (idx !== -1) {
            /* Déjà ouvert : rafraîchir son contenu */
            openPanels[idx].title = title || openPanels[idx].title;
            syncPanels();
            loadPanelContent(idx, url);
            return;
        }

        /* Slot plein : le plus ancien laisse sa place */
        var shifted = false;
        while (openPanels.length >= maxPanels()) {
            openPanels.shift();
            shifted = true;
        }

        openPanels.push({ url: url, title: title || '' });
        syncPanels();

        if (shifted) {
            reloadAllPanels();
        } else {
            loadPanelContent(openPanels.length - 1, url);
        }
    }

    function closePanelAt(idx) {
        if (idx < 0 || idx >= openPanels.length) {
            return;
        }
        openPanels.splice(idx, 1);
        syncPanels();
        reloadAllPanels();
    }

    /* Deuxième clic sur la même entrée = fermeture. */
    function togglePanel(url, title) {
        var idx = openPanels.findIndex(function (p) { return p.url === url; });
        if (idx !== -1) {
            closePanelAt(idx);
        } else {
            openPanel(url, title);
        }
    }

    /*
     * ===== Mobile (<1024px) — Phase 3 =====
     *
     * Le bandeau bas devient un carrousel scroll-snap à 3 positions
     * (minimap · sélection · actions, wireframe mobile-main). Les trois
     * blocs existants sont déplacés UNE FOIS dans #hud-carousel ; en
     * desktop ce conteneur est en display:contents, donc la grille est
     * strictement inchangée — le déplacement est fait à tous les
     * viewports pour éviter toute gestion de resize.
     */
    function isMobileViewport() {
        return window.matchMedia('(max-width: 1023px)').matches;
    }

    /* Fait défiler le carrousel bas vers une position (0 minimap,
     * 1 sélection, 2 actions). */
    function scrollCarouselTo(index, smooth) {
        var el = document.getElementById('hud-carousel');
        if (el) {
            el.scrollTo({
                left: index * el.clientWidth,
                behavior: smooth ? 'smooth' : 'auto'
            });
        }
    }

    function initMobile() {

        var $carousel = $('<div id="hud-carousel"></div>').insertAfter('#hud-main');
        $carousel.append($('#hud-minimap'), $('#ajax-data'), $('#hud-actions'));

        /* Pagination : un point par position, synchronisé au scroll */
        var labels = ['Minimap', 'Sélection', 'Actions'];
        var $dots = $('#hud-dots');
        labels.forEach(function (label, i) {
            $('<button class="hud-dot" aria-label="' + label + '" data-index="' + i + '"></button>')
                .appendTo($dots);
        });

        function syncDots() {
            var el = $carousel[0];
            var index = Math.round(el.scrollLeft / Math.max(1, el.clientWidth));
            $('.hud-dot').removeClass('hud-dot--active')
                .filter('[data-index="' + index + '"]').addClass('hud-dot--active');
        }

        $carousel.on('scroll', syncDots);

        $dots.on('click', '.hud-dot', function () {
            scrollCarouselTo($(this).data('index'), true);
        });

        /* Position par défaut : sélection (milieu), sans animation */
        if (isMobileViewport()) {
            $carousel[0].scrollLeft = $carousel[0].clientWidth;
        }
        syncDots();

        /* Tiroir de navigation (hamburger) */
        $('#hud-burger').on('click', function () {
            $('#hud').toggleClass('hud--drawer-open');
        });

        /* Bulle de chat : ouvre le panneau latéral en sheet */
        $('#hud-bubble').on('click', function () {
            $('#hud').toggleClass('hud--chat-open');
        });

        /* Fermeture de la sheet chat (bouton × mobile) */
        $('#hud-side-close').on('click', function () {
            $('#hud').removeClass('hud--chat-open');
        });

        /* Fond : referme tiroir et sheet */
        $('#hud-backdrop').on('click', function () {
            $('#hud').removeClass('hud--drawer-open hud--chat-open');
        });

        /* Le tiroir se referme après un clic de navigation */
        $('#hud-rail').on('click', 'a', function () {
            $('#hud').removeClass('hud--drawer-open');
        });
    }

    /* Dernier message dans la bulle flottante */
    function updateBubbleText() {
        var latest = $('#hud-feed-mdj .hud-feed-item').first();
        var author = latest.find('strong').text();
        var text = latest.find('.hud-mdj-text').text();
        if (text) {
            $('#hud-bubble-text').text((author ? author + ' : ' : '') + text);
        }
    }

    $(document).ready(function () {

        loadFeed('mdj');
        loadFeed('events');

        /* Artisanat, Banque et Sorts, émis en fin de rail par
         * HudLayoutView, prennent leur place : possessions après
         * Inventaire, Sorts après Caractéristiques. */
        $('#show-craft, #show-bank').insertAfter('#show-inventory');
        $('#show-spells').insertAfter('#show-caracs');

        /* Rail : chaque entrée ouvre son panneau indépendant
         * (hors tutoriel — navigation normale). */
        var RAIL_PANELS = {
            'show-inventory': { url: 'load_inventory.php', title: 'Inventaire' },
            'show-craft': { url: 'load_inventory.php?craft', title: 'Artisanat' },
            'show-bank': { url: 'load_inventory.php?bank', title: 'Banque' },
            'show-spells': { url: 'load_upgrades.php?spells', title: 'Sorts & Techniques' }
        };

        $(document).on('click', '#show-inventory, #show-craft, #show-bank, #show-spells', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            var entry = RAIL_PANELS[this.id];
            togglePanel(entry.url, entry.title);
        });

        /* Rail : Profil en panneau (l'ancre de MenuView n'a pas d'id) */
        $(document).on('click', '#hud-rail a[href="account.php"]', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            togglePanel('load_account.php', 'Profil');
        });

        /* Rail : Caractéristiques en panneau (hors tutoriel).
         * On débranche le toggle flyout de MenuView et on route vers le
         * panneau ; le flyout #load-caracs est vidé pour ne jamais
         * dupliquer #mvt-counter / #action-counter dans le DOM. Pendant
         * le tutoriel, comportement flyout d'origine (ses steps
         * observent #load-caracs et y surlignent les compteurs). */
        if (!tutorialActive()) {
            $('#show-caracs').off('click');

            $(document).on('click', '#show-caracs', function (e) {
                e.preventDefault();

                if (tutorialActive()) {
                    /* Tutoriel lancé depuis cette page : retomber sur le
                     * comportement flyout via les helpers de view.js. */
                    if ($('#load-caracs').is(':visible')) {
                        window.closeCaracsPanel();
                    } else {
                        window.openCaracsPanel();
                    }
                    return;
                }

                $('#load-caracs').hide().empty();
                togglePanel('load_caracs.php', 'Caractéristiques');
            });
        }

        /* Rail : Évènements → onglet Événements du panneau latéral
         * (la page complète logs.php reste accessible via « Tout voir »). */
        $(document).on('click', '#hud-rail a[href^="logs.php"]', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            $('.hud-tab[data-tab="events"]').trigger('click');
        });

        /* Panneaux persistés : rouvrir après un rechargement de page
         * (déplacement, action…). */
        if (!tutorialActive()) {
            try {
                var saved = JSON.parse(sessionStorage.getItem('hudPanels') || '[]');
                openPanels = saved.slice(0, maxPanels());
            } catch (err) {
                openPanels = [];
            }
            if (openPanels.length) {
                syncPanels();
                reloadAllPanels();
            }
        }

        /* Chip joueur : fiche perso en panneau */
        $(document).on('click', '#hud-chip-name', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            openPanel(panelUrl($(this).attr('href')), 'Personnage');
        });

        /* Navigation interne aux panneaux : réécrire les liens
         * panneau-compatibles (ouverture dans un slot libre — ex.
         * Banque depuis l'Inventaire s'ouvre à côté), fermer sur
         * « Retour » (index.php), laisser passer le reste (réputation,
         * faction, wiki…). */
        $('.hud-panel-content').on('click', 'a[href]', function (e) {
            var href = $(this).attr('href');

            if (/^index\.php/.test(href)) {
                e.preventDefault();
                closePanelAt($(this).closest('.hud-panel').data('slot'));
                return;
            }

            var fragment = panelUrl(href);
            if (fragment) {
                e.preventDefault();
                togglePanel(fragment, panelTitle(href));
            }
        });

        $('.hud-panel-close').on('click', function () {
            closePanelAt($(this).closest('.hud-panel').data('slot'));
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && openPanels.length) {
                closePanelAt(openPanels.length - 1);
            }
        });

        /* childList sans subtree : seul le remplacement du contenu de
         * #ajax-data (view.js .html()) déclenche — ni notre propre
         * déplacement (nœud profond), ni les mises à jour de .card-text
         * après une action. */
        var ajaxData = document.getElementById('ajax-data');
        if (ajaxData) {
            new MutationObserver(function () {
                /* Mutations issues de composeSelection : ignorer. */
                if (selfCompose) {
                    selfCompose = false;
                    return;
                }
                relocateCardActions();
                /* Mobile : montrer le résultat de l'observation — le
                 * carrousel rejoint la position sélection. */
                if (isMobileViewport() && ajaxData.childNodes.length) {
                    scrollCarouselTo(1, true);
                }
            }).observe(ajaxData, { childList: true });
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
        centerMap();
        $(window).on('resize', function () {
            fitMinimap();
            centerMap();
        });

        initMobile();
    });

})();
