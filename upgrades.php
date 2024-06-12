<?php

require_once('config.php');


// caracs trio
$trio['pv'] = array(4,2,1);
$trio['ct'] = array(110,50,30);
$trio['f'] = array(120,55,30);
$trio['agi'] = array(95,45,25);
$trio['e'] = array(120,55,30);
$trio['pm'] = array(5,3,1);
$trio['fm'] = array(100,50,30);
$trio['m'] = array(110,55,35);
$trio['a'] = array(800,200,100);
$trio['mvt'] = array(100,50,30);
$trio['r'] = array(70,55,30);
$trio['rm'] = array(50,40,20);
$trio['cc'] = array(100,50,30);
$trio['p'] = array(110,85,78);
$trio['spd'] = array(400,100,50);


// return cost
function return_cost( $progress, $upgraded ){

    $next = $upgraded + 1;

    $total = $progress[0];

    for( $i = 1; $i < $next; $i++ ){

        if( $i < 3 ){

            $total = $total + $progress[1];
        }
        elseif( $i >= 3 ){

            $total = $total + $progress[2];
        }
    }

    return $total;
}


$player = new Player($_SESSION['playerId']);

$player->get_data();

$player->get_caracs();


if(!empty($_POST['carac'])){

    include('scripts/upgrades/carac.php');
    exit();
}


$ui = new Ui('Améliorations');



echo '
<div><a href="index.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a><a href="upgrades.php"><button>Caractéristiques</button></a><a href="upgrades.php?spells"><button>Sorts</button></a></div>
';


// spells
if(isset($_GET['spells'])){

    include('scripts/upgrades/spells.php');
    exit();
}


echo '
<table class="box-shadow marbre" border="1" align="center">';


echo '<tr><th>Améliorations</th><th>Valeur</th><th>Coût</th><th>+1</th></tr>';


foreach(CARACS as $k=>$e){


    if($k == 'ae'){

        continue;
    }


    $cost = return_cost($trio[$k], $player->upgrades->$k);


    $color = 'green';
    $disabled = '';

    if($cost > $player->row->pi){

        $color =  'red';
        $disabled = 'disabled';
    }

    echo '
    <tr>
        <td>
            '. $e .'
        </td>
        <td>
            '. $player->caracs->$k .'
        </td>
        <td>
            <font color="'. $color .'">'. $cost .'Pi</font>
        </td>
        <td>
            ';

            echo '
            <button
                data-carac="'. $k .'"
                '. $disabled .'
                class="upgrade"
                >
                <span class="ra ra-archery-target"></span>
            </button>
            ';

            echo '
        </td>
    </tr>
    ';
}

echo '
</table>
';


echo $player->row->pi .' Points d\'investissement (Pi)';


?>
<script>
$(document).ready(function(){

    $('.upgrade').click(function(e){

        $('.upgrade').prop('disabled', true);

        let carac = $(this).data('carac');

        $.ajax({
            type: "POST",
            url: 'upgrades.php',
            data: {'carac':carac}, // serializes the form's elements.
            success: function(data)
            {
                alert(data);

                document.location.reload();
            }
        });
    });
});
</script>

