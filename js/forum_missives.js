$(document).ready(function() {

    $('.post-rewards').addClass('desaturate');


    $('#add-dest').click(function(e) {


        $('#dest').hide();
        $('#dest-list').show();
    });


    $('#dest-list').on('change', function(e) {


        var dest = $(this).val();

        $.ajax({
            type: "POST",
            url: 'forum.php?topic='+ window.topName,
            data: {'addDest': dest},
            success: function(data) {
              //debugger;
              document.location.reload();
            }
        });
    });


    $('.dest').click(function(e) {


        var dest = $(this).data('id');

        if (!confirm('Supprimer ce personnage de la conversation?')) {
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'forum.php?topic='+ window.topName,
            data: {'removeDest': dest},
            success: function(data) {
                document.location.reload();
            }
        });
    });
});
