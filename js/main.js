// ctrl enter to submit textarea
$(document).ready(function(){

    $('textarea').keydown( function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.keyCode == 13 || e.keyCode == 10)) {

            $('form').submit();

            $('.submit').click();
        }
    });


    function close_all(){

        $('#ui-card').hide();
        $('#ui-dialog').hide();
    }


    // special listener for escape key
    document.body.addEventListener('keydown', function(e) {
        if (e.key == "Escape") {
            close_all();
        }
        else if (e.keyCode == 176) {

            open_console(false);
        }
    });
});


function open_console(defaultCmd){


    var lastCmd = '';

    $.ajax({
        type: "POST",
        url: 'console.php',
        data: {'getLastCmd':1}, // serializes the form's elements.
        success: function(data)
        {

            lastCmd = data.trim();


            if($('.reply')[0] != null){

                lastCmd = 'topic '+ $('.reply').data('topic');
            }

            if(defaultCmd){

                lastCmd = defaultCmd;
            }

            var cmd = prompt('', lastCmd);

            if(cmd != null && cmd != ''){

                $.ajax({
                    type: "POST",
                    url: 'console.php',
                    data: {'cmd':cmd}, // serializes the form's elements.
                    success: function(data)
                    {
                        alert(data);

                        if(data.trim() == 'editor'){

                            document.location = 'editor.php';

                            return false;
                        }
                        else if(data.trim() == 'tiled'){

                            document.location = 'tiled.php';

                            return false;
                        }
                        else if(data.slice(-5) == ',json'){

                            document.location = 'editor.php?url='+ data.trim();

                            return false;
                        }
                        else if(data.slice(-5) == ',card'){

                            document.location = 'print_card.php?playerId='+ data.trim();

                            return false;
                        }


                        document.location.reload();
                    }
                });
            }
        }
    });



    return false;
}


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

    const baseTitle = $(document).prop('title');

    var checkMailFunction = function () {

        $.ajax({
            type: "GET",
            url: 'check_mail.php',
            data: {}, // serializes the form's elements.
            success: function(data)
            {
                if(data.trim() != '0'){

                    var $avatar = $('#player-avatar');

                    var $popup = $('<div class="cartouche bulle blink" style="pointer-events: none;">'+ data.trim() +'</div>');

                    $avatar.append($popup);

                    // change favicon
                    $("link[rel*='icon']").attr("href", "img/ui/favicons/favicon_alert.png");

                    // change title
                    var newTitle = '('+ data.trim() +') '+ baseTitle;

                    $(document).prop('title', newTitle);
                }
            }
        });

        setTimeout(checkMailFunction, 60000);

    }

    if($('#player-avatar')[0] != null){

        setTimeout(checkMailFunction, 1);
    }
});

