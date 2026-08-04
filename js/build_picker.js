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

    var highlighter = new TutorialHighlighter();
    highlighter.highlight('#current-player-avatar', { padding: 50, pulsate: true });

    /* The built form's cut-out: the clicked cell is the ORIGIN, the other
       offsets follow it. A single cell needs no ghost. */
    var footprint = (pending.footprint && pending.footprint.length) ? pending.footprint : [[0, 0]];

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
        '.build-ghost-ok{box-shadow: inset 0 0 0 3px rgba(40,167,69,.85);}' +
        '.build-ghost-bad{box-shadow: inset 0 0 0 3px rgba(220,53,69,.85);}' +
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

    /* Preview only — the server stays the judge at the click: an element
       (water, lava) refuses a cell the ghost may still paint green. */
    function onHover(e){
        var caseEl = e.target.closest ? e.target.closest('.case') : null;
        clearGhost();
        if(!caseEl || !caseEl.classList.contains('go')){
            return;
        }
        var coords = (caseEl.getAttribute('data-coords') || '').split(',');
        if(coords.length !== 2){
            return;
        }
        var x = parseInt(coords[0], 10);
        var y = parseInt(coords[1], 10);
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
        if($ghostImg){
            $ghostImg.remove();
        }
        $('#build-ghost-style').remove();
        $banner.remove();
        highlighter.clearAll();
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

        if(!caseEl.classList.contains('go')){
            return;
        }

        var coords = (caseEl.getAttribute('data-coords') || '').split(',');
        if(coords.length !== 2){
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
