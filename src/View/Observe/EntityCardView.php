<?php

namespace App\View\Observe;

use App\Entity\BuildingDetails;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use App\Service\ActionService;
use App\Service\BuildingService;
use App\Service\FactionService;
use App\Service\RaceService;
use App\Service\Action\ActionTargeting;
use App\Factory\PlayerFactory;
use Classes\Player;
use Classes\Ui;

/**
 * Carte d'une ENTITÉ sélectionnée (personnage, PNJ, bâtiment, objet
 * unique) pour le panneau d'observation — extrait tel quel
 * d'observe.php (ménage n°2 : le contrôleur route, les vues par type
 * d'entité rendent).
 *
 * Rend la carte mutualisée (Ui::get_card) avec effets, boutons
 * d'action (filtrés par portée self/target, catégorie TargetType et
 * contexte d'affichage), boutons de navigation (Missive, Marchander,
 * Apprendre, Parler), pastille d'état de bâtiment, et l'équipement
 * porté (HUD). Les co-occupants de la case sont listés en
 * « autre joueur » (échappés directement dans le tampon).
 */
final class EntityCardView
{
    /**
     * @param \mysqli_result $res lignes players de la case (id, name)
     * @param int|string     $x   coordonnées de la case observée
     * @param int|string     $y
     * @param object         $coords coords du joueur (z / plan)
     *
     * @return array{0: string, 1: string} [$card, $equipStrip]
     */
    public static function render(Player $player, \mysqli_result $res, $x, $y, object $coords): array
    {
        $card = "";
        $equipStrip = "";
        $raceService = new RaceService();
        while($row = $res->fetch_object()){


            $target = PlayerFactory::legacy($row->id);

            $target->get_data();

            $target->get_caracs();
            if(!empty($card)){
                echo ' <div class="case-infos">  <div class="text"> autre joueur:  <a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a> ['.$target->getDisplayId().']</div> </div>';
               continue;
            }

            $dataName = '<a href="infos.php?targetId='. $target->id .'">'. $target->data->name .'</a>';

            $dataName .= '<div class="effects">';

            foreach($target->getEffects() as $effect){


                if(in_array($effect->getName(), EFFECTS_HIDDEN)){

                    continue;
                }

                $dataName .= ' <a href="infos.php?targetId='. $target->id .'"><span class="ra '. EFFECTS_RA_FONT[$effect->getName()] .'"></span></a>';
            }

            $dataName .= '</div>';


            $dataImg = '';


            if($player->check_missive_permission($target)){

                $dataImg .= '<a href="forum.php?newTopic=Missives&targetId='. $target->id .'"><button
                        class="action">
                        <span class="ra ra-quill-ink"></span>
                        <span class="action-name">Missive</span>
                        </button></a><br/>';
            }


            $actions = $player->get_actions();
            $actionService = new ActionService();
            $actions = self::sortActionsByCategory($actions, $actionService);
            $actionTargeting = new ActionTargeting();

            foreach($actions as $actionName){
                if ($actionName == "attaquer") {
                    if ($player->id != $target->id) {
                        $actionData = $actionService->getActionByName("melee");
                        if ($actionData == null) {
                            continue;
                        }
                        if (!$actionTargeting->matchesDisplayContext($actionData, $player, $target)) {
                            continue;
                        }
                        $dataImg .= self::buildActionToDisplay($target, $actionData, $actionService, "attaquer");
                    }
                    continue;
                }

                $actionData = $actionService->getActionByName($actionName);
                if ($actionData == null) {
                    continue;
                }

                // Show the action button only in the context its scope allows:
                // self on yourself, target on someone else, both in either, none
                // nowhere (a no-outcome action — e.g. a technique modifier — has no
                // button here, as the old loop did).
                $observingSelf = ($player->id == $target->id);
                $allowed = $observingSelf
                    ? $actionTargeting->canTargetSelf($actionData)
                    : $actionTargeting->canTargetOther($actionData);

                // And only when the action's TargetType accepts the entity branch
                // of the selection — no Barbier button on a palissade, no Réparer
                // button on a character (the executor would block them anyway).
                $targetCategory = \App\Enum\EntityCategory::fromPlayerType($target->data->player_type ?? 'real');
                $allowed = $allowed && $actionTargeting->canTargetCategory($actionData, $targetCategory);

                /* Contexte d'affichage : les conditions marquées
                 * display_context au workbench sont évaluées ici — le bouton
                 * n'apparaît que si elles passent (ex. RequiresDistance
                 * contextuelle = visible seulement à portée). */
                $allowed = $allowed && $actionTargeting->matchesDisplayContext($actionData, $player, $target);

                if ($allowed) {
                    $dataImg .= self::buildActionToDisplay($target, $actionData, $actionService);
                }
            }


            /* class="action" comme Missive : sans elle, la grille d'actions
             * du HUD ignore ces boutons (nom toujours affiché, taille libre). */
            if($target->have_option('isMerchant')){

                $dataImg .= '<a href="merchant.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-ammo-bag"></span> <span class="action-name">Marchander</span></button></a>';
            }

            if($target->have_option('isTrainer')){

                $dataImg .= '<a href="warschool.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-axe"></span> <span class="action-name">Apprendre</span></button></a>';
            }


            $raceJson = $raceService->getRaceData($target->data->race);

            $pnjText = $target->id<0 ? ' - PNJ' : '';

            // Handle missing race data
            if (!$raceJson) {
                $dataType = ucfirst($target->data->race ?? 'inconnu') . $pnjText;
            } else {
                $dataType = $raceJson->name . $pnjText;
            }

            if ($target->id > 0 && !empty($target->data->isInactive)) {
                $dataType .= ' (inactif)';
            }

            $text = $target->data->text;


            $pvPct = ($target->caracs->pv > 0)
                ? floor($target->getRemaining('pv') / $target->caracs->pv * 100)
                : 100;


            /* Bâtiment : satellite + raison de fermeture calculés ICI, avant
             * la carte — le bouton « Parler » (ouvre la fiche, comme
             * « Marchander » chez les PNJ) doit entrer dans $dataImg, et la
             * pastille d'état après la carte réutilise les mêmes valeurs. */
            $buildingDetails = null;
            $buildingClosure = null;
            if (($target->data->player_type ?? '') === 'building') {

                $buildingService = new BuildingService();
                $buildingDetails = $buildingService->getDetails($target->id);

                if ($buildingDetails !== null) {

                    $buildingClosure = $buildingService->closureReason($buildingDetails, (int) $pvPct);

                    /* Adjacent seulement — même règle que la garde serveur de
                     * la fiche : plus loin, pas d'affordance qui mène à un
                     * « il faut être à côté ». */
                    $obsDistance = \Classes\View::get_distance(
                        $player->getCoords(),
                        (object) array('x' => $x, 'y' => $y, 'z' => $coords->z, 'plan' => $coords->plan)
                    );

                    if ($buildingDetails->getDialog() !== '' && $buildingClosure === null && $obsDistance <= 1) {

                        $dataImg .= '<a href="infos.php?targetId='. $target->id .'"><button class="action"><span class="ra ra-speech-bubble"></span> <span class="action-name">Parler</span></button></a>';
                    }
                }
            }


            $factionJson = (new FactionService())->getFactionData($target->data->faction);

            $faction = '';
            if ($factionJson && isset($factionJson->raFont)) {
                $faction = '<a href="faction.php?faction='. $target->data->faction .'"><span class="ra '. $factionJson->raFont .'"></span></a>';
            }

            if(
                $target->data->secretFaction != ''
                &&
                $target->data->secretFaction == $player->data->secretFaction
            ){

                $secretJson = (new FactionService())->getFactionData($target->data->secretFaction);

                if ($secretJson) {
                    $faction .= '<a href="faction.php?faction='. $target->data->secretFaction .'"><span class="ra '. $secretJson->raFont .'"></span></a>';
                }
            }

            $data = (object) array(
                'bg'=>$target->data->portrait,
                'name'=>$dataName,
                'img'=>$dataImg,
                'pvPct'=>$pvPct,
                'type'=>$dataType,
                'text'=>$text,
                'race'=>$target->data->race,
                'faction'=>$faction
            );

            $card .= Ui::get_card($data);

            /* Bâtiment sélectionné : pastille d'ÉTAT (toujours), porte
             * Ouvert/Fermé pour tout ÉDIFICE (races.structure_nature — un
             * mur construit n'a pas de porte ; son is_open signifiera un
             * jour la passabilité). La CONVERSATION vit dans la fiche
             * (StructureSheetView, façon marchand, garde d'adjacence côté
             * serveur) — le bouton « Parler » ci-dessus l'ouvre. */
            if ($buildingDetails !== null) {

                $stateLabels = array(
                    BuildingDetails::STATE_BUILT => 'Construit',
                    BuildingDetails::STATE_CONSTRUCTION => 'En construction',
                    BuildingDetails::STATE_RUIN => 'Ruine',
                );
                $stateLabel = $stateLabels[$buildingDetails->getBuildState()] ?? ucfirst($buildingDetails->getBuildState());

                $isEdifice = (bool) $raceService->getRaceByName((string) $target->data->race)?->isEdifice();

                $door = '';
                if ($isEdifice) {
                    $door = $buildingClosure === null
                        ? '<span class="building-status-door building-status-door--open">Ouvert</span>'
                        : '<span class="building-status-door building-status-door--closed">Fermé'
                            . ($buildingClosure !== 'fermé volontairement' ? ' (' . $buildingClosure . ')' : '') . '</span>';
                }

                $card .= '<div class="building-status'
                    . ($isEdifice && $buildingClosure !== null ? ' building-status--closed' : '') . '">'
                    . $door
                    . '<span class="building-status-state">' . $stateLabel . ' · PV ' . (int) $pvPct . '%</span>'
                    . '</div>';
            }

            /* Équipement porté par le personnage observé — alvéoles pour
             * la vue de sélection du HUD papier, visibles sur écrans
             * larges seulement (js/hud.js + css/hud.css). L'habillage
             * hérité garde sa carte telle quelle. */
            if (Ui::usesPaperTheme()) {

                $equipStrip = \App\View\EquipmentSlotsView::render($target->id);
            }
        }

        return array($card, $equipStrip);
    }

