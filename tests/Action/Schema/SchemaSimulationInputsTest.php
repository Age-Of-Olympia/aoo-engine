<?php

namespace Tests\Action\Schema;

use App\Enum\FieldType;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Action\Schema\SchemaSimulationInputs;
use App\Action\Schema\SimulationField;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class SchemaSimulationInputsTest extends TestCase
{
    /** @return array<string, string> trait key => side */
    private function bySide(array $fields): array
    {
        $map = [];
        foreach ($fields as $field) {
            $this->assertSame(SimulationField::KIND_TRAIT, $field->kind);
            $map[$field->key] = $field->side;
        }

        return $map;
    }

    public function testOnlyTraitFieldsSetToARealCaracBecomeInputsOnTheirSide(): void
    {
        $schema = new ParameterSchema(
            new ParameterField('atk', FieldType::TRAIT, 'Attaque'),
            new ParameterField('def', FieldType::TRAIT, 'Défense', side: 'target'),
            new ParameterField('bonus', FieldType::TRAIT_OR_INT, 'Bonus'),
            new ParameterField('divisor', FieldType::INT, 'Diviseur'),
        );

        $map = $this->bySide(SchemaSimulationInputs::derive($schema, ['atk' => 'f', 'def' => 'e', 'bonus' => 'm']));

        $this->assertSame(['f' => 'actor', 'e' => 'target', 'm' => 'actor'], $map);
    }

    public function testFixedNumericTraitOrIntReadsNothingAndIsSkipped(): void
    {
        $schema = new ParameterSchema(new ParameterField('bonus', FieldType::TRAIT_OR_INT, 'Bonus'));

        $this->assertSame([], SchemaSimulationInputs::derive($schema, ['bonus' => 3]));
        $this->assertSame([], SchemaSimulationInputs::derive($schema, ['bonus' => '-3']));
    }

    public function testTraitDivisorPairExposesTheTrait(): void
    {
        // e.g. jet_infuse bonusDamagesTrait ["m", 3] = caracs.m / 3.
        $schema = new ParameterSchema(new ParameterField('bonus', FieldType::TRAIT_OR_INT, 'Bonus'));

        $this->assertSame(['m' => 'actor'], $this->bySide(SchemaSimulationInputs::derive($schema, ['bonus' => ['m', 3]])));
    }

    public function testSlashSeparatedRollTypeSplitsIntoEachCarac(): void
    {
        // The target defense roll keeps the better of two caracs ("cc/agi").
        $schema = new ParameterSchema(new ParameterField('targetRollType', FieldType::TRAIT_OR_INT, 'Jet cible', side: 'target'));

        $this->assertSame(['cc' => 'target', 'agi' => 'target'], $this->bySide(SchemaSimulationInputs::derive($schema, ['targetRollType' => 'cc/agi'])));
    }

    public function testNonCaracArrayTokensAreNotMistakenForTraits(): void
    {
        // ApplyStatus reuses TRAIT_OR_INT for value, but its array form is a computed
        // directive (["rollDivisor", 3], ["remaining", "a"]), never a carac.
        $schema = new ParameterSchema(new ParameterField('value', FieldType::TRAIT_OR_INT, 'Valeur'));

        $this->assertSame([], SchemaSimulationInputs::derive($schema, ['value' => ['rollDivisor', 3]]));
        $this->assertSame([], SchemaSimulationInputs::derive($schema, ['value' => ['remaining', 'a']]));
        $this->assertSame([], SchemaSimulationInputs::derive($schema, ['value' => 4]));
    }
}
