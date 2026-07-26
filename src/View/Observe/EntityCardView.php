<?php

namespace App\View\Observe;

use App\Entity\BuildingDetails;
use App\Enum\EntityCategory;
use App\Factory\PlayerFactory;
use App\Interface\ActionInterface;
use App\Interface\ActorInterface;
use App\Service\Action\ActionTargeting;
use App\Service\ActionService;
use App\Service\BuildingService;
use App\Service\FactionService;
use App\Service\RaceService;
use Classes\Player;
use Classes\Str;
use Classes\Ui;
use Classes\View;

/**
 * Carte d'une ENTITÉ sélectionnée (personnage, PNJ, bâtiment, objet
 * unique) pour le panneau d'observation : la carte mutualisée
 * (Ui::get_card) avec effets, boutons d'action filtrés (portée
 * self/target, catégorie TargetType, contexte d'affichage), boutons de
 * navigation (Missive, Marchander, Apprendre, Parler), pastille d'état
 * de bâtiment et équipement porté (HUD). Les co-occupants de la case
 * sont listés en « autre joueur » (échoués directement dans le tampon).
 */
final class EntityCardView
{
    /**
     * @param \mysqli_result $res    lignes players de la case (id, name)
     * @param int|string     $x      coordonnées de la case observée
     * @param int|string     $y
     * @param object         $coords coords du joueur (z / plan)
     *
     * @return array{0: string, 1: string} [$card, $equipStrip]
     */
    public static function render(Player $player, \mysqli_result $res, $x, $y, object $coords): array
    {
        $card = '';
        $equipStrip = '';
        $raceService = new RaceService();

        while ($row = $res->fetch_object()) {
            $target = PlayerFactory::legacy($row->id);
            $target->get_data();
            $target->get_caracs();

            if (!empty($card)) {
                echo ' <div class="case-infos">  <div class="text"> autre joueur:  <a href="infos.php?targetId='
                    . $target->id . '">' . $target->data->name . '</a> [' . $target->getDisplayId() . ']</div> </div>';
                continue;
            }

            [$card, $equipStrip] = self::renderTarget($player, $target, $raceService, $x, $y, $coords);
        }

        return [$card, $equipStrip];
    }

    /**
     * La carte complète de la PREMIÈRE entité de la case.
     *
     * @return array{0: string, 1: string} [$card, $equipStrip]
     */
    private static function renderTarget(Player $player, Player $target, RaceService $raceService, $x, $y, object $coords): array
    {
        $pvPct = ($target->caracs->pv > 0)
            ? floor($target->getRemaining('pv') / $target->caracs->pv * 100)
            : 100;

        // Bâtiment : satellite + raison de fermeture calculés AVANT la
        // carte — le bouton « Parler » entre dans les actions, la
        // pastille d'état après la carte réutilise les mêmes valeurs.
        $buildingDetails = null;
        $buildingClosure = null;
        if (($target->data->player_type ?? '') === 'building') {
            $buildingDetails = (new BuildingService())->getDetails($target->id);
            if ($buildingDetails !== null) {
                $buildingClosure = (new BuildingService())->closureReason($buildingDetails, (int) $pvPct);
            }
        }

        $data = (object) [
            'bg' => $target->data->portrait,
            'name' => self::nameWithEffects($target),
            'img' => self::buttonsHtml($player, $target, $buildingDetails, $buildingClosure, $x, $y, $coords),
            'pvPct' => $pvPct,
            'type' => self::typeLabel($raceService, $target),
            /* Message du jour : saisi par le joueur, donc assaini ici —
             * Ui::get_card sert aussi des textes composés par le jeu
             * (état d'une ressource, avec ses propres balises), on ne
             * peut donc pas assainir dans le composant. */
            'text' => Str::richText($target->data->text),
            'race' => $target->data->race,
            'faction' => self::factionHtml($player, $target),
        ];

        $card = Ui::get_card($data);

        if ($buildingDetails !== null) {
            $card .= self::buildingStatusHtml($raceService, $target, $buildingDetails, $buildingClosure, (int) $pvPct);
        }

        // Équipement porté — alvéoles de la vue de sélection du HUD
        // papier (écrans larges) ; l'habillage hérité garde sa carte.
        $equipStrip = Ui::usesPaperTheme() ? \App\View\EquipmentSlotsView::render($target->id) : '';

        return [$card, $equipStrip];
    }

