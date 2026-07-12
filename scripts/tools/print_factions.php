<?php
use App\Service\FactionService;
use Classes\Ui;

$ui = new Ui('Print Factions');


foreach((new FactionService())->getAllFactions() as $faction){

    $code = $faction->getCode();

    if($faction->getRaFont() === ''){

        continue;
    }


    echo '<div id="fac-'. $code .'" style="background: white; width: 150px;">';

    echo '<span style="font-size: 800%;" class="ra '. $faction->getRaFont() .'"></span>';

    echo '</div>';

    echo '<button class="save" data-faction="'. $code .'">Save '. $code .'.png</button>';
}



?>
<script src="js/html2canvas.js"></script>
<script>
$(document).ready(function(){
    $('.save').click(function() {

            var fac = $(this).data('faction');

            html2canvas(document.querySelector('#fac-'+ fac)).then(canvas => {
                // Convertir le canvas en image
                let imgData = canvas.toDataURL('image/png');

                // Créer un lien pour télécharger l'image
                let link = document.createElement('a');
                link.href = imgData;
                link.download = 'faction-'+ fac +'.png';
                link.click();
            });
        });
});
</script>
