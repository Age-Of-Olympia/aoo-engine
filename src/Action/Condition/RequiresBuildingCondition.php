<?php

namespace App\Action\Condition;

use App\Entity\ActionCondition;
use App\Enum\FieldType;
use App\Interface\ActorInterface;
use App\Interface\HasParameterSchemaInterface;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\BuildingService;
use App\Service\RaceService;

/**
 * The gesture happens AT a building: an OPEN one of the given types
 * within range of the actor (G4 in design-buildings-entities.md — "une
 * Forge avant un Magasin"). Every cell the building holds counts for
 * the distance, and the one closure rule answers for its state: a
 * ruin, a construction site, a damaged or voluntarily shut workshop
 * serves nobody.
 *
 * Declare it `display_context` on the action so the button only shows
 * where the building stands, and `blocking` so nothing is paid without
 * one.
 */
class RequiresBuildingCondition extends BaseCondition implements HasParameterSchemaInterface
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'types',
                FieldType::LIST,
                'Types de bâtiment acceptés',
                help: 'Noms d\'entrées du catalogue races (kind building), séparés par des virgules — vide : n\'importe quel bâtiment',
            ),
            new ParameterField(
                'range',
                FieldType::INT,
                'Portée (cases)',
                default: 1,
            ),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $preConditionResult = parent::check($actor, $target, $condition, $conditionObject);
        if (!$preConditionResult->isSuccess()) {
            return $preConditionResult;
        }

        // The workbench simulation stands on no board.
        if ($actor->isSimulated()) {
            return new ConditionResult(true, array(), array());
        }

        $types = self::names($condition->getParameters()['types'] ?? []);
        $range = max(0, (int) ($condition->getParameters()['range'] ?? 1));

        $coords = $actor->getCoords();
        if ($coords === null) {
            return new ConditionResult(false, array(), array(self::missingMessage($types)));
        }

        $nearby = (new BuildingService())->openBuildingNearby($coords, $types, $range);

        if ($nearby['open'] !== null) {
            return new ConditionResult(true, array(), array());
        }

        if ($nearby['shut'] !== null) {
            return new ConditionResult(false, array(), array('Le bâtiment est ' . $nearby['shut'] . '.'));
        }

        return new ConditionResult(false, array(), array(self::missingMessage($types)));
    }

    /** Name what is missing by its catalogue label, not its code. */
    private static function missingMessage(array $types): string
    {
        if ($types === []) {
            return 'Aucun bâtiment à portée.';
        }

        $raceService = new RaceService();
        $labels = array_map(
            static function (string $type) use ($raceService): string {
                $race = $raceService->getRaceByName($type);

                return $race !== null ? $race->getLabel() : $type;
            },
            $types
        );

        return 'Il faut être près de : ' . implode(', ', $labels) . '.';
    }

    /**
     * @param mixed $raw a list, or the comma-separated text the form yields
     * @return list<string>
     */
    private static function names($raw): array
    {
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return array_values(array_filter(array_map(
            static fn($name): string => trim((string) $name),
            $values
        ), static fn(string $name): bool => $name !== ''));
    }
}
