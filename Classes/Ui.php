<?php
namespace Classes;

class Ui{

    public function __construct($title='', $loadJQueryUi=false){

        /*
         * construct html page with a title
         */
        echo $this->get_header($title, $loadJQueryUi);
    }


    public function get_header($title, $loadJQueryUi){

        /*
         * return header and extra files timestamped
         */

        ob_start();

        echo '
        <!DOCTYPE html>
        <html lang="fr">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <meta name="title" content="Age Of Olympia">
                <meta name="description" content="Jeu de rôle par navigateur au tour par tour, rétro-clone de Legends Of Olympia.">
                <meta name="keywords" content="JDR,jeu en ligne,JDR en ligne,jeu de rôle,jeu de role,en ligne,Legends of Olympia,Age of Olympia,LoO,AoO">
                <meta name="robots" content="index, follow">
                <meta name="language" content="French">

                <title>Age of Olympia - ' . $title . '</title>
                <link rel="icon" type="image/x-icon" href="/img/ui/favicons/favicon.png">
                <script src="js/jquery.js"></script>
                <script src="js/main.js?v=20250516"></script>
                <script src="js/console.js?v=20250516"></script>
                <link href="css/main.min.css?v=20251011" rel="stylesheet" />
                <link rel="stylesheet" href="css/rpg-awesome.min.css">';

        if($loadJQueryUi){
            echo ' <script src="js/jquery-ui.min.js"></script>
                <link rel="stylesheet" href="css/jquery-ui.min.css" />
                ';
        }

        echo '    </head>
            <body>
                ';

        return Str::minify(ob_get_clean());
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

        return Str::minify('
                <sup style="position: absolute; top: 0px; right: 0px; opacity: 0.5;">'. sqln()-1 .' req</sup>
            </body>
        </html>
        ');
    }


    // STATIC

    public static function get_card($data) : string{

        ob_start();

        echo '
        <div id="ui-card">
            ';


            echo '<div class="card-wrapper '. $data->race .'">';


                echo '<div class="card-name">'. $data->name .'</div>';


                echo '<div class="card-image"><img src="'. $data->bg .'" class="card-portrait" /><div id="action-data"></div></div>';


                if(!empty($data->pvPct)){


                    $height = floor((100 - $data->pvPct) * 225 / 100);
                    $height = min($height, 225);

                    // life red filter
                    echo '
                    <div
                        id="red-filter"
                        style="background: #770001; width: 210px; height: '. $height .'px; position: absolute; bottom: 176px; left: 29px; opacity: 0.5; transition: height 0.2s linear;"
                    >
                    </div>
                    ';
                }


                echo '<div class="card-actions">';


                if(!isset($data->noClose)){

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
                }


                echo $data->img;

                echo '</div>';


                echo '<div class="card-type">'. $data->type .'</div>';


                if(!empty($data->faction)){

                    echo '<div class="card-faction">'. $data->faction .'</div>';
                }


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
            <div class="inventory-preview">

                <div class="preview-n">x'. $itemList[$defaultItem->id]->n .'</div>

                <div class="preview-img">
                    <img
                        src="img/items/'. $defaultItem->row->name .'.webp"
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
        <td align="right">
            ';

            echo '<input type="text" value="chercher" id="item-search" style="opacity: 0.5;" class="desaturate" />';

         echo '
        </td>
        </tr>
        <tr>
        <td>
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

                $emplacement = '<div class="emplacement" data-id="'. $row->id .'"><img src="img/ui/inventory/'. $row->equiped .'.jpeg" /></div>';
            }


            $type = (!empty($item->data->type)) ? $item->data->type : '';

            $emp = (!empty($item->data->emplacement)) ? $item->data->emplacement : '';

            echo '
            <tr
                class="item-case"
                id="'. $row->id .'"
                data-id="'. $row->id .'"
                data-name="'. $itemName .'"
                data-n="'. $row->n .'"
                data-text="'. $item->data->text .'"
                data-emplacement="'. $emp .'"
                data-price="'. $item->data->price .'"
                data-type="'. $type .'"
                data-img="img/items/'. $item->row->name .'.webp"
            >
                <td width="50">
                    <div>
                        <img
                            src="img/ui/fillers/50.png"
                            height="50"
                            data-src="img/items/'. $row->name .'_mini.webp"
                        />
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
        window.id = <?php echo $defaultItem->row->id ?>;
        window.name = "<?php echo $defaultItem->row->name ?>";
        window.type = "<?php echo $type ?>";
        window.n =    <?php echo $itemList[$defaultItem->row->id]->n ?>;
        window.price =    1;
        </script>
        <script src="js/inventUi.js?v=20250625"></script>
        <?php

        return Str::minify(ob_get_clean());
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

                    <img src="img/ui/fillers/1.png" data-img="'. $options['avatar'] .'" />
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


                    $player = (!empty($options['player'])) ? $options['player'] : false;

                    $target = (!empty($options['target'])) ? $options['target'] : false;


                    $dialog = new Dialog($options['dialog'], $player, $target);


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
        // $dialogVersion = filemtime('js/dialog.js');
        // $dialogCssVersion = filemtime('css/dialog.min.css');

        echo '
        <script src="js/dialog.js"></script>
        <link rel="stylesheet" href="css/dialog.min.css">
        ';

        return Str::minify(ob_get_clean());
    }

}
