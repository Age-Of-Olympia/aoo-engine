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
use App\View\Entity\EntityParts;
use App\View\Entity\EntityProfile;
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
    public static function render(Player $player, \mysqli_result $res, $x, $y, object $coords, int $focusId = 0): array
    {
        $ids = [];

        while ($row = $res->fetch_object()) {
            $ids[] = (int) $row->id;
        }

        if ($ids === []) {
            return ['', ''];
        }

        /* Whoever was asked for gets the card; failing that, the first. Asking
         * matters because the card is where the ACTIONS are: without it, the
         * others could be read but never acted on. */
        $focus = in_array($focusId, $ids, true) ? $focusId : $ids[0];

        $target = PlayerFactory::legacy($focus);
        $target->get_data();
        $target->get_caracs();

        [$card, $equipStrip] = self::renderTarget($player, $target, $x, $y, $coords);

        self::echoOthers($ids, $focus, $x, $y);

        return [$card, $equipStrip];
    }

    /**
     * The rest of the cell, as things one can switch to.
     *
     * They used to link to the character sheet, which answered « error target
     * id » on anything that is not a character, and never let one act on what
     * was clicked. Clicking now re-opens the panel ON that entity.
     *
     * @param list<int> $ids
     */
    private static function echoOthers(array $ids, int $focus, $x, $y): void
    {
        foreach ($ids as $id) {
            if ($id === $focus) {
                continue;
            }

            $other = PlayerFactory::legacy($id);
            $other->get_data();

            echo ' <div class="case-infos"> <div class="text"> aussi ici : '
                . '<a href="#" class="case-other" data-observe-entity="' . $id . '"'
                . ' data-observe-coords="' . (int) $x . ',' . (int) $y . '">'
                . htmlspecialchars((string) $other->data->name, ENT_QUOTES, 'UTF-8') . '</a>'
                . ' [' . $other->getDisplayId() . ']</div> </div>';
        }
    }

    /**
     * La carte complète de la PREMIÈRE entité de la case : la forme
     * courte de la fiche, lue dans le même EntityProfile, avec les
     * gestes de la case en plus.
     *
     * @return array{0: string, 1: string} [$card, $equipStrip]
     */
    private static function renderTarget(Player $player, Player $target, $x, $y, object $coords): array
    {
        $profile = new EntityProfile($player, $target);

        /* An altar shows WHOSE it is: its god's portrait behind the card and
         * a link to their sheet. */
        $name = '<a href="infos.php?targetId=' . $target->id . '">' . $target->data->name . '</a>'
            . EntityParts::effectIconsHtml($profile)
            . ($profile->god !== null
                ? ' <a href="infos.php?targetId=' . $profile->god->id . '">('
                    . htmlspecialchars((string) $profile->god->data->name, ENT_QUOTES, 'UTF-8') . ')</a>'
                : '');

        $data = (object) [
            'bg' => $profile->portraitUrl(),
            /* A multi-cell decor shows whole, not by the corner its
             * portrait happens to name. */
            'portraitHtml' => (new SceneryPortraitView())->compose((int) $target->id),
            'name' => $name,
            'img' => self::buttonsHtml($player, $target, $profile->details, $profile->closure, $x, $y, $coords),
            'pvPct' => $profile->pvPct,
            'type' => $profile->typeLabel(),
            'text' => $profile->textHtml(),
            'race' => $target->data->race,
            'faction' => EntityParts::factionIconsHtml($profile),
        ];

        $card = Ui::get_card($data)
            . EntityParts::statusHtml($profile)
            . self::familyStatusHtml($player, $target, (string) $coords->plan, (int) $x, (int) $y);

        // Équipement porté — alvéoles de la vue de sélection du HUD
        // papier (écrans larges) ; l'habillage hérité garde sa carte.
        $equipStrip = Ui::usesPaperTheme() && $profile->detailed ? \App\View\EquipmentSlotsView::render($target->id) : '';

        return [$card, $equipStrip];
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
         * du HUD ignore ces boutons (nom toujours affiché, taille libre).
         * Un bouton par comptoir servi, avec son icône et son libellé
         * (DialogService::counterButtons) : « Banque », « Réparer »,
         * « Recycler », sinon « Marchander » ou « Apprendre ». Bâtiment
         * fermé : aucun comptoir, même règle que Parler. */
        $counterDialog = ($buildingClosure === null && $buildingDetails !== null)
            ? $buildingDetails->getDialog()
            : '';

        $counters = $counterDialog !== ''
            ? (new \App\Service\DialogService())->counterButtons($counterDialog)
            : [];

        foreach ($counters as $counter) {
            $url = $counter['script'] . '?targetId=' . $target->id
                . ($counter['tab'] !== '' ? '&' . $counter['tab'] : '');
            // Icon and label come from the admin's dialog JSON
            $html .= '<a href="' . htmlspecialchars($url) . '"><button class="action"><span class="ra '
                . htmlspecialchars($counter['icon']) . '"></span> <span class="action-name">'
                . htmlspecialchars($counter['label']) . '</span></button></a>';
        }

        $html .= self::containerBlockHtml($player, $target);

        /* A counter screen already carries the tenant's dialogue: no
         * second door to the same conversation. Lire (inscription) and
         * Parler on a dialogue-only building are untouched. */
        $html .= self::parlerButtonHtml($player, $target, $buildingDetails, $buildingClosure, $counters !== []);

        return $html;
    }

    /**
     * The way into a container, on anything the type says can be shut —
     * a chest, an édifice: "Ouvrir" (the two-pane screen) shows when
     * the container serves from here. The LOCK is not this button's
     * business: `fermer` / `ouvrir` are engine actions whose display
     * conditions put them in the grid.
     */
    private static function containerBlockHtml(Player $player, Player $target): string
    {
        if (!(new \App\Service\LockService())->isLockable((int) $target->id)) {
            return '';
        }

        $service = new \App\Service\ContainerService();
        $html = '';

        try {
            $service->assertUsable((int) $target->id, (int) $player->id);

            /* A navigation button, like Marchander: a link around it and
             * no data-action, so both action handlers step aside and the
             * HUD panel router (panelUrl in js/hud.js) slides the
             * fragment in. The full page stays the no-JS fallback. */
            $html .= '<a href="container.php?targetId=' . (int) $target->id . '"><button class="action">'
                . '<span class="ra ra-ammo-bag"></span> <span class="action-name">Ouvrir</span></button></a>';
        } catch (\RuntimeException) {
            // Too far, shut, or not one of its people: no way in from here.
        }

        /* The LOCK is a real engine action — `fermer` / `ouvrir`, whose
         * display conditions (reach, control, state) put the button in
         * the grid like any other gesture. Nothing to add here. */
        return $html;
    }

    /** Les boutons d'action du joueur, passés au triple filtre d'affichage. */
    private static function actionButtonsHtml(Player $player, Player $target): string
    {
        $actionService = new ActionService();
        $actionTargeting = new ActionTargeting();
        $actionService->preload($player->get_actions());
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

        return $allowed
            && $actionTargeting->canTargetEntity($actionData, $target->data->player_type ?? 'real')
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
        bool $hasCounter
    ): string {
        if ($buildingClosure !== null) {
            return '';
        }

        /* Ce que l'objet a à dire décide du verbe : une inscription se
         * LIT (players.text, le MDJ d'un bâtiment), une échoppe
         * s'ADRESSE (dialogue du catalogue) — sauf quand un comptoir
         * (Marchander, Apprendre) porte déjà cette conversation. */
        $inscription = \App\Service\BuildingService::inscriptionOf($target);
        $hasDialog = !$hasCounter && $buildingDetails !== null && $buildingDetails->getDialog() !== '';

        if ($inscription === '' && !$hasDialog) {
            return '';
        }

        $readsFromAfar = $inscription !== ''
            && \App\Service\BuildingService::readsFromAfar($target, $buildingDetails);

        /* To the whole entity: one talks to a building from any of its
         * cells, not only the one clicked. */
        $distance = View::get_distance_to_entity($player->getCoords(), (int) $target->id, $target->getCoords());

        $icon = $inscription !== '' ? 'ra-scroll-unfurled' : 'ra-speech-bubble';
        $label = $inscription !== '' ? 'Lire' : 'Parler';

        /* Trop loin : on le DIT plutôt que de masquer le bouton. Une
         * affordance absente ne se distingue pas d'un objet muet — le
         * joueur ne saurait jamais qu'il avait quelque chose à lire. */
        if (!$readsFromAfar && $distance > 1) {
            return '<button class="action" disabled title="Approchez-vous pour ' . strtolower($label) . '">'
                . '<span class="ra ' . $icon . '"></span> '
                . '<span class="action-name">' . ($inscription !== '' ? 'Trop loin pour lire' : 'Trop loin pour parler')
                . '</span></button>';
        }

        return '<a href="infos.php?targetId=' . $target->id . '"><button class="action"><span class="ra ' . $icon . '"></span> <span class="action-name">' . $label . '</span></button></a>';
    }

    /** The badge under the card that says what this family of entity is good for. */
    private static function familyStatusHtml(Player $viewer, Player $target, string $plan, int $x, int $y): string
    {
        return match ((string) ($target->data->player_type ?? '')) {
            'resource' => self::resourceStatusHtml($viewer, $target, $plan),
            'plant' => self::plantStatusHtml($viewer, $x, $y),
            'route' => self::statusBadgeHtml('Courir y est possible', false),
            default => '',
        };
    }

    /**
     * A plant has no exhausted state: picked in one go, it is gone. It is
     * harvestable as long as it is there — from its own cell.
     */
    private static function plantStatusHtml(Player $viewer, int $x, int $y): string
    {
        $onOwnTile = $x === (int) $viewer->coords->x && $y === (int) $viewer->coords->y;

        return self::statusBadgeHtml('Récoltable', false)
            . ($onOwnTile
                ? '<button class="action action--direct ground-take" data-plants="1">'
                    . '<span class="ra ra-hand"></span> <span class="action-name">Cueillir</span></button>'
                : '<sup>Allez sur la case pour cueillir.</sup>');
    }

    /**
     * Pastille de RÉCOLTE : ce qu'on peut encore tirer de la case.
     *
     * L'état d'une ressource — debout ou épuisée — était lisible tant que le
     * mur portait ses dégâts sur la carte. Depuis qu'il vit dans le satellite
     * `resources`, la carte ne le disait plus : on fouillait un rocher déjà
     * vidé sans le savoir, et il n'y avait plus aucun moyen de le voir avant
     * d'y passer son tour.
     *
     * L'habillage est celui de la pastille des bâtiments, à dessein : c'est la
     * même phrase — « voici l'état de ce qui occupe la case » — et deux styles
     * pour une seule idée finiraient par diverger.
     */
    private static function resourceStatusHtml(Player $viewer, Player $target, string $plan): string
    {
        // Same catalogue as the search condition: a type without a yield is
        // not harvestable, whatever the map says.
        $race = (string) ($target->data->race ?? '');
        $yields = (new \App\Service\Map\HarvestCatalogService())->yieldsFor($plan);

        if (!isset($yields[$race])) {
            $hint = $viewer->have_option('isAdmin') || $viewer->have_option('isSuperAdmin')
                ? ' <small>(type « ' . htmlspecialchars($race) . ' » sans rendement — '
                    . '<a href="/admin/harvest-types.php?action=edit&name=' . urlencode($race) . '">régler</a>)</small>'
                : '';

            return self::statusBadgeHtml('Rien à récolter' . $hint, true);
        }

        $exhausted = (new \App\Service\Map\ResourceStateService())->isExhausted((int) $target->id);

        return self::statusBadgeHtml($exhausted ? 'Épuisé' : 'Récoltable', $exhausted);
    }

    /** Pastille sous la carte — `--closed` grise ce dont on ne tire plus rien. */
    private static function statusBadgeHtml(string $label, bool $spent): string
    {
        return '<div class="building-status' . ($spent ? ' building-status--closed' : '') . '">'
            . '<span class="building-status-state">' . $label . '</span>'
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

        $byCategory = ['offensive' => [], 'heal' => [], 'utility' => []];

        foreach ($actions as $actionName) {
            if (in_array($actionName, $basics)) {
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

        // Ordre de la liste de départ, restreint à ce que l'entité a vraiment.
        $result = array_values(array_intersect($basics, $actions));
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

        /* A gesture carrying a text (InscriptionText): the button asks for
         * it, pre-filled with what is written now (js/observe.js). */
        $askText = '';
        foreach ($action->getConditions() as $condition) {
            if ($condition->getConditionType() === 'InscriptionText' && $target instanceof \Classes\Player) {
                $askText = ' data-ask-text="Inscription :" data-text-default="'
                    . htmlspecialchars(\App\Service\BuildingService::inscriptionOf($target), ENT_QUOTES, 'UTF-8', false) . '"';
            }
        }

        return '<button
                class="action"
                data-coords-x="' . $target->getCoords()->x . '"
                data-coords-y="' . $target->getCoords(refresh:false)->y . '"
                data-coords-z="' . $target->getCoords(refresh:false)->z . '"
                data-coords-plan="' . $target->getCoords(refresh:false)->plan . '"
                data-target-id="' . $target->getId() . '"
                data-action="' . $name . '"' . $askText . '
                >
                ' . $icon . '
                <span class="action-name">' . $label . '</span>
                </button><br/>';
    }
}
