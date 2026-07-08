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
                <script src="js/main.js?v=20260715c"></script>
                <script src="js/console.js?v=20260614"></script>
                <link href="css/main.min.css?v=20260713" rel="stylesheet">
                <link rel="stylesheet" href="css/rpg-awesome.min.css">';

        // Environment-specific body background: test/experimental get a distinct
        // image so it's obvious you're not on prod. Emitted only when it differs
        // from the default, so prod's markup is unchanged. Placed after
        // main.min.css so it overrides the body background-image there.
        $appBg = function_exists('aoo_app_background') ? aoo_app_background() : '/img/ui/bg/bg.jpeg';
        if ($appBg !== '/img/ui/bg/bg.jpeg') {
            echo '<style>body{background-image:url(\'' . $appBg . '\')}</style>';
        }

        /* Thème papier & encre des pages autonomes : chargé UNIQUEMENT
         * quand le joueur (réel, pas le personnage de tutoriel) a
         * l'option newHud — l'habillage hérité ne change pas d'un
         * pixel pour les autres. have_option est mémoïsé, le test est
         * gratuit. Filigrane « aootest » hors prod, comme le HUD. */
        if (self::usesPaperTheme()) {
            echo '<link href="css/paper-app.css?v=20260715a" rel="stylesheet">';

            $paperBg = function_exists('aoo_paper_background') ? aoo_paper_background() : '/img/ui/paper/paper.jpg';
            if ($paperBg !== '/img/ui/paper/paper.jpg') {
                echo '<style>body{background-image:url(\'' . $paperBg . '\')}</style>';
            }
        }

        if($loadJQueryUi){
            echo ' <script src="js/jquery-ui.min.js"></script>
                <link rel="stylesheet" href="css/jquery-ui.min.css" />
                ';
        }

        // Tutorial System (feature-flagged for specific players)
        $tutorialVersion = '20260705a';
        echo '
                <!-- Modal System -->
                <link href="css/modal.css?v=20260715" rel="stylesheet">
                <script src="js/modal.js?v=20260715"></script>

                <!-- Tutorial System -->
                <link href="css/tutorial/tutorial.css?v=' . $tutorialVersion . '" rel="stylesheet">
                <script src="js/tutorial/TutorialPositionManager.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialUI.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialTooltip.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialHighlighter.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialGameIntegration.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialInit.js?v=' . $tutorialVersion . '"></script>
        ';

        echo '    </head>
            <body>
                ';

        // Pre-dim placeholder so the page paints with the spotlight
        // dim already on screen — TutorialHighlighter swaps it for the
        // real SVG mask once it's positioned. Without this the page
        // shows fully un-dimmed for ~200ms before the JS runs, which
        // playtesters called out as a flash on every reload.
        if (!empty($_SESSION['in_tutorial'])) {
            echo '<div id="tutorial-pre-dim"></div>';
        }

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

    /**
     * Le joueur courant voit-il le thème papier (option newHud) ?
     *
     * Lue sur le joueur RÉEL — pendant le tutoriel, le personnage
     * temporaire n'a pas d'options mais l'interface choisie par le
     * joueur doit rester la même (même logique qu'index.php).
     */
    public static function usesPaperTheme(): bool
    {
        /* Surface publique (inscription, connexion, reset) : papier
         * pour tout le monde — l'accueil l'est déjà. */
        if (empty($_SESSION['playerId'])) {
            return true;
        }

        try {
            $mainPlayerId = \App\Tutorial\TutorialHelper::getMainPlayerId();
            $optionPlayerId = $mainPlayerId > 0 ? $mainPlayerId : (int) $_SESSION['playerId'];

            return (bool) \App\Factory\PlayerFactory::legacy($optionPlayerId)->have_option('newHud');
        } catch (\Throwable $e) {
            return false;
        }
    }

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


    /**
     * @param string|null $aeInfo     compteur d'Actions d'Équipement (HUD)
     * @param bool        $rowActions boutons Utiliser/Jeter/Artisanat sur
     *                                chaque ligne (panneau inventaire du
     *                                HUD) — ils délèguent aux boutons de
     *                                l'aperçu via js/inventory.js, la
     *                                logique d'état reste unique.
     * @param int|null    $aeLeft     Ae restantes ce tour : grise les
     *                                boutons de ligne qui en coûtent
     *                                (équiper, parchemins) — mêmes règles
     *                                qu'InventoryService::useItem.
     * @param int|null    $aLeft      Actions restantes : grise Utiliser
     *                                des consommables/structures.
     */
    public static function print_inventory($itemList, ?string $aeInfo = null, bool $rowActions = false, ?int $aeLeft = null, ?int $aLeft = null){


        $defaultItem = new Item(1);
        $defaultItem->get_data();

        /* Liste vide (ex. artisanat sans matériaux) : l'objet par
         * défaut n'y figure pas, l'aperçu affiche alors x0. */
        $defaultItemN = isset($itemList[$defaultItem->id]) ? $itemList[$defaultItem->id]->n : 0;

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

                <div class="preview-n">x'. $defaultItemN .'</div>

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

            if($aeInfo !== null){

                echo $aeInfo;
            }

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
                data-bankable="'. $item->row->is_bankable .'"
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
                ';

            if($rowActions){

                /* Mêmes règles de coût qu'InventoryService::useItem et
                 * js/inventUi.js : déséquiper est gratuit ; équiper et
                 * lire un parchemin coûtent 1 Ae ; consommer coûte 1 A.
                 * Sans le point requis, le bouton est grisé et
                 * l'infobulle dit pourquoi. */
                $isEquipped = !empty($row->equiped);

                if($isEquipped){

                    $usable = true;
                    $useTitle = 'Déséquiper';
                }
                elseif($type == 'equipement'){

                    $usable = ($aeLeft === null || $aeLeft > 0);
                    $useTitle = $usable ? 'Équiper (1 Ae)' : 'Équiper (1 Ae) — plus d\'Action d\'Équipement ce tour';
                }
                elseif($type == 'parchemin' || $emp != ''){

                    $usable = ($aeLeft === null || $aeLeft > 0);
                    $useTitle = $usable ? 'Utiliser (1 Ae)' : 'Utiliser (1 Ae) — plus d\'Action d\'Équipement ce tour';
                }
                elseif($type == 'consommable' || $type == 'structure'){

                    $usable = ($aLeft === null || $aLeft > 0);
                    $useTitle = $usable ? 'Utiliser (1 A)' : 'Utiliser (1 A) — plus d\'Action ce tour';
                }
                else{

                    $usable = false;
                    $useTitle = 'Utiliser';
                }

                /* Porté : bouton « rendre » plein (nuit) — impossible à
                 * confondre avec la main « prendre/équiper ». */
                $useIcon = $isEquipped ? 'ra-reverse' : 'ra-hand';
                $wornClass = $isEquipped ? ' row-action--worn' : '';

                echo '
                <td class="item-actions">
                    <button class="row-action'. $wornClass .'" data-action="use" title="'. $useTitle .'" '. ($usable ? '' : 'disabled') .'><span class="ra '. $useIcon .'"></span></button>
                    <button class="row-action" data-action="drop" title="Jeter"><span class="ra ra-underhand"></span></button>
                    <button class="row-action" data-action="craft" title="Artisanat"><span class="ra ra-forging"></span></button>
                </td>
                ';
            }

            echo '
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
        window.type = "<?php echo $type ?? '' ?>";
        window.n =    <?php echo $defaultItemN ?>;
        window.price =    1;
        </script>
        <script src="js/inventUi.js?v=20260220"></script>
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
