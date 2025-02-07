
$(document).ready(function(){

    $('.pnj').click(function(e){
        $.ajax({
            type: "POST",
            url: 'pnjs.php',
            data: {'switch':$(this).data('id')}, // serializes the form's elements.
            success: function(data)
            {
                document.location = 'index.php';
            }
        });
    });

    $('.bulle').click(function(e){

        $.ajax({
            type: "POST",
            url: 'pnjs.php',
            data: {'switch':$(this).data('id')}, // serializes the form's elements.
            success: function(data)
            {
                document.location = 'forum.php?forum=Missives';
            }
        });
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
        $.ajax({
            type: "POST",
            url: 'pnjs.php',
            data: {'switch':$(this).data('id')},    
            success: function(data)
            {
                document.location = 'index.php';
            }
        });
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