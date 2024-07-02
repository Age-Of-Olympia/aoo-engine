<?php

if(isset($_GET['forget']) && !empty($_POST['spell'])){

    include('scripts/upgrades/forget_spell.php');
    exit();
}


echo '
<table class="box-shadow marbre" border="1" align="center">';


echo '<tr><th colspan="2">Sort</th><th></th><th>Coût</th><th colspan="2">Effet</th></tr>';


foreach($player->get_spells() as $e){


    $spellJson = json()->decode('actions', $e);


    $img = (!empty($spellJson->img)) ? $spellJson->img : 'img/spells/'. $e .'.jpeg';


    echo '
    <tr>
        <td valign="top">
            <img src="'. $img .'" width="50" />
        </td>
        <td align="left">
            '. $spellJson->name .'
        </td>
        <td>
            <span class="ra '. $spellJson->raFont .'"></span>
        </td>
        <td>
            '. implode(', ', Item::get_cost($spellJson->costs)) .'
        </td>
        <td align="left">
            '. $spellJson->text .'
        </td>
        ';


        if(isset($_GET['forget'])){

            echo '
            <td valign="top">
                <input
                    type="button"
                    class="forget"
                    data-spell="'. $e .'"
                    data-name="'. $spellJson->name .'"
                    value="Oublier"
                    />
            </td>
            ';
        }

        echo '
    </tr>
    ';
}


if(!isset($_GET['forget'])){

    echo '
    <tr>
        <td colspan="5" align="right">

            <a href="upgrades.php?spells&forget"><button>Oublier un sort</button></a>
        </td>
    </tr>
    ';
}

echo '
</table>
';

?>
<script>
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
</script>
