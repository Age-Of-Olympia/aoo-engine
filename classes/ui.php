<?php

class ui{

    public function __construct($title=''){

        /*
         * construct html page with a title
         */

        echo $this->get_header($title);
    }

    public function get_header($title){

        /*
         * return header and extra files timestamped
         */

        $jsVersion = filemtime('js/main.js');
        $cssVersion = filemtime('css/main.css');

        return '
        <!DOCTYPE html>
        <html lang="fr">
            <head>
                <meta charset="UTF-8">
                <title>Age of Olympia - '. $title .'</title>
                <script src="js/jquery.js"></script>
                <script src="js/main.js?v='. $jsVersion .'"></script>
                <link href="css/main.css?v='. $cssVersion .'" rel="stylesheet" />
                <link rel="stylesheet" href="css/rpg-awesome.min.css">
            </head>
            <body>
                ';
    }

    public function __destruct(){

        /*
         * on destruct, print footer
         */

        echo $this->get_footer();
    }

    public function get_footer(){

        /*
         * print footer
         * close tags
         */

        return '
            </body>
        </html>
        ';
    }


    // STATIC

    public static function get_card($data) : string{

        ob_start();

        echo '
        <div id="ui-card">
            ';


            echo '
            <div
                class="card-template"
                style="background: url(\''. $data->bg .'\') no-repeat center; background-size: auto 450px;"
                >
                ';

                echo '
                <div class="card-template-name">
                    ';

                    echo $data->name;

                    echo '
                </div>
                ';

                echo '
                <div class="card-template-img">
                    ';

                    echo '<button
                        class="action close-card"
                        data-action="close-card"
                        >
                        <span class="ra ra-x-mark"></span>
                        <span class="action-name">Fermer</span>
                        </button><br />';

                    echo $data->img;

                    echo '
                </div>
                ';

                echo '
                <div class="card-template-type">'. $data->type .'</div>
                ';


                echo '
                <div class="card-template-text">'. $data->text .'</div>
                ';

                echo '
            </div>';

            echo '
        </div>
        ';

        $return = ob_get_contents();
        ob_clean();

        return $return;
    }


    public static function get_inventory($player) : string {

        // tampon start
        ob_start();


        echo '
        <div id="ui-item-list">
            ';

            // or
            $defaultItem = new Item(1);

            $defaultItem->get_data();


            // item list
            $itemList = Item::get_item_list($player->id);

            $defaultN = (!empty($itemList[$defaultItem->row->name])) ? $itemList[$defaultItem->row->name] : 0;


            // item preview
            echo '
            <div class="item-preview">

                <div class="preview-name">
                    '. ucfirst($defaultItem->data->name) .'

                    <span class="preview-n">x'. $defaultN .'</span>

                    <span class="preview-infos">


                        Prix: '. $defaultItem->data->price .'po
                    </span>
                </div>


                <div class="preview-wrapper">

                    <div class="preview-img">
                        <img src="img/ui/fillers/150.png"
                            data-src="img/items/'. $defaultItem->row->name .'.png"
                            data-filler="img/ui/fillers/150.png"
                            />
                    </div>

                    <div class="preview-text">

                        '. $defaultItem->data->text .'
                    </div>

                </div>

                <div class="preview-action">

                    <input type="button" value="Utiliser" /><input type="button" value="Déposer" /><input type="button" class="alert" id="action-close" value="Fermer" />
                </div>

            </div>
            ';


            // item list
            echo '
            <div id="ui-item-invent">
                ';

                foreach($itemList as $k=>$e){


                    $itemJson = json()->decode('items', $k);

                    // $n = Str::get_k($e);


                    echo '<div
                            class="item-case"

                            data-name="'. ucfirst($itemJson->name) .'"
                            data-n="'. $e .'"
                            data-text="'. $itemJson->text .'"
                            data-price="'. $itemJson->price .'"
                            data-img="img/items/'. $itemJson->name .'.png"
                            >';
                        echo '<img src="img/items/'. $itemJson->name .'_mini.png" />';
                        echo '<div class="item-case-n">x'. $e .'</div>';
                    echo '</div>';
                }

                echo '
            </div>
            ';


            echo '
        </div>
        ';


        // js
        ?>
        <script>
        $(document).ready(function(){


            var $previewImg = $(".preview-img img");

            // first img preload
            preload($previewImg.data("src"), $previewImg);


            $(".item-case").click(function(e){


                var $item = $(this);

                let name =  $item.data("name");
                let n =     $item.data("n");
                let text =  $item.data("text");
                let price = $item.data("price");
                let infos = $item.data("infos");
                let img =   $item.data("img");

                $(".preview-name").html(name +" <span class='preview-n'>x"+ n +"</span><span class='preview-infos'>"+ infos +" Prix: "+ price +"<span class='creds'>po</span></span>");
                $(".preview-text").text(text);

                preload(img, $previewImg);
            });


            $("#action-close").click(function(e){


                e.preventDefault();

                $("#ui-item-list").hide();
            });
        });
        </script>
        <?php


        // get tampon & clean
        $return = ob_get_contents();
        ob_end_clean();


        return $return;
    }
}
