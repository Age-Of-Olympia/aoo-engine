<?php
namespace Classes;

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


        // Passerelle unique des dialogues : table `dialogs` d'abord, repli
        // fichier JSON legacy tant que la ligne n'est pas seedée
        $this->dialogJson = (new \App\Service\DialogService())->loadDialog($dialog);


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


        return Str::minify(ob_get_clean());
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
        AND
        lastLoginTime > ?
        AND xp > 500
        GROUP BY
        race
        ';

        $result = $db->exe($sql, $limit);

        // races n
        $raceNTbl = array();


        // default
        $playableRaces = (new \App\Service\RaceService())->getPlayableRaceNames();
        foreach($playableRaces as $e)
            $raceNTbl[$e] = 0;


        $raceBonusTbl = array();

        while($row = $result->fetch_assoc()){


            if(!in_array($row['race'], $playableRaces)){

                continue;
            }


            $raceNTbl[$row['race']] = $row['n'];
        }

        // print_r($raceNTbl);

        $raceNTblFormat= [];
        foreach($raceNTbl as $k=>$e){
            $raceNTblFormat[$k] = '('. $e .' âmes)';
        }

        $values = array_values($raceNTbl);
        asort($values);
        $values = array_values(array_unique($values));

        $min1 = $values[0] ?? -1;
        $min2 = $values[1] ?? -1;
        foreach($raceNTbl as $k=>$e){
            if($e == $min1){
                $raceTbl[] = $k;
                $raceNTblFormat[$k] .= ' <span style="color: gold;">+200Po en bonus !</span>';
            }
            else if($e == $min2 && $min2 > $min1){
                $raceTbl[] = $k;
                $raceNTblFormat[$k] .= ' <span style="color: gold;">+100Po en bonus !</span>';
            }
        }

        return $raceNTblFormat;
    }


    public static function refresh_register_dialog(){

        // Logique déplacée dans la passerelle : écrit la ligne `dialogs`
        // (ou le fichier legacy tant que la table n'est pas seedée)
        (new \App\Service\DialogService())->refreshRegisterDialog();
    }
}
