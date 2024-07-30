<?php

// drag and drop for upload img
$uploadedN = count(File::get_uploaded($player));
$uploadMax = File::get_uploaded_max($player);

if($uploadedN >= $uploadMax){

    echo '
    <div id="drop_file_zone" style="display: none;">
        <sup>Vous avez atteinds '. $uploadMax .'/'. $uploadMax .' images uploadées<br />
        <a href="account.php?uploaded=1" OnClick="if(confirm(\'Quitter la page?\')) return true; return false;">Besoin de plus?</a>
        </sup>
    </div>
    ';
}
else{

    ?>
    <link rel="stylesheet" href="css/upload_img.css" />
    <div id="drop_file_zone" ondrop="upload_file(event)" ondragover="this.style.backgroundColor='white';return false" onmouseout="this.style.backgroundColor='';return false" style="display: none;">
        <div id="drag_upload_file">
            Déposez image<br />
            ou<br />
            <input type="button" value="Selectionner Fichier" onclick="file_explorer();" />
            <input type="file" id="selectfile" />
        </div>
        <font size="2">Vous avez uploadé <?php echo $uploadedN ?>/<?php echo $uploadMax ?> images<br />Formats: .jpeg .jpg .gif .png <a href="https://minipic.app/" target="_new" title="Compressez vos images avant upload!">.webp</a><br />
        Les images non utilisées seront automatiquement supprimées.</font>
    </div>
    <div class="img-content"></div>
    <script src="js/upload_img.js"></script>
    <?php

    echo '
    <table border="1" id="uploaded-table" align="center" style="display: none;">
    <tr>
        <td>Fichier</td>
        <td></td>
    </table>
    ';
}
