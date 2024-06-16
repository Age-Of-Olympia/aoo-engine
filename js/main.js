// ctrl enter to submit textarea
$(document).ready(function(){

    $('textarea').keydown( function(e) {
        if ((e.ctrlKey || e.metaKey) && (e.keyCode == 13 || e.keyCode == 10)) {

            $('form').submit();

            // alert();

            auto_save(true);
        }
    });

    // special listener for escape key
    document.body.addEventListener('keydown', function(e) {
        if (e.key == "Escape") {
            $('#ui-card').hide();
            $('#ui-item-list').hide();
            $('#ui-dialog').hide();
            $('#ui-map').hide();
            $('#ui-profil').hide();
        }
        else if (e.keyCode == 176) {

            e.preventDefault();

            var lastCmd = '';

            $.ajax({
                type: "POST",
                url: 'console.php',
                data: {'getLastCmd':1}, // serializes the form's elements.
                success: function(data)
                {

                    lastCmd = data.trim();

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
                                }
                                else if(data.trim() == 'tiled'){

                                    document.location = 'tiled.php';
                                }
                                else if(data.slice(-5) == ',json'){

                                    document.location = 'editor.php?url='+ data.trim();
                                }
                            }
                        });
                    }
                }
            });



            return false;
        }
    });
});


// copy to clipboard
function copyToClipboard(element) {
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val($(element).text()).select();
    document.execCommand("copy");
    $temp.remove();
    $(element).text('Copié dans le presse-papier!');
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
