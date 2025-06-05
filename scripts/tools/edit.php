<?php
use Classes\Json;
use Classes\Ui;

$ui = new Ui('json edit tool');


if(!isset($_GET['dir']) || !isset($_GET['subDir']) || !isset($_GET['finalDir'])){

    exit('use cmd: "edit [file] [private]"<br />ie: "edit items/adonis"<br />or: "edit plans/faille_naine private"');
}


$dir = $_GET['dir'];
$subDir = $_GET['subDir'];
$finalDir = $_GET['finalDir'];

$path = 'datas/'. $dir .'/'. $subDir .'/'. $finalDir .'.json';

$dataJson = json()->decode($subDir, $finalDir);

if(!$dataJson){

    exit('path error');
}


if(!empty($_POST['data'])){

    echo '<div id="data">';

    if(!json()->isJson($_POST['data'])){

        echo 'json not valid: please validate before saving';
    }

    else{

        Json::write_json($path, $_POST['data']);

        echo 'saved';
    }

    echo '</div>';

    exit();
}


echo '<div>editing: '. $path .'</div>';


$data = Json::encode($dataJson);


echo '
<textarea style="width: 100%;" rows="50">'. $data .'</textarea>
';

echo '<button id="back">Retour</button>';
echo '<button id="sendJSONLint">Validate</button>';
echo '<button id="save">Save</button>';
echo '<button id="reset">Reset</button>';


?>
<script>
$(document).ready(function(){

    $('#sendJSONLint').click(function(e){


        var data = $('textarea').val();

        var url = 'https://jsonlint.com/?json='+ data;

        window.open(url, '_blank');
    });

    $('#save').click(function(e){


        var data = $('textarea').val();

        $.ajax({
            type: "POST",
            url: 'tools.php?edit&dir=<?php echo $dir ?>&subDir=<?php echo $subDir ?>&finalDir=<?php echo $finalDir ?>',
            data: {'data':data}, // serializes the form's elements.
            success: function(data)
            {

                var htmlData = $('<div></div>').html(data).find('#data');
                alert(htmlData.html());
            }
        });
    });

    $('#reset').click(function(e){

        if(confirm('confirm reset?')){

            document.location.reload();
        }
    });

    $('#back').click(function(e){

        if(confirm('confirm back?')){

            document.location = "index.php";
        }
    });

    $('textarea').keydown(function(e) {
        if (e.key === 'Tab') {
            e.preventDefault(); // Empêche le comportement par défaut du Tab

            var start = this.selectionStart;
            var end = this.selectionEnd;

            // Ajouter 4 espaces
            var indent = '    ';
            var value = $(this).val();
            $(this).val(value.substring(0, start) + indent + value.substring(end));

            // Repositionner le curseur après les espaces ajoutés
            this.selectionStart = this.selectionEnd = start + indent.length;
        }
    });
});
</script>
