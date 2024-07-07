<?php

$ui = new Ui($target->data->name .' (réputation)');

echo '<div><a href="infos.php?targetId='. $target->id .'"><button><span class="ra ra-sideswipe"></span> Retour</button></a></div>';
echo '
<div id="pr-wrapper" class="glow">
    Points de Réputation:<br />
    <span id="pr">0</span><br />
    <span id="pr-text">'. Str::get_reput($target->data->pr) .'</span>
</div>
<style>
#filler{visibility: hidden;}
#pr-text{display: none;}
#pr-wrapper{

    font-family: goudy;
    font-size: 75px;
    font-weight: bold;
    background-image: linear-gradient(
        to right,
        #462523 0,
            #cb9b51 22%,
        #f6e27a 45%,
        #f6f2c0 50%,
        #f6e27a 55%,
        #cb9b51 78%,
        #462523 100%
        );
    color:transparent;
    -webkit-background-clip:text;
}
</style>
';

?>
<script>
    var $badge = $('#pr');

    var n = $badge.html();

    var interval = setInterval(function () {

        <?php if(!$target->data->pr): ?>

        $('#pr-text').fadeIn('slow');

        clearInterval( interval );

        return false;

        <?php endif; ?>


        var value = parseInt($badge.html());
        value++;
        $badge.html(value);


        if( value == <?php echo $target->data->pr ?> ){

            $('#pr-text').fadeIn('slow');

            clearInterval( interval );
        }

    }, 10);
</script>
<?php



echo '<h2>Récompenses</h2>';

$sql = '
SELECT postName, topName, img, pr FROM
players_forum_rewards AS a
WHERE
a.to_player_id = '. $target->id .'
AND
a.from_player_id != a.to_player_id
ORDER BY a.pr,a.img
';

$db = new Db();

$result = $db->exe($sql);

if( !$result->num_rows ) exit('Aucune récompense.');

$imgList = array();


echo '
<table border="1" align="center" class="marbre">
<tr>
    <th>Sujet</th>
    <td>Récompenses</td>
    <td>Pr
    ';


while( $row = $result->fetch_assoc() ){

    $img = '
        <a href="forum.php?topic='. $row['topName'] .'#'. $row['postName'] .'"><img src="'. $row['img'] .'" class="img-reward" /></a>
        ';

    if( !isset($imgList[ $row['topName'] ]) ){


        if(!empty($pr)){

            echo '</td><td>'. $pr;
        }


        echo '</td></tr><tr>';


        $topJson = json()->decode('forum/topics', $row['topName']);

        $forumJson = json()->decode('forum/forums', $topJson->forum_id);


        if(!empty($forumJson->factions) && !in_array($player->data->faction, $forumJson->factions)){


            echo '<th>?</th>
            <td>';
        }
        else{


            echo '<th><a href="forum.php?topic='. $topJson->name .'">'. $topJson->title .'</a></th>
            <td>';
        }


        $imgList[ $row['topName'] ] = 1;

        $pr = 0;
    }


    $postJson = json()->decode('forum/posts', $row['postName']);

    echo $img;


    $pr += $row['pr'];
}

echo '
</td><td>'. $pr .'</td>
</tr>
</table>
';