    /** Nom cliquable + icônes d'effets visibles. */
    private static function nameWithEffects(Player $target): string
    {
        $name = '<a href="infos.php?targetId=' . $target->id . '">' . $target->data->name . '</a>';

        $name .= '<div class="effects">';
        foreach ($target->getEffects() as $effect) {
            if ($target->effectService->isHidden($effect->getName())) {
                continue;
            }
            $name .= ' <a href="infos.php?targetId=' . $target->id . '"><span class="ra '
                . $target->effectService->getIcon($effect->getName()) . '"></span></a>';
        }

        return $name . '</div>';
    }

    /**
     * Tous les boutons de la carte : Missive, les actions du joueur
     * (filtrées), puis la navigation (Marchander, Apprendre, Parler).
     */
    private static function buttonsHtml(
        Player $player,
        Player $target,
        ?BuildingDetails $buildingDetails,
        ?string $buildingClosure,
        $x,
        $y,
        object $coords
    ): string {
        $html = '';

        if ($player->check_missive_permission($target)) {
            $html .= '<a href="forum.php?newTopic=Missives&targetId=' . $target->id . '"><button
                    class="action">
                    <span class="ra ra-quill-ink"></span>
                    <span class="action-name">Missive</span>
                    </button></a><br/>';
        }

        $html .= self::actionButtonsHtml($player, $target);

        /* class="action" comme Missive : sans elle, la grille d'actions
         * du HUD ignore ces boutons (nom toujours affiché, taille libre). */
        if ($target->have_option('isMerchant')) {
            $html .= '<a href="merchant.php?targetId=' . $target->id . '"><button class="action"><span class="ra ra-ammo-bag"></span> <span class="action-name">Marchander</span></button></a>';
        }

        if ($target->have_option('isTrainer')) {
            $html .= '<a href="warschool.php?targetId=' . $target->id . '"><button class="action"><span class="ra ra-axe"></span> <span class="action-name">Apprendre</span></button></a>';
        }

        $html .= self::parlerButtonHtml($player, $target, $buildingDetails, $buildingClosure, $x, $y, $coords);

        return $html;
    }

    /** Les boutons d'action du joueur, passés au triple filtre d'affichage. */
    private static function actionButtonsHtml(Player $player, Player $target): string
    {
        $actionService = new ActionService();
        $actionTargeting = new ActionTargeting();
        $actions = self::sortActionsByCategory($player, $player->get_actions(), $actionService);

        $html = '';
        foreach ($actions as $actionName) {
            $actionData = $actionService->getActionByName($actionName);
            if ($actionData == null) {
                continue;
            }

            if (self::isDisplayable($player, $target, $actionData, $actionTargeting)) {
                $html .= self::buildActionToDisplay($target, $actionData, $actionService);
            }
        }

        return $html;
    }

    /**
     * Le triple filtre d'affichage d'un bouton : portée (self sur soi,
     * target sur autrui), catégorie de cible (TargetType — pas de
     * Barbier sur une palissade), et contexte d'affichage (conditions
     * display_context, ex. RequiresDistance = visible à portée).
     */
    private static function isDisplayable(Player $player, Player $target, ActionInterface $actionData, ActionTargeting $actionTargeting): bool
    {
        $allowed = ($player->id == $target->id)
            ? $actionTargeting->canTargetSelf($actionData)
            : $actionTargeting->canTargetOther($actionData);

        $targetCategory = EntityCategory::fromPlayerType($target->data->player_type ?? 'real');

        return $allowed
            && $actionTargeting->canTargetCategory($actionData, $targetCategory)
            && $actionTargeting->matchesDisplayContext($actionData, $player, $target);
    }

