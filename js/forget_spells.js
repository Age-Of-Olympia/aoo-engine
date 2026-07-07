$(document).ready(function(){

    $('.forget').click(function(e){

        $('.forget').prop('disabled', true);

        let spell = $(this).data('spell');
        let passive = $(this).data('passive');
        let name = $(this).data('name');

        let postData = {};
        let targetUrl = '';

        if (passive !== undefined) {
            postData = {'passive': passive};
            targetUrl = 'upgrades.php?spells&forget_p';
        } else {
            postData = {'spell': spell};
            targetUrl = 'upgrades.php?spells&forget';
        }

        aooConfirm('Oublier '+ name +'?').then(function(ok){

            if(!ok){

                $('.forget').prop('disabled', false);
                return;
            }

            $.ajax({
                type: "POST",
                url: targetUrl,
                data: postData,
                success: function(data)
                {
                    document.location.reload();
                },
                error: function() {
                    aooAlert("Erreur lors de la suppression.");
                    $('.forget').prop('disabled', false);
                }
            });
        });
    });
});
