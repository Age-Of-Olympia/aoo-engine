<?php

require_once('config.php');


if(!empty($_POST['url'])){


    $url = 'datas/'. str_replace('..', '.', $_POST['url']);

    if(!file_exists($url)){

        exit('error loading file');
    }


    if(!empty($_POST['data'])){


        $myfile = fopen($url, "w") or die("Unable to open file!");
        fwrite($myfile, $_POST['data']);
        fclose($myfile);

        exit('saved!');
    }


    $data = file_get_contents($url);

    exit($data);
}


$ui = new Ui($title="Editeur");


$url = '';

if(!empty($_GET['url'])){

    $dataJson = json()->decode(explode(',', $_GET['url'])[0], explode(',', $_GET['url'])[1]);

    if(!$dataJson){

        exit($_GET['url'] .' not found');
    }

    $dir = (!file_exists('public/'. explode(',', $_GET['url'])[0] .'/'. explode(',', $_GET['url'])[1] .'.json')) ? 'private' : 'public';

    $url = ''. $dir .'/'. explode(',', $_GET['url'])[0] .'/'. explode(',', $_GET['url'])[1] .'.json';
}


echo '<h1>Editeur</h1>';

echo '
<div>
    <input type="text" id="url" value="'. $url .'" />
    <input type="button" value="load" />
</div>

<div>
    <textarea id="data" style="width: 500px; height: 500px;"></textarea><br />
    <input type="button" value="save" />
</div>
';


echo '
<br />
<div>
    <a href="editor.php?url='. explode(',', $_GET['url'])[0] .',exemple,json"><button>exemple</button></a>
</div>
';


?>
<script>
$(document).ready(function(){


    function load_file(){

        let url = $('#url').val().trim();

        if(url == ''){

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'editor.php',
            data: {'url':url}, // serializes the form's elements.
            success: function(data)
            {
                $('#data').text(data.trim());
            }
        });
    }

    if($('#url').val() != ''){

        load_file();
    }

    $('input[value="load"]').click(function(e){

        load_file();
    });

    $('input[value="save"]').click(function(e){

        let url = $('#url').val().trim();
        let data = $('#data').val();

        if(url == ''){

            return false;
        }

        $.ajax({
            type: "POST",
            url: 'editor.php',
            data: {'url':url,'data':data}, // serializes the form's elements.
            success: function(data)
            {
                alert(data);
            }
        });
    });
});
</script>
