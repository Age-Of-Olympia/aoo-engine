$(document).ready(function(){

    $('button[data-change="name"]').click(function(e){

        if(window.alreadyChanged){

            alert('Vous avez déjà changé de nom une fois.\nDemandez à un Admin si vous souhaitez le modifier une fois de plus.');

            return false;
        }

        aooPrompt('Nouveau nom:').then(function(name){

            if(name == null || name.trim() == ''){

                return;
            }


            var oldName = window.oldName;

            if(name == oldName){

                aooAlert('Le nouveau nom est identique à l\'ancien nom.');

                return;
            }

            $.ajax({
                type: "POST",
                url: 'account.php',
                data: {'changeName': name}, // serializes the form's elements.
                success: function(data)
                {
                    htmlContent = $('<div>').html(data).find('#data').html();
                    aooAlert($('<div>').html(htmlContent).text());
                }
            });
        });
    });

    $('.option').click(function(e){

        e.preventDefault();

        var $box = $(this);

        if($(this).data('option') == 'reloadView'){

            $.ajax({
                type: "POST",
                url: 'refresh_view.php',
                data: {}, // serializes the form's elements.
                success: function(data)
                {
                    alert(data);

                    $box.prop('checked', true);
                }
            });

            return false;
        }


        $.ajax({
            type: "POST",
            url: 'account.php',
            data: {
                'option': $box.data('option')
            }, // serializes the form's elements.
            success: function(data)
            {
                $box.prop('checked', !$box.prop('checked'));

                /* Option de plateau basculée depuis le panneau Profil
                 * (HUD) : le plateau se rafraîchit comme depuis le
                 * popover de calques — à chaud ou par rechargement,
                 * qui vaut confirmation. Habillage hérité (ou option
                 * sans rapport avec le plateau) : alerte d'origine. */
                if(window.hudApplyBoardOption && window.hudApplyBoardOption($box.data('option'), $box.prop('checked'))){

                    return;
                }

                // alert(data);
                alert('Changement effectué.');
            }
        });
    });

    $('#next-turn-apply').click(function(e){

        e.preventDefault();

        var value = $('#next-turn-input').val();

        if(!value){

            alert('Choisissez une date et une heure.');

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'api/player/set_next_turn.php',
            data: {'nextTurn': value},
            dataType: 'json',
            success: function(data)
            {
                alert('Prochain tour déplacé au ' + data.formatted + '.');

                /* reload so the reschedule window (min/max) is recomputed
                   from the new next turn time */
                window.location.reload();
            },
            error: function(xhr)
            {
                var data = xhr.responseJSON;

                alert((data && data.error) || 'Erreur lors du changement de tour.');
            }
        });
    });

    $('.change-mail').click(function(e){
        e.preventDefault();

        $("#email-dialog").dialog({
            modal: true,
            width: 400,
            buttons: {
                "Enregistrer": function() {
                    var mail = $("#new-email").val();
                    
                    if(!mail || mail == ''){
                        return false;
                    }

                    if(!isEmail(mail)){
                        alert('Cette adresse mail n\'est pas valide.');
                        return false;
                    }

                    $.ajax({
                        type: "POST",
                        url: 'scripts/account/change_mail.php',
                        data: {'changeMail': mail},
                        success: function(data) {
                            var htmlContent = $(data).filter('#data').html();
                            alert(htmlContent);
                            if(htmlContent.includes('succès')) {
                                $("#current-email").text(mail);
                                $("#email-dialog").dialog("close");
                            }
                        }
                    });
                },
                "Annuler": function() {
                    $(this).dialog("close");
                }
            }
        });
    });

});

function isEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}
