<?php

if(isset($_GET['forget']) && !empty($_POST['spell'])){

    include('scripts/upgrades/forget_spell.php');

    echo Str::minify(ob_get_clean());

    exit();
}


echo '
<table class="box-shadow marbre" border="1" cellspacing="0" align="center">';


echo '<tr><th colspan="2">Sort</th><th></th><th>Coût</th><th>Bonus</th><th colspan="2">Effet</th></tr>';


$spellList = $player->get_spells();

$spellsN = count($spellList);

$trStyle = '';

$buttonStyle = '';

$maxSpells = $player->get_max_spells(count($spellList));

if($maxSpells < 0){

    $max = $maxSpells + $spellsN;

    echo '<tr><th colspan="6"><font color="red">Vous ne pouvez pas utiliser vos sorts (max.'. $max .')</font></th>';

    $trStyle = (!isset($_GET['forget'])) ? 'style="opacity: 0.5;"' : '';

    $buttonStyle = 'class="blink" style="color: red;"';
}

foreach($spellList as $e){


    $spellJson = json()->decode('actions', $e);


    $img = (!empty($spellJson->img)) ? $spellJson->img : 'img/spells/'. $e .'.jpeg';


    echo '
    <tr '. $trStyle .'>
        <td valign="top" width="50">
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
        ';

        $bonus = '';

        if(!empty($spellJson->bonusDamages)){

            $bonus = '+'. $spellJson->bonusDamages;
        }
        elseif(!empty($spellJson->bonusHeal)){

            $bonus = '+'. $spellJson->bonusHeal;
        }


        echo '<td>'. $bonus .'</td>';


        echo '
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
                    style="height: 50px;"
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
        <td colspan="6" align="right">

            <a href="upgrades.php?spells&forget"><button '. $buttonStyle .'>Oublier un sort</button></a>
        </td>
    </tr>
    ';
}

echo '
</table>
';

?>
<script src="js/forget_spells.js"></script>
<?php

echo Str::minify(ob_get_clean());
