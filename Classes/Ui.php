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
                <script src="js/main.js?v=20260806"></script>
                <script src="js/console.js?v=20260614"></script>
                ' . self::characterStoreTag() . '
                <link href="css/main.min.css?v=20260805" rel="stylesheet">
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
                <script src="js/modal.js?v=20260725"></script>

                <!-- Tutorial System -->
                <link href="css/tutorial/tutorial.css?v=' . $tutorialVersion . '" rel="stylesheet">
                <script src="js/tutorial/TutorialPositionManager.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialUI.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialTooltip.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialHighlighter.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialGameIntegration.js?v=' . $tutorialVersion . '"></script>
                <script src="js/tutorial/TutorialInit.js?v=' . $tutorialVersion . '"></script>

                <!-- Choix de case de construction (réutilise le spotlight tutoriel) -->
                <script src="js/build_picker.js?v=20260804c"></script>
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
     * L'étagère de réglages d'interface du personnage ACTIF.
     *
     * Zoom du damier, recentrage, volets ouverts, case sélectionnée, dernier
     * évènement lu : autant de réglages qui appartiennent à un personnage et
     * non à un navigateur. La perception fixe la taille du plateau — le zoom
     * qui cadre un nain à p=4 laisse un elfe à p=7 hors champ —, si bien
     * qu'en changeant de personnage on héritait d'un cadrage faux.
     *
     * L'identifiant est celui du personnage ACTIF, donc celui du tutoriel
     * pendant un tutoriel : ses réglages n'écrasent plus ceux du joueur.
     *
     * Hors session (connexion, inscription) l'identifiant vaut 0 et
     * `js/aoo-store.js` retombe sur une étagère anonyme.
     */
    private static function characterStoreTag(): string
    {
        try {
            $characterId = \App\Tutorial\TutorialHelper::getActivePlayerId();
        } catch (\Throwable $e) {
            $characterId = 0;
        }

        return '<script>window.aooCharacterId = ' . (int) $characterId . ';</script>'
            . '<script src="js/aoo-store.js?v=20260727"></script>';
    }

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

    /**
     * The cut-out a constructible's built form will claim, as offsets JSON
     * for the build picker's ghost — '' when single-cell (no ghost needed).
     */
    private static function constructibleFootprint(string $itemName): string
    {
        static $catalogue = null;
        if ($catalogue === null) {
            $catalogue = (new \App\Service\Map\EntityTypeFootprintService())->catalogue();
        }

        $footprint = $catalogue[$itemName] ?? null;
        if ($footprint === null || $footprint->isSingleCell()) {
            return '';
        }

        return htmlspecialchars((string) json_encode(array_values($footprint->offsets())), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sprite the picker ghosts over the chosen cells — the board's own
     * rule (View::structureSprite), never a copy of it: an imageless
     * type shows the same initials frame it will stand with.
     */
    private static function constructibleGhostImage(string $itemName, string $label): string
    {
        return htmlspecialchars(View::structureSprite($itemName, $label), ENT_QUOTES, 'UTF-8');
    }

    public static function get_card($data) : string{

        ob_start();

        echo '
        <div id="ui-card">
            ';


            /* La carte magic disparue, le lien race↔couleur se lit ici :
             * cadre du portrait et pastille du type (via --race-bg/--race-fg,
             * même convention que scripts/logs/body.php) */
            $raceJson = (new \App\Service\RaceService())->getRaceData((string) ($data->race ?? ''));
            $raceStyle = '';
            $raceColorClass = '';
            if ($raceJson !== null && !empty($raceJson->bgColor)) {
                $raceStyle = ' style="--race-bg: '. $raceJson->bgColor .'; --race-fg: '. ($raceJson->color ?: '#eee') .';"';
                $raceColorClass = ' race-colored';
            }

            echo '<div class="card-wrapper '. $data->race . $raceColorClass .'"'. $raceStyle .'>';


                echo '<div class="card-name">'. $data->name .'</div>';


                /* A multi-cell figure has no single image: the card
                 * recomposes it from its pieces. */
                /* A multi-cell figure has no single image: the card
                 * recomposes it from its pieces. */
                $portrait = $data->portraitHtml
                    ?? '<img src="'. $data->bg .'" class="card-portrait" />';

                /* isset, not !empty: at 0 PV — the worst case — !empty(0)
                 * was false and nothing was veiled at all. */
                $veil = '';

                if(isset($data->pvPct)){

                    /* A PERCENTAGE, and INSIDE the portrait: pinned to a
                     * 225 px box it meant nothing once a figure changed
                     * proportions. */
                    $lost = min(100, max(0, 100 - (int) $data->pvPct));

                    // life filter, teinté par la race/le type (wound_color)
                    $woundColor = (new \App\Service\RaceService())->getRaceWoundColor($data->race ?? null);

                    $veil = '<div id="red-filter" data-lost="'. $lost .'"'
                        . ' style="background: '. $woundColor .'; position: absolute;'
                        . ' left: 0; right: 0; bottom: 0; height: '. $lost .'%;'
                        . ' opacity: 0.5; transition: height 0.2s linear; pointer-events: none;"></div>';
                }

                echo '<div class="card-image"><span class="card-portrait-box"'
                    . ' style="position:relative;display:block;">'
                    . $portrait . $veil
                    . '</span><div id="action-data"></div></div>';


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


                /* Pas de boîte quand il n'y a rien dedans : un cadre vide
                 * se lit comme un défaut d'affichage. Depuis que les
                 * décors n'ont plus de texte de création, la plupart des
                 * entités n'ont rien à dire. */
                if(trim((string) ($data->text ?? '')) !== ''){

                    echo '<div class="card-text">'. $data->text .'</div>';
                }


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
     *                                (équiper) — mêmes règles
     *                                qu'InventoryService::useItem.
     * @param int|null    $aLeft      Actions restantes : grise Utiliser
     *                                des consommables/structures.
     */
    public static function print_inventory($itemList, ?string $aeInfo = null, bool $rowActions = false, ?int $aeLeft = null, ?int $aLeft = null, ?string $bagLabel = null){


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


        /* Two explicit columns: the image block LEFT, the description
         * RIGHT — structure in the markup, so no stylesheet cascade can
         * stack them again. */
        echo '
            <div class="inventory-preview">

                <div class="preview-left">
                    <div class="preview-n">x'. $defaultItemN .'</div>

                    <div class="preview-img">
                        <img
                            src="img/items/'. $defaultItem->row->name .'.webp"
                            data-filler="img/ui/fillers/150.png"
                            width="150"
                            height="150"
                        />
                    </div>
                    <div class="preview-state" style="color:#7a4a12;font-weight:bold;"></div>
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

        /* Two sections when asked (the inventory page): what is WORN
         * above, the BAG below with its line gauge in the header — the
         * limit reads exactly on what it counts. Other callers (bank,
         * craft, market) keep the flat list. */
        $groups = [[null, $itemList]];
        if ($bagLabel !== null) {
            $worn = [];
            $bag = [];
            foreach ($itemList as $k => $r) {
                if (!empty($r->equiped)) {
                    $worn[$k] = $r;
                } else {
                    $bag[$k] = $r;
                }
            }
            $groups = [['Équipé', $worn], [$bagLabel, $bag]];
        }

        foreach ($groups as [$sectionTitle, $sectionRows]) {

            if ($sectionTitle !== null && $sectionRows === []) {
                continue;
            }
            if ($sectionTitle !== null) {
                echo '<tr class="inventory-section"><td colspan="9" style="text-align:center; font-weight:bold; padding: 6px 4px;">'
                    . $sectionTitle . '</td></tr>';
            }

        foreach($sectionRows as $row){


            $item = new Item($row->id, $row);

            $item->get_data();

            $caracs = Item::get_item_carac($item->data);


            $itemName = Item::get_formatted_name(ucfirst($item->data->name), $row);

            /* Instance nommée à la création : son nom prime, le type
             * catalogue reste entre parenthèses. */
            if(!empty($row->custom_name)){

                $itemName = '« '. htmlspecialchars($row->custom_name, ENT_QUOTES, 'UTF-8') .' » ('. $itemName .')';
            }

            /* État d'une instance (usure, seuils décidés en revue) :
             * ligne colorée sous les caracs + data-state pour l'aperçu.
             * La ligne vient d'ItemInstanceService, seule source des
             * seuils et des paliers de couleur — le marché et les
             * échanges affichent EXACTEMENT le même état. */
            $stateLine = \App\Service\ItemInstanceService::stateLine($row);
            $stateAttr = '';
            if($stateLine !== ''){

                $stateAttr = \App\Service\ItemInstanceService::isBroken((int) $row->durability)
                    ? 'Brisé — ne contribue plus ses caractéristiques.'
                    : 'Durabilité '. (int) $row->durability .'/'. (int) $row->durability_max;
            }


            $emplacement = '';

            if(!empty($row->equiped) && $row->equiped != ''){

                $emplacement = '<div class="emplacement" data-id="'. $row->id .'"><img src="img/ui/inventory/'. $row->equiped .'.jpeg" /></div>';
            }


            $type = (!empty($item->data->type)) ? $item->data->type : '';

            $emp = (!empty($item->data->emplacement)) ? $item->data->emplacement : '';

            /* Ligne d'INSTANCE (objet individualisé) vs ligne de pile :
             * id DOM distinct (i{instanceId} — deux lignes du même objet
             * catalogue ne doivent pas partager un id), et attribut dédié
             * pour les flux qui devront viser l'individu. */
            $isInstance = isset($row->instance_id);
            $domId = $isInstance ? 'i'. (int) $row->instance_id : $row->id;

            /* Ce que « Utiliser » ferait (source unique côté serveur) —
             * vide : rien, le bouton doit rester grisé partout. */
            $useKind = (string) \App\Service\InventoryService::useKind($item);

            /* The item's face, wherever it shows: its art, else the
             * exemplar chain (walls sprite, initials frame) — a chest
             * has no img/items file and showed a broken-image glyph. */
            $rowSprite = \Classes\View::exemplarSprite((string) $item->row->name, strip_tags((string) $itemName));
            $miniCandidate = 'img/items/'. $row->name .'_mini.webp';
            $rowMini = file_exists($miniCandidate) ? $miniCandidate : $rowSprite;

            echo '
            <tr
                class="item-case"
                id="'. $domId .'"
                data-id="'. $row->id .'"
                data-instance-id="'. ($isInstance ? (int) $row->instance_id : '') .'"
                data-equiped="'. (!empty($row->equiped) ? '1' : '0') .'"
                data-use-kind="'. $useKind .'"
                data-name="'. $itemName .'"
                data-n="'. $row->n .'"
                data-text="'. $item->data->text .'"
                data-emplacement="'. $emp .'"
                data-price="'. $item->data->price .'"
                data-type="'. $type .'"
                data-bankable="'. $item->row->is_bankable .'"
                data-state="'. $stateAttr .'"
                data-build-action="'. ($type == Item::TYPE_CONSTRUCTIBLE ? 'construire' : '') .'"
                data-fp="'. ($type == Item::TYPE_CONSTRUCTIBLE ? self::constructibleFootprint((string) $item->row->name) : '') .'"
                data-fp-img="'. ($type == Item::TYPE_CONSTRUCTIBLE ? self::constructibleGhostImage((string) $item->row->name, (string) $itemName) : '') .'"
                data-img="'. htmlspecialchars($rowSprite, ENT_QUOTES, 'UTF-8') .'"
            >
                <td width="50">
                    <div>
                        <img
                            src="img/ui/fillers/50.png"
                            height="50"
                            data-src="'. htmlspecialchars($rowMini, ENT_QUOTES, 'UTF-8') .'"
                        />
                    </div>
                </td>
                <td align="left" class="item-name">
                    '. $itemName .'<br />
                    '. implode(', ', $caracs) . $stateLine .'

                    '. $emplacement .'
                </td>
                <td width="50">
                    x'. $row->n .'
                </td>
                ';

            if($rowActions){

                /* Mêmes règles de coût qu'InventoryService::useItem et
                 * js/inventUi.js : déséquiper est gratuit ; équiper
                 * coûte 1 Ae ; consommer coûte 1 A. Sans le point
                 * requis, le bouton est grisé et l'infobulle dit
                 * pourquoi. */
                $isEquipped = !empty($row->equiped);

                if($isEquipped){

                    $usable = true;
                    $useTitle = 'Déséquiper';
                }
                elseif($type == Item::TYPE_CONSTRUCTIBLE){

                    /* Un objet constructible se bâtit DEPUIS l'inventaire —
                     * un bouton par objet possédé, pas un bouton d'action
                     * par type dans le panneau de case. */
                    $usable = ($aLeft === null || $aLeft > 0);
                    $useTitle = $usable ? 'Construire (1 A)' : 'Construire (1 A) — plus d\'Action ce tour';
                }
                elseif($useKind === \App\Service\InventoryService::USE_EQUIP){

                    $usable = ($aeLeft === null || $aeLeft > 0);
                    $verb = $type == 'equipement' ? 'Équiper' : 'Utiliser';
                    $useTitle = $usable ? $verb .' (1 Ae)' : $verb .' (1 Ae) — plus d\'Action d\'Équipement ce tour';
                }
                elseif($useKind === \App\Service\InventoryService::USE_CONSUME){

                    $usable = ($aLeft === null || $aLeft > 0);
                    $useTitle = $usable ? 'Utiliser (1 A)' : 'Utiliser (1 A) — plus d\'Action ce tour';
                }
                else{

                    /* Aucun usage réel (consommable sans bonus,
                     * matériau…) : un clic ne ferait RIEN — bouton
                     * grisé, et useItem refuserait de toute façon. */
                    $usable = false;
                    $useTitle = 'Cet objet n\'a pas d\'usage direct';
                }

                /* Porté : bouton « rendre » plein (nuit) — impossible à
                 * confondre avec « équiper ». Une icône par geste :
                 * équiper ≠ consommer ≠ lire (retours joueurs juillet
                 * 2026 — la même main pour tout prêtait à confusion). */
                if($isEquipped){
                    $useIcon = 'ra-reverse';
                }
                elseif($type == 'equipement'){
                    $useIcon = 'ra-vest';
                }
                elseif($type == 'consommable'){
                    $useIcon = 'ra-potion';
                }
                else{
                    $useIcon = 'ra-hand';
                }
                $wornClass = $isEquipped ? ' row-action--worn' : '';

                /* Jeter vaut pour les deux représentations : la pile part
                 * en bourse de case (map_items), l'instance individualisée
                 * (arme usée…) descend au sol avec son identité (dropAt →
                 * entité au sol) — une instance encore portée se
                 * déséquipe d'abord. L'Artisanat, lui, n'opère que sur la
                 * PILE (décrément) : masqué sur une ligne d'instance. */
                $stackActions = '';
                if(!$isInstance){

                    $craftAction = '<button class="row-action" data-action="craft" title="Artisanat"><span class="ra ra-forging"></span></button>';

                    $stackActions = '
                    <button class="row-action" data-action="drop" title="Jeter"><span class="ra ra-underhand"></span></button>'. $craftAction;
                }
                elseif(!$isEquipped){

                    $stackActions = '
                    <button class="row-action" data-action="drop" title="Jeter"><span class="ra ra-underhand"></span></button>';
                }

                echo '
                <td class="item-actions">
                    <button class="row-action'. $wornClass .'" data-action="use" title="'. $useTitle .'" '. ($usable ? '' : 'disabled') .'><span class="ra '. $useIcon .'"></span></button>'. $stackActions .'
                </td>
                ';
            }

            echo '
            </tr>
            ';
        }
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
        <script src="js/inventUi.js?v=20260804"></script>
        <?php

        return Str::minify(ob_get_clean());
    }

    /**
     * Voile de sang sur un portrait : la part manquante de PV monte
     * depuis le bas (même lecture que le filtre rouge de la carte de
     * sélection ci-dessus). Chaîne vide si indemne. Styles en ligne :
     * utilisable dans l'habillage hérité comme dans le HUD sans
     * toucher aux pipelines CSS. Le parent doit être en
     * position:relative ; pointer-events:none laisse cliquer au
     * travers (cartes de personnages secondaires).
     */
    /**
     * @param string|null $woundColor teinte du voile (hex #RRGGBB),
     *        races.wound_color — null/invalide : rouge sang historique
     */
    public static function get_pv_veil(int $pvPct, ?string $woundColor = null): string
    {
        if ($pvPct >= 100) {

            return '';
        }

        $height = min(100 - $pvPct, 100);
        $rgb = self::hexToRgb($woundColor);

        /* --pv-veil-rgb : la teinte de race exposée à la feuille de
         * style. Les styles en ligne restent le rendu de référence
         * (aucun habillage ne peut faire disparaître le voile) ; la
         * variable permet au HUD de RENFORCER le voile en vignette
         * mobile sans réécrire la couleur — cf. css/hud.css. */
        return '<div class="pv-veil" style="--pv-veil-rgb: ' . $rgb . '; position: absolute; left: 0; bottom: 0; width: 100%; height: ' . $height . '%; background: rgba(' . $rgb . ', 0.35); border-top: 2px solid rgba(' . $rgb . ', 0.7); pointer-events: none;"></div>';
    }

    /**
     * '#RRGGBB' -> 'r, g, b' pour composer des rgba(). Toute entrée
     * invalide retombe sur le rouge sang du voile historique.
     */
    private static function hexToRgb(?string $hex): string
    {
        $parsed = sscanf((string) $hex, '#%02x%02x%02x');

        if (!is_array($parsed) || in_array(null, $parsed, true)) {

            $parsed = [119, 0, 1];
        }

        return implode(', ', $parsed);
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


                    /* Passerelle unique (table dialogs, repli fichier) ; le
                       cache par requête évite la double lecture avec le
                       new Dialog() du rendu ci-dessous */
                    $dialogJson = (new \App\Service\DialogService())->loadDialog($options['dialog']);

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
        <script src="js/dialog.js?v=20260716"></script>
        <link rel="stylesheet" href="css/dialog.min.css">
        ';

        return Str::minify(ob_get_clean());
    }

}
