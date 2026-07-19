<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\BuildSitePick;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
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
 * map_walls muets de build.php : une palissade construite par un
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
class PlaceStructureOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
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

        try {
            $id = (new BuildingService())->place(
                $type,
                $goCoords,
                $actor->id,
                (string) ($actor->data->faction ?? ''),
                $name !== '' ? $name : null
            );
        } catch (\InvalidArgumentException $e) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [$e->getMessage()]);
        }

        $this->getOutcome()?->getAction()?->setRefreshScreen(true);

        $message = 'Vous construisez ' . htmlspecialchars($name !== '' ? $name : $type, ENT_QUOTES, 'UTF-8')
            . ' <span class="ra ra-tower"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ') — structure #' . $id . '.';

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
    }
}
