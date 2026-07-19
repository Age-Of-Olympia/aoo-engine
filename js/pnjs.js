
/* Bascule de personnage : le HUD persiste ses panneaux ouverts dans
 * sessionStorage et les restaure au rechargement — sans purge, le menu
 * des pnj se rouvrirait tout seul une fois le personnage changé
 * (agaçant sur smartphone où le panneau couvre tout). */
function forgetPnjPanel(){

    ['hudPanels', 'hudPanelHistory'].forEach(function(key){
        try {
            var panels = JSON.parse(sessionStorage.getItem(key) || '[]');
            panels = panels.filter(function(p){
                return ((p && p.url) || '').indexOf('load_pnjs.php') === -1;
            });
            sessionStorage.setItem(key, JSON.stringify(panels));
        } catch (err) { /* stockage illisible : ne pas bloquer la bascule */ }
    });
}

/* POST de bascule commun aux trois entrées (.pnj, .bulle, .impersonate) */
function switchToPnj(id, destination){

    $.ajax({
        type: "POST",
        url: 'pnjs.php',
        data: {'switch': id},
        success: function(data)
        {
            forgetPnjPanel();
            document.location = destination;
        }
    });
}

$(document).ready(function(){

    $('.pnj').click(function(e){
        switchToPnj($(this).data('id'), 'index.php');
    });

    $('.bulle').click(function(e){
        switchToPnj($(this).data('id'), 'forum.php?forum=Missives');
    });

    $(".masquer-pnj").click(function(e){
        e.stopPropagation();
        let payload = {
            playerId:$(this).data('player-id'),
            pnjId:$(this).data('id'),
            display: false
        };
        editPnjVisibility(payload);
    })

    $("article.pnj").hover(
        function() {
            $(this).find(".masquer-pnj").fadeIn(); // Afficher avec un effet de fondu
        },
        function() {
            $(this).find(".masquer-pnj").fadeOut(); // Masquer avec un effet de fondu
        }
    );

    $("#display-hidden-pnjs").click(function(e){
            $("#hidden-pnjs-list").toggle(); 
    });

    $("button.showPnj").click(function(e){
        let payload = {
            playerId:$(this).data('player-id'),
            pnjId:$(this).data('id'),
            display: true
        };
        editPnjVisibility(payload);
    });

    $("button.impersonate").click(function(e){
        switchToPnj($(this).data('id'), 'index.php');
    });
});

function editPnjVisibility(payload){
    
    let url= 'api/pnjs/pnjs-edit.php';
    aooFetch(url,payload,null)
    .then(data => {
        document.location='pnjs.php'
    })
    .catch((error) => {
        console.error('Error:', error);
        location.reload();
      });
    
}