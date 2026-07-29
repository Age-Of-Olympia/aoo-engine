<?php

namespace App\Action\Condition;

use App\Action\Schema\FieldType;
use App\Action\Schema\HasParameterSchema;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Entity\ActionCondition;
use App\Interface\ActorInterface;

/**
 * Aim at a named type, where TargetType only aims at a category.
 *
 * Reads `players.race`, which for a structure is its type.
 */
class TargetRaceCondition extends BaseCondition implements HasParameterSchema
{
    public static function parameterSchema(): ParameterSchema
    {
        return new ParameterSchema(
            new ParameterField(
                'allowed',
                FieldType::LIST,
                'Types visés',
                required: true,
                help: 'Noms d\'entrées du catalogue races, séparés par des virgules (ex. altar)',
            ),
        );
    }

    public function check(ActorInterface $actor, ?ActorInterface $target, ActionCondition $condition, ConditionObject $conditionObject): ConditionResult
    {
        $allowed = self::names($condition->getParameters()['allowed'] ?? []);

        if ($allowed === []) {
            // Naming nothing forbids nothing.
            return new ConditionResult(true, array(), array());
        }

        $race = (string) ($target->data->race ?? '');

        if ($race !== '' && in_array($race, $allowed, true)) {
            return new ConditionResult(true, array(), array());
        }

        $condition->setBlocking(true);

        return new ConditionResult(false, array(), ['Cette action ne vise pas cela.']);
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
