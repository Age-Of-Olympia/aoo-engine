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

    var $banner = $(
        '<div id="build-picker-banner" style="position:fixed;top:12px;left:50%;transform:translateX(-50%);' +
        'z-index:100000;background:#f4e9d4;border:2px solid #810303;border-radius:6px;padding:10px 16px;' +
        'font-weight:bold;box-shadow:0 2px 10px rgba(0,0,0,0.4);">' +
        '<span class="ra ra-tower"></span> Construire ' + pending.name +
        ' : cliquez une case libre adjacente ' +
        '<button id="build-picker-cancel" style="margin-left:10px;">Annuler</button>' +
        '</div>'
    );
    $('body').append($banner);

    function cleanup(){
        aooStore.remove('pendingBuild');
        document.removeEventListener('click', onClick, true);
        document.removeEventListener('keydown', onKey, true);
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
});
