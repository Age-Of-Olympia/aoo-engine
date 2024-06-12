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

                    echo '<div id="action-data"></div>';

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


    public static function print_inventory($itemList){


        ob_start();

        echo '
        <table border="1" align="center" class="marbre">
        <tr>
        <td>
        ';

        echo '
        <div style="margin: 0 auto; width: 500px; position: relative;">
            ';


            echo '
            <div style="max-height: 308px; max-width: 200px; overflow: scroll; float: left;">
                <table border="1">
                    ';

                    $defaultItem = new Item(1);
                    $defaultItem->get_data();

                    foreach($itemList as $k=>$e){


                        $itemJson = json()->decode('items', $k);

                        echo '
                        <tr>
                        ';

                            echo '
                            <td>
                                <div
                                    class="item-case"

                                    data-name="'. ucfirst($itemJson->name) .'"
                                    data-n="'. $e .'"
                                    data-text="'. $itemJson->text .'"
                                    data-price="'. $itemJson->price .'"
                                    data-img="img/items/'. $itemJson->name .'.png"
                                    >
                                        <img src="img/items/'. $itemJson->name .'_mini.png" />
                                    </div>
                            </td>
                            ';

                            echo '
                            <td align="left">
                                '. ucfirst($itemJson->name) .'
                            </td>
                            ';

                            echo '
                            <td>
                                x'. $e .'
                            </td>
                            ';

                            echo '
                        </tr>
                        ';
                    }

                    echo '
                </table>
            </div>
            ';


            echo '
            <div style="width: 300px; position: absolute; left: 200px;">

                <div class="preview-img">
                    <img
                        src="img/ui/fillers/150.png"
                        data-src="img/items/'. $defaultItem->row->name .'.png"
                        data-filler="img/ui/fillers/150.png"
                        width="150"
                        />
                </div>

                <div class="preview-text">

                    '. $defaultItem->data->text .'
                </div>

                <div class="preview-action">

                    <input type="button" value="Utiliser" /><input type="button" value="Déposer" />
                </div>
            </div>
            ';

            echo '
        </div>
        ';

        echo '
        </td>
        </tr>
        </table>
        ';


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

                $(".preview-text").text(text);

                preload(img, $previewImg);
            });
        });
        </script>
        <?php



        $return = ob_get_contents();
        ob_clean();

        return $return;
    }
}
