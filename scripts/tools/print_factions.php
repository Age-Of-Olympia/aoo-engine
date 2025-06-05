<?php
use Classes\File;
use Classes\Ui;

$ui = new Ui('Print Factions');


foreach(File::scan_dir('datas/public/factions/', without:'.json') as $k=>$e){


    $facJson = json()->decode('factions', $e);

    if(!isset($facJson->raFont)){

        continue;
    }


    echo '<div id="fac-'. $e .'" style="background: white; width: 150px;">';

    echo '<span style="font-size: 800%;" class="ra '. $facJson->raFont .'"></span>';

    echo '</div>';

    echo '<button class="save" data-faction="'. $e .'">Save '. $e .'.png</button>';
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
