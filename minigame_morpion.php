<?php
use Classes\Player;
use Classes\File;
use Classes\Ui;

require_once('config.php');

$player = new Player($_SESSION['playerId']);


$_SESSION['morpionDir'] = 'img/ui/forum/rewards/pokemon/';


if(!isset($_SESSION['coups'])){

    $_SESSION['coups'] = array();
}

if(!isset($_SESSION['wins'])){

    $_SESSION['wins'] = 0;
}

if(!isset($_SESSION['looses'])){

    $_SESSION['looses'] = 0;
}


$combi = array(
    ['a1','a2','a3'],
    ['b1','b2','b3'],
    ['c1','c2','c3'],

    ['a1','b1','c1'],
    ['a2','b2','c2'],
    ['a3','b3','c3'],

    ['a1','b2','c3'],
    ['a3','b2','c1']
                );


// shuffle($combi);


$combiWin = $combi[0]; // forcément une combinaison gagnante



if(!empty($_POST['caseName'])){


    $files = File::scan_dir($_SESSION['morpionDir']);

    $win = $files[0];


    if(in_array($_POST['caseName'], $combiWin)){

        $file = $win;
    }
    else{

        $file = $files[rand(1,3)];
    }


    // stop
    if(count($_SESSION['coups']) >= 3){


        // unset($_SESSION['coups']);

        echo 'stop';

        exit();
    }


    echo '<img src="'. $_SESSION['morpionDir'] . $file .'" />';

    $_SESSION['coups'][] = $file;



    if(count($_SESSION['coups']) == 3){

        if(
            $_SESSION['coups'][0] == $_SESSION['coups'][1]
            && $_SESSION['coups'][1] == $_SESSION['coups'][2]
        ){


            $_SESSION['wins'] += 1;
        }
        else{

            $_SESSION['looses'] += 1;
        }
    }


    exit();
}


// reset game
printr($_SESSION['coups']);
unset($_SESSION['coups']);


$ui = new Ui('Voter pour Aoo!');


echo '<h1>Votez pour Aoo!</h1>';


echo '
<table id="grill" border="1" class="marbre" align="center">
';


$colNames = ['x','a','b','c'];


for($x=1; $x<=3; $x++){


    $col = $colNames[$x];


    echo '
    <tr>
    ';

    for($y=1; $y<=3; $y++){


        $row = $y;


        $caseName = $col . $row;


        echo '
        <td
            style="width: 50px; height: 50px; cursor: pointer;"
            data-name="'. $caseName .'"
            >'. $caseName .'</td>
        ';
    }

    echo '
    </tr>
    ';
}


echo '
</table>
';


echo '<div>Parties gagnées: '. $_SESSION['wins'] .'</div>';
echo '<div>Parties perdues: '. $_SESSION['looses'] .'</div>';


?>
<script>
$(document).ready(function(e){


    $('#grill td').click(function(e){


        let $case = $(this);

        let caseName = $case.data('name');

        $.ajax({
            type: "POST",
            url: 'minigame_morpion.php',
            data: {'caseName': caseName}, // serializes the form's elements.
            success: function(data)
            {

                if(data.trim() == 'stop'){

                    // document.location.reload();
                    return false;
                }

                $case.html(data);
            }
        });
    });
});
</script>
