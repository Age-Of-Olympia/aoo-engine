



// copy to clipboard
function copyToClipboard(element) {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(element).text()).select();
    document.execCommand("copy");
    $temp.remove();
    $(element).text('Copié!');
}


// load data on element
function load_data(data, element){

    if(!$(element)[0]){

        alert(data);

        return false;
    }

    $(element).html(data);
}

function aooFetch(url,  payload = null, method = null,autoProcess = true) {
    const headers = { 'Content-Type': 'application/json' }
    const config = {
        method: method?method:payload ? 'POST' : 'GET',
        headers: {
            ...headers,
            //todo: add token
        },
    }
    if (payload) {
        config.body = JSON.stringify(payload)
    }

    return window
        .fetch(url, config)
        .then((response) => {
            if (response.ok) {
                if (autoProcess) {
                    return response.json()
                } else {
                    return Promise.resolve(response);
                }
            } else {
                const errorMessage = response.text()
                return Promise.reject(new Error(errorMessage))
            }
        })
}

function autoModal(data) {
    if (data.error) {
        aooAlert(data.error);
    }
    else if (data.result) {
        if (data.result.message && data.result.redirect) {
            /* Message PUIS redirection : l'alerte modale n'est pas bloquante */
            aooAlert(data.result.message).then(function () {
                document.location = data.result.redirect;
            });
            return;
        }
        if (data.result.message)
            aooAlert(data.result.message);
        if (data.result.redirect)
            document.location = data.result.redirect;
    }
}

/* NB : le paramètre s'appelait « alert » et masquait window.alert —
 * l'appeler levait TypeError et le reload n'arrivait jamais. */
function autoError(log=true,showAlert=true,reload=true) {
    return function (error) {
        if (log) {
            console.error('Error:', error);
        }
        if (showAlert) {
            aooAlert('Une erreur est survenue, veuillez réessayer.').then(function () {
                if (reload) {
                    location.reload();
                }
            });
            return;
        }
        if (reload) {
            location.reload();
        }
    }
}

/* Paramètre d'URL de la vue courante : dans un panneau HUD, l'URL de
 * la page (index.php) ne porte pas les paramètres du fragment — le
 * routeur (js/hud.js) les expose dans window.hudPanelQuery. Les
 * scripts partagés (marché, contrats…) doivent lire targetId ici
 * plutôt que dans window.location.search. */
function aooViewParam(name) {

    if (window.hudPanelQuery) {

        var value = new URLSearchParams(window.hudPanelQuery).get(name);
        if (value !== null) {
            return value;
        }
    }

    return new URLSearchParams(window.location.search).get(name);
}

/* Recharge la vue après une action (achat, apprentissage, échange,
 * équipement…) : dans le HUD, recharge le panneau ouvert et les
 * valeurs vivantes du bandeau sans toucher à la page — un reload
 * fermait le panneau (retours joueurs juillet 2026) ; dans
 * l'habillage hérité, reload classique. Point unique : tous les
 * scripts d'action doivent passer par ici plutôt que par
 * document.location.reload(). */
function aooReload() {

    if (window.hudReloadPanels) {

        window.hudReloadPanels();
        if (window.hudRefreshAfterAction) {
            window.hudRefreshAfterAction();
        }
        return;
    }

    document.location.reload();
}

// preload img
function preload(img, element){


    let $target = element;

    // filler
    $target.animate(

            {opacity:0},

            100,

            function(){

                // Créer un nouvel objet Image
                let mainImage = new Image();

                mainImage.src = img;

                mainImage.onload = function() {

                    $target.attr("src", this.src).animate({opacity:1}, 300);
                };

                // En cas d'erreur de chargement
                mainImage.onerror = function() {

                    alert('error preloading img: '+ img);

                    $target.attr("src", img);
                };
            }
    );
}

