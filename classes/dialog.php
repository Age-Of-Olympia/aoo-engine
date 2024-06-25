<?php


class Dialog{


    private $dialog; // dialog id/name
    private $dialogJson; // dialog json file (in datas/private/dialogs/ or datas/public/dialogs/)


    function __construct($dialog){


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


        echo '
        <div
            id="node'. $node->id .'"
            class="dialog-node"

            data-node="'. $node->id .'"
            data-avatar="'. $avatar .'"
            data-type="'. $type .'"
            >

            '. $node->text .'

            <div class="dialog-node-options">

                ';

                $n = 1;

                foreach($node->options as $option){


                    echo '
                    <div
                        data-go="'. $option->go .'" class="node-option">

                        '. $n .'.

                        '. $option->text .'

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
}
