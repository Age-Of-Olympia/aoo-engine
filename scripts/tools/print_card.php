<?php
use Classes\Player;
use Classes\Ui;

if(!isset($_GET['playerId'])){


    $ui = new Ui('Carte');


    ?>
    <input type="number" id="mat" value="<?php echo $_SESSION['playerId'] ?>" />
    <button class="submit">Ok</button>
    <script>
        $(document).ready(function() {
            $('.submit').click(function() {
                window.location = 'tools.php?print_card&playerId=' + $('#mat').val();
            });
        });
    </script>
    <?php

    exit();
}


$player = new Player(explode(',', $_GET['playerId'])[0]);

$player->get_data();


$ui = new Ui('Carte de '. $player->data->name);


$dataName = '<a href="infos.php?targetId='. $player->id .'">'. $player->data->name .'</a>';


$raceJson = json()->decode('races', $player->data->race);

$factionJson = json()->decode('factions', $player->data->faction);


$data = (object) array(
    'bg'=>$player->data->portrait,
    'name'=>$dataName,
    'img'=>'',
    'type'=>$raceJson->name,
    'text'=>'<textarea spellcheck="false"></textarea>',
    'race'=>$player->data->race,
    'faction'=>'<a href="faction.php?faction='. $player->data->faction .'"><span class="ra '. $factionJson->raFont .'"></span></a>',
    'noClose'=>1
);


$card = Ui::get_card($data);

echo $card;


echo '<button id="save">Save .png</button>';


?>
<script src="js/html2canvas.js"></script>
<script>
$(document).ready(function(){
    document.getElementById('save').addEventListener('click', function() {

            var text = $('textarea').val();
            $('.card-text').html(text);

            html2canvas(document.querySelector('#ui-card')).then(canvas => {
                // Convertir le canvas en image
                let imgData = canvas.toDataURL('image/png');

                // Créer un lien pour télécharger l'image
                let link = document.createElement('a');
                link.href = imgData;
                link.download = 'ui-card.png';
                link.click();

                $('.card-text').html('<textarea spellcheck="false">'+ text +'</textarea>');
            });
        });
});
</script>
