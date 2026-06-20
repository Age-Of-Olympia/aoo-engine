<?php

namespace Tests\Action\Schema;

use App\Action\Schema\ActionSchemaCatalog;
use App\Service\Action\ActionParameterValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionParameterValidatorTest extends TestCase
{
    private ActionParameterValidator $validator;
    private ActionSchemaCatalog $catalog;

    protected function setUp(): void
    {
        if (!defined('CARACS')) {
            define('CARACS', ['cc' => 'CC', 'f' => 'F', 'agi' => 'Agi', 'pm' => 'PM']);
        }
        $this->validator = new ActionParameterValidator();
        $this->catalog = new ActionSchemaCatalog();
    }

    public function testCoercesComputeParametersToTypedValues(): void
    {
        $result = $this->validator->coerce($this->catalog->schemaForCondition('Compute'), [
            'actorRollType' => 'cc',
            'targetRollType' => 'agi',
            'actorRollBonus' => '3',
            'actorAdvantage' => '1',
        ]);

        $this->assertSame('cc', $result['actorRollType']);
        $this->assertSame(3, $result['actorRollBonus']);
        $this->assertTrue($result['actorAdvantage']);
        $this->assertFalse($result['targetAdvantage']);
        $this->assertSame(0, $result['targetRollBonus']);
    }

    public function testRejectsUnknownTrait(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerce($this->catalog->schemaForCondition('Compute'), [
            'actorRollType' => 'banana',
            'targetRollType' => 'cc',
        ]);
    }

    public function testRejectsInvalidEnumValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerce($this->catalog->schemaForOutcomeInstruction('malus'), ['to' => 'nobody']);
    }

    public function testRejectsMissingRequiredField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerce($this->catalog->schemaForCondition('Compute'), ['targetRollType' => 'cc']);
    }

    public function testTraitOrIntAcceptsEitherForm(): void
    {
        $schema = $this->catalog->schemaForOutcomeInstruction('manaloss');

        $this->assertSame(5, $this->validator->coerce($schema, ['lossType' => 'fixed', 'value' => '5'])['value']);
        $this->assertSame('pm', $this->validator->coerce($schema, ['lossType' => 'carac', 'value' => 'pm'])['value']);
    }

    public function testTraitOrIntAcceptsASlashJoinedTraitSet(): void
    {
        $result = $this->validator->coerce($this->catalog->schemaForCondition('Compute'), [
            'actorRollType' => 'cc',
            'targetRollType' => 'cc/agi',
        ]);

        $this->assertSame('cc/agi', $result['targetRollType']);
    }

    public function testAppliesDefaultsForBlankOptionalFields(): void
    {
        $result = $this->validator->coerce($this->catalog->schemaForOutcomeInstruction('malus'), []);

        $this->assertSame(1, $result['rollDivisor']);
        $this->assertSame('target', $result['to']);
    }
}
