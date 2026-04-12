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

        if(!confirm('Oublier '+ name +'?')){

            $('.forget').prop('disabled', false);
            return false;
        }

        $.ajax({
            type: "POST",
            url: targetUrl,
            data: postData,
            success: function(data)
            {
                // alert(data);
                document.location.reload();
            },
            error: function() {
                alert("Erreur lors de la suppression.");
                $('.forget').prop('disabled', false);
            }
        });
    });
});
