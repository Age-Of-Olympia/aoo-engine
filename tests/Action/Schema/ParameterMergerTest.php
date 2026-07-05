<?php

namespace Tests\Action\Schema;

use App\Action\Schema\FieldType;
use App\Action\Schema\ParameterField;
use App\Action\Schema\ParameterSchema;
use App\Service\Action\ParameterMerger;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ParameterMergerTest extends TestCase
{
    private function schema(): ParameterSchema
    {
        return new ParameterSchema(new ParameterField('lvl', FieldType::INT, 'Level'));
    }

    public function testReturnsNullWhenNothingWasPosted(): void
    {
        $this->assertNull((new ParameterMerger())->merge($this->schema(), ['lvl' => 1], null, null));
    }

    public function testCoercesTypedFieldsToTheSchema(): void
    {
        $merged = (new ParameterMerger())->merge($this->schema(), [], ['lvl' => '5'], null);

        $this->assertSame(['lvl' => 5], $merged);
    }

    public function testPreservesExistingNonSchemaKeysWhenRawNotPosted(): void
    {
        // typed-only save must not drop keys the schema doesn't own.
        $merged = (new ParameterMerger())->merge($this->schema(), ['extra' => 'keep', 'lvl' => 9], ['lvl' => '3'], null);

        $this->assertSame(['extra' => 'keep', 'lvl' => 3], $merged);
    }
}
