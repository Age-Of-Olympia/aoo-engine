<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
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
 * satellite. La case est choisie automatiquement (première case libre
 * autour, rayon croissant) — le choix explicite de la case viendra
 * avec l'UI dédiée, l'instruction acceptera alors des coordonnées
 * fournies par l'appelant.
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
        $type = (string) ($params['type'] ?? '');
        $name = trim((string) ($params['name'] ?? ''));

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

        /* Case CHOISIE par le joueur (mode choix de case, build_picker) :
         * validée strictement — adjacente ET libre, exactement les cases
         * .go que le masque a proposées. Sinon : première case libre
         * adjacente, rayon croissant (ancien comportement). */
        if (isset($_POST['buildX'], $_POST['buildY'])) {
            $requested = ((int) $_POST['buildX']) . ',' . ((int) $_POST['buildY']);

            $around = View::get_coords_arround(clone $actor->coords, 1);
            $taken = View::get_coords_taken(clone $actor->coords);

            if (!in_array($requested, $around, true) || in_array($requested, $taken, true)) {
                return new OutcomeResult(
                    false,
                    outcomeSuccessMessages: array(),
                    outcomeFailureMessages: ['Impossible de construire là — la case doit être adjacente et libre.']
                );
            }

            $goCoords->x = (int) $_POST['buildX'];
            $goCoords->y = (int) $_POST['buildY'];
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
