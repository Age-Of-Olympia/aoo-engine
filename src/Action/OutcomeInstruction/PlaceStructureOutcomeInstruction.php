<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\BuildSitePick;
use App\Action\Condition\ConditionObject;
use App\Enum\FieldType;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\BuildingService;
use Classes\Player;
use Classes\View;
use Doctrine\ORM\Mapping as ORM;

/**
 * G2 (docs/design-items-instances.md §4) : pose une structure sur une
 * case libre adjacente à l'acteur, via BuildingService — le débouché
 * data-driven de l'action « construire ». Remplace la pose de
 * map_resources muets de build.php : une palissade construite par un
 * joueur a des PV, s'attaque et se répare.
 *
 * L'acteur devient propriétaire, sa faction est reprise sur le
 * satellite. Deux modes de choix de case :
 * - case CHOISIE (build_picker.js → buildX/buildY) : validée par
 *   BuildSiteCondition et transmise via ConditionObject::getBuildCoords
 *   ({@see \App\Action\Condition\BuildSitePick}, source unique) ;
 * - sinon, automatique : première case libre adjacente, rayon 1.
 */
#[ORM\Entity]
class PlaceStructureOutcomeInstruction extends OutcomeInstruction implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('type', FieldType::STRING, 'Type de structure', required: true, help: "Nom d'une entrée races de sorte « structure » (ex. palissade)"),
            new ParameterField('name', FieldType::STRING, 'Nom affiché', help: 'Vide = libellé du type'),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $params = $this->getParameters() ?? [];
        // Type statique, sinon dérivé de l'objet du geste (ItemPick) : le
        // nom d'objet EST le type de structure — la convention des sprites
        // et des pseudo-races (action générique « construire »).
        $type = (string) ($params['type'] ?? $conditionObject->getPickedItem()?->row->name ?? '');
        $name = trim((string) ($params['name'] ?? ''));

        if ($type === '') {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['PlaceStructure : aucun type (ni statique, ni fourni au geste).']);
        }

        if (!isset($actor->coords)) {
            $actor->getCoords();
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(
                true,
                outcomeSuccessMessages: ['Construirait : ' . $type . '.'],
                outcomeFailureMessages: array()
            );
        }

        $goCoords = clone $actor->coords;

        if (BuildSitePick::requested()) {
            /* Priorité au résultat déposé par BuildSiteCondition (déjà
             * validé, avant tout paiement) ; repli sur le résolveur
             * partagé pour une action sans la condition attachée. */
            $picked = $conditionObject->getBuildCoords() ?? BuildSitePick::resolve($actor->coords, $type);

            if ($picked === null) {
                return new OutcomeResult(
                    false,
                    outcomeSuccessMessages: array(),
                    outcomeFailureMessages: [BuildSitePick::REFUSAL]
                );
            }

            $goCoords = $picked;
        } else {
            View::get_free_coords_id_arround($goCoords, 1);
        }

        /* A road is walked on, not stood in. Its catalogue subtype says so,
           and it goes to the ground layer the map editor writes — the one
           the running bonus, the drawn map and `observe` all read. Installed
           as an object instead, it put a THING on the cell: the board drew
           an object, and no reader of roads saw a road. */
        $layerName = $this->groundLayerOf($type);

        if ($layerName !== null) {
            $laid = (new \App\Service\Map\GroundLayerService())->lay($layerName, $type, $goCoords, (int) $actor->id);

            $this->getOutcome()?->getAction()?->setRefreshScreen(true);

            return $laid['ok']
                ? new OutcomeResult(true, outcomeSuccessMessages: [$laid['message']], outcomeFailureMessages: array())
                : new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$laid['message']]);
        }

        // A type still described by a race mints a building; anything else
        // places the object itself. Families leaving `races` switch sides here
        // on their own.
        $race = $this->raceService()->getRaceByName($type);
        $isRaceTyped = $race?->isStructureKind() ?? false;

        try {
            $id = $isRaceTyped
                ? (new BuildingService())->place(
                    $type,
                    $goCoords,
                    $actor->id,
                    (string) ($actor->data->faction ?? ''),
                    $name !== '' ? $name : null,
                    asConstructionSite: true
                )
                : $this->placeTheObjectItself($type, $goCoords, $actor, $conditionObject->getBuildFor());
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        /* The recipe's materials go INTO the walls: razed later, the entity
         * spills them with the loot rules — a partial refund, and the same
         * place the admin hides treasure. Player gesture only: admin and
         * editor placements dress sets, their walls hold nothing. */
        $ingredients = (new \App\Service\RecipeService())->ingredientsForResult($type);
        if ($ingredients !== []) {
            (new \App\Service\FabricService())->storeByName($id, $ingredients);
        }

        $this->getOutcome()?->getAction()?->setRefreshScreen(true);

        $label = htmlspecialchars($name !== '' ? $name : $type, ENT_QUOTES, 'UTF-8');

        // A type declaring work was born a SITE (place, asConstructionSite):
        // `travailler` will raise it gesture by gesture.
        $work = $isRaceTyped ? $race->getBuildWork() : 0;
        if ($work > 0) {
            $message = 'Vous ouvrez le chantier de ' . $label
                . ' <span class="ra ra-hammer"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ') — 0/' . $work . '.';

            return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
        }

        $message = 'Vous construisez ' . $label
            . ' <span class="ra ra-tower"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ') — structure #' . $id . '.';

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
    }

    /**
     * Place the object itself: its exemplar is born standing on the cell.
     *
     * Ownership follows the validated choice (ChestSite,
     * ConditionObject::getBuildFor): 'faction' gives the object to the
     * builder's faction with no personal owner; anything else keeps the
     * builder as owner — today's personal chest.
     *
     * @throws \RuntimeException when the type is in neither catalogue
     */
    private function placeTheObjectItself(string $type, object $coords, Player $actor, ?string $buildFor): int
    {
        $item = \Classes\Item::get_item_by_name($type);

        if (!$item instanceof \Classes\Item) {
            throw new \RuntimeException("« {$type} » n'est ni une race de structure, ni un objet du catalogue.");
        }

        $coordsId = (int) View::get_coords_id($coords);
        $forFaction = $buildFor === \App\Action\Condition\ChestSiteCondition::FOR_FACTION;

        return (new \App\Service\ItemInstanceService())->installFromCatalogAt(
            (int) $item->id,
            $coordsId,
            (int) $actor->id,
            $forFaction ? null : (int) $actor->id,
            $forFaction ? (string) ($actor->data->faction ?? '') : ''
        );
    }

    /**
     * The ground layer this catalogue type belongs to, or null when it is an
     * object like any other. Read from `items.subtype`, the vocabulary the
     * workbench field already documents ("walls, routes…").
     */
    private function groundLayerOf(string $type): ?string
    {
        $item = \Classes\Item::get_item_by_name($type);

        if (!$item instanceof \Classes\Item) {
            return null;
        }

        $subtype = (string) ($item->row->subtype ?? '');

        return \App\Service\Map\GroundLayerService::isLayer($subtype) ? $subtype : null;
    }

    /** Instantiated on demand: this class is a Doctrine entity. */
    private function raceService(): \App\Service\RaceService
    {
        return new \App\Service\RaceService();
    }
}
