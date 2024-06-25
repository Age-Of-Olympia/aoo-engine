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
                <link rel="icon" type="image/x-icon" href="/img/ui/favicons/favicon.png">
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
                <sup style="position: absolute; bottom: 0px; right: 0px;">'. sqln()-1 .' req</sup>
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


            echo '<div class="card-wrapper '. $data->race .'">';


                echo '<div class="card-name">'. $data->name .'</div>';


                echo '<div class="card-image"><img src="'. $data->bg .'" class="card-portrait" /></div>';


                echo '<div class="card-actions">';

                echo '
                <button
                    class="action close-card"
                    data-action="close-card"
                    >
                    <span class="ra ra-x-mark"></span>
                    <span class="action-name">Fermer</span>
                    </button>
                    <br />
                    ';

                echo $data->img;

                echo '</div>';


                echo '<div class="card-type">'. $data->type .'</div>';


                echo '<div class="card-text">'. $data->text .'</div>';


            echo '</div>';

            echo '
        </div>
        ';

        $return = ob_get_contents();
        ob_clean();

        return $return;
    }


    public static function print_inventory($itemList){


        $defaultItem = new Item(1);
        $defaultItem->get_data();

        ob_start();


        echo '
        <table border="1" align="center" class="marbre">
        <tr>
        <td>
        ';

        echo '
        <div class="inventory-container">
            ';

        echo '
            <div class="">
                <div class="preview-img">
                    <img
                        src="img/ui/fillers/150.png"
                        data-src="img/items/'. $defaultItem->row->name .'.png"
                        data-filler="img/ui/fillers/150.png"
                        width="150"
                        height="150"
                    />
                </div>
                <div class="preview-text">
                    '. $defaultItem->data->text .'
                </div>
                <div class="preview-action">
                </div>
            </div>
            ';

        echo '
        </td>
        </tr>
        <tr>
        <td/>
            ';

        echo '
            <div class="item-list">
                <table border="1">
                    ';

        foreach($itemList as $row){


            $item = new Item($row->id, $row);

            $item->get_data();

            $caracs = Item::get_item_carac($item->data);


            $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);


            $emplacement = '';

            if(!empty($row->equiped) && $row->equiped != ''){

                $emplacement = '<div class="emplacement"><img src="img/ui/inventory/'. $row->equiped .'.jpeg" /></div>';
            }


            echo '
            <tr
                class="item-case"
                data-id="'. $row->id .'"
                data-name="'. $itemName .'"
                data-n="'. $row->n .'"
                data-text="'. $item->data->text .'"
                data-price="'. $item->data->price .'"
                data-img="img/items/'. $item->row->name .'.png"
            >
                <td width="50">
                    <div>
                        <img src="img/items/'. $row->name .'_mini.png" height="50" />
                    </div>
                </td>
                <td align="left" class="item-name">
                    '. $itemName .'<br />
                    '. implode(', ', $caracs) .'

                    '. $emplacement .'
                </td>
                <td width="50">
                    x'. $row->n .'
                </td>
            </tr>
            ';
        }

        echo '
                </table>
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


            window.id = "<?php echo $defaultItem->row->id ?>";
            window.name = "<?php echo $defaultItem->row->name ?>";
            window.n =    <?php echo $itemList[$defaultItem->row->id]->n ?>;
            window.price =    1;

            var $previewImg = $(".preview-img img");

            // first img preload
            preload($previewImg.data("src"), $previewImg);

            $(".item-case").click(function(e){


                var $item = $(this);

                window.id =  $item.data("id");
                window.name =  $item.data("name");
                window.n =     $item.data("n");
                let text =  $item.data("text");
                window.price = $item.data("price");
                let infos = $item.data("infos");
                let img =   $item.data("img");

                $(".preview-text").text(text);

                preload(img, $previewImg);
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }


    public static function print_map() {

        ob_start();

        echo '
        <div id="ui-map">
        <?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg
            xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1"
            baseProfile="full"
            id="svg-map"
            width="800"
            height="532"
            >
        ';

        $text = array();

        foreach (File::scan_dir('img/ui/map/', $without=".png") as $e) {

            if ($e == 'parchemin') {
                continue;
            }

            $mapJson = json()->decode('plans', $e);
            $opacity = 0.4;


            // plan at war
            $fill = (!empty($mapJson->war)) ? 'style="fill: red;"' : '';
            $colored = (!empty($mapJson->war)) ? 'colored-red' : '';



            echo '
            <image
                x="'. $mapJson->x .'"
                y="'. $mapJson->y .'"
                class="map location '. $colored .'"
                data-plan="'. $e .'"
                data-name="'. $mapJson->name .'"
                data-opacity="'. $opacity .'"
                href="img/ui/map/'. $e .'.png"
                style="opacity: '. $opacity .';"
            />
            ';


            $text[] = '
            <text
                class="text"
                data-plan="'. $e .'"
                x="'. ($mapJson->x + 50) .'"
                y="'. ($mapJson->y + 50) .'"
                '. $fill .'
            >
                '. $mapJson->name .'
            </text>
            ';
        }

        echo implode('', $text);

        echo '
        </svg>
        </div>
        ';

        $return = ob_get_contents();
        ob_clean();

        return $return;
    }



   #
    # dialog ui
    #

    public static function get_dialog($player, $options, $landingData='#ui-data') : string {


        /*
         * show a floating dialog pannel with options
         */


        // tampon start
        ob_start();


        echo '
        <div id="ui-dialog">
            ';

            if(!empty($options['json'])){

                $options = (array) $options['json'];
            }


            echo '
            <div
                class="dialog-template">
                ';


                // player avatar
                echo '
                <div class="dialog-template-img">

                    <img src="img/ui/fillers/1.png" data-src="'. $options['avatar'] .'" />
                </div>
                ';

                echo '
                <div class="dialog-template-name">'. $options['name'] .'</div>
                ';


                // dialog
                if(!empty($options['dialog'])){


                    $dialogJson = json()->decode('dialogs', $options['dialog']);

                    if(!$dialogJson){


                        ob_clean();

                        ob_start();

                        echo '<script>alert("'. $options['dialog'] .'");</script>';

                        return ob_get_clean();
                    }


                    $dialog = new Dialog($options['dialog']);


                    // get dialog data
                    echo '
                    <div class="dialog-template-box">
                        ';


                        echo $dialog->get_data();


                        echo '
                    </div>
                    ';
                }


                // repace text
                $text = $options['text'];


                // replace str
                // $text = Str::replace_str($player, $text);


                echo '
                <div class="dialog-template-text">'. $text .'</div>
                ';

                echo '
            </div>';

            echo '
        </div>';


        // js & css
        $dialogVersion = filemtime('js/dialog.js');
        $dialogCssVersion = filemtime('css/dialog.css');

        echo '
        <script src="js/dialog.js?v='. $dialogVersion .'"></script>
        <link rel="stylesheet" href="css/dialog.css?v='. $dialogVersion .'">
        ';


        return ob_get_clean();
    }
}
