<?php
use Classes\Db;
use Classes\Forum;
if(!empty($_POST['post'])){


    $postJson = json()->decode('forum', 'posts/'. $_POST['post']);

    $topJson = json()->decode('forum', 'topics/'. $postJson->top_id);

    if($topJson->forum_id == 'Missives'){


        ?>
        <div id="data">
        <script>alert('Vous ne pouvez pas récompenser une Missive.');</script>
        </div>
        <?php

        exit();
    }


    if($postJson->author == $_SESSION['playerId']){


        ?>
        <div id="data">
        <script>alert('Vous ne pouvez pas récompenser vos propres posts.');</script>
        </div>
        <?php

        exit();
    }


    if(!is_numeric($_POST['post']) || !$postJson){

        exit('error post name');
    }


    if(!empty($_POST['img'])){


        if(!file_exists($_POST['img'])){

            exit('error img');
        }


        $sql = '
        SELECT
        img, pr
        FROM
        players_forum_rewards
        WHERE
        postName = ""
        AND
        from_player_id = ?
        AND
        img = ?
        ORDER BY
        pr
        DESC
        ';

        $db = new Db();

        $res = $db->exe($sql, array($_SESSION['playerId'], $_POST['img']));

        if(!$res->num_rows){

            exit('error reward');
        }


        $row = $res->fetch_object();

        $reward = (object) array(
            'img'=>$row->img,
            'pr'=>$row->pr,
            'player_id'=>$_SESSION['playerId']
        );


        Forum::give_reward($postJson, $reward);


        $sql = '
        UPDATE
        players_forum_rewards
        SET
        postName = ?,
        topName = ?,
        to_player_id = ?
        WHERE
        postName = ""
        AND
        from_player_id = ?
        AND
        img = ?
        ORDER BY
        pr
        DESC
        LIMIT 1
        ';

        $res = $db->exe($sql, array(
            $postJson->name,
            $postJson->top_id,
            $postJson->author,
            $_SESSION['playerId'],
            $_POST['img']
        ));


        exit();
    }


    $sql = '
    SELECT
    img, pr
    FROM
    players_forum_rewards
    WHERE
    postName = ""
    AND
    from_player_id = ?
    ORDER BY
    pr
    DESC
    ';

    $db = new Db();

    $res = $db->exe($sql, $_SESSION['playerId']);


    if(!$res->num_rows){

        ?>
        <div id="data">
        <script>alert('Vous ne possédez aucune récompense à donner.');</script>
        </div>
        <?php

        exit();
    }


    echo '
    <div id="data">
    ';


    while($row = $res->fetch_object()){


        echo '
        <img
            data-post="'. $_POST['post'] .'"
            src="'. $row->img .'"
            class="new-reward"
            title="'. $row->pr .'Pr"
        />
        ('. $row->pr .'Pr)
        ';
    }


    ?>
    <script>
    $('.new-reward').click(function(e){


            var $this = $(this);


            $.ajax({
                type: "POST",
                url: 'forum.php?rewards',
                data: {
                    'img': $this.attr('src'),
                    'post': $this.data('post')
                }, // serializes the form's elements.
                success: function(data)
                {
                    // alert(data);
                    $.ajax({
                        type: "GET",
                        url: 'forum.php?topic='+ window.topicName +'&page='+ window.pageN +'#'+ $this.data('post'),
                        data: {

                        }, // serializes the form's elements.
                        success: function(data)
                        {
                            // alert(data);
                            $('body').html(data);
                        }
                    });
                }
            });

        });
    </script>
<?php


    echo '
    </div>
    ';

}
