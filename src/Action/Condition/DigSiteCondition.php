<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterSchema;
use Classes\Db;

/**
 * Valide la case à creuser AVANT tout paiement (même règle que
 * BuildSite : un refus bloquant n'engage aucun coût) : digX/digY au
 * POST, souterrain (z < 0), adjacente ou sous ses pieds, pas déjà
 * creusée. L'instruction digtunnel ne fait ensuite que l'effet.
 */
class DigSiteCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $condition->setBlocking(true);

        $digX = $_POST['digX'] ?? null;
        $digY = $_POST['digY'] ?? null;
        if (!is_numeric($digX) || !is_numeric($digY)) {
            return new ConditionResult(false, array(), ['Aucune case à creuser fournie.']);
        }

        $coords = $actor->getCoords(refresh: false);

        if ((int) $coords->z >= 0) {
            return new ConditionResult(false, array(), ['On ne creuse que sous terre.']);
        }
        if (max(abs((int) $digX - (int) $coords->x), abs((int) $digY - (int) $coords->y)) > 1) {
            return new ConditionResult(false, array(), ['Cette case est trop loin pour creuser.']);
        }

        $res = (new Db())->exe(
            'SELECT COUNT(*) AS n FROM map_tiles t JOIN coords c ON c.id = t.coords_id
             WHERE c.x = ? AND c.y = ? AND c.z = ? AND c.plan = ?',
            array((int) $digX, (int) $digY, (int) $coords->z, (string) $coords->plan)
        );
        if ((int) ($res->fetch_object()->n ?? 0) > 0) {
            return new ConditionResult(false, array(), ['Cette case est déjà creusée.']);
        }

        return new ConditionResult(true, array(), array());
    }
}