$(document).ready(function(){


    // ctrl enter to submit
    $('textarea').keydown( function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.keyCode == 13 || e.keyCode == 10)) {

            $('form').submit();

            $('.submit').click();
        }
    });


    // close card & dialog
    function close_all(){

        if($('#console-wrapper').is(':visible')){
            $('#console-wrapper').hide();
            document.location.reload();
            return false;
        }

        $('#ui-card').hide();
        $('#ui-dialog').hide();
        $('#console-wrapper').hide();
        $('#input-line').val('');
    }


    // special listener for escape key
    document.body.addEventListener('keydown', function(e) {


        if (e.key == "Escape") {
            close_all();
        }

    });

    //bind console keys
    bind_console_keys(document.body);

    // check mail
    const baseTitle = $(document).prop('title');

    var checkMailFunction = function () {

        let url = 'check_mail.php';
        aooFetch(url)
            .then(data => {
                let avatar = $('#player-avatar');
                let currentPlayerId = parseInt(avatar.attr('data-id'));

                let otherCharactersNewMails = 0;
                let currentCharacterNewMails = 0;
                for (const playerid in data) {
                    if (playerid == currentPlayerId) {
                        currentCharacterNewMails = data[playerid];
                    } else {
                        otherCharactersNewMails += data[playerid];
                    }
                }
                let totalNewMails = otherCharactersNewMails + currentCharacterNewMails;

                let popupOtherCharacter = $('#other-characters-mails');
                if (!popupOtherCharacter.length)
                    popupOtherCharacter = $('<div id="other-characters-mails" class="cartouche bulle blink" style="pointer-events: none; display:none; background:blue;"></div>').appendTo(avatar);

                let popupCurrentCharacter = $('#current-characters-mails');
                if (!popupCurrentCharacter.length)
                    popupCurrentCharacter = $('<div id="current-characters-mails" class="cartouche bulle blink" style="pointer-events: none; display:none;"></div>').appendTo('#missive-btn');

                popupCurrentCharacter.text(currentCharacterNewMails);
                popupCurrentCharacter.toggle(currentCharacterNewMails > 0);

                popupOtherCharacter.text(otherCharactersNewMails);
                popupOtherCharacter.toggle(otherCharactersNewMails > 0);

                /* Mobile (HUD) : le rail vit dans le tiroir fermé — écho
                 * du badge missives sur le bouton burger. */
                let burgerBadge = $('#hud-burger-mails');
                if (!burgerBadge.length && $('#hud-burger').length)
                    burgerBadge = $('<span id="hud-burger-mails" class="cartouche bulle blink" style="pointer-events: none; display:none;"></span>').appendTo('#hud-burger');

                burgerBadge.text(currentCharacterNewMails);
                burgerBadge.toggle(currentCharacterNewMails > 0);

                // change favicon
                $("link[rel*='icon']").attr("href", totalNewMails > 0 ? "img/ui/favicons/favicon_alert.png" : "img/ui/favicons/favicon.png");

                // change title
                var newTitle = baseTitle;
                if (totalNewMails > 0) {
                    newTitle = '(' + totalNewMails + ') ' + newTitle;
                }
                $(document).prop('title', newTitle);
            })
            .catch((error) => {
                console.error('Error:', error);
            });

        /* Ré-armement à tir unique : un appel manuel (lecture de
         * missive en panneau) ne doit pas empiler une seconde chaîne
         * de polls parallèle. */
        clearTimeout(window.checkMailTimer);
        window.checkMailTimer = setTimeout(checkMailFunction, 60000);

    }

    /* Exposée : lire une missive en panneau HUD (sans rechargement)
     * doit rafraîchir les badges sans attendre le poll de 60 s. */
    window.refreshMailBadges = checkMailFunction;

    if($('#player-avatar')[0] != null){

        setTimeout(checkMailFunction, 1);
    }


    window.addEventListener('wheel', function(event) {
        if (document.body.scrollHeight <= window.innerHeight && event.deltaY !== 0) {
            // Si le contenu du corps ne déborde pas verticalement
            event.preventDefault();
            window.scrollBy(event.deltaY, 0);
        }
    });
});