    /**
     * « Parler » / « Lire » (navigation vers la fiche, comme Marchander) :
     * bâtiment ouvert porteur d'un dialogue. La portée suit celle du
     * dialogue — même règle que la garde serveur de la fiche, pas
     * d'affordance qui mène à un « il faut être à côté ».
     */
    private static function parlerButtonHtml(
        Player $player,
        Player $target,
        ?BuildingDetails $buildingDetails,
        ?string $buildingClosure,
        $x,
        $y,
        object $coords
    ): string {
        if ($buildingDetails === null || $buildingDetails->getDialog() === '' || $buildingClosure !== null) {
            return '';
        }

        /* Ce qu'on fait du dialogue décide du verbe ET de la portée :
         * une pancarte se LIT, éventuellement de loin ; une échoppe
         * s'ADRESSE, et seulement de près. Le défaut reste l'historique. */
        $traits = (new \App\Service\DialogService())->traits($buildingDetails->getDialog());
        $isInformative = $traits['kind'] === \App\Service\DialogService::KIND_INFORMATIVE;

        if (!$traits['readableFromAfar']) {
            $distance = View::get_distance(
                $player->getCoords(),
                (object) ['x' => $x, 'y' => $y, 'z' => $coords->z, 'plan' => $coords->plan]
            );
            if ($distance > 1) {
                return '';
            }
        }

        $icon = $isInformative ? 'ra-scroll-unfurled' : 'ra-speech-bubble';
        $label = $isInformative ? 'Lire' : 'Parler';

        return '<a href="infos.php?targetId=' . $target->id . '"><button class="action"><span class="ra ' . $icon . '"></span> <span class="action-name">' . $label . '</span></button></a>';
    }

    /** Ligne de type : libellé de race, suffixes PNJ et inactif. */
    private static function typeLabel(RaceService $raceService, Player $target): string
    {
        $raceJson = $raceService->getRaceData($target->data->race);
        $pnjText = $target->id < 0 ? ' - PNJ' : '';

        $label = $raceJson
            ? $raceJson->name . $pnjText
            : ucfirst($target->data->race ?? 'inconnu') . $pnjText;

        if ($target->id > 0 && !empty($target->data->isInactive)) {
            $label .= ' (inactif)';
        }

        return $label;
    }

    /** Icônes de faction — la secrète seulement entre membres. */
    private static function factionHtml(Player $player, Player $target): string
    {
        $factionService = new FactionService();

        $faction = '';
        $factionJson = $factionService->getFactionData($target->data->faction);
        if ($factionJson && isset($factionJson->raFont)) {
            $faction = '<a href="faction.php?faction=' . $target->data->faction . '"><span class="ra '
                . $factionJson->raFont . '"></span></a>';
        }

        if ($target->data->secretFaction != '' && $target->data->secretFaction == $player->data->secretFaction) {
            $secretJson = $factionService->getFactionData($target->data->secretFaction);
            if ($secretJson) {
                $faction .= '<a href="faction.php?faction=' . $target->data->secretFaction . '"><span class="ra '
                    . $secretJson->raFont . '"></span></a>';
            }
        }

        return $faction;
    }

