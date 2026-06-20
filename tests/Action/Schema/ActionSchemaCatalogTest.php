<?php

namespace Tests\Action\Schema;

use App\Action\Schema\ActionSchemaCatalog;
use App\Action\Schema\FieldType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionSchemaCatalogTest extends TestCase
{
    private ActionSchemaCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new ActionSchemaCatalog();
    }

    public function testEnumeratesConditionTypesFromTheRegistry(): void
    {
        $types = $this->catalog->allConditionTypes();

        $this->assertContains('Compute', $types);
        $this->assertContains('MeleeCompute', $types);
    }

    public function testEnumeratesOutcomeInstructionTypesByDiscriminatorKey(): void
    {
        $types = $this->catalog->allOutcomeInstructionTypes();

        // keys are the lowercased class name without "OutcomeInstruction"
        $this->assertContains('malus', $types);
        $this->assertContains('manaloss', $types);
        $this->assertContains('lifeloss', $types);
    }

    public function testComputeConditionSchemaDescribesItsRollParameters(): void
    {
        $schema = $this->catalog->schemaForCondition('Compute');

        $actorRoll = $schema->field('actorRollType');
        $this->assertNotNull($actorRoll);
        $this->assertSame(FieldType::TRAIT, $actorRoll->type);
        $this->assertTrue($actorRoll->required);

        $this->assertSame(FieldType::BOOL, $schema->field('actorAdvantage')->type);
        $this->assertSame(0, $schema->field('actorRollBonus')->default);
    }

    public function testComputeSubclassesInheritTheComputeSchema(): void
    {
        $schema = $this->catalog->schemaForCondition('MeleeCompute');

        $this->assertNotNull($schema->field('actorRollType'));
        $this->assertFalse($schema->isEmpty());
    }

    public function testMalusOutcomeSchema(): void
    {
        $schema = $this->catalog->schemaForOutcomeInstruction('malus');

        $this->assertSame(1, $schema->field('rollDivisor')->default);
        $to = $schema->field('to');
        $this->assertSame(FieldType::ENUM, $to->type);
        $this->assertSame(['actor' => 'Acteur', 'target' => 'Cible'], $to->options);
    }

    public function testManaLossOutcomeSchema(): void
    {
        $schema = $this->catalog->schemaForOutcomeInstruction('manaloss');

        $lossType = $schema->field('lossType');
        $this->assertSame(FieldType::ENUM, $lossType->type);
        $this->assertArrayHasKey('difference', $lossType->options);
        $this->assertSame(FieldType::TRAIT_OR_INT, $schema->field('value')->type);
    }

    public function testTypesWithoutASchemaFallBackToEmpty(): void
    {
        $this->assertTrue($this->catalog->schemaForCondition('Plan')->isEmpty());
        $this->assertTrue($this->catalog->schemaForOutcomeInstruction('resource')->isEmpty());
        $this->assertTrue($this->catalog->schemaForOutcomeInstruction('unknownType')->isEmpty());
    }

    public function testRolledOutOutcomeSchemas(): void
    {
        $lifeLoss = $this->catalog->schemaForOutcomeInstruction('lifeloss');
        $this->assertSame(FieldType::TRAIT, $lifeLoss->field('actorDamagesTrait')->type);
        $this->assertSame(FieldType::BOOL, $lifeLoss->field('drain')->type);

        $healing = $this->catalog->schemaForOutcomeInstruction('healing');
        $this->assertSame(FieldType::TRAIT_OR_INT, $healing->field('actorHealingTrait')->type);

        $player = $this->catalog->schemaForOutcomeInstruction('player');
        $this->assertSame(FieldType::ENUM, $player->field('player')->type);
    }
}
