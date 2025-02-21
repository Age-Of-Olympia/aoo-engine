$(document).ready(function() {

    $('.post-rewards').addClass('desaturate');


    $('#add-dest').click(function(e) {
        
        $('#dest').hide();
        $('#dest-list').show();
        $('#autocomplete').show();

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
                $dataHtml = $('<div>');
                $dataHtml.html(data);
                if($dataHtml.find('#error')[0] != null){
                    alert($dataHtml.find('#error').text());
                }
                else{
                    document.location.reload();
                }
            }
        });
    });
});
