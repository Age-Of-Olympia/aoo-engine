$(document).ready(function(){


    window.clickedCases = [];

    // Caracs panel persistence: cookie so MenuView can inline the
    // panel server-side on the next page render. That eliminates the
    // post-reload pop-in / layout shift the previous AJAX restore had.
    function setCaracsCookie(open) {
        document.cookie = 'caracs_panel_open=' + (open ? '1' : '0') + '; path=/; SameSite=Lax';
    }

    $(document).on('click', '#show-caracs', function() {
        // Wait for the MenuView click handler to toggle visibility.
        setTimeout(function() {
            setCaracsCookie($('#load-caracs').is(':visible'));
        }, 100);
    });

    /**
     * Programmatic close: hide the panel and clear the cookie so it
     * stays closed across reloads. Tutorial steps call this when they
     * need the panel out of the way.
     */
    window.closeCaracsPanel = function() {
        $('#load-caracs').hide();
        setCaracsCookie(false);
    };

    /**
     * Programmatic open: AJAX-load the panel content and show it,
     * then mark the cookie so the server inlines it on the next
     * reload. No fadeIn — this is for tutorial-driven setup, not
     * user click feedback.
     */
    window.openCaracsPanel = function() {
        var $panel = $('#load-caracs');
        if ($panel.is(':visible') && $panel.children().length) {
            setCaracsCookie(true);
            return;
        }
        $.ajax({
            type: 'POST',
            url: 'load_caracs.php',
            success: function(data) {
                $panel.html(data).show();
                setCaracsCookie(true);
            }
        });
    };

    // Player display option: ⛔ on every blocked tile across the
    // whole map. Driven by window.showBlockedTiles (set server-side
    // in MainView from the showBlockedTiles player option, gated to
    // false during a tutorial session so the tutorial's own scoped
    // markers stay solo). Uses the shared helper from blocked-tiles.js.
    //
    // Markers are appended inside #svg-container (which is already
    // position:relative) so they ride along with the map when #view
    // scrolls horizontally — no scroll listener / recompute needed.
    if (window.showBlockedTiles && typeof window.drawBlockedTileMarkers === 'function') {
        var $svgContainer = $('#svg-container');
        var redrawBlockedTiles = function() {
            window.drawBlockedTileMarkers(null, 'blocked-tile-marker', $svgContainer);
        };
        redrawBlockedTiles();
        window.addEventListener('resize', redrawBlockedTiles);
    }

    // Right-click coordinate tool (available for everyone, TP button only for admins)
    $(document).on('contextmenu', '.case', function(e) {
        e.preventDefault();

        var coords = $(this).data('coords');

        if(!coords) {
            return;
        }

        let [x, y] = coords.split(',');

        // Build HTML with coords button (always shown) and TP button (admin only)
        var html = '<button id="admin-coords-close" title="Fermer">✕</button><div id="case-coords"><button OnClick="copyToClipboard(this);">x'+ x +',y'+ y +'</button>';

        if(window.isAdmin) {
            html += '<br><button onclick="teleport(\'' + coords + '\')">TP</button>';
        }

        html += '</div>';

        $('#admin-coords').html(html);

        /* La boîte se pose à l'endroit du clic (bornée aux bords de
         * l'écran), plus au coin fixe de la page. */
        var box = document.getElementById('admin-coords');
        box.style.right = 'auto';
        box.style.bottom = 'auto';
        var bx = Math.min(e.clientX + 10, window.innerWidth - box.offsetWidth - 12);
        var by = Math.min(e.clientY + 10, window.innerHeight - box.offsetHeight - 12);
        box.style.left = Math.max(6, bx) + 'px';
        box.style.top = Math.max(6, by) + 'px';

        // Bind close button
        $('#admin-coords-close').off('click').on('click', function(e) {
            e.stopPropagation();
            $('#admin-coords').html('');
        });
    });



    /**
     * Rafraîchit le sprite d'UNE entité du damier en place (bascule
     * blessé/réparé d'un bâtiment) : le plateau est un SVG inline chargé
     * avec la page, patcher les deux nœuds <image> (avatar + ombre)
     * évite de re-télécharger tout le plateau. Appelé par la réponse
     * d'action.php quand la cible est une structure.
     */
    window.aooUpdateBoardSprite = function(id, href){
        /* nœuds émis par View.php : id="players{id}" et son ombre */
        ['', '-shadow'].forEach(function(suffix){
            var node = document.getElementById('players' + String(id) + suffix);
            if(node && node.tagName.toLowerCase() === 'image'){
                node.setAttribute('href', href);
                node.setAttribute('xlink:href', href);
            }
        });
    };

    /**
     * Map bindings live on the SVG's own nodes (.case tiles, #go-rect),
     * so they die whenever the map markup is replaced. Exposed as
     * window.bindMapView so the HUD can rebind after an AJAX view swap
     * (js/hud.js hudRefreshAfterMove) — legacy full reloads simply call
     * it once at ready.
     */
    /**
     * Ligne de tir sur le damier : observe.php envoie la case du
     * tireur, la case visée et l'éventuel premier obstacle. On trace
     * une vraie ligne de centre à centre — verte tant que la
     * trajectoire est libre, rouge à partir de l'obstacle (marqué d'un
     * point). Effacée à chaque nouveau clic de case.
     */
    window.clearLineOfFire = function(){
        $('.lof-mark').remove();
    };

    window.showLineOfFire = function(from, to, blocker){

        window.clearLineOfFire();

        /* Centre (pixels SVG) d'une case logique — null si hors damier. */
        function tileCenter(tile){
            var $case = $('.case[data-coords="'+ tile[0] +','+ tile[1] +'"]').first();
            if(!$case.length){
                return null;
            }
            return {
                x: parseFloat($case.attr('x')) + 25,
                y: parseFloat($case.attr('y')) + 25
            };
        }

        var svg = $('.case').first().closest('svg');
        var a = tileCenter(from);
        var b = tileCenter(to);
        if(!svg.length || !a || !b){
            return;
        }

        function segment(p1, p2, color){
            var line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            line.setAttribute('class', 'lof-mark');
            line.setAttribute('x1', p1.x);
            line.setAttribute('y1', p1.y);
            line.setAttribute('x2', p2.x);
            line.setAttribute('y2', p2.y);
            line.setAttribute('stroke', color);
            line.setAttribute('stroke-width', '4');
            line.setAttribute('stroke-linecap', 'round');
            line.setAttribute('stroke-dasharray', '9 7');
            line.setAttribute('pointer-events', 'none');
            svg[0].appendChild(line);
        }

        var blockerCenter = blocker ? tileCenter(blocker) : null;

        if(blockerCenter){
            /* La trajectoire reste UNE droite (c'est elle que Bresenham
               rasterise) : l'obstacle, qui peut être une case adjacente
               à la droite, est projeté dessus pour placer le point
               d'impact — pas de coude. */
            var dx = b.x - a.x;
            var dy = b.y - a.y;
            var t = ((blockerCenter.x - a.x) * dx + (blockerCenter.y - a.y) * dy) / (dx * dx + dy * dy);
            t = Math.max(0, Math.min(1, t));
            var hit = {x: a.x + t * dx, y: a.y + t * dy};

            segment(a, hit, 'rgba(60, 170, 60, 0.85)');
            segment(hit, b, 'rgba(205, 40, 40, 0.85)');

            /* Point d'impact */
            var dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            dot.setAttribute('class', 'lof-mark');
            dot.setAttribute('cx', hit.x);
            dot.setAttribute('cy', hit.y);
            dot.setAttribute('r', '6');
            dot.setAttribute('fill', 'rgba(205, 40, 40, 0.9)');
            dot.setAttribute('pointer-events', 'none');
            svg[0].appendChild(dot);
        }
        else{
            segment(a, b, 'rgba(60, 170, 60, 0.85)');
        }
    };

    window.bindMapView = function(){

    $('.case').off('click').on('click', function(e){

        window.clearLineOfFire();

        // Block clicks if tutorial overlay is in blocking mode
        if ($('#tutorial-overlay').hasClass('blocking')) {
            return false;
        }

        $('#destroy-rect').hide();
        $('#destroy-img').hide();

        $('#go-rect').hide();
        $('#go-img').hide();


        var coords = $(this).data('coords');

        var i = $(this).attr('x');
        var j = $(this).attr('y');


        var $case = $('[x="'+ i +'"][y="'+ j +'"]');

        let [x, y] = coords.split(',');


        // show go button if applicable (no player standing on the case)
        /* Les structures passables (data-passable, ex. une table) ne
           confisquent pas le bouton Aller. */
        var hasPlayer = $('image[data-table="players"][x="'+ i +'"][y="'+ j +'"]').not('[data-passable]').length > 0;

        if($case.hasClass('go') && !hasPlayer){

            $('#go-rect')
                .show()
                .attr({'x': i, 'y': j})
                .data('coords', x +','+ y);

            var imgY = j - 20;

            $('#go-img').show().attr({'x': i, 'y': imgY});
        }


        if($('.clicked-cases-reseter[data-coords="'+ coords +'"]')[0] != null){

            $('.clicked-cases-reseter[data-coords="'+ coords +'"]').remove();

        } else if(window.clickedCases[coords]){

            let data = window.clickedCases[coords];

            $('#ajax-data').html(data);

            return false;
        }


        $.ajax({
            type: "POST",
            url: 'observe.php',
            data: {'coords':coords},
            success: function(data)
            {
                $('#ajax-data').html(data);

                window.clickedCases[coords] = data;
            }
        });

        return false;
    });


    $('#go-rect').off('click').on('click', function(e){

        var coords = $(this).data('coords');

        /* Validate coords before sending */
        if (!coords || typeof coords !== 'string' || !coords.includes(',')) {
            console.error('Invalid coords for movement:', coords);
            alert('Erreur: coordonnées invalides');
            document.location.reload();
            return false;
        }

        $('#go-rect').off('click');
        $('#go-img').attr('href', 'img/ui/view/gear.webp');
        // $('#view').css({'filter':'grayscale(1)', 'transition':'filter 0.5s'});

        $.ajax({
            type: "POST",
            url: 'go.php',
            data: {'coords':coords}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);

                if(data.trim() != ''){


                    $('#ajax-data').html(data);

                    return false;
                }

                // Notify tutorial system about successful movement
                if (window.tutorialUI && window.tutorialUI.isActive) {
                    console.log('[View] Notifying tutorial about movement to:', coords);

                    // Send validation but skip UI update (page will reload)
                    window.notifyTutorial('movement', {
                        action: 'move',  // Required for validation
                        coords: coords,
                        timestamp: Date.now()
                    }, true); // skipUIUpdate = true to avoid showing next step before reload

                    // Give tutorial 100ms to save, then reload
                    setTimeout(function() {
                        document.location.reload();
                    }, 100);
                } else if (typeof window.hudRefreshAfterMove === 'function') {
                    // HUD: the view refreshes over AJAX (js/hud.js) —
                    // zoom and scroll of the damier are untouched.
                    window.hudRefreshAfterMove();
                } else {
                    // Legacy layout: reload the page
                    document.location.reload();
                }
            }
        });
    });

    }; // end window.bindMapView

    window.bindMapView();
});
