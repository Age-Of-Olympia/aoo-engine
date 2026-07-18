<?php

namespace App\Action\OutcomeInstruction;

use App\Entity\OutcomeInstruction;
use App\Action\Condition\BuildSitePick;
use App\Action\Condition\ConditionObject;
use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use Classes\Db;
use Classes\Player;
use Classes\View;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pose une COUCHE de sol (route…) sur une case adjacente — le pendant
 * de PlaceStructure pour ce qui ne devient pas une entité-obstacle :
 * une route se marche, elle n'occupe pas la case. Remplace le dernier
 * usage de build.php.
 *
 * Même choix de case que PlaceStructure (build_picker.js → BuildSite,
 * sinon première case libre adjacente) ; refuse une case qui porte
 * déjà cette couche.
 */
#[ORM\Entity]
class PlaceLayerOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField('layer', FieldType::STRING, 'Couche (table map_*)', required: true, help: 'ex. routes — écrit dans map_{couche}'),
            new ParameterField('name', FieldType::STRING, 'Nom posé', required: true, help: "ex. route — le sprite img/{couche}/{nom}"),
        );
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $params = $this->getParameters() ?? [];
        // Blindage du nom de table : la couche vient des paramètres d'action
        // (admin), jamais d'une entrée joueur — mais un identifiant strict
        // coûte une ligne.
        $layer = preg_replace('/[^a-z_]/', '', (string) ($params['layer'] ?? ''));
        $name = (string) ($params['name'] ?? '');

        if ($layer === '' || $name === '') {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Couche ou nom manquant.']);
        }

        if (!isset($actor->coords)) {
            $actor->getCoords();
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Poserait : ' . $name . '.'], outcomeFailureMessages: array());
        }

        $goCoords = clone $actor->coords;

        if (BuildSitePick::requested()) {
            $picked = $conditionObject->getBuildCoords() ?? BuildSitePick::resolve($actor->coords);

            if ($picked === null) {
                return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: [BuildSitePick::REFUSAL]);
            }

            $goCoords = $picked;
        } else {
            View::get_free_coords_id_arround($goCoords, 1);
        }

        $coordsId = View::get_coords_id($goCoords);
        $db = new Db();

        $already = $db->exe('SELECT id FROM map_' . $layer . ' WHERE coords_id = ?', $coordsId);
        if ($already && $already->num_rows) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Il y a déjà cela ici.']);
        }

        $db->insert('map_' . $layer, [
            'name' => $name,
            'coords_id' => $coordsId,
            'player_id' => $actor->id,
        ]);
        View::refresh_players_svg($goCoords);

        $this->getOutcome()?->getAction()?->setRefreshScreen(true);

        $message = 'Vous aménagez ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
            . ' <span class="ra ra-implosion"></span> en (' . $goCoords->x . ', ' . $goCoords->y . ').';

        return new OutcomeResult(true, outcomeSuccessMessages: [$message], outcomeFailureMessages: array());
    }
}
