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
        /* Nos propres actions (data-own, FeedRenderer) ne sont jamais
         * « non lues » : le badge ne signale que ce que les autres
         * nous font. */
        var unread = $('#hud-feed-events .hud-feed-item').not('[data-own]').filter(function () {
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
     * Mobile : pas de boutons — le pincement sur le plateau pilote le
     * même damierZoom (initPinchZoom), la page ne zoome pas.
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

        /* Mobile non zoomé : mise en page naturelle du SVG. Zoomé
         * (pincement, initPinchZoom) : même dimensionnement explicite
         * que le desktop, le cadre défile. */
        if (isMobileViewport() && damierZoom <= 1) {
            svg.style.width = '';
            svg.style.height = '';
            svg.style.maxWidth = '';
            return;
        }

        /* Toute la hauteur du cadre (−2px de garde d'arrondi), la
         * largeur suit le ratio carré du viewBox. En mode théâtre la
         * colonne du plateau est AUTO (dimensionnée par son contenu) :
         * la hauteur de scène est la seule référence stable. */
        var base = $('#hud').hasClass('hud--theater')
            ? map.clientHeight
            : Math.min(map.clientWidth, map.clientHeight);
        var side = Math.max(120, base - 2) * damierZoom;
        svg.style.width = side + 'px';
        svg.style.height = side + 'px';
        svg.style.maxWidth = 'none';

        fitWeatherMasks();
    }

    /*
     * Masques météo (.view-mask — brume, nuages) : calés EXACTEMENT
     * sur la planche. Leur width/height 100 % (et le max-width 650
     * hérité, inline) se résolvaient sur le conteneur — trop large en
     * vue standard, trop étroit au théâtre. La planche n'occupe pas
     * tout le SVG : la marge graduée (buildMapRulers) agrandit le
     * viewBox, le masque se réduit et se décale d'autant.
     */
    function fitWeatherMasks() {
        var svg = document.getElementById('svg-view');
        var $masks = $('#view .view-mask');
        if (!svg || !$masks.length) {
            return;
        }

        if (isMobileViewport()) {
            $masks.css({ width: '', height: '', maxWidth: '', maxHeight: '', top: '' });
            return;
        }

        var sidePx = parseFloat(svg.style.width);
        if (!sidePx) {
            return;
        }

        var vb = (svg.getAttribute('viewBox') || '0 0 650 650').split(/[ ,]+/).map(Number);
        var orig = (svg.dataset.origViewbox || svg.getAttribute('viewBox') || '0 0 650 650').split(/[ ,]+/).map(Number);
        var scale = sidePx / vb[2];

        $masks.css({
            width: (orig[2] * scale) + 'px',
            height: (orig[3] * scale) + 'px',
            maxWidth: 'none',
            maxHeight: 'none',
            top: ((orig[1] - vb[1]) * scale) + 'px'
        });
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
    window.hudShowActionResult = function (html, isFinal) {
        /* Tutoriel : ses étapes observent la carte héritée — le
         * résultat y reste écrit comme avant, pas de modale. */
        if (sessionStorage.getItem('tutorial_active') === 'true') {
            $('.card-text').html('').addClass('action-text')
                .append($('<div></div>').html(html));
            return;
        }

        /* Résultat final : l'action a coûté et produit — pilules du
         * bandeau (A, PV…), prochain tour, cible observée (PV,
         * charges d'actions) et flux d'évènements se rafraîchissent. */
        if (isFinal) {
            refreshAfterAction();
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
     * Après une action : re-rend la page côté serveur et ne remet à
     * jour que les valeurs vivantes — pilules du bandeau haut et
     * prochain tour. La cible observée est re-observée (PV, charges)
     * et le flux d'évènements rechargé.
     */
    /* Pilules du bandeau haut, remplacées une à une depuis un re-rendu
     * serveur : innerHTML ET class/style — la jauge intégrée
     * (--hud-missing) vit dans les attributs de la pilule elle-même. */
    function refreshPills(doc) {
        $('.hud-pill').each(function () {
            var fresh = doc.getElementById(this.id);
            if (fresh) {
                this.innerHTML = fresh.innerHTML;
                this.className = fresh.className;
                this.setAttribute('style', fresh.getAttribute('style') || '');
            }
        });

        /* Effets : remplacés en bloc — contrairement aux pilules
         * fixes, ils apparaissent et disparaissent. */
        var freshEffects = doc.getElementById('hud-effects');
        var effects = document.getElementById('hud-effects');
        if (freshEffects && effects) {
            effects.innerHTML = freshEffects.innerHTML;
        }
    }

    function refreshAfterAction() {
        $.ajax({ url: document.location.href, cache: false })
            .done(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');

                refreshPills(doc);
                refreshBoardSprites(doc);

                var freshTimer = doc.getElementById('next-turn-timer');
                var timer = document.getElementById('next-turn-timer');
                if (freshTimer && timer) {
                    timer.innerHTML = freshTimer.innerHTML;
                    timer.title = freshTimer.title || timer.title;
                }
            });

        refreshSelection();

        loadFeed('events');
    }

    /* Sprites du damier après une action : la bascule blessé/réparé
     * d'un bâtiment change players.avatar côté serveur — recopier les
     * href des <image> du SVG depuis le re-rendu, nœud par nœud (le
     * damier en place garde zoom et défilement). Un nœud absent du
     * re-rendu (bâtiment détruit) est retiré du damier. Les
     * apparitions (pose) passent par les redraws complets existants. */
    function refreshBoardSprites(doc) {
        var freshView = doc.getElementById('svg-view');
        var currentView = document.getElementById('svg-view');
        if (!freshView || !currentView) {
            return;
        }

        $(currentView).find('image[id]').each(function () {
            var fresh = freshView.querySelector('image[id="' + this.id + '"]');
            if (!fresh) {
                this.remove();
                return;
            }
            var freshHref = fresh.getAttribute('href') || fresh.getAttribute('xlink:href');
            var currentHref = this.getAttribute('href') || this.getAttribute('xlink:href');
            if (freshHref !== null && freshHref !== currentHref) {
                this.setAttribute('href', freshHref);
                this.setAttribute('xlink:href', freshHref);
            }
        });
    }

    /* Re-observe la sélection courante : PV, charges, message du
     * jour… — utilisée après une action et par le bouton de
     * rafraîchissement du panneau latéral. */
    function refreshSelection() {
        var selCoords = sessionStorage.getItem('hudSelCoords');
        if (selCoords) {
            $.post('observe.php', { coords: selCoords }, function (data) {
                $('#ajax-data').html(data);
            });
        }
    }

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
                refreshPills(doc);

                /* Sélection et actions de l'ancienne case : obsolètes. */
                $('#ajax-data').empty();
                $('#hud-actions').empty();
                sessionStorage.removeItem('hudSelCoords');
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

                /* Un déplacement journalise (move — et les actions qu'il
                 * démarre : creuser…) : le flux d'évènements se recharge
                 * comme après une action, sinon l'évènement n'apparaît
                 * qu'au prochain rafraîchissement manuel. */
                loadFeed('events');
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
            var willBeOn = !$layer.hasClass('hud-layer--on');

            $.post('account.php', { option: option })
                .done(function () {
                    window.hudApplyBoardOption(option, willBeOn);
                });
        });
    }

    /* Options d'affichage du plateau — liste partagée entre le popover
     * de calques et les options du panneau Profil (js/account.js). */
    var BOARD_OPTIONS = ['raceHint', 'raceHintMax', 'showBlockedTiles', 'hideGrid', 'noMask', 'hideBoardCoords', 'hideLineOfFire', 'hideBuildingsLayer'];

    /*
     * Applique côté client une option de plateau que le serveur vient
     * de basculer : synchronise le popover de calques (buildMapRulers
     * lit son état), applique à chaud ce qui peut l'être, recharge la
     * vue sinon (les panneaux persistent). Renvoie false pour une
     * option étrangère au plateau — l'appelant garde alors son
     * comportement. Exposée : le panneau Profil rafraîchit ainsi le
     * plateau exactement comme le popover.
     */
    window.hudApplyBoardOption = function (option, isOn) {
        if (BOARD_OPTIONS.indexOf(option) === -1) {
            return false;
        }

        $('.hud-layer[data-option="' + option + '"]').toggleClass('hud-layer--on', isOn);

        if (option === 'showBlockedTiles') {
            window.showBlockedTiles = isOn;
            if (isOn) {
                redrawBlockedMarkers();
            } else if (typeof window.clearBlockedTileMarkers === 'function') {
                window.clearBlockedTileMarkers('blocked-tile-marker');
            }
            return true;
        }
        /* Bordure graduée : dessinée côté client, bascule à chaud. */
        if (option === 'hideBoardCoords') {
            buildMapRulers();
            return true;
        }
        /* Ligne de tir : le tracé vient du script de la réponse
           observe — on purge le cache des cases (les réponses mémorisées
           embarquent, ou non, le script selon l'ancien état) et on
           efface le tracé courant quand on masque. */
        if (option === 'hideLineOfFire') {
            window.clickedCases = {};
            if (isOn && typeof window.clearLineOfFire === 'function') {
                window.clearLineOfFire();
            }
            return true;
        }
        document.location.reload();
        return true;
    };

    /*
     * Pincement sur le plateau (mobile/tactile) : le geste pilote
     * damierZoom — SEUL le plateau zoome, la page ne bouge pas
     * (touch-action pan-x pan-y sur #game-map + preventDefault du
     * geste à deux doigts ; un pincement HORS du plateau garde le
     * zoom de page natif, accessibilité oblige). rAF-throttlé :
     * setDamierZoom recale masques et marqueurs à chaque pas.
     */
    function initPinchZoom() {
        var map = document.getElementById('game-map');
        if (!map) {
            return;
        }

        var pinch = null;
        var rafPending = false;

        function dist(touches) {
            var dx = touches[0].clientX - touches[1].clientX;
            var dy = touches[0].clientY - touches[1].clientY;
            return Math.sqrt(dx * dx + dy * dy) || 1;
        }

        map.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                pinch = { d: dist(e.touches), z: damierZoom };
            }
        }, { passive: true });

        map.addEventListener('touchmove', function (e) {
            if (!pinch || e.touches.length !== 2) {
                return;
            }
            e.preventDefault();
            var target = pinch.z * dist(e.touches) / pinch.d;
            if (!rafPending) {
                rafPending = true;
                requestAnimationFrame(function () {
                    rafPending = false;
                    setDamierZoom(target);
                });
            }
        }, { passive: false });

        map.addEventListener('touchend', function (e) {
            if (e.touches.length < 2) {
                pinch = null;
            }
        }, { passive: true });

        map.addEventListener('touchcancel', function () {
            pinch = null;
        }, { passive: true });
    }

    /*
     * ===== Tirer pour rafraîchir (mobile) =====
     *
     * La page du HUD ne défile jamais (body overflow hidden) : le
     * geste natif du navigateur ne se déclenche plus (retours joueurs
     * juillet 2026). Réimplémenté à la main : tirer vers le bas d'un
     * doigt depuis une zone au repos recharge la page. Sont exclus le
     * plateau (un doigt y déplace la carte — panoramique), les
     * pincements et tout contenu déjà défilé (le geste y remonte le
     * contenu, il ne rafraîchit pas). Pas de preventDefault : rien ne
     * défile là où le geste est accepté, les listeners restent
     * passifs.
     */
    function initPullToRefresh() {
        var THRESHOLD = 80; /* px de tirage avant armement */
        var start = null;
        var armed = false;
        var $hint = null;

        function hint() {
            if (!$hint) {
                $hint = $('<div id="hud-ptr" aria-hidden="true"><span class="ra ra-cycle"></span></div>')
                    .appendTo('#hud');
            }
            return $hint;
        }

        function reset() {
            start = null;
            armed = false;
            if ($hint) {
                $hint.removeClass('hud-ptr--visible hud-ptr--armed');
            }
        }

        /* Un ancêtre a déjà défilé : le doigt remonte ce contenu. */
        function insideScrolledContent(el) {
            while (el && el !== document.body) {
                if (el.scrollTop > 0) {
                    return true;
                }
                el = el.parentElement;
            }
            return false;
        }

        /* e.touches direct pour les évènements synthétiques (tests). */
        function touchesOf(e) {
            return (e.originalEvent || e).touches || [];
        }

        $(document).on('touchstart.hudPtr', function (e) {
            start = null;
            var touches = touchesOf(e);
            if (!isMobileViewport()
                || touches.length !== 1
                || e.target.closest('#game-map')
                || insideScrolledContent(e.target)) {
                return;
            }
            start = { x: touches[0].clientX, y: touches[0].clientY };
            armed = false;
        });

        $(document).on('touchmove.hudPtr', function (e) {
            if (!start) {
                return;
            }
            var touches = touchesOf(e);
            if (touches.length !== 1) {
                reset();
                return;
            }
            var dy = touches[0].clientY - start.y;
            var dx = Math.abs(touches[0].clientX - start.x);
            /* geste franchement vertical et vers le bas uniquement */
            if (dy < 10 || dx > dy) {
                if (armed || ($hint && $hint.hasClass('hud-ptr--visible'))) {
                    armed = false;
                    hint().removeClass('hud-ptr--visible hud-ptr--armed');
                }
                return;
            }
            armed = dy >= THRESHOLD;
            hint().addClass('hud-ptr--visible')
                .toggleClass('hud-ptr--armed', armed);
        });

        $(document).on('touchend.hudPtr', function () {
            if (!start) {
                return;
            }
            var doReload = armed;
            reset();
            if (doReload) {
                document.location.reload();
            }
        });

        /* Annulation système (appel, changement d'app…) : jamais de
         * rechargement sur un geste interrompu. */
        $(document).on('touchcancel.hudPtr', reset);
    }

    function initDamierZoom() {
        $('<div id="hud-zoom">'
            + '<button id="hud-theater-btn" title="Mode théâtre : le plateau seul en scène" aria-label="Mode théâtre">⛶</button>'
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
     * Mode théâtre : le plateau seul en scène — rail, panneau latéral,
     * minimap et bandeaux masqués (CSS .hud--theater), sélection et
     * actions en nappes flottantes sur le plateau. Persisté par onglet
     * pour survivre aux rechargements qui restent (calques…).
     */
    function setTheaterMode(on) {
        $('#hud').toggleClass('hud--theater', on);
        $('#hud-theater-btn')
            .attr('title', on
                ? 'Quitter le mode théâtre'
                : 'Mode théâtre : le plateau seul en scène');
        sessionStorage.setItem('hudTheater', on ? '1' : '0');

        /* Le cadre du damier change de taille : tout se recale. */
        fitDamier();
        centerMap();
        buildMapRulers();
        redrawBlockedMarkers();
    }

    function initTheaterMode() {
        $('#hud-theater-btn').on('click', function () {
            setTheaterMode(!$('#hud').hasClass('hud--theater'));
        });

        /* Poignée du volet discussions (mdj / évènements) : au théâtre
         * on regarde aussi ce que les gens disent — ouvrable/fermable,
         * persisté par onglet comme le mode lui-même. Elle rejoint la
         * pile de médaillons du plateau, au-dessus du zoom. */
        $('<button id="hud-theater-chat-btn" title="Discussions" aria-label="Discussions">'
            + '<span class="ra ra-speech-bubbles"></span></button>').prependTo('#hud-zoom');

        $('#hud-theater-chat-btn').on('click', function () {
            var on = !$('#hud').hasClass('hud--theater-chat');
            $('#hud').toggleClass('hud--theater-chat', on);
            sessionStorage.setItem('hudTheaterChat', on ? '1' : '0');
        });

        if (sessionStorage.getItem('hudTheaterChat') === '1') {
            $('#hud').addClass('hud--theater-chat');
        }

        if (sessionStorage.getItem('hudTheater') === '1') {
            setTheaterMode(true);
        }

        /* Clic hors des nappes (mode théâtre OU normal) : la sélection
         * et ses actions se referment, la carte au repos (nom du lieu)
         * revient. Un clic sur une case relance une observation, la
         * nouvelle sélection remplace simplement l'ancienne. */
        $(document).on('click', function (e) {
            if (!$('#ajax-data').children('.hud-sel').length) {
                return;
            }
            if (!e.target.isConnected) {
                return;
            }
            if ($(e.target).closest(
                '#ajax-data, #hud-actions, #hud-zoom, #hud-layers,'
                + ' #hud-side, #hud-theater-chat-btn, #hud-topbar,'
                + ' .aoo-dialog-bg, #hud-action-modal, .hud-panel, #hud-rail'
            ).length) {
                return;
            }
            $('#ajax-data').empty();
            $('#hud-actions').empty();
            sessionStorage.removeItem('hudSelCoords');
            renderIdleSelection();
        });
    }

    /*
     * Sélection persistée par onglet : la case observée est mémorisée
     * et re-observée après un rechargement — le panneau de sélection
     * rouvre là où on l'avait laissé.
     */
    function initSelectionMemory() {
        /* Le clic de case ne bulle pas (view.js return false) : la
         * mémorisation s'accroche à la requête observe.php elle-même. */
        $(document).ajaxSuccess(function (e, xhr, settings) {
            if (settings.url === 'observe.php'
                && typeof settings.data === 'string'
                && settings.data.indexOf('coords=') === 0) {
                sessionStorage.setItem('hudSelCoords',
                    decodeURIComponent(settings.data.slice('coords='.length)));
            }
        });

        /* « Fermer » sur la carte : la sélection est volontairement
         * refermée, ne pas la rouvrir au prochain chargement. */
        $(document).on('click', '.close-card', function () {
            sessionStorage.removeItem('hudSelCoords');
        });

        var saved = sessionStorage.getItem('hudSelCoords');
        if (saved && !isMobileViewport() && !tutorialActive()) {
            $.post('observe.php', { coords: saved }, function (data) {
                $('#ajax-data').html(data);
            });
        }
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

        /* Option d'affichage (popover calques) : bordure graduée
         * masquée — le nettoyage ci-dessus a déjà rendu la planche
         * nue, on recale juste les masques météo sur le viewBox
         * d'origine. */
        if ($('.hud-layer[data-option="hideBoardCoords"]').hasClass('hud-layer--on')) {
            fitWeatherMasks();
            return;
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

        /* Le viewBox vient de changer : recaler les masques météo. */
        fitWeatherMasks();
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
                /* Bâtiment : pastille d'état (Ouvert/Fermé + PV) sous la
                 * ligne de type — émise par observe.php à côté de la carte. */
                $d.find('.building-status'),
                $w.children('.card-text')
            );
            /* Couleurs de race (--race-bg/--race-fg posées sur
             * .card-wrapper par Ui::get_card) : le wrapper disparaît,
             * on reporte classe et variables sur la coquille #ui-card */
            if ($w.hasClass('race-colored')) {
                $card.addClass('race-colored').attr('style', $w.attr('style'));
            }

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

        /* Équipement porté (observe.php, personnage sélectionné) :
         * colonne dédiée de la grille, écrans larges seulement (CSS). */
        $sel.append($d.children('.equip-strip'));

        /* Dialogue porté par un bâtiment OUVERT (observe.php n'émet
         * .view-dialog que dans ce cas) : cellule dédiée de la zone de
         * sélection — le dialogue prend la place, l'état reste en
         * pastille compacte dans .hud-sel-main. */
        var $dialog = $d.children('.view-dialog');
        if ($dialog.length) {
            $sel.addClass('hud-sel--dialog').append($dialog);
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

        var styleAttr = $filter.attr('style') || '';
        var match = styleAttr.match(/height:\s*([\d.]+)px/);
        var lostPct = match ? Math.min(100, Math.max(0, parseFloat(match[1]) / 225 * 100)) : 0;
        /* La teinte vient du serveur (races.wound_color — bronze pour une
           structure) : la conserver, --hud-blood n'est que le repli CSS. */
        var colorMatch = styleAttr.match(/background:\s*([^;]+)/);

        $filter.removeAttr('style')
            .addClass('hud-pv-lost')
            .css('height', lostPct + '%')
            .appendTo($('#ajax-data .card-image'));

        if (colorMatch) {
            $filter.css('background', colorMatch[1].trim());
        }
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
        /* Fiche perso : vue de base, réputation et récompenses.
         * Ids négatifs = PNJ : la fiche se charge pareil. */
        if (/^infos\.php\?targetId=-?\d+(&reputation|&rewards)?$/.test(href)) {
            return href.replace(/^infos\.php/, 'load_infos.php');
        }
        /* Marchands et instructeurs : toutes les sous-pages
         * (dialogue, offres, demandes, échanges, banque, inventaire,
         * disciplines de l'école de guerre). */
        if (/^merchant\.php\?/.test(href)) {
            return href.replace(/^merchant\.php/, 'load_merchant.php');
        }
        if (/^warschool\.php\?/.test(href)) {
            return href.replace(/^warschool\.php/, 'load_warschool.php');
        }
        /* Améliorations et Sorts, y compris les modes « oublier »
         * (?spells&forget / &forget_p — leur lien éjectait vers la
         * page héritée) ; ?caracTables reste un export plein-page. */
        if (/^upgrades\.php(\?spells(&forget(_p)?)?)?$/.test(href)) {
            return href.replace(/^upgrades\.php/, 'load_upgrades.php');
        }
        /* Profil : racine et sous-pages (mot de passe, mail, galeries
         * de portraits/avatars, histoire) ; mdj reste plein-page (le
         * HUD a son formulaire dans le panneau latéral). */
        if (href === 'account.php'
            || /^account\.php\?(changePsw|changeMail|portraits|avatars|story)$/.test(href)) {
            return href.replace(/^account\.php/, 'load_account.php');
        }
        /* Forum : accueil (catégories), listes de sujets, fils,
         * derniers messages, répondre, éditer et nouveau sujet en
         * fragments ; la recherche reste plein-page. */
        if (href === 'forum.php' || /^forum\.php\?(forum=|topic=|lastPosts|reply=|edit=|newTopic=)/.test(href)) {
            return href.replace(/^forum\.php/, 'load_forum.php');
        }
        /* Carte : la vue simple en panneau ; les pages avec options
         * de couches (map.php?world / ?local) restent plein-page. */
        if (href === 'map.php') {
            return 'load_map.php';
        }
        /* Pages de lecture : classements (onglets compris), factions,
         * évènements (onglets compris), personnages secondaires. */
        if (/^classements\.php/.test(href)) {
            return href.replace(/^classements\.php/, 'load_classements.php');
        }
        if (/^faction\.php\?faction=/.test(href)) {
            return href.replace(/^faction\.php/, 'load_faction.php');
        }
        if (/^logs\.php/.test(href)) {
            return href.replace(/^logs\.php/, 'load_logs.php');
        }
        if (href === 'pnjs.php') {
            return 'load_pnjs.php';
        }
        return null;
    }

    function panelTitle(href) {
        if (href.indexOf('craft') !== -1) {
            return 'Artisanat';
        }
        /* Sous-pages du profil et du forum : AVANT les tests
         * génériques « account » / « forum ». */
        if (href.indexOf('changePsw') !== -1) {
            return 'Mot de passe';
        }
        if (href.indexOf('changeMail') !== -1) {
            return 'Email';
        }
        if (href.indexOf('portraits') !== -1) {
            return 'Portrait';
        }
        if (href.indexOf('avatars') !== -1) {
            return 'Avatar';
        }
        if (href.indexOf('story') !== -1) {
            return 'Histoire';
        }
        if (href.indexOf('reply=') !== -1) {
            return 'Répondre';
        }
        if (href.indexOf('edit=') !== -1) {
            return 'Éditer';
        }
        if (href.indexOf('newTopic=Missives') !== -1) {
            return 'Nouvelle missive';
        }
        if (href.indexOf('newTopic=') !== -1) {
            return 'Nouveau sujet';
        }
        if (href.indexOf('bank') !== -1) {
            return 'Banque';
        }
        if (href.indexOf('inventory') !== -1) {
            return 'Inventaire';
        }
        /* AVANT le test « reputation » : l'onglet
         * classements.php?reputation est un classement. */
        if (href.indexOf('classements') !== -1) {
            return 'Classements';
        }
        if (href.indexOf('reputation') !== -1) {
            return 'Réputation';
        }
        if (href.indexOf('rewards') !== -1) {
            return 'Récompenses';
        }
        if (href.indexOf('merchant') !== -1) {
            return 'Marchand';
        }
        if (href.indexOf('warschool') !== -1) {
            return 'École de guerre';
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
        if (href === 'forum.php' || href.indexOf('lastPosts') !== -1) {
            return 'Forum';
        }
        if (href.indexOf('faction') !== -1) {
            return 'Faction';
        }
        if (href.indexOf('logs') !== -1) {
            return 'Évènements';
        }
        if (href.indexOf('pnjs') !== -1) {
            return 'Personnages';
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

    /* Pile de retour : les panneaux REMPLACÉS (Inventaire → Artisanat,
     * liste de missives → fil…) — la flèche du bandeau du panneau
     * rouvre le précédent. Vidée quand le panneau est fermé
     * volontairement ; persistée comme openPanels. */
    var panelHistory = [];
    var PANEL_HISTORY_MAX = 10;

    function maxPanels() {
        return 1;
    }

    /* Pastille orange du bouton Forum (bandeau haut + clone du
     * tiroir) : recomptée côté serveur — Forum::put_view vient
     * d'invalider le cache, la lecture se reflète sans rechargement. */
    function refreshForumBadge() {
        $.get('check_forum.php').done(function (data) {
            var n = data && typeof data.n === 'number' ? data.n : 0;
            var $badges = $('[id="forum-unread-badge"]');

            if (n < 1) {
                $badges.remove();
                return;
            }
            if ($badges.length) {
                $badges.text(n);
                return;
            }
            $('#hud-topbar a[href="forum.php"] > button, #hud-rail a.hud-quick-clone[href="forum.php"] > button')
                .append('<span id="forum-unread-badge" class="cartouche bulle">' + n + '</span>');
        });
    }

    function loadPanelContent(slot, url) {
        var $content = $('#hud-panel-' + slot + ' .hud-panel-content');
        $content.html('Chargement…');
        $.get(url)
            .done(function (data) {
                /* Les scripts du fragment (marché, contrats…) lisent
                 * leurs paramètres (targetId…) via aooViewParam :
                 * l'URL de la page ne les porte pas. */
                window.hudPanelQuery = url.indexOf('?') !== -1
                    ? url.slice(url.indexOf('?') + 1)
                    : '';
                $content.html(data);

                /* Le fragment peut imposer son titre — la fiche d'une
                   STRUCTURE arrive par la même URL infos que celle d'un
                   personnage, seul le contenu sait lequel des deux. */
                var titleOverride = $content.find('[data-panel-title]').first().attr('data-panel-title');
                if (titleOverride && openPanels[slot]) {
                    openPanels[slot].title = titleOverride;
                    syncPanels();
                }

                /* Ouvrir un fil le marque « vu » côté serveur
                 * (Forum::put_view au rendu) : pastilles missives ET
                 * forum se rafraîchissent tout de suite, pas au poll
                 * de 60 s ni au prochain rechargement. */
                if (/^load_forum\.php\?topic=/.test(url)) {
                    if (typeof window.refreshMailBadges === 'function') {
                        window.refreshMailBadges();
                    }
                    refreshForumBadge();
                }
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
            $(this).find('.hud-panel-back').toggle(panelHistory.length > 0);
        });
        $('#hud').toggleClass('hud--panel-open', openPanels.length > 0);
        sessionStorage.setItem('hudPanels', JSON.stringify(openPanels));
        sessionStorage.setItem('hudPanelHistory', JSON.stringify(panelHistory));
    }

    function reloadAllPanels() {
        openPanels.forEach(function (entry, slot) {
            loadPanelContent(slot, entry.url);
        });
    }

    function openPanel(url, title, fromHistory) {
        var idx = openPanels.findIndex(function (p) { return p.url === url; });

        if (idx !== -1) {
            /* Déjà ouvert : rafraîchir son contenu */
            openPanels[idx].title = title || openPanels[idx].title;
            syncPanels();
            loadPanelContent(idx, url);
            return;
        }

        /* Slot plein : le plus ancien laisse sa place — et alimente la
         * pile de retour (sauf si on est justement en train d'y
         * revenir). Point de passage unique : TOUT remplacement de
         * panneau devient « retournable », quel que soit le flux. */
        var shifted = false;
        while (openPanels.length >= maxPanels()) {
            var replaced = openPanels.shift();
            if (!fromHistory) {
                panelHistory.push(replaced);
                if (panelHistory.length > PANEL_HISTORY_MAX) {
                    panelHistory.shift();
                }
            }
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
        /* Fermeture volontaire : la pile de retour meurt avec le
         * panneau — rouvrir plus tard repart d'un historique neuf. */
        panelHistory = [];
        syncPanels();
        reloadAllPanels();
    }

    /* Flèche du bandeau du panneau : rouvre le panneau remplacé. */
    function goBackPanel() {
        var prev = panelHistory.pop();
        if (prev) {
            openPanel(prev.url, prev.title, true);
        }
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

    /* Exposé aux scripts hérités injectés dans les panneaux : ouvrir
     * un panneau sans quitter le plateau (bouton Artisanat par ligne
     * de l'inventaire, liens d'objets de l'artisanat — js/inventory.js
     * et CraftView). */
    window.hudOpenPanel = openPanel;

    /* Idem pour la pastille du Forum : « Tout marquer comme lu »
     * (ForumHomeView) doit la recompter sans rechargement. */
    window.hudRefreshForumBadge = refreshForumBadge;

    /* Après une action DANS un panneau (équiper, oublier un sort…) :
     * recharge le contenu du panneau et les valeurs vivantes du
     * bandeau (pilules, prochain tour, observation, évènements) sans
     * recharger la page — le document.location.reload() des scripts
     * hérités fermait le panneau (retour joueurs juillet 2026). */
    window.hudReloadPanels = reloadAllPanels;
    window.hudRefreshAfterAction = refreshAfterAction;

    /* Navigation générique pour les scripts hérités qui naviguent en
     * JS (options de dialogue marchand/instructeur…) : panneau si
     * l'URL a un fragment, navigation pleine page sinon. */
    window.hudNavigate = function (url) {
        var fragment = panelUrl(url);
        if (fragment) {
            openPanel(fragment, panelTitle(url));
            return;
        }
        document.location = url;
    };

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

        /* Raccourcis du bandeau haut (console admin, classements,
         * forum, menu principal, déconnexion) : le CSS mobile masque
         * .hud-quick, ils étaient inatteignables — clones en fin de
         * tiroir, après un séparateur. Le rail desktop les ignore
         * (CSS ≥1024px sur .hud-quick-clone). */
        var $quick = $('#hud-topbar .hud-quick a');
        if ($quick.length) {
            $('<span class="hud-rail-sep hud-quick-clone"></span>').appendTo('#hud-rail #menu');
            $quick.each(function () {
                $(this).clone().addClass('hud-quick-clone').appendTo('#hud-rail #menu');
            });
        }

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

        /* « Évènements » quitte le rail : le flux vit dans l'onglet
         * Événements du panneau latéral (et logs.php via son bouton
         * livre) — son icône peinte passe à Sorts & Techniques. */
        $('#hud-rail a[href^="logs.php"]').remove();

        /* Séparateurs de groupes du rail : le personnage (caracs,
         * sorts, possessions) / le monde (évènements, carte,
         * missives) / le compte. Posés après le repositionnement. */
        var $menu = $('#hud-rail #menu');
        $('<span class="hud-rail-sep"></span>').insertAfter('#show-bank');
        $('<span class="hud-rail-sep"></span>')
            .insertAfter($menu.children('a[href="forum.php?forum=Missives"]'));

        /* Rail : Inventaire, Artisanat, Banque, Sorts, Profil,
         * Missives, Carte… — plus AUCUN gestionnaire dédié : leurs
         * hrefs pleine-page ont tous un fragment (panelUrl), le
         * routeur global de liens (plus bas) les ouvre en panneau.
         * Seul Caractéristiques garde le sien (ancre href="#",
         * bascule flyout pendant le tutoriel). */

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
                /* Directement la page d'amélioration (dépense d'XP) :
                 * l'ancien volet de lecture seule faisait doublon avec
                 * les pilules du bandeau haut (retour testeur). */
                togglePanel('load_upgrades.php', 'Caractéristiques');
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
         * (déplacement, action…) — pile de retour comprise. */
        if (!tutorialActive()) {
            try {
                var saved = JSON.parse(sessionStorage.getItem('hudPanels') || '[]');
                openPanels = saved.slice(0, maxPanels());
            } catch (err) {
                openPanels = [];
            }
            try {
                panelHistory = JSON.parse(sessionStorage.getItem('hudPanelHistory') || '[]');
            } catch (err) {
                panelHistory = [];
            }
            if (openPanels.length) {
                syncPanels();
                reloadAllPanels();
            }
        }

        /* ===== Routeur global de liens =====
         * UN SEUL délégué : tout lien dont l'URL a un équivalent
         * fragment (panelUrl) s'ouvre en panneau, où qu'il soit —
         * rail, bandeau haut (chip, avatar, raccourcis), tiroir
         * mobile, bandeau de sélection, flux, contenu de panneau.
         * Dans un panneau, « Retour » (index.php) referme le panneau.
         * Le reste (wiki, formulaires, récompenses, déconnexion…)
         * navigue normalement. */
        $(document).on('click', 'a[href]', function (e) {
            if (tutorialActive()) {
                return;
            }
            var href = $(this).attr('href');

            if (/^index\.php/.test(href) && $(this).closest('.hud-panel-content').length) {
                e.preventDefault();
                closePanelAt($(this).closest('.hud-panel').data('slot'));
                return;
            }

            var fragment = panelUrl(href);
            if (fragment) {
                e.preventDefault();
                $('#hud').removeClass('hud--drawer-open');
                /* Depuis l'INTÉRIEUR d'un panneau (onglets du
                 * classement, liens du forum…), re-cliquer l'entrée
                 * déjà affichée recharge le contenu au lieu de fermer
                 * le panneau : le toggle ouvrir/fermer n'a de sens que
                 * pour les entrées extérieures (rail, bandeau, tiroir). */
                if ($(this).closest('.hud-panel-content').length) {
                    openPanel(fragment, panelTitle(href));
                } else {
                    togglePanel(fragment, panelTitle(href));
                }
            }
        });

        $('.hud-panel-close').on('click', function () {
            closePanelAt($(this).closest('.hud-panel').data('slot'));
        });

        $('.hud-panel-back').on('click', goBackPanel);

        /* « Agrandir » : étend le panneau sur toute la largeur
         * disponible, SANS changer de page (retours joueurs juillet
         * 2026) — lire ou répondre au forum tient mieux en grand.
         * État persisté comme les panneaux eux-mêmes. */
        $('.hud-panel-fullpage').on('click', function () {
            var wide = !$('#hud').hasClass('hud--panel-wide');
            $('#hud').toggleClass('hud--panel-wide', wide);
            sessionStorage.setItem('hudPanelWide', wide ? '1' : '');
            $(this).attr('title', wide ? 'Réduire le panneau' : 'Agrandir le panneau');
        });

        if (sessionStorage.getItem('hudPanelWide') === '1') {
            $('#hud').addClass('hud--panel-wide');
            $('.hud-panel-fullpage').attr('title', 'Réduire le panneau');
        }

        /* ===== Formulaires dans les panneaux =====
         * Un formulaire dont l'action a un équivalent fragment est
         * envoyé en AJAX vers ce fragment et sa réponse remplace le
         * contenu du panneau (mot de passe, mail…) — sinon l'envoi
         * naviguerait vers la page héritée. Les autres formulaires
         * (récompenses, recherche…) gardent l'envoi normal. */
        $(document).on('submit', '.hud-panel-content form', function (e) {
            if (tutorialActive()) {
                return;
            }
            var fragment = panelUrl($(this).attr('action') || '');
            if (!fragment) {
                return;
            }
            e.preventDefault();
            var $content = $(this).closest('.hud-panel-content');
            $.post(fragment, $(this).serialize())
                .done(function (data) {
                    $content.html(data);
                })
                .fail(function () {
                    $content.html('<p class="hud-feed-empty">Impossible d\'envoyer le formulaire.</p>');
                });
        });

        /* Clic hors du panneau : fermeture (comme un popover). Sont
         * exclus le panneau lui-même, le rail et la modale de résultat
         * (son fond se ferme déjà lui-même, le panneau reste) — et
         * tout lien qui OUVRE un panneau (même règle panelUrl que le
         * routeur global : le clic qui vient d'ouvrir ne referme pas). */
        $(document).on('click', function (e) {
            if (!openPanels.length) {
                return;
            }
            /* Cible détachée du DOM : le gestionnaire de l'élément a
             * déjà re-rendu son conteneur (lien de fil de missive,
             * bouton +1…). Un tel clic vient toujours de DANS un
             * composant vivant, jamais du dehors — closest() ne peut
             * plus le prouver puisque l'ancêtre est détaché. */
            if (!e.target.isConnected) {
                return;
            }
            if ($(e.target).closest(
                '.hud-panel, #hud-rail, #hud-burger, #hud-action-modal'
            ).length) {
                return;
            }
            var $link = $(e.target).closest('a[href]');
            if ($link.length && panelUrl($link.attr('href'))) {
                return;
            }
            closePanelAt(openPanels.length - 1);
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

                /* Boutons de NAVIGATION (Marchander, Apprendre…) : un
                 * lien autour, pas de data-action — rien à confirmer,
                 * le routeur de panneaux prend le clic directement. */
                if (!btn.dataset.action && btn.closest('a[href]')) {
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

            /* Onglet actif persisté par onglet navigateur : un
             * rechargement rouvre le flux qu'on lisait. */
            sessionStorage.setItem('hudFeedTab', tab);

            loadFeed(tab);
        });

        if (sessionStorage.getItem('hudFeedTab') === 'events') {
            $('.hud-tab[data-tab="events"]').trigger('click');
        }

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

            /* Le message du jour affiché ailleurs doit suivre : sans
             * cela les personnages gardaient leur ancien message tant
             * que la page n'était pas rechargée (retours joueurs
             * juillet 2026) — re-observation de la sélection (bulle du
             * bandeau bas) et rechargement du panneau ouvert (fiche). */
            refreshSelection();
            reloadAllPanels();
        });

        fitMinimap();
        centerMap();
        initDamierZoom();
        initPinchZoom();
        initPullToRefresh();
        initTheaterMode();
        initSelectionMemory();
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
