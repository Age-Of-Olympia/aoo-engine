/* Choix de la case de construction — réutilise le SPOTLIGHT du
   tutoriel (celui de l'étape mouvements) : voile SVG troué autour du
   joueur (padding 50 = les 8 cases), croix rouges sur les cases
   non-praticables, seules les cases .go répondent.

   Armé par l'inventaire (bouton « Construire (1 A) » → sessionStorage
   pendingBuild → retour au damier) ; Échap ou Annuler pour sortir. */
$(document).ready(function(){

    var raw = aooStore.get('pendingBuild');
    if(!raw){
        return;
    }

    var pending;
    try {
        pending = JSON.parse(raw);
    } catch(e){
        aooStore.remove('pendingBuild');
        return;
    }

    /* Charge utile complète exigée : un pendingBuild tronqué armerait un
       POST action=undefined. */
    if(!pending || typeof pending.action !== 'string' || typeof pending.name !== 'string' || !pending.itemId){
        aooStore.remove('pendingBuild');
        return;
    }

    /* Seulement sur le damier, et seulement si la machinerie du
       tutoriel est là (chargée partout par Ui). La demande ne survit PAS
       à une page inapte : sinon le picker se ré-armerait des jours plus
       tard, sur une visite sans rapport. */
    if(!$('#current-player-avatar').length || typeof TutorialHighlighter === 'undefined'){
        aooStore.remove('pendingBuild');
        return;
    }

    /* The built form's cut-out: the clicked cell is the ORIGIN, the other
       offsets follow it. A single cell needs no ghost. */
    var footprint = (pending.footprint && pending.footprint.length) ? pending.footprint : [[0, 0]];

    /* The zone follows the emprise: a 2×2 form may put its ORIGIN two
       cells away and still touch the builder — the spotlight widens with
       the footprint's extent, the acceptance rule below does the rest. */
    var maxExtent = footprint.reduce(function(m, off){
        return Math.max(m, Math.abs(off[0]), Math.abs(off[1]));
    }, 0);

    var highlighter = new TutorialHighlighter();
    highlighter.highlight('#current-player-avatar', { padding: 50 * (1 + maxExtent), pulsate: true });

    /* Picker view only: the wrong-way signs give way to a red filter on
       the blocked tiles — the signs stay for the tutorial and map tools.
       The highlighter REDRAWS them during its pulsate loop, so a one-time
       clear is not enough: the draw hook is muted for the whole picking. */
    var savedDrawMarkers = window.drawBlockedTileMarkers;
    if(typeof savedDrawMarkers === 'function'){
        window.clearBlockedTileMarkers('blocked-tile-marker');
        window.drawBlockedTileMarkers = function(){};
    }

    var playerCoords = (function(){
        var avatar = document.getElementById('current-player-avatar');
        if(!avatar){ return null; }
        /* The avatar lives in the SVG overlay, not inside a .case: the
           builder's cell is the one whose rect contains its center. */
        var r = avatar.getBoundingClientRect();
        var cx = r.left + r.width / 2;
        var cy = r.top + r.height / 2;
        var found = null;
        document.querySelectorAll('.case[data-coords]').forEach(function(el){
            if(found){ return; }
            var cr = el.getBoundingClientRect();
            if(cx >= cr.left && cx < cr.right && cy >= cr.top && cy < cr.bottom){
                found = el;
            }
        });
        var c = found ? (found.getAttribute('data-coords') || '').split(',') : [];
        return c.length === 2 ? { x: parseInt(c[0], 10), y: parseInt(c[1], 10) } : null;
    })();

    /* Same rule as the server (BuildSitePick): some cell of the built
       form within one step of the builder. */
    function footprintTouchesPlayer(x, y){
        if(!playerCoords){ return false; }
        return footprint.some(function(off){
            return Math.max(Math.abs((x + off[0]) - playerCoords.x),
                            Math.abs((y + off[1]) - playerCoords.y)) <= 1;
        });
    }

    if(playerCoords){
        var reach = 1 + maxExtent;
        document.querySelectorAll('.case[data-blocked]').forEach(function(el){
            var c = (el.getAttribute('data-coords') || '').split(',');
            if(c.length !== 2){ return; }
            if(Math.max(Math.abs(parseInt(c[0], 10) - playerCoords.x),
                        Math.abs(parseInt(c[1], 10) - playerCoords.y)) <= reach){
                el.classList.add('build-blocked-tint');
            }
        });
    }

    var $banner = $(
        '<div id="build-picker-banner" style="position:fixed;top:12px;left:50%;transform:translateX(-50%);' +
        'z-index:100000;background:#f4e9d4;border:2px solid #810303;border-radius:6px;padding:10px 16px;' +
        'font-weight:bold;box-shadow:0 2px 10px rgba(0,0,0,0.4);">' +
        '<span class="ra ra-tower"></span> Construire ' + pending.name +
        ' : cliquez une case libre adjacente' +
        (footprint.length > 1 ? ' (emprise de ' + footprint.length + ' cases, la case cliquée est l’origine)' : '') +
        ' <button id="build-picker-cancel" style="margin-left:10px;">Annuler</button>' +
        '</div>'
    );
    $('body').append($banner);

    $('head').append(
        '<style id="build-ghost-style">' +
        '.build-ghost-ok{outline:3px solid rgba(40,167,69,.85);outline-offset:-3px;}' +
        '.build-ghost-bad{outline:3px solid rgba(220,53,69,.85);outline-offset:-3px;}' +
        '.build-blocked-tint{box-shadow: inset 0 0 0 999px rgba(220,53,69,.32);}' +
        '#build-ghost-img{position:fixed;pointer-events:none;opacity:.55;z-index:99990;display:none;}' +
        '</style>'
    );

    /* The building itself, ghosted over the hovered cells — its avatar, or
       the initials frame an imageless type already shows on the board. */
    var $ghostImg = null;
    if(pending.ghostImg){
        $ghostImg = $('<img id="build-ghost-img" alt="">').attr('src', pending.ghostImg);
        $('body').append($ghostImg);
    }

    function clearGhost(){
        document.querySelectorAll('.build-ghost-ok, .build-ghost-bad').forEach(function(el){
            el.classList.remove('build-ghost-ok');
            el.classList.remove('build-ghost-bad');
        });
        if($ghostImg){
            $ghostImg.hide();
        }
    }

    function clearBlockedTint(){
        document.querySelectorAll('.build-blocked-tint').forEach(function(el){
            el.classList.remove('build-blocked-tint');
        });
    }

    /* Preview only — the server stays the judge at the click: an element
       (water, lava) refuses a cell the ghost may still paint green. */
    function onHover(e){
        var caseEl = e.target.closest ? e.target.closest('.case') : null;
        clearGhost();
        if(!caseEl){
            return;
        }
        var coords = (caseEl.getAttribute('data-coords') || '').split(',');
        if(coords.length !== 2){
            return;
        }
        var x = parseInt(coords[0], 10);
        var y = parseInt(coords[1], 10);
        if(!footprintTouchesPlayer(x, y)){
            return;
        }
        var cells = footprint.map(function(off){
            return document.querySelector('.case[data-coords="' + (x + off[0]) + ',' + (y + off[1]) + '"]');
        });
        var allFree = cells.every(function(el){ return el && !el.hasAttribute('data-blocked'); });
        cells.forEach(function(el){
            if(el){ el.classList.add(allFree ? 'build-ghost-ok' : 'build-ghost-bad'); }
        });

        /* Stretch the sprite over the union of the cells actually on
           screen — measured from their rects, so the board's orientation
           never enters the computation. */
        if($ghostImg && cells.some(function(el){ return !!el; })){
            var left = Infinity, top = Infinity, right = -Infinity, bottom = -Infinity;
            cells.forEach(function(el){
                if(!el){ return; }
                var r = el.getBoundingClientRect();
                left = Math.min(left, r.left);
                top = Math.min(top, r.top);
                right = Math.max(right, r.right);
                bottom = Math.max(bottom, r.bottom);
            });
            $ghostImg.css({
                left: left + 'px',
                top: top + 'px',
                width: (right - left) + 'px',
                height: (bottom - top) + 'px'
            }).show();
        }
    }

    function cleanup(){
        aooStore.remove('pendingBuild');
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('keydown', onKey, true);
        document.removeEventListener('mouseover', onHover, true);
        clearGhost();
        clearBlockedTint();
        if($ghostImg){
            $ghostImg.remove();
        }
        $('#build-ghost-style').remove();
        $banner.remove();
        highlighter.clearAll();

        /* Give the signs back to whoever displays them. */
        if(typeof savedDrawMarkers === 'function'){
            window.drawBlockedTileMarkers = savedDrawMarkers;
            if(window.showBlockedTiles){
                savedDrawMarkers(null, 'blocked-tile-marker', $('#svg-container'));
            }
        }
    }

    function onKey(e){
        if(e.key === 'Escape'){
            cleanup();
        }
    }

    function onClick(e){

        if(e.target.id === 'build-picker-cancel'){
            e.preventDefault();
            cleanup();
            return;
        }

        var caseEl = e.target.closest ? e.target.closest('.case') : null;
        if(!caseEl){
            return;
        }

        /* Phase capture : court-circuiter l'observation/le déplacement
           pendant le choix — une case non-constructible ne fait rien. */
        e.preventDefault();
        e.stopImmediatePropagation();

        var coords = (caseEl.getAttribute('data-coords') || '').split(',');
        if(coords.length !== 2){
            return;
        }

        /* Same gate as the ghost: the form must touch the builder and no
           cell of it may be blocked — the server re-judges either way. */
        var ox = parseInt(coords[0], 10);
        var oy = parseInt(coords[1], 10);
        if(!footprintTouchesPlayer(ox, oy)){
            return;
        }
        var blockedCell = footprint.some(function(off){
            var el = document.querySelector('.case[data-coords="' + (ox + off[0]) + ',' + (oy + off[1]) + '"]');
            return !el || el.hasAttribute('data-blocked');
        });
        if(blockedCell){
            return;
        }

        cleanup();

        $.post('action.php', { action: pending.action, itemId: pending.itemId, buildX: coords[0], buildY: coords[1] }, function(data){

            var text = $('<div></div>').html(
                data.replace(/<script[\s\S]*?<\/script>/gi, '')
                    .replace(/<style[\s\S]*?<\/style>/gi, '')
            ).text().replace(/\s+/g, ' ').trim();

            aooAlert(text).then(function(){
                document.location.reload();
            });
        });
    }

    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKey, true);
    document.addEventListener('mouseover', onHover, true);
});
