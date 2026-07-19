<?php

namespace App\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Entity\OutcomeInstruction;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use Classes\Db;
use Classes\Item;
use Classes\Player;
use Classes\View;
use Doctrine\ORM\Mapping as ORM;

/**
 * Creuse une galerie (map_tiles 'caverne') sur la case SOUTERRAINE visée
 * — l'action `creuser`, déclenchée par le déplacement (go.php POSTe
 * digX/digY avant d'invoquer le moteur ; le mouvement démarre des
 * actions, cadrage du 2026-07-19). La case est relue ici avec les mêmes
 * gardes, qu'on arrive par go.php ou par un POST direct d'action.php :
 * même plan, même z, z négatif, adjacente (ou sous ses pieds), pas déjà
 * creusée — un POST forgé ne creuse que ce qu'un pas légitime creuserait.
 *
 * Sans Pioche en main, creuser inflige MALUS_PER_MINE (la confirmation
 * préalable est le rôle de go.php — annuler ne dépense rien). L'XP (1,
 * = XP_PER_MINE) vient de la règle du type 'search' — pas de put_xp ici.
 */
#[ORM\Entity]
class DigTunnelOutcomeInstruction extends OutcomeInstruction implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function execute(Player $actor, Player $target, ConditionObject $conditionObject): OutcomeResult
    {
        $digX = $_POST['digX'] ?? null;
        $digY = $_POST['digY'] ?? null;
        if (!is_numeric($digX) || !is_numeric($digY)) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Aucune case à creuser fournie.']);
        }

        if (!isset($actor->coords)) {
            $actor->getCoords();
        }

        if ($actor->isSimulated()) {
            return new OutcomeResult(true, outcomeSuccessMessages: ['Creuserait la case (' . (int) $digX . ', ' . (int) $digY . ').'], outcomeFailureMessages: array());
        }

        $z = (int) $actor->coords->z;
        if ($z >= 0) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['On ne creuse que sous terre.']);
        }
        if (max(abs((int) $digX - (int) $actor->coords->x), abs((int) $digY - (int) $actor->coords->y)) > 1) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Cette case est trop loin pour creuser.']);
        }

        $goCoords = (object) ['x' => (int) $digX, 'y' => (int) $digY, 'z' => $z, 'plan' => (string) $actor->coords->plan];
        $coordsId = View::get_coords_id($goCoords);
        if ($coordsId === null) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Coordonnées invalides.']);
        }

        $db = new Db();
        $already = $db->exe('SELECT COUNT(*) AS n FROM map_tiles WHERE coords_id = ?', $coordsId);
        if ((int) ($already->fetch_object()->n ?? 0) > 0) {
            return new OutcomeResult(false, outcomeSuccessMessages: array(), outcomeFailureMessages: ['Cette case est déjà creusée.']);
        }

        $messages = [];

        // La bascule visuelle et la confirmation sont côté go.php ; ici la
        // règle : sans Pioche en main, l'effort laisse un malus.
        if (!isset($actor->emplacements->main1)) {
            $actor->get_caracs();
        }
        if (($actor->emplacements->main1->data->name ?? '') != 'Pioche') {
            $actor->put_malus(MALUS_PER_MINE);
            $messages[] = 'Creuser sans Pioche, qu\'est-ce que ça fatigue ! (malus)';
        }

        $pierre = Item::get_item_by_name('pierre');
        $pierre->add_item($actor, 1);

        $db->insert('map_tiles', ['name' => 'caverne', 'coords_id' => $coordsId]);

        array_unshift($messages, 'Vous creusez la galerie et trouvez 1 pierre.');

        return new OutcomeResult(true, outcomeSuccessMessages: $messages, outcomeFailureMessages: array());
    }
}
