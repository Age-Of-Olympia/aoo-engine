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

        var show = unread > 0 && activeTab() !== 'events';

        $('#hud-events-badge').text(unread).toggle(show);
        /* Écho sur le point « Discussions » du carrousel mobile : le
         * badge de l'onglet est invisible quand un autre volet occupe
         * l'écran. */
        $('.hud-dot[data-index="0"]').toggleClass('hud-dot--badge', show);
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
        $('.hud-dot[data-index="0"]').removeClass('hud-dot--badge');
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
     * Le damier s'adapte à la HAUTEUR de son cadre (comme il s'adapte
     * à l'écran en mobile) : une perception élevée reste visible en
     * entier. Taille posée en inline — l'attribut width="100%" et le
     * max-width 650px hérités du SVG ne se battent pas en CSS seul.
     * damierZoom (boutons +/− du cadre) multiplie la taille ajustée :
     * à 1 tout tient, au-delà le cadre défile (recentré sur soi).
     * Mobile : pincement natif inchangé, pas de boutons.
     */
    var damierZoom = 1;
    var DAMIER_ZOOM_MAX = 2.5;
    var DAMIER_ZOOM_STEP = 1.25;

    /* Réglages du damier conservés par onglet (sessionStorage) : le
     * déplacement recharge la page (view.js), le joueur doit
     * retrouver son niveau de zoom et, hors déplacement, son
     * panoramique. Après un déplacement le panoramique est remis au
     * centre : la nouvelle case du joueur EST l'endroit cliqué. */
    var DAMIER_ZOOM_KEY = 'hudDamierZoom';
    var DAMIER_PAN_KEY = 'hudDamierPan';

    function restorePan() {
        var el = document.querySelector('#hud #game-map');
        var saved = null;
        try {
            saved = JSON.parse(sessionStorage.getItem(DAMIER_PAN_KEY));
        } catch (e) {
            saved = null;
        }
        if (!el || !saved) {
            return;
        }
        el.scrollLeft = (el.scrollWidth - el.clientWidth) / 2 + (saved.dx || 0);
        el.scrollTop = (el.scrollHeight - el.clientHeight) / 2 + (saved.dy || 0);
    }

    function initDamierMemory() {
        /* Panoramique : delta par rapport au centre, sauvé au fil du
         * défilement. Un recentrage (zoom, resize) sauve un delta nul,
         * cohérent avec ce que le joueur voit. */
        var panTimer = null;
        $('#hud #game-map').on('scroll', function () {
            var el = this;
            clearTimeout(panTimer);
            panTimer = setTimeout(function () {
                sessionStorage.setItem(DAMIER_PAN_KEY, JSON.stringify({
                    dx: Math.round(el.scrollLeft - (el.scrollWidth - el.clientWidth) / 2),
                    dy: Math.round(el.scrollTop - (el.scrollHeight - el.clientHeight) / 2)
                }));
            }, 150);
        });

        /* Déplacement (#go-rect, recréé à chaque panneau observe.php) :
         * après le rechargement, recentré sur la nouvelle position. */
        $(document).on('click', '#go-rect', function () {
            sessionStorage.removeItem(DAMIER_PAN_KEY);
        });
    }

    function fitDamier() {
        var svg = document.getElementById('svg-view');
        var map = document.getElementById('game-map');
        if (!svg || !map) {
            return;
        }

        if (isMobileViewport()) {
            svg.style.width = '';
            svg.style.height = '';
            svg.style.maxWidth = '';
            return;
        }

        /* Toute la hauteur du cadre (−2px de garde d'arrondi), la
         * largeur suit le ratio carré du viewBox. */
        var side = Math.max(120, Math.min(map.clientWidth, map.clientHeight) - 2) * damierZoom;
        svg.style.width = side + 'px';
        svg.style.height = side + 'px';
        svg.style.maxWidth = 'none';
    }

    /* Les ⛔ (option showBlockedTiles) sont dessinés par view.js AVANT
     * fitDamier : après chaque redimensionnement du damier (fit, zoom,
     * re-rendu), on les redessine aux nouvelles positions. */
    function redrawBlockedMarkers() {
        if (window.showBlockedTiles
            && typeof window.drawBlockedTileMarkers === 'function'
            && sessionStorage.getItem('tutorial_active') !== 'true') {
            window.drawBlockedTileMarkers(null, 'blocked-tile-marker', $('#svg-container'));
        }
    }

    function setDamierZoom(zoom) {
        damierZoom = Math.min(DAMIER_ZOOM_MAX, Math.max(1, zoom));
        sessionStorage.setItem(DAMIER_ZOOM_KEY, String(damierZoom));
        fitDamier();
        centerMap();
        redrawBlockedMarkers();
        $('#hud-zoom-out').prop('disabled', damierZoom <= 1);
        $('#hud-zoom-in').prop('disabled', damierZoom >= DAMIER_ZOOM_MAX);
    }

    /*
     * Résultat d'action en modale par-dessus le damier (appelée par
     * js/observe.js à la place de l'écriture dans .card-text quand le
     * HUD est actif) : la fiche de la cible reste intacte — le résultat
     * est un évènement, pas une description (retour testeur). Feuille
     * papier, fermeture par ×, clic sur le fond ou Échap.
     */
    window.hudShowActionResult = function (html) {
        /* Tutoriel : ses étapes observent la carte héritée — le
         * résultat y reste écrit comme avant, pas de modale. */
        if (sessionStorage.getItem('tutorial_active') === 'true') {
            $('.card-text').html('').addClass('action-text')
                .append($('<div></div>').html(html));
            return;
        }

        var $modal = $('#hud-action-modal');

        if (!$modal.length) {
            $modal = $('<div id="hud-action-modal" role="dialog" aria-label="Résultat de l\'action">'
                + '<div class="hud-action-modal-sheet">'
                + '<button class="hud-action-modal-close" title="Fermer" aria-label="Fermer">×</button>'
                + '<div class="hud-action-modal-body"></div>'
                + '</div></div>').appendTo('#hud');

            $modal.on('click', function (e) {
                if (e.target === this) {
                    $modal.hide();
                }
            });
            $modal.find('.hud-action-modal-close').on('click', function () {
                $modal.hide();
            });
            $(document).on('keydown.hudActionModal', function (e) {
                if (e.key === 'Escape' && $modal.is(':visible')) {
                    $modal.hide();
                }
            });
        }

        $modal.find('.hud-action-modal-body').html(html);
        $modal.show();
    };

    /*
     * Rafraîchit la vue après un déplacement SANS recharger la page
     * (appelé par js/view.js à la place du reload quand le HUD est
     * actif) : re-rend la page côté serveur et ne remplace que les
     * régions qui changent — damier, position, minimap, pilules de
     * caracs. Zoom conservé, défilement recentré sur la nouvelle case.
     */
    window.hudRefreshAfterMove = function () {
        $.ajax({ url: document.location.href, cache: false })
            .done(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var freshMap = doc.getElementById('game-map');
                var current = document.getElementById('game-map');
                if (!freshMap || !current) {
                    document.location.reload();
                    return;
                }

                current.innerHTML = freshMap.innerHTML;
                current.setAttribute('data-map-hash', freshMap.getAttribute('data-map-hash') || '');

                ['hud-location', 'hud-minimap'].forEach(function (id) {
                    var fresh = doc.getElementById(id);
                    var el = document.getElementById(id);
                    if (fresh && el) {
                        el.innerHTML = fresh.innerHTML;
                    }
                });

                /* Pilules du bandeau haut (PA, MVT…) : remplacées une à
                 * une, le reste du bandeau garde ses gestionnaires. */
                $('.hud-pill').each(function () {
                    var fresh = doc.getElementById(this.id);
                    if (fresh) {
                        this.innerHTML = fresh.innerHTML;
                    }
                });

                /* Sélection et actions de l'ancienne case : obsolètes. */
                $('#ajax-data').empty();
                $('#hud-actions').empty();
                window.clickedCases = [];

                /* Les gestionnaires du SVG sont morts avec l'ancien
                 * balisage : re-liaison (js/view.js). */
                if (typeof window.bindMapView === 'function') {
                    window.bindMapView();
                }

                fitMinimap();
                fitDamier();
                buildMapRulers();
                centerMap();
                redrawBlockedMarkers();
                renderIdleSelection();
            })
            .fail(function () {
                document.location.reload();
            });
    };

    /*
     * Calques d'affichage de la carte (popover boussole, façon applis
     * de carto) : chaque bascule flippe son option joueur via
     * account.php. showBlockedTiles s'applique à chaud ; les calques
     * rendus côté serveur (indices de race, grille, masques)
     * rechargent la vue.
     */
    function initMapLayers() {
        $('#hud-layers-btn').on('click', function (e) {
            e.stopPropagation();
            var pop = document.getElementById('hud-layers-pop');
            pop.hidden = !pop.hidden;
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('#hud-layers').length) {
                $('#hud-layers-pop').prop('hidden', true);
            }
        });

        $('#hud-layers-pop').on('click', '.hud-layer', function () {
            var $layer = $(this);
            var option = $layer.data('option');
            $layer.toggleClass('hud-layer--on');

            $.post('account.php', { option: option })
                .done(function () {
                    if (option === 'showBlockedTiles') {
                        window.showBlockedTiles = $layer.hasClass('hud-layer--on');
                        if (window.showBlockedTiles) {
                            redrawBlockedMarkers();
                        } else if (typeof window.clearBlockedTileMarkers === 'function') {
                            window.clearBlockedTileMarkers('blocked-tile-marker');
                        }
                        return;
                    }
                    document.location.reload();
                })
                .fail(function () {
                    /* Échec serveur : la bascule revient à son état. */
                    $layer.toggleClass('hud-layer--on');
                });
        });
    }

    function initDamierZoom() {
        $('<div id="hud-zoom">'
            + '<button id="hud-zoom-in" title="Zoomer" aria-label="Zoomer">+</button>'
            + '<button id="hud-zoom-out" title="Dézoomer" aria-label="Dézoomer" disabled>-</button>'
            + '</div>').appendTo('#hud');

        $('#hud-zoom-in').on('click', function () {
            setDamierZoom(damierZoom * DAMIER_ZOOM_STEP);
        });
        $('#hud-zoom-out').on('click', function () {
            setDamierZoom(damierZoom / DAMIER_ZOOM_STEP);
        });
    }

    /*
     * Coordonnées en bordure du damier : des <text> SVG injectés dans
     * la planche elle-même, dans une marge ajoutée au viewBox — x en
     * haut, y à gauche. Partie intégrante du damier : elles se
     * déplacent, défilent et se redimensionnent avec lui.
     */
    function buildMapRulers() {
        var svg = document.getElementById('svg-view');
        if (!svg) {
            return;
        }

        var oldG = svg.querySelector('#hud-svg-coords');
        if (oldG) {
            oldG.parentNode.removeChild(oldG);
        }
        if (svg.dataset.origViewbox) {
            svg.setAttribute('viewBox', svg.dataset.origViewbox);
        }

        var cols = {};
        var rows = {};
        var xs = [];
        $(svg).find('.case').each(function () {
            var c = (this.getAttribute('data-coords') || '').split(',');
            var x = parseFloat(this.getAttribute('x'));
            var y = parseFloat(this.getAttribute('y'));
            if (c.length !== 2 || isNaN(x) || isNaN(y)) {
                return;
            }
            cols[c[0]] = x;
            rows[c[1]] = y;
            xs.push(x);
        });
        if (!xs.length) {
            return;
        }

        /* Taille de tuile : plus petit écart entre deux colonnes */
        var tile = 50;
        xs.sort(function (a, b) { return a - b; });
        for (var i = 1; i < xs.length; i++) {
            if (xs[i] - xs[i - 1] > 0) {
                tile = xs[i] - xs[i - 1];
                break;
            }
        }

        /* Bordure graduée sur les QUATRE côtés : bandes sombres dans
         * la marge du viewBox, numéros parchemin par-dessus. */
        var G = 16;
        var vb = svg.dataset.origViewbox || svg.getAttribute('viewBox') || '0 0 650 650';
        svg.dataset.origViewbox = vb;
        var p = vb.split(/[ ,]+/).map(Number);
        svg.setAttribute('viewBox',
            (p[0] - G) + ' ' + (p[1] - G) + ' ' + (p[2] + 2 * G) + ' ' + (p[3] + 2 * G));

        var NS = 'http://www.w3.org/2000/svg';
        var g = document.createElementNS(NS, 'g');
        g.setAttribute('id', 'hud-svg-coords');

        function band(bx, by, bw, bh) {
            var r = document.createElementNS(NS, 'rect');
            r.setAttribute('x', bx);
            r.setAttribute('y', by);
            r.setAttribute('width', bw);
            r.setAttribute('height', bh);
            r.setAttribute('class', 'hud-svg-coord-band');
            g.appendChild(r);
        }

        band(p[0] - G, p[1] - G, p[2] + 2 * G, G);          /* haut  */
        band(p[0] - G, p[1] + p[3], p[2] + 2 * G, G);       /* bas   */
        band(p[0] - G, p[1], G, p[3]);                      /* gauche */
        band(p[0] + p[2], p[1], G, p[3]);                   /* droite */

        function coordLabel(tx, ty, str) {
            var t = document.createElementNS(NS, 'text');
            t.setAttribute('x', tx);
            t.setAttribute('y', ty);
            t.setAttribute('text-anchor', 'middle');
            t.setAttribute('class', 'hud-svg-coord');
            t.textContent = str;
            g.appendChild(t);
        }

        Object.keys(cols).forEach(function (cx) {
            var mid = cols[cx] + tile / 2;
            coordLabel(mid, p[1] - G / 2 + 4.5, cx);                 /* haut */
            coordLabel(mid, p[1] + p[3] + G / 2 + 4.5, cx);          /* bas  */
        });
        Object.keys(rows).forEach(function (cy) {
            var mid = rows[cy] + tile / 2 + 4.5;
            coordLabel(p[0] - G / 2, mid, cy);                       /* gauche */
            coordLabel(p[0] + p[2] + G / 2, mid, cy);                /* droite */
        });

        svg.appendChild(g);
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
            /* Rappel de la cible en tête du volet (mobile : le volet
             * Actions est seul à l'écran, sans ce rappel on ne sait
             * plus sur qui on agit — masqué en desktop par le CSS). */
            var targetName = $('#ajax-data .card-name a').first().text().trim();
            if (targetName) {
                $('<div class="hud-actions-target"></div>')
                    .text(targetName)
                    .appendTo($panel);
            }

            $panel.append($actions);
            /* Icônes seules dans la grille : le nom passe en title
             * (survol desktop, appui long mobile via contextmenu). */
            $actions.find('.action').each(function () {
                var name = $(this).find('.action-name').text().trim();
                if (name) {
                    this.title = name;
                }
            });
            /* Clic direct : on saute l'étape « révéler les noms »
             * d'observe.js (les noms sont déjà portés par les title). */
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

    /* Zone de sélection au repos : sans case sélectionnée, le bandeau
     * présente le lieu (nom du plan — sa description et d'autres
     * données viendront s'y ajouter). view.js réécrit #ajax-data en
     * entier à la première observation, la carte au repos disparaît
     * d'elle-même. */
    function renderIdleSelection() {
        var $d = $('#ajax-data');
        if ($d.children().length || $d.text().trim()) {
            return;
        }

        var name = ($('#hud-location').text().split('—')[0] || '').trim();
        if (!name) {
            return; /* le message :empty::before du CSS reste en place */
        }

        selfCompose = true;
        $('<div class="hud-sel-idle"></div>')
            .append($('<div class="hud-sel-idle-name"></div>').text(name))
            .append('<div class="hud-sel-idle-hint">Cliquez sur une case pour l\'observer.</div>')
            .appendTo($d);
    }

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

            /* Faction fusionnée dans la ligne de type :
             * « Nain (inactif) · ⚒ » */
            var $type = $w.children('.card-type');
            var $faction = $w.children('.card-faction');
            if ($type.length && $faction.length) {
                $type.append(' · ').append($faction);
            }

            var $main = $('<div class="hud-sel-main"></div>').append(
                $w.children('.card-name'),
                $type,
                $faction.parent().hasClass('card-type') ? $() : $faction,
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
        /* Forum : liste de sujets et fils en fragments (Missives dans
         * son panneau, navigation interne comprise) ; répondre, éditer
         * et créer un sujet restent plein-page (formulaires). */
        if (/^forum\.php\?(forum|topic)=/.test(href)) {
            return href.replace(/^forum\.php/, 'load_forum.php');
        }
        /* Carte : la vue simple en panneau ; les pages avec options
         * de couches (map.php?world / ?local) restent plein-page. */
        if (href === 'map.php') {
            return 'load_map.php';
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
        if (href.indexOf('forum') !== -1 || href.indexOf('topic') !== -1) {
            return 'Missives';
        }
        if (href.indexOf('map') !== -1) {
            return 'Carte';
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

        /* Libellés du tiroir : certains boutons du menu hérité sont
         * icône seule (le nom vit dans le title du lien) — sans copie,
         * le tiroir mélangeait entrées nommées et pictogrammes muets.
         * Le texte ajouté reste invisible en rail desktop (font-size:0). */
        $('#hud-rail #menu > a[title]').each(function () {
            var $btn = $(this).children('button').first();
            if ($btn.length && !$btn.text().trim()) {
                $btn.append(document.createTextNode(' ' + this.title));
            }
        });

        /* Volets : discussions (panneau latéral entier : onglets
         * Général/Événements + message du jour), sélection, actions.
         * La minimap quitte le carrousel mobile (retour testeur : la
         * carte reste accessible via l'entrée Carte du tiroir) et la
         * bulle de chat flottante disparaît — c'est le volet 0.
         * En desktop #hud-carousel est en display:contents : chaque
         * bloc garde sa cellule de grille, rien ne change. */
        var $carousel = $('<div id="hud-carousel"></div>').insertAfter('#hud-main');
        $carousel.append($('#hud-side'), $('#ajax-data'), $('#hud-actions'));

        /* Pagination : un point par position, synchronisé au scroll */
        var labels = ['Discussions', 'Sélection', 'Actions'];
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

        /* Fond : referme le tiroir */
        $('#hud-backdrop').on('click', function () {
            $('#hud').removeClass('hud--drawer-open');
        });

        /* Le tiroir se referme après un clic de navigation */
        $('#hud-rail').on('click', 'a', function () {
            $('#hud').removeClass('hud--drawer-open');
        });
    }

    $(document).ready(function () {

        loadFeed('mdj');
        loadFeed('events');

        /* Artisanat, Banque et Sorts, émis en fin de rail par
         * HudLayoutView, prennent leur place : possessions après
         * Inventaire, Sorts après Caractéristiques. */
        $('#show-craft, #show-bank').insertAfter('#show-inventory');
        $('#show-spells').insertAfter('#show-caracs');

        /* « Vue » (retour au damier) n'a plus de sens dans le HUD :
         * on est toujours sur le damier, les sous-pages sont des
         * panneaux. */
        $('#show-damier').remove();

        /* Séparateurs de groupes du rail : le personnage (caracs,
         * sorts, possessions) / le monde (évènements, carte,
         * missives) / le compte. Posés après le repositionnement. */
        var $menu = $('#hud-rail #menu');
        $('<span class="hud-rail-sep"></span>').insertAfter('#show-bank');
        $('<span class="hud-rail-sep"></span>')
            .insertAfter($menu.children('a[href="forum.php?forum=Missives"]'));

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

        /* Rail : Missives et Carte en panneaux — dernières entrées qui
         * naviguaient encore vers des pages complètes (retour testeur). */
        $(document).on('click', '#hud-rail a[href="forum.php?forum=Missives"]', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            togglePanel('load_forum.php?forum=Missives', 'Missives');
        });

        $(document).on('click', '#hud-rail a[href="map.php"]', function (e) {
            if (tutorialActive()) {
                return;
            }
            e.preventDefault();
            togglePanel('load_map.php', 'Carte');
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
                renderIdleSelection();
                /* Mobile : montrer le résultat de l'observation — le
                 * carrousel rejoint la position sélection. */
                if (isMobileViewport() && ajaxData.childNodes.length) {
                    scrollCarouselTo(1, true);
                }
            }).observe(ajaxData, { childList: true });
            relocateCardActions();
            renderIdleSelection();
        }

        /* Fermer depuis le panneau : observe.js (handler direct) cache
         * déjà #ui-card ; on vide aussi le panneau d'actions. (Le
         * bouton est masqué dans la grille, gardé par prudence.) */
        $('#hud-actions').on('click', '.close-card', function () {
            $('#hud-actions').empty();
        });

        /* Confirmation par bouton : premier clic ARME le bouton (il
         * s'élargit en pleine ligne avec son nom + annuler), second
         * clic exécute. Listener en phase capture : il précède le
         * handler direct d'observe.js et permet de le court-circuiter.
         * Épingle aussi window.visible — observe.js est ré-exécuté par
         * les résultats d'action et remettait son « révélateur de
         * noms » qui élargissait tous les boutons. */
        var actionsEl = document.getElementById('hud-actions');
        if (actionsEl) {
            actionsEl.addEventListener('click', function (e) {
                window.visible = true;

                var btn = e.target.closest('.action');
                if (!btn || !actionsEl.contains(btn)) {
                    disarmActions();
                    return;
                }

                if (e.target.closest('.hud-action-cancel')) {
                    e.preventDefault();
                    e.stopPropagation();
                    disarmActions();
                    return;
                }

                if (!btn.classList.contains('hud-action--armed')) {
                    e.preventDefault();
                    e.stopPropagation();
                    disarmActions();
                    btn.classList.add('hud-action--armed');
                    $(btn).append(
                        '<span class="hud-action-cancel" title="Annuler">'
                        + '<span class="ra ra-cancel"></span></span>'
                    );
                    /* Nom dans le bandeau bas, persistant tant qu'armé :
                     * nom en Goudy, consigne en dessous — le bandeau
                     * remplit l'espace vide du panneau au lieu d'une
                     * ligne grise noyée. */
                    $('#hud-action-hint').remove();
                    $('<div id="hud-action-hint"></div>')
                        .append($('<span class="hud-action-hint-name"></span>').text(btn.title || ''))
                        .append($('<span class="hud-action-hint-tip"></span>').text('cliquez à nouveau pour confirmer'))
                        .appendTo('#hud-actions');
                    return;
                }

                /* Armé : on laisse filer le clic vers observe.js */
                disarmActions();
            }, true);
        }

        function disarmActions() {
            $('#hud-actions .hud-action--armed')
                .removeClass('hud-action--armed')
                .find('.hud-action-cancel').remove();
            $('#hud-actions .hud-action-cancel').remove();
            $('#hud-action-hint').remove();
        }

        /* Appui long mobile (contextmenu) : afficher le nom de
         * l'action en bandeau au lieu du menu contextuel. */
        var hintTimer = null;
        $('#hud-actions').on('contextmenu', '.action', function (e) {
            e.preventDefault();
            $('#hud-action-hint').remove();
            $('<div id="hud-action-hint"></div>')
                .append($('<span class="hud-action-hint-name"></span>').text(this.title || ''))
                .appendTo('#hud-actions');
            clearTimeout(hintTimer);
            hintTimer = setTimeout(function () {
                $('#hud-action-hint').remove();
            }, 1600);
        });

        $('.hud-tab').on('click', function () {
            var tab = $(this).data('tab');

            $('.hud-tab').removeClass('hud-tab--active');
            $(this).addClass('hud-tab--active');

            $('#hud-feed-mdj').toggle(tab === 'mdj');
            $('#hud-feed-events').toggle(tab === 'events');
            $('#hud-mdj-form').toggle(tab === 'mdj');

            if (tab === 'events') {
                markEventsSeen();
            }

            loadFeed(tab);
        });

        /* Saisie du message du jour façon chat : POST vers le endpoint
         * existant (account.php?mdj) puis rechargement du flux. */
        $('#hud-mdj-form').on('submit', function (e) {
            e.preventDefault();

            var text = $.trim($('#hud-mdj-input').val());
            if (!text) {
                return;
            }

            var $btn = $(this).find('button');
            $btn.prop('disabled', true);

            $.post('account.php?mdj', {
                'text': text,
                'author-id': $('#player-avatar').data('id')
            })
                .done(function (data) {
                    if (String(data).indexOf('Changement de personnage') !== -1) {
                        alert('Erreur lors de la sauvegarde du message du jour, veuillez réessayer.');
                        return;
                    }
                    /* Message posté : champ vidé ET rendu — le clavier
                     * mobile se referme (blur). */
                    $('#hud-mdj-input').val('').trigger('blur');
                    loadFeed('mdj');
                })
                .fail(function () {
                    alert('Erreur lors de la sauvegarde du message du jour.');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                });
        });

        $('#hud-feed-refresh').on('click', function () {
            loadFeed(activeTab());
        });

        fitMinimap();
        centerMap();
        initDamierZoom();
        initMapLayers();
        fitDamier();
        buildMapRulers();
        redrawBlockedMarkers();

        /* Réglages mémorisés du damier : zoom retrouvé après tout
         * rechargement, panoramique retrouvé hors déplacement. */
        initDamierMemory();
        var savedZoom = parseFloat(sessionStorage.getItem(DAMIER_ZOOM_KEY));
        if (savedZoom > 1) {
            setDamierZoom(savedZoom);
        }
        restorePan();
        $(window).on('resize', function () {
            fitMinimap();
            centerMap();
            fitDamier();
            buildMapRulers();
            redrawBlockedMarkers();
        });

        /* La carte est re-rendue après un déplacement (view.js) : la
         * taille, les règles et les ⛔ se recalent après chaque vague
         * de mutations. Les mutations qui ne concernent QUE nos ⛔
         * sont ignorées — sans ce filtre, le redraw des marqueurs
         * redéclencherait l'observer en boucle (150 ms). Observé sur
         * #game-map (pérenne) : hudRefreshAfterMove remplace tout le
         * contenu, dont le nœud #view lui-même. */
        var viewEl = document.getElementById('game-map');
        if (viewEl) {
            var rulerTimer = null;
            new MutationObserver(function (muts) {
                var relevant = muts.some(function (m) {
                    var nodes = Array.prototype.slice.call(m.addedNodes)
                        .concat(Array.prototype.slice.call(m.removedNodes));
                    return nodes.some(function (n) {
                        var marker = n.classList && n.classList.contains('blocked-tile-marker');
                        var rulers = n.id === 'hud-svg-coords';
                        return !marker && !rulers;
                    });
                });
                if (!relevant) {
                    return;
                }
                clearTimeout(rulerTimer);
                rulerTimer = setTimeout(function () {
                    fitDamier();
                    buildMapRulers();
                    redrawBlockedMarkers();
                }, 150);
            }).observe(viewEl, { childList: true, subtree: true });
        }

        initMobile();

        /* La minimap n'a sa taille définitive qu'une fois déplacée
         * dans le carrousel (mobile, initMobile) : la première mesure
         * de fitMinimap est faite trop tôt et peut valoir zéro — or
         * les images du volet sont absolues dans leur wrapper, sans
         * mesure correcte il reste en 0×0 (carte invisible). Re-mesure
         * après le déplacement, puis à chaque changement de taille du
         * bloc (barre d'adresse mobile, rotation, resize). */
        fitMinimap();
        if (window.ResizeObserver) {
            var minimapBox = document.getElementById('hud-minimap');
            if (minimapBox) {
                new ResizeObserver(function () {
                    fitMinimap();
                }).observe(minimapBox);
            }
        }
    });

})();
