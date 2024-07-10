<?php

class ForumPostCmd extends Command
{
    public function __construct() {
        parent::__construct("post",[new Argument('action',false), new Argument('post_id',false)]);
    }

    public function execute(  array $argumentValues ) : string
    {


        if(!empty($argumentValues[0]) && $argumentValues[0] == 'delete'){
            $postJson = json()->decode('forum', 'posts/'. $argumentValues[1]);

            if(!$postJson){
                return 'error post not found';
            }


            $postJson->text = '(message supprimé)';

            $data = Json::encode($postJson);

            Json::write_json('datas/private/forum/posts/'. $argumentValues[1] .'.json', $data);

            return 'post '.$postJson->name .' supprimé';
        }


        return 'action not found';

    }
}
