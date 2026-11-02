/* Brouillons forum et missives : un par formulaire (réponse à un sujet,
 * nouveau sujet dans un forum, édition d'un message) et par personnage,
 * écrits dans localStorage à chaque frappe pour survivre à la fermeture
 * du panneau ou de la page. La clé et le personnage qui écrit viennent du
 * serveur (data-draft, data-draft-owner) : le fragment sait quel
 * personnage postera, la page hôte peut l'ignorer après un changement de
 * pnj dans un autre onglet. */
var DRAFT_PREFIX = 'forumDraft:';
var DRAFT_MAX_AGE = 30 * 24 * 3600 * 1000;

/* Brouillons abandonnés depuis plus de DRAFT_MAX_AGE, tous personnages. */
function purgeForumDrafts(){

    try {
        var now = Date.now();
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
        }
    } catch (err) { /* stockage indisponible : rien à purger */ }
}

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

    purgeForumDrafts();

    window.forumDraftClear = function(){
        aooStore.removeLocal(draftName, draftOwner);
    };

    var draft = null;
    try { draft = JSON.parse(aooStore.getLocal(draftName, draftOwner)); } catch (err) { /* illisible */ }

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

        if(text.trim() == '' && title.trim() == ''){
            window.forumDraftClear();
            return;
        }

        var value = {'text': text, 'at': Date.now()};
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
