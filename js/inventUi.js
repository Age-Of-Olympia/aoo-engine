$(document).ready(function(){


    var $previewImg = $(".preview-img img");

    // first img preload
    // preload($previewImg.data("src"), $previewImg);

    $(".item-case").click(function(e){


        var $item = $(this);

        window.id =  $item.data("id");
        window.name =  $item.data("name");
        window.type =  $item.data("type");
        window.emplacement =  $item.data("emplacement");
        window.n =     $item.data("n");
        let text =  $item.data("text");
        window.price = $item.data("price");
        let infos = $item.data("infos");
        let img =   $item.data("img");


        if($('.emplacement[data-id="'+ window.id +'"]')[0] != null){

            $('.action[data-action="use"]')
            .html('Déséquiper')
            .prop('disabled', false);
        }
        else{

            if(window.freeEmp && window.type == 'equipement'){

                $('.action[data-action="use"]')
                .html('Équiper (1 Ae)')
                .prop('disabled', (window.aeLeft <= 0));
            }
            else if(!window.freeEmp && window.type == 'equipement'){
                if(window.emplacement == "munition" || window.emplacement == "doigt"){
                    $('.action[data-action="use"]')
                    .html('Équiper (1 Ae)')
                    .prop('disabled', (window.aeLeft <= 0));
                }
                else{
                    $('.action[data-action="use"]')
                    .html('<font color="red">Équiper (Max.)</font>')
                    .prop('disabled', (window.aeLeft <= 0));

                }
            }
            else if(window.type == 'parchemin' || window.emplacement != ''){

                $('.action[data-action="use"]')
                .html('Utiliser (1 Ae)')
                .prop('disabled', (window.aeLeft <= 0));
            }
            else if(window.type == 'consommable' || window.type == 'structure'){

                $('.action[data-action="use"]')
                .html('Utiliser (1 A)')
                .prop('disabled', (window.aLeft <= 0));
            }
            else{

                $('.action[data-action="use"]')
                .html('Utiliser')
                .prop('disabled', true);
            }
        }


        $(".preview-n").text('x'+ n);
        $(".preview-text").text(text);

        preload(img, $previewImg);
    });


    $('#item-search').click(function(){


        $(this).css({'opacity':'1'}).removeClass('desaturate')


        if($(this).val() == 'chercher'){

            $(this).val('');
        }
    })
    .on('blur', function(){

        $(this).addClass('desaturate').css({'opacity':'0.5'});
    })
    .on('keyup', function(){


        // Récupère la valeur de l'input et la convertit en minuscules
        var name = $(this).val().toLowerCase();

        var $search = null;

        // Parcourt tous les éléments avec l'attribut data-name
        $('[data-name]').each(function() {
            var dataName = $(this).data('name').toLowerCase();

            // Vérifie si data-name contient la valeur de name
            if (dataName.includes(name)) {
                $search = $(this);
                return false; // Sortir de la boucle each() si un élément est trouvé
            }
        });

        // Si aucun élément n'est trouvé, sortir de la fonction
        if (!$search) {
            return false;
        }

        document.location = '#'+ $search.attr('id');

        $(this).focus();
    });
});
