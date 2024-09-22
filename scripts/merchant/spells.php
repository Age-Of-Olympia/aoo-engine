<?php

ob_start();

$spellPrices = array(
    'dmg1'=>100,
    'dmg2'=>200,
    'rp'=>50,
    'soins'=>100,
	'esquive'=>200,
	'enchant'=>100,
	'corrupt'=>100,
	'dps'=>300,
    'special'=>300
);


$playerGold = $player->get_gold();


echo '<h1>Parchemins de sorts</h1>';


echo '<table border="1" align="center" class="marbre">';

echo '<tr><td><img src="img/items/parchemin_sort_mini.webp" /></td><td align="left">';

echo $playerGold .'Po<br />';

$parchemin = Item::get_item_by_name('parchemin');

$parcheminN = $parchemin->get_n($player);

$parcheminTxt = ($parcheminN) ? $parcheminN .' Parchemins' : '<font color="red">Cela nécessite 1 Parchemin vierge</font>';

echo $parcheminTxt;

echo '</td></tr></table>';

echo '<p>Faites inscrire un sort sur un Parchemin.</p>';


if($player->data->race == $target->data->race){

    echo '<font color="red">Attention! Ce personnage n\'est pas de votre peuple.<br />
    Vous ne pourrez lancer les Sorts inscrits sur ses Parchemins qu\'une seule fois.</font>
    ';
}


$raceJson = json()->decode('races', $target->data->race);


echo '<h2>Sorts proposés</h2>';

echo '<table border="1" align="center" class="marbre">';


if(!isset($raceJson->spells)){

    exit('<p>Ce personnage ne vend pas de sorts.</p>');
}


foreach($raceJson->spells as $e){


    $dir = explode('/', $e)[0];

    $price = $spellPrices[$dir];


    if(!empty($_POST['spellId'])){


        if($_POST['spellId'] == $e){


            $sql = 'SELECT id FROM items WHERE name = "parchemin_sort" AND spell = ?';

            $db = new Db();

            $res = $db->exe($sql, $e);

            if($res->num_rows){

                $row = $res->fetch_object();

                $parcheminSort = new Item($row->id);
            }
            else{

                $options = array(
                    'spell'=>$e
                );

                $parcheminSort = Item::put_item('parchemin_sort', $private=0, $options);
            }

            $gold = new Item(1);


            if($playerGold + $price < 0){

                exit('<div id="data">Pas assez d\'Or.</div>');
            }

            if(!$parchemin->add_item($player, -1)){

                exit('<div id="data">Cela nécessite 1 Parchemin vierge.</div>');
            }

            $parcheminSort->add_item($player, 1);

            exit('<div id="data">Parchemin inscrit avec succès et placé dans votre inventaire.</div>');
        }

        continue;
    }


    $spellJson = json()->decode('actions', $e);


    echo '
    <tr>
        ';

        echo '
        <td>
            <img src="img/spells/'. $e .'.jpeg" />
        </td>
        ';

        echo '
        <td align="left">
            Parchemin de sort<br /><sup>'. $spellJson->name .'</sup>
        </td>
        ';


        $disabled = '';
        $priceTxt = $price .'Po';
        if($playerGold < $price){

            $disabled = 'disabled';
            $priceTxt = '<font color="red">'. $priceTxt .'</font>';
        }

        echo '
        <td>
            '. $priceTxt .'
        </td>
        ';

        echo '
        <td>
            <button style="height: 50px;" class="create" data-spell-id="'. $e .'" '. $disabled .'>Créer</button>
        </td>
        ';


        echo '
    </tr>
    ';
}

echo '</table>';

echo Str::minify(ob_get_clean());

?>
<script>
$(document).ready(function(){

    $('.create').click(function(e){

        $('.create').prop('disabled', true);

        var spellId = $(this).data('spell-id');

        $.ajax({
            type: "POST",
            url: 'merchant.php?targetId=<?php echo $target->id ?>&spells',
            data: {
                'spellId':spellId
            }, // serializes the form's elements.
            success: function(data)
            {
                // alert(data);
                var content = $('<div>').html(data).find('#data').html();
                alert(content);
                document.location.reload();
            }
        });
    });
});
</script>
