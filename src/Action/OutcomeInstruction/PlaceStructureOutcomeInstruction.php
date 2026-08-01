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
            $picked = $conditionObject->getBuildCoords() ?? BuildSitePick::resolve($actor->coords);

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

        /* Deux poses, et c'est le CATALOGUE qui tranche : un type encore décrit
         * par une race donne un bâtiment, tout le reste pose l'objet lui-même.
         *
         * La règle se vide d'elle-même. Chaque famille qui quitte `races` —
         * les conteneurs viennent de le faire — bascule du premier cas au
         * second sans qu'on revienne ici, et le jour où plus rien n'est décrit
         * par une race, il ne reste que la pose d'objet.
         *
         * Bâtir un objet POSE cet objet : le geste consommait l'exemplaire pour
         * frapper un bâtiment d'après une race homonyme, jetant au passage tout
         * ce que l'objet était. */
        $isRaceTyped = $this->raceService()->getRaceByName($type)?->isStructureKind() ?? false;

        try {
            $id = $isRaceTyped
                ? (new BuildingService())->place(
                    $type,
                    $goCoords,
                    $actor->id,
                    (string) ($actor->data->faction ?? ''),
                    $name !== '' ? $name : null
                )
                : $this->placeTheObjectItself($type, $goCoords, (int) $actor->id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        $this->getOutcome()?->getAction()?->setRefreshScreen(true);

        $message = 'Vous construisez ' . htmlspecialchars($name !== '' ? $name : $type, ENT_QUOTES, 'UTF-8')
            . ' <span class="ra ra-tower"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ') — structure #' . $id . '.';

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
    }

    /**
     * Pose l'objet lui-même : son exemplaire naît debout sur la case.
     *
     * L'unité a déjà quitté le sac — `RequiresItem` l'a consommée au paiement —
     * si bien qu'il n'y a rien à y reprendre : ce qui est posé est l'objet
     * qu'on vient de dépenser, et il garde désormais une identité propre.
     *
     * @throws \RuntimeException type absent du catalogue des objets
     */
    private function placeTheObjectItself(string $type, object $coords, int $actorId): int
    {
        $item = \Classes\Item::get_item_by_name($type);

        if (!$item instanceof \Classes\Item) {
            throw new \RuntimeException("« {$type} » n'est ni une race de structure, ni un objet du catalogue.");
        }

        $coordsId = (int) View::get_coords_id($coords);

        return (new \App\Service\ItemInstanceService())
            ->installFromCatalogAt((int) $item->id, $coordsId, $actorId, $actorId);
    }

    /** Le catalogue des races, instancié à la demande — la classe est une entité Doctrine. */
    private function raceService(): \App\Service\RaceService
    {
        return new \App\Service\RaceService();
    }
}