    /**
     * Pastille d'état sous la carte : porte Ouvert/Fermé pour tout
     * ÉDIFICE (un mur construit n'a pas de porte), état + PV pour tous.
     */
    private static function buildingStatusHtml(
        RaceService $raceService,
        Player $target,
        BuildingDetails $details,
        ?string $closure,
        int $pvPct
    ): string {
        $stateLabels = [
            BuildingDetails::STATE_BUILT => 'Construit',
            BuildingDetails::STATE_CONSTRUCTION => 'En construction',
            BuildingDetails::STATE_RUIN => 'Ruine',
        ];
        $stateLabel = $stateLabels[$details->getBuildState()] ?? ucfirst($details->getBuildState());

        $isEdifice = (bool) $raceService->getRaceByName((string) $target->data->race)?->isEdifice();

        $door = '';
        if ($isEdifice) {
            $door = $closure === null
                ? '<span class="building-status-door building-status-door--open">Ouvert</span>'
                : '<span class="building-status-door building-status-door--closed">Fermé'
                    . ($closure !== 'fermé volontairement' ? ' (' . $closure . ')' : '') . '</span>';
        }

        return '<div class="building-status'
            . ($isEdifice && $closure !== null ? ' building-status--closed' : '') . '">'
            . $door
            . '<span class="building-status-state">' . $stateLabel . ' · PV ' . $pvPct . '%</span>'
            . '</div>';
    }

    /**
     * Trie les actions : bases, puis offensives, soins et utilitaires
     * (chaque groupe en ordre alphabétique).
     */
    private static function sortActionsByCategory(Player $player, array $actions, ActionService $actionService): array
    {
        /* L'ordre des actions de base vient de la LISTE DE DÉPART de la
         * race (race_starter_actions.position), éditable dans la page
         * Races. Elle était recopiée ici dans un tableau littéral, où
         * l'attaque devait sa première place à son seul rang d'écriture
         * — la scission d'« attaquer » l'avait donc fait disparaître au
         * milieu des actions offensives. Réordonner est désormais un
         * geste d'admin, pas une modification de code. */
        $raceData = (new RaceService())->getRaceData((string) ($player->data->race ?? ''));
        $basics = is_array($raceData->actions ?? null) ? $raceData->actions : [];

        $offensiveTypes = ['melee', 'distance', 'spell', 'technique'];

        $byCategory = ['basics' => [], 'offensive' => [], 'heal' => [], 'utility' => []];

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
            if ($ormType === 'heal') {
                $byCategory['heal'][] = $actionName;
            } elseif (in_array($ormType, $offensiveTypes)) {
                $byCategory['offensive'][] = $actionName;
            } else {
                $byCategory['utility'][] = $actionName;
            }
        }

        $result = [];
        foreach ($basics as $basic) {
            if (isset($byCategory['basics'][$basic])) {
                $result[] = $basic;
            }
        }
        sort($byCategory['offensive']);
        sort($byCategory['heal']);
        sort($byCategory['utility']);

        return array_merge($result, $byCategory['offensive'], $byCategory['heal'], $byCategory['utility']);
    }

    /** Un bouton d'action du panneau (coût en info-bulle, coords de la cible). */
    private static function buildActionToDisplay(ActorInterface $target, ActionInterface $action, ActionService $actionService, ?string $nameOverride = null): string
    {
        $icon = (new \App\View\Action\ActionIconView())->forAction($action, 'span');
        $costs = $actionService->getCostsArray(null, $action);
        if ($costs !== []) {
            $icon = '<span flow="up" tooltip="Coût : ' . implode(', ', $costs) . '">' . $icon . '</span>';
        }

        $name = $nameOverride ?? $action->getName();
        $label = $nameOverride !== null ? ucfirst($nameOverride) : $action->getDisplayName();

        return '<button
                class="action"
                data-coords-x="' . $target->getCoords()->x . '"
                data-coords-y="' . $target->getCoords(refresh:false)->y . '"
                data-coords-z="' . $target->getCoords(refresh:false)->z . '"
                data-coords-plan="' . $target->getCoords(refresh:false)->plan . '"
                data-target-id="' . $target->getId() . '"
                data-action="' . $name . '"
                >
                ' . $icon . '
                <span class="action-name">' . $label . '</span>
                </button><br/>';
    }
}
