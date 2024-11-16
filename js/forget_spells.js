$(document).ready(function(){

    $('.forget').click(function(e){

        $('.forget').prop('disabled', true);

        let spell = $(this).data('spell');
        let name = $(this).data('name');

        if(!confirm('Oublier '+ name +'?')){

            $('.forget').prop('disabled', false);
            return false;
        }

        $.ajax({
            type: "POST",
            url: 'upgrades.php?spells&forget',
            data: {'spell':spell}, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                document.location.reload();
            }
        });
    });
});
