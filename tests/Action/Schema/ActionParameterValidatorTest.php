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

    public function testTraitOrIntKeepsADynamicJsonArrayValue(): void
    {
        $schema = $this->catalog->schemaForOutcomeInstruction('manaloss');

        $this->assertSame(
            ['rollDivisor', 3],
            $this->validator->coerce($schema, ['lossType' => 'carac', 'value' => '["rollDivisor",3]'])['value']
        );
        $this->assertSame(
            [1, 2],
            $this->validator->coerce($schema, ['lossType' => 'carac', 'value' => '[1,2]'])['value']
        );
    }

    public function testTraitOrIntStillRejectsNonNumericNonTraitNonArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerce(
            $this->catalog->schemaForOutcomeInstruction('manaloss'),
            ['lossType' => 'carac', 'value' => 'banana']
        );
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

    public function testCoerceRawBuildsAFlatMapWithJsonTypedValues(): void
    {
        $result = $this->validator->coerceRaw([
            ['k' => 'a', 'v' => '1'],
            ['k' => 'pm', 'v' => '10'],
            ['k' => 'energie', 'v' => 'both'],
            ['k' => 'imposture', 'v' => '[1,2]'],
            ['k' => 'adrenaline', 'v' => 'true'],
        ]);

        $this->assertSame([
            'a' => 1,
            'pm' => 10,
            'energie' => 'both',
            'imposture' => [1, 2],
            'adrenaline' => true,
        ], $result);
    }

    public function testCoerceRawDropsBlankKeysAndTrims(): void
    {
        $result = $this->validator->coerceRaw([
            ['k' => '', 'v' => '5'],
            ['k' => '  a  ', 'v' => '1'],
            ['v' => '9'],
        ]);

        $this->assertSame(['a' => 1], $result);
    }

    public function testCoerceRawSkipsReservedSchemaKeys(): void
    {
        $result = $this->validator->coerceRaw([
            ['k' => 'adrenaline', 'v' => 'true'],
            ['k' => 'duration', 'v' => '1'],
        ], ['duration', 'player', 'value', 'stackable']);

        $this->assertSame(['adrenaline' => true], $result);
    }

    public function testCoerceRawRejectsAKeyThatIsNotAnInertIdentifier(): void
    {
        // A key becomes an effect name echoed unescaped into outcome HTML, so a
        // payload-bearing key must be rejected rather than persisted.
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerceRaw([
            ['k' => '<img src=x onerror=alert(1)>', 'v' => 'true'],
        ]);
    }

    public function testCoerceRawRejectsKeysWithSpacesOrPunctuation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerceRaw([['k' => 'foo bar', 'v' => '1']]);
    }

    public function testCoerceRawRejectsANumericKey(): void
    {
        // A numeric key would be reindexed by array_merge, breaking the
        // raw-key-first ordering ApplyStatus relies on.
        $this->expectException(InvalidArgumentException::class);
        $this->validator->coerceRaw([['k' => '1', 'v' => 'true']]);
    }

    public function testCoerceRawAcceptsCamelCaseAndUnderscoreIdentifiers(): void
    {
        $result = $this->validator->coerceRaw([
            ['k' => 'remainingNullable', 'v' => 'pm'],
            ['k' => 'anti_berserk', 'v' => '1'],
        ]);

        $this->assertSame(['remainingNullable' => 'pm', 'anti_berserk' => 1], $result);
    }
}
