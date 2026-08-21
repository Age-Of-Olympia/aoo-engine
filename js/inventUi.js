$(document).ready(function(){


    var $previewImg = $(".preview-img img");

    // first img preload
    // preload($previewImg.data("src"), $previewImg);

    $(".item-case").click(function(e){


        var $item = $(this);

        window.id =  $item.data("id");
        /* Instance PRÉCISE cliquée (ligne individualisée) — null sur une
           ligne de pile, pour ne pas laisser fuiter la sélection
           précédente. */
        window.instanceId = $item.data("instance-id") || null;
        /* LA ligne cliquée est-elle portée ? C'est elle qui décide du
           sens de la bascule côté serveur (rowEquipped) : un arc abîmé
           porté ne doit pas transformer « équiper » le lot neuf en
           déséquipement. */
        window.rowEquipped = String($item.data("equiped")) === "1";
        /* Ce que « Utiliser » ferait, décidé par le serveur
           (InventoryService::useKind) : equip | consume | vide. */
        window.useKind = $item.data("use-kind") || '';
        window.name =  $item.data("name");
        window.type =  $item.data("type");
        window.emplacement =  $item.data("emplacement");
        window.n =     $item.data("n");
        let text =  $item.data("text");
        window.price = $item.data("price");
        window.buildAction = $item.data("build-action");
        /* A lockable type (chest): the build picker will offer the
           personal/faction owner choice. */
        window.lockable = String($item.data("lockable")) === "1";
        /* Cut-out of the built form (offsets JSON) and its sprite — the
           avatar, or the initials-frame data URI — for the picker's ghost. */
        window.buildFootprint = $item.data("fp") || null;
        window.buildGhostImg = $item.attr("data-fp-img") || null;
        let infos = $item.data("infos");
        let img =   $item.data("img");
        let bankable = $item.data("bankable") ;

        $('.action[data-action="store"]').prop('disabled', !bankable);

        /* « Déséquiper » seulement si LA ligne cliquée est portée — le
           test hérité par objet catalogue (.emplacement[data-id])
           affichait Déséquiper sur le lot neuf dès qu'un exemplaire
           abîmé du même objet était porté. */
        if(window.rowEquipped){

            $('.action[data-action="use"]')
            .html('Déséquiper')
            .prop('disabled', false);
        }
        else{

            if(window.type == 'constructible'){

                /* Bâtir depuis l'objet : un bouton par objet possédé. */
                $('.action[data-action="use"]')
                .html('Construire (1 A)')
                .prop('disabled', (window.aLeft <= 0));
            }
            else if(window.useKind == 'equip' && window.type == 'equipement' && !window.freeEmp
                && window.emplacement != 'munition' && window.emplacement != 'doigt'){

                $('.action[data-action="use"]')
                .html('<font color="red">Équiper (Max.)</font>')
                .prop('disabled', (window.aeLeft <= 0));
            }
            else if(window.useKind == 'equip'){

                $('.action[data-action="use"]')
                .html((window.type == 'equipement' ? 'Équiper' : 'Utiliser') + ' (1 Ae)')
                .prop('disabled', (window.aeLeft <= 0));
            }
            else if(window.useKind == 'consume'){

                $('.action[data-action="use"]')
                .html('Utiliser (1 A)')
                .prop('disabled', (window.aLeft <= 0));
            }
            else{

                /* Aucun usage réel (décision serveur, data-use-kind vide) :
                   plus jamais de « Utiliser » qui ne fait rien. */
                $('.action[data-action="use"]')
                .html('Sans usage')
                .prop('disabled', true);
            }
        }

        $(".preview-n").text('x'+ n);
        /* État d'une instance (durabilité / brisé) : rempli seulement
           quand la ligne en porte un, vidé sinon. */
        $(".preview-state").text($item.data("state") || '');
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
