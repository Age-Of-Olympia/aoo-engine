var i = $wall.attr('x');
var j = $wall.attr('y');


// only on go cases (at distance 1)
if($('.go[x="'+ i +'"][y="'+ j +'"]')[0] != null){


    $('#destroy-rect')
        .show()
        .attr({'x': i, 'y': j})
        .data('coords', x +','+ y);

    var imgY = j - 20 ;

    $('#destroy-img').show().attr({'x': i, 'y': imgY});


    $('#destroy-rect').click(function(e){

        if(!confirm("Détruire ce mur? (1A)")){
            return false;
        }

        var wallId = $wall.attr('id');

        $.ajax({
            type: "POST",
            url: 'destroy.php',
            data: {'wallId':wallId}, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
                document.location.reload();
            }
        });
    });
}
