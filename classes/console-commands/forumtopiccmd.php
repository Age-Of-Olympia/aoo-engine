<?php

class ForumTopicCmd extends Command
{
    public function __construct() {
        parent::__construct("post",[new Argument('action',false), new Argument('topic_id',false)
            , new Argument('player_id_or_name',true)]);
    }

    public function execute(  array $argumentValues ) : string
    {

        $topJson = json()->decode('forum', 'topics/'. $argumentValues[1]);


        if($argumentValues[0] == 'close'){


            $topJson->closed = 1;


            $data = Json::encode($topJson);

            Json::write_json('datas/private/forum/topics/'. $argumentValues[1] .'.json', $data);

            return 'topic '. $topJson->title .' closed';
        }

        if($argumentValues[0] == 'open'){

            unset($topJson->closed);

            $data = Json::encode($topJson);

            Json::write_json('datas/private/forum/topics/'. $argumentValues[1] .'.json', $data);

            return 'topic '. $topJson->title .' opened';
        }

        if($argumentValues[0] == 'approve'){


            if(!empty($topJson->approved)){

                unset($topJson->approved);
            }
            else{

                $topJson->approved = 1;
            }


            $data = Json::encode($topJson);

            Json::write_json('datas/private/forum/topics/'. $argumentValues[1] .'.json', $data);

            return 'topic '. $topJson->title .' approved';
        }

        if($argumentValues[0] == 'add'){

            $player = parent::getPlayer($argumentValues[2]);

            $player->get_data();

            Forum::add_dest($player, $topJson);

            return 'topic '. $player->data->name .' ajouté à '. $topJson->title;
        }

        return 'action not found';

    }
}