    /**
     * Trie les actions par catégorie pour regrouper soins et offensives.
     * Ordre: bases -> offensives (melee, distance, spell, technique) -> soins (heal) -> utilitaires
     */
    private static function sortActionsByCategory(array $actions, ActionService $actionService): array {
        $basics = array(
            "attaquer",
            "courir",
            "entrainement",
            "fouiller",
            "prier",
            "repos",
            "vol_a_la_tire"
        );
        $offensiveTypes = array('melee', 'distance', 'spell', 'technique');
        $healType = 'heal';

        $byCategory = array(
            'basics' => array(),
            'offensive' => array(),
            'heal' => array(),
            'utility' => array()
        );

        foreach ($actions as $actionName) {
            if (in_array($actionName, $basics)) {
                $byCategory['basics'][$actionName] = array_search($actionName, $basics);
                continue;
            }
            $actionData = $actionService->getActionByName($actionName);
            if ($actionData === null) {
                $byCategory['utility'][] = $actionName;
                continue;
            }
            $ormType = $actionData->getOrmType();
            if ($ormType === $healType) {
                $byCategory['heal'][] = $actionName;
            } elseif (in_array($ormType, $offensiveTypes)) {
                $byCategory['offensive'][] = $actionName;
            } else {
                $byCategory['utility'][] = $actionName;
            }
        }

        $result = array();
        foreach ($basics as $b) {
            if (isset($byCategory['basics'][$b])) {
                $result[] = $b;
            }
        }
        sort($byCategory['offensive']);
        sort($byCategory['heal']);
        sort($byCategory['utility']);
        return array_merge($result, $byCategory['offensive'], $byCategory['heal'], $byCategory['utility']);
    }

    private static function buildActionToDisplay(ActorInterface $target, ActionInterface $action, ActionService $actionService, ?string $nameOverride = null) : string {
        $icon = (new \App\View\Action\ActionIconView())->forAction($action, 'span');
        $costs = $actionService->getCostsArray(null, $action);
        if ($costs !== []) {
            $icon = '<span flow="up" tooltip="Coût : '. implode(', ', $costs) .'">'. $icon .'</span>';
        }

        $name = $nameOverride ?? $action->getName();
        $label = $nameOverride !== null ? ucfirst($nameOverride) : $action->getDisplayName();

        return '<button
                class="action"
                data-coords-x="'.$target->getCoords()->x.'"
                data-coords-y="'.$target->getCoords(refresh:false)->y.'"
                data-coords-z="'.$target->getCoords(refresh:false)->z.'"
                data-coords-plan="'.$target->getCoords(refresh:false)->plan.'"
                data-target-id="'. $target->getId() .'"
                data-action="'. $name .'"
                >
                '. $icon .'
                <span class="action-name">'. $label .'</span>
                </button><br/>';
    }
}
