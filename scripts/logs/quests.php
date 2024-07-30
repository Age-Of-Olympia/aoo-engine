<?php

echo '<h1>Quêtes</h1>';


$path = 'datas/private/players/'. $_SESSION['playerId'] .'.quests.html';


if(!file_exists($path)){

    ob_start();


    $quest = new Quest($_SESSION['playerId']);


    $playerQuests = $quest->get_player_quests();


    if(!count($playerQuests)){

        echo '<a href="javascript: alert(\'Aucune quête en cours.\');"><img src="img/ui/bg/journal_closed.webp" /></a>';

        exit();
    }


    echo '
    <table border="0" align="center" class="journal">
    ';

    $pageN = 1;

    foreach($playerQuests as $row){


        $style = ($pageN == 1) ? '' : 'style="display: none"';

        $imgPath = 'img/quests/'. $row->name .'.png';

        $img = (file_exists($imgPath)) ? $imgPath : 'img/quests/default.png';

        $status = ($row->status == 'pending') ? 'En cours' : 'Terminée';

        echo '
        <tr '. $style .'>
            <td data-page="left">
                <div class="page">
                    <center>
                        <b>'. $row->name .'</b><br />
                        <sup>'. $row->text .'</sup><br />
                        <img src="'. $img .'" /><br />
                        <sup>'. $status .'</sup>
                    </center>

                    <div class="footer">'. Str::numberToRoman($pageN) .'</div>
                </div>
            </td>
            ';

            $pageN++;

            echo '
            <td data-page="right">
                <div class="page">
                    ';

                    $steps = $quest->get_steps($row->name);


                    $n = 1;

                    foreach((array) $steps as $k=>$step){


                        if($step->status == 'pending'){


                            echo $n .'. '. $step->name;

                            break;
                        }

                        else if($step->status == 'permanent'){


                            echo '* '. $step->name .'<br />';
                        }

                        else{

                            echo $n .'. <s>'. $step->name .'</s><br />';
                        }

                        $n++;
                    }

                    echo '
                    <div class="footer">'. Str::numberToRoman($pageN) .'</div>
                </div>
            </td>
        </tr>
        ';

        $pageN++;
    }

    echo '
    </table>
    ';

    $data = ob_get_clean();

    $myfile = fopen($path, "w") or die("Unable to open file!");
    fwrite($myfile, $data);
    fclose($myfile);

    echo $data;
}

else{


    echo file_get_contents($path);
}

?>
<script src="js/quests.php"></script>
