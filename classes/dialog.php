<?php


class Dialog{


    private $dialog;        // dialog id/name
    private $dialogJson;    // dialog json file (in datas/private/dialogs/ or datas/public/dialogs/)
    private $player;        // to customize dialog text
    private $target;        // to customize dialog text


    function __construct($dialog, $player=false, $target=false){


        if($player){

            $this->player = $player;
        }

        if($target){

            $this->target = $target;
        }


        $this->dialogJson = json()->decode('dialogs', $dialog);


        if(!$this->dialogJson){

            echo '
            <br />
            <button OnClick="$(\'#ui-dialog\').hide()">
                Fermer
            </button>
            ';

            ?>
            <script>
            $(document).ready(function(){

                $('#ui-dialog, .dialog-template').css('height', '150px');
            });
            </script>
            <?php

            exit();
        }
    }


    public function get_node($node){


        // avatar & type option
        $avatar = (!empty($node->avatar)) ? $node->avatar : '';
        $type = (!empty($node->type)) ? $node->type : '';


        $notHidden = '';

        if($node->id == 'bonjour'){

            $notHidden = 'style="display: block;"';
        }


        echo '
        <div
            id="node'. $node->id .'"
            class="dialog-node"

            data-node="'. $node->id .'"
            data-avatar="'. $avatar .'"
            data-type="'. $type .'"

            '. $notHidden .'
            >

            '. $this->customize($node->text) .'


            <div class="dialog-node-options">

                ';

                $n = 1;


                if(!empty($node->shuffle)){

                    shuffle($node->options);
                }


                foreach($node->options as $option){


                    echo '
                    <div
                        ';

                        if(!empty($option->go)){

                            echo 'data-go="'. $option->go .'"
                            ';
                        }
                        elseif(!empty($option->url)){

                            echo 'data-url="'. $this->customize($option->url) .'"
                            ';
                        }

                        if(!empty($option->set)){


                            foreach($option->set as $k=>$e){


                                echo 'data-set-name="'. $k .'"
                                ';

                                echo 'data-set-val="'. $e .'"
                                ';
                            }
                        }


                        echo '
                        class="node-option"
                        >';

                        echo $n .'. ';

                        echo $this->customize($option->text);

                        echo '
                    </div>
                    ';


                    $n++;
                }

                echo '
            </div>

        </div>
        ';
    }


    public function get_data() : string{


        // tampon start
        ob_start();


        foreach($this->dialogJson->dialog as $node){


            echo $this->get_node($node);
        }


        // error node
        $node = (object) array();
        $node->id = 'error';
        $node->text = "(Il n'y a rien ici)";
        $node->options = (object) array(
            (object) array(
                "go"=>"RESET",
                "text"=>"Ok."
            )
        );


        echo $this->get_node($node);


        return ob_get_clean();
    }


    public function customize($text){


        if(!empty($this->player)){

            $text = str_replace('PLAYER_ID', $this->player->id, $text);
            $text = str_replace('PLAYER_NAME', $this->player->data->name, $text);
        }

        if(!empty($this->target)){

            $text = str_replace('TARGET_ID', $this->target->id, $text);
        }


        return $text;
    }


    public static function get_race_n(){

        $db = new Db();

        // time limit
        $limit = time() - INACTIVE_TIME;

        // AND
        // nextTurnTime > ?

        $sql = '
        SELECT COUNT(*) AS n, race
        FROM
        players
        WHERE
        id > 0

        GROUP BY
        race
        ';

        $result = $db->exe($sql);

        // races n
        $raceNTbl = array();


        // default
        foreach(RACES as $e)
            $raceNTbl[$e] = 0;


        $raceBonusTbl = array();

        while($row = $result->fetch_assoc()){


            if(!in_array($row['race'], RACES)){

                continue;
            }


            $raceNTbl[$row['race']] = $row['n'];
        }

        // print_r($raceNTbl);

        $raceNTblFormat= [];
        foreach($raceNTbl as $k=>$e){
            $raceNTblFormat[$k] = '('. $e .' âmes)';
        }

        $raceNTblCopy = $raceNTbl;

        sort($raceNTbl);

        $raceTbl = array();
        foreach($raceNTblCopy as $k=>$e){
            if($e == $raceNTbl[0]){
                $raceTbl[] = $k;
                $raceNTblFormat[$k] .= " <font color='gold'>+20Po en bonus!</font>";
            }
        }

        return $raceNTblFormat;
    }


    public static function refresh_register_dialog(){


        $options = array();


        foreach(self::get_race_n() as $k=>$e){


            $raceJson = json()->decode('races', $k);

            $options[] = (object) array('go'=>$k, 'text'=>$raceJson->name .' '. $e);
        }


        $regJson = json()->decode('dialogs', 'register');

        $regJson->dialog[0]->options = $options;

        $data = Json::encode($regJson);

        Json::write_json('datas/public/dialogs/register.json', $data);
    }
}
