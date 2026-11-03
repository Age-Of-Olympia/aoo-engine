/* Brouillons forum et missives : un par formulaire (réponse à un sujet,
 * nouveau sujet dans un forum, édition d'un message) et par personnage,
 * écrits dans localStorage à chaque frappe pour survivre à la fermeture
 * du panneau ou de la page. La clé et le personnage qui écrit viennent du
 * serveur (data-draft, data-draft-owner) : le fragment sait quel
 * personnage postera, la page hôte peut l'ignorer après un changement de
 * pnj dans un autre onglet.
 *
 * Une copie part au serveur (api/forum/draft.php) au plus toutes les
 * DRAFT_SYNC_DELAY, et par beacon quand la page se ferme ou passe en
 * arrière-plan, pour reprendre le brouillon sur un autre appareil. Le
 * fragment apporte la copie serveur (data-draft-server) : à l'ouverture,
 * le plus récent des deux (champ at) l'emporte. Une copie serveur vide
 * est un brouillon envoyé ou effacé ailleurs.
 *
 * Toutes les dates at sont à l'heure du serveur : chaque formulaire et
 * chaque réponse de draft.php la donnent, le navigateur en tire son
 * décalage. Deux appareils mal réglés se comparent ainsi sur la même
 * horloge, et aucune date ne part dans le futur (elle ne serait jamais
 * purgée et gagnerait toutes les comparaisons). */
var DRAFT_PREFIX = 'forumDraft:';
var DRAFT_MAX_AGE = 30 * 24 * 3600 * 1000;
var DRAFT_SYNC_DELAY = 5000;
var DRAFT_SYNC_URL = 'api/forum/draft.php';

/* Décalage horloge serveur - horloge locale, en ms. */
window.forumDraftClockOffset = window.forumDraftClockOffset || 0;

/* Maintenant, à l'heure du serveur. */
function draftNow(){
    return Math.round(Date.now() + window.forumDraftClockOffset);
}

/* Envois en attente, par clé@personnage : survivent à la réexécution du
 * fragment HUD et à la fermeture du panneau. */
window.forumDraftPending = window.forumDraftPending || {};

/* Brouillons abandonnés depuis plus de DRAFT_MAX_AGE, tous personnages.
 * Une date future (horloge en avance avant ce recalage) est ramenée à
 * maintenant : elle vieillira et sera purgée comme les autres. */
function purgeForumDrafts(){

    try {
        var now = draftNow();
        for(var i = localStorage.length - 1; i >= 0; i--){
            var k = localStorage.key(i);
            if(!k || k.indexOf(DRAFT_PREFIX) !== 0){
                continue;
            }
            var draft = null;
            try { draft = JSON.parse(localStorage.getItem(k)); } catch (err) { /* illisible : purgé */ }
            if(!draft || !draft.at || now - draft.at > DRAFT_MAX_AGE){
                localStorage.removeItem(k);
            }
            else if(draft.at > now){
                draft.at = now;
                localStorage.setItem(k, JSON.stringify(draft));
            }
        }
    } catch (err) { /* stockage indisponible : rien à purger */ }
}

/* Envoi d'un brouillon en attente ; l'échec est silencieux, la copie
 * locale reste et repartira à la prochaine ouverture. */
function sendForumDraft(id){

    var pending = window.forumDraftPending[id];
    if(!pending){
        return;
    }
    clearTimeout(pending.timer);
    delete window.forumDraftPending[id];
    pending.lastSent = Date.now();
    window.forumDraftLastSent = window.forumDraftLastSent || {};
    window.forumDraftLastSent[id] = pending.lastSent;

    var sentAt = Date.now();
    aooFetch(DRAFT_SYNC_URL, pending.payload).then(function(data){

        var result = data && data.result;
        if(!result || !result.serverNow){
            return;
        }
        /* l'heure serveur date du milieu de l'aller-retour */
        window.forumDraftClockOffset = result.serverNow - (sentAt + Date.now()) / 2;

        /* date future recalée par le serveur : la copie locale suit, si
         * elle n'a pas changé depuis l'envoi */
        if(result.at && result.at !== pending.payload.at){
            var name = DRAFT_PREFIX + pending.payload.key;
            var local = null;
            try { local = JSON.parse(aooStore.getLocal(name, pending.payload.owner)); } catch (err) { /* illisible */ }
            if(local && local.at === pending.payload.at && result.at < local.at){
                local.at = result.at;
                aooStore.setLocal(name, JSON.stringify(local), pending.payload.owner);
            }
        }
    }).catch(function(){ /* hors ligne */ });
}

/* Tout ce qui attend part par beacon : la page se ferme ou passe en
 * arrière-plan (mobile), une requête classique serait coupée. */
function flushForumDrafts(){

    Object.keys(window.forumDraftPending).forEach(function(id){

        var pending = window.forumDraftPending[id];
        clearTimeout(pending.timer);
        delete window.forumDraftPending[id];

        /* text/plain : un Blob JSON n'est pas accepté par tous les navigateurs */
        var body = new Blob([JSON.stringify(pending.payload)], {type: 'text/plain'});
        if(!navigator.sendBeacon || !navigator.sendBeacon(DRAFT_SYNC_URL, body)){
            aooFetch(DRAFT_SYNC_URL, pending.payload).catch(function(){ /* hors ligne */ });
        }
    });
}

/* Au plus un envoi par DRAFT_SYNC_DELAY pendant la frappe, et un dernier
 * après la dernière frappe. */
