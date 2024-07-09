<?php

echo '<div><a href="account.php"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';

echo '<h1>Vos images téléversées</h1>';

$uploaded = File::get_uploaded($player);

$uploadedN = count($uploaded);
$uploadMax = File::get_uploaded_max($player);

echo '
Images uploadées: '. $uploadedN .'/'. $uploadMax .'.<br />
<sup>Seules les images non-utilisées peuvent être supprimées.<br />
Vous pouvez uploader autant d\'images que vous avez de Points de réputation (Pr).
</sup>
';


echo '
<table border="1" align="center" class="dialog-table">
    <tr>
        <th>Image</th>
        <th>Infos</th>
    </tr>

    ';

    // delete all disabled
    $deleteAll = true;

    foreach($uploaded as $e){

        echo '
        <tr>
            ';

            echo '
            <td>
                <a href="img/ui/forum/uploads/'. $_SESSION['playerId'] .'/'. $e .'">
                    <img src="img/ui/other/blank_50.png" data-src="img/ui/forum/uploads/'. $_SESSION['playerId'] .'/'. $e .'" width="150" />
                </a>
            </td>
            ';

            echo '
            <td valign="top">
                ';

                echo '
                <sup>
                Fichier: <a href="#" class="copy-img">'. $e .'</a><br />';

                $time = explode('.', $e)[0];

                echo '
                Uploadé le: '. date('d/m/Y à H:i', $time) .'<br />

                ';


                if(!empty($_POST['delete'])){

                    if($_POST['delete'] == 'all'){

                        unlink('img/ui/forum/uploads/'. $_SESSION['playerId'] .'/'. $e .'');

                        continue;
                    }

                    if($_POST['delete'] == $e){

                        unlink('img/ui/forum/uploads/'. $_SESSION['playerId'] .'/'. $e .'');

                        exit();
                    }

                    continue;
                }

                echo '
                <br />
                <input type="button" class="suppr" value="Supprimer" delete="'. $e .'" />
                ';


                echo '
                </sup>
            </td>
        </tr>
        ';
    }

    echo '

</table>
';

// all

$disabled = ($deleteAll) ? '' : 'disabled';

echo '
<br />
<input type="button" class="suppr" value="Supprimer toutes les images" delete="all" '. $disabled .' />
';

?>
<script src="js/progressive_loader.js"></script>
<script>
$(".suppr").click(function(e){
    if(!confirm("Supprimer image?")){
        return false;
    }

    var del = $(this).attr("delete");

    $.ajax({
        type: "POST",
        url: "account.php?uploads",
        data: {"delete":del}, // serializes
        success: function(data)
        {
            // alert(data);
            document.location.reload();
        }
    });
});

$('.copy-img').click(function(e){
    e.preventDefault();
    var txt = $(this).html();
    navigator.clipboard.writeText("img/ui/forum/uploads/<?php echo $_SESSION['playerId'] ?>/"+txt);
    alert('URL copiée dans le presse-papier!');
});
</script>
<?php

exit();
