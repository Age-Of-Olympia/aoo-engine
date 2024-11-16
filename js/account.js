$(document).ready(function(){

    $('button[data-change="name"]').click(function(e){

        if(window.alreadyChanged){

            alert('Vous avez déjà changé de nom une fois.\nDemandez à un Admin si vous souhaitez le modifier une fois de plus.');

            return false;
        }

        var name = prompt('Nouveau nom:');


        if(name == null || name.trim() == ''){

            return false;
        }


        var oldName = window.oldName;

        if(name == oldName){

            alert('Le nouveau nom est identique à l\'ancien nom.');

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'account.php',
            data: {'changeName': name}, // serializes the form's elements.
            success: function(data)
            {
                htmlContent = $('<div>').html(data).find('#data').html();
                alert(htmlContent);
            }
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

                // alert(data);
                alert('Changement effectué.');

                $box.prop('checked', !$box.prop('checked'));
            }
        });
    });

    $('.change-mail').click(function(e){


        e.preventDefault();

        var mail = prompt('Entrez une adresse mail valide:');

        if(!mail || mail == ''){

            return false;
        }

        if(!isEmail(mail)){

            alert('Cette adresse mail n\'est pas valide.');

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'account.php',
            data: {
                'changeMail': mail
            }, // serializes the form's elements.
            success: function(data)
            {

                // alert(data);
                alert('Changement effectué.');
            }
        });
    });
});

function isEmail(email) {
    var regex = /^([a-zA-Z0-9_.+-])+\@(([a-zA-Z0-9-])+\.)+([a-zA-Z0-9]{2,4})+$/;
    return regex.test(email);
}