function scheduleForumDraftSync(id, payload){

    var lastSent = (window.forumDraftLastSent || {})[id] || 0;
    var pending = window.forumDraftPending[id];
    if(pending){
        clearTimeout(pending.timer);
    }

    var wait = Math.max(0, DRAFT_SYNC_DELAY - (Date.now() - lastSent));
    window.forumDraftPending[id] = {
        payload: payload,
        timer: setTimeout(function(){ sendForumDraft(id); }, wait)
    };
}

$(window).off('pagehide.forumDraft').on('pagehide.forumDraft', flushForumDrafts);
$(document).off('visibilitychange.forumDraft').on('visibilitychange.forumDraft', function(){
    if(document.visibilityState === 'hidden'){
        flushForumDrafts();
    }
});

$(document).ready(function(){


    $('#delete').click(function(e){

        aooConfirm('Êtes-vous certain de vouloir effacer le texte?').then(function(ok){

            if(ok){

                $('#forum-textarea textarea').val('').trigger('input').focus();
            }
        });
    });

    $('#add-rows').click(function(e){

        $('#forum-textarea textarea').css('height','+=200px');

    });

    $('#upload').click( function(e) {
        $('#drop_file_zone').show();
    });


    /* The form's own textarea: in the HUD, other panels may hold one too. */
    var $form = $('#forum-textarea').first();
    var $area = $form.find('textarea').first();
    var $title = $form.find('input.name').first();

    var draftName = $form.data('draft');
    var draftOwner = $form.data('draft-owner');

    window.forumDraftClear = function(){};

    if(!draftName || !draftOwner){
        return;
    }

    draftName = DRAFT_PREFIX + draftName;

    var serverNow = Number($form.data('draft-server-now'));
    if(serverNow){
        window.forumDraftClockOffset = serverNow - Date.now();
    }

    purgeForumDrafts();

    var syncId = draftName + '@' + draftOwner;

    /* Message envoyé : le serveur a effacé sa copie, rien ne doit plus
     * partir de ce brouillon. */
    window.forumDraftClear = function(){
        var pending = window.forumDraftPending[syncId];
        if(pending){
            clearTimeout(pending.timer);
            delete window.forumDraftPending[syncId];
        }
        aooStore.removeLocal(draftName, draftOwner);
    };

    var syncDraft = function(text, title, at){
        scheduleForumDraftSync(syncId, {
            'owner': draftOwner,
            'key': draftName.slice(DRAFT_PREFIX.length),
            'text': text,
            'title': title,
            'at': at
        });
    };

    var local = null;
    try { local = JSON.parse(aooStore.getLocal(draftName, draftOwner)); } catch (err) { /* illisible */ }
    if(local && local.at > draftNow()){
        local.at = draftNow();
        aooStore.setLocal(draftName, JSON.stringify(local), draftOwner);
    }
    var server = $form.data('draft-server') || null;

    var draft = local;
    if(server && (!local || server.at > local.at)){

        if((server.text || '') == '' && (server.title || '') == ''){
            /* envoyé ou effacé sur un autre appareil */
            window.forumDraftClear();
            draft = null;
        }
        else{
            draft = {'text': server.text, 'at': server.at};
            if($title.length){
                draft.title = server.title || '';
            }
            aooStore.setLocal(draftName, JSON.stringify(draft), draftOwner);
        }
    }
    else if(local && (!server || local.at > server.at)){
        /* tapé hors ligne, ou envoi coupé : le serveur rattrape */
        syncDraft(local.text || '', local.title || '', local.at);
    }

    /* En édition la textarea porte le message d'origine : le brouillon
     * ne le remplace que s'il en diffère. */
    if(draft && (draft.text !== $area.val() || (draft.title || '') !== ($title.val() || ''))){

        $area.val(draft.text || '');
        if($title.length){
            $title.val(draft.title || '');
        }
        $form.after('<div class="forum-draft-restored"><i>Brouillon restauré.</i></div>');
    }

    var saveDraft = function(){

        var text = $area.val();
        var title = $title.length ? $title.val() : '';
        var at = draftNow();

        /* vidé : le serveur en garde une trace pour les autres appareils */
        syncDraft(text, title, at);

        if(text.trim() == '' && title.trim() == ''){
            aooStore.removeLocal(draftName, draftOwner);
            return;
        }

        var value = {'text': text, 'at': at};
        if($title.length){
            value.title = title;
        }
        aooStore.setLocal(draftName, JSON.stringify(value), draftOwner);
    };

    $area.on('input', saveDraft);
    $title.on('input', saveDraft);

});




function insert_img(url) {


    // Préparer le texte à insérer
    var imgText = '[img]' + url + '[/img]';

    insert_textarea(imgText);
}

function insert_textarea(addText){

    // Sélectionner l'élément textarea
    var $textarea = $('#forum-textarea textarea');

    // Obtenir la position actuelle du curseur
    var curPos = $textarea.prop('selectionStart');

    // Obtenir la valeur actuelle de la zone de texte
    var text = $textarea.val();

    // Insérer le texte à la position du curseur
    var newText = text.slice(0, curPos) + addText + text.slice(curPos);

    // Mettre à jour la valeur de la zone de texte (input : enregistre le brouillon)
    $textarea.val(newText).trigger('input');

    // Ajuster la position du curseur après l'insertion du texte
    $textarea.prop('selectionStart', curPos + addText.length);
    $textarea.prop('selectionEnd', curPos + addText.length);

    // Mettre le focus sur la zone de texte
    $textarea.focus();
}
