<?php
namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Interface\ActorInterface;
use App\Action\Condition\ConditionObject;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterSchema;
use App\Service\BuildingService;

/**
 * La ligne de tir doit être dégagée — garde UNIQUE, pour les six types de
 * conditions de calcul qui la déclarent en précondition (DistanceCompute,
 * DistancePureCompute, TechniqueCompute, TechniquePureCompute, SpellCompute,
 * SpellPureCompute).
 *
 * # Ce qu'elle faisait avant
 *
 * Elle appelait `View::get_walls_between()`, qui portait sa PROPRE copie de
 * Bresenham et n'interrogeait que `map_resources`. En cas d'obstacle, elle
 * n'échouait pas : elle émettait une alerte JavaScript et faisait `exit()`.
 *
 * Deux conséquences, l'une visible et l'autre pas :
 *
 * - depuis que les murs sont devenus des entités et ont quitté
 *   `map_resources`, cette garde ne les voyait plus. Les techniques et les
 *   sorts TRAVERSAIENT les murs, tandis que les tirs à distance étaient bien
 *   arrêtés — eux passent par `lineOfFireReport`, qui lit les entités ;
 * - les deux géométries divergeaient. Bresenham n'est pas symétrique, et
 *   seule celle de `LineOfFire` a été corrigée.
 *
 * # Ce qu'elle fait maintenant
 *
 * Elle délègue à `BuildingService::lineOfFireReport`, la même source que
 * l'aide à l'écran et que le tir à distance : une géométrie, un catalogue
 * d'obstacles (`races.blocks_projectiles` — une table ou un tonneau laisse
 * passer), un message qui nomme l'obstacle et le situe.
 *
 * # An obstacle REFUSES the shot, it does not receive it
 *
 * The shot used to leave anyway and fail like a dodge: the projectile
 * crashed into the obstacle and the action was paid for. Testers read that
 * as an attack against the obstacle — an action and its cost lost on a
 * gesture the character can see to be impossible.
 *
 * It is therefore declared BLOCKING in `action_condition_preconditions`:
 * the executor stops before the outcomes and before the costs. Nothing is
 * loosed, nothing is paid. A dodge stays a paid failure — the arrow did
 * leave, and its row is not blocking.
 *
 * Le tracé est rendu par `window.showLineOfFire`, celui du clic droit :
 * pointillés du tireur à la cible, un point sur chaque obstacle. Il est
 * transitoire, là où l'ancien clignotement durait jusqu'au rechargement.
 */
class ObstacleCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema();
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        if ($target === null || $actor->isSimulated() || $target->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        $from = $actor->getCoords();

        /* Aim at the target's NEAREST cell, as range measures it: aiming at
         * a far cell traced a line through the object's own body. */
        $to = \Classes\View::get_nearest_cell_of($from, $target->getId(), $target->getCoords());

        $report = (new BuildingService())->lineOfFireReport($from, $to, $target->getId());

        if ($report['blocker'] === null) {
            return new ConditionResult(true, array(), array());
        }

        [$blockerX, $blockerY] = $report['blocker'];

        $message = htmlspecialchars((string) $report['blockerName'], ENT_QUOTES, 'UTF-8')
            . ' bloque la ligne de tir en (' . $blockerX . ', ' . $blockerY . ')'
            . ' : vous ne tirez pas.'
            . $this->traceScript($from, $to, $report);

        return new ConditionResult(false, array(), array($message));
    }

    /**
     * Le tracé, rendu par le même code que le clic droit sur une case.
     *
     * Injecté dans le message : ActionResultsView concatène les messages
     * bruts dans #ajax-data, que jQuery exécute. C'est le canal qu'utilisait
     * déjà l'ancienne alerte — sans le `exit()` qui tuait la requête avant
     * que l'action ne rende son résultat.
     *
     * @param array{tiles: list<array{int,int}>, blocker: ?array{int,int}, blockerName: ?string, blockers: list<array{int,int}>} $report
     */
    private function traceScript(object $from, object $to, array $report): string
    {
        $payload = json_encode([
            'from' => [(int) $from->x, (int) $from->y],
            'to' => [(int) $to->x, (int) $to->y],
            'blocker' => $report['blocker'],
            'blockers' => $report['blockers'],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<script>(function(t){'
            . 'if(window.showLineOfFire){window.showLineOfFire(t.from,t.to,t.blocker,t.blockers);}'
            . '})(' . $payload . ');</script>';
    }
}
