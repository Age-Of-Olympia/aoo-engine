$(document).ready(function() {

    var tagsEqual = ['url'];
    window.tagsTbl = [];

    $('.tool-button button').click(function(e) {
        e.preventDefault();

        let tag = $(this).data('tag');
        let openTag = tag;
        let closeTag = tag;

        if (tagsEqual.includes(tag)) {
            openTag = tag + '=';  // Ajouter le signe égal uniquement à la balise d'ouverture
        }

        if (window.tagsTbl[tag] == null) {
            window.tagsTbl[tag] = true;
            let addText = '[' + openTag + ']';
            wrapSelectedText(addText, '[/' + closeTag + ']');
        } else {
            window.tagsTbl[tag] = null;
            let addText = '[/' + closeTag + ']';
            wrapSelectedText(addText, '');
        }
    });

    // Fonction pour insérer du texte dans le textarea à l'endroit de la sélection
    function wrapSelectedText(beforeText, afterText) {
        let textarea = $('textarea');  // Remplacez cela par votre sélecteur de textarea
        let start = textarea[0].selectionStart;
        let end = textarea[0].selectionEnd;
        let text = textarea.val();

        if (start !== end) {
            // Il y a une sélection, on insère autour du texte sélectionné
            let selectedText = text.substring(start, end);
            let newText = text.substring(0, start) + beforeText + selectedText + afterText + text.substring(end);
            textarea.val(newText);
        } else {
            // Pas de sélection, on insère simplement à la position actuelle du curseur
            let newText = text.substring(0, start) + beforeText + afterText + text.substring(end);
            textarea.val(newText);
        }

        // Replace le curseur à la fin de l'insertion
        textarea[0].selectionStart = textarea[0].selectionEnd = start + beforeText.length;
        textarea.focus();
    }
});
