<?php

namespace Tests\Action\Combat;

use App\Simulation\LenientData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class LenientDataTest extends TestCase
{
    protected function tearDown(): void
    {
        LenientData::$strict = false;
    }

    public function testReturnsModelledPropertiesAndNullForTheRest(): void
    {
        $data = new LenientData(['name' => 'Sim']);

        $this->assertSame('Sim', $data->name);
        $this->assertNull($data->somethingUnmodelled);
    }

    public function testStrictModeThrowsOnAnUnmodelledProperty(): void
    {
        LenientData::$strict = true;
        $data = new LenientData([]);

        $this->expectException(\RuntimeException::class);
        $data->missing; /* @phpstan-ignore expr.resultUnused */
    }
}
