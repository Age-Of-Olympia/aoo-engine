<?php

namespace Tests\Action\Combat;

use App\Simulation\SimulationGuard;
use Classes\Db;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-combat')]
class SimulationGuardTest extends TestCase
{
    public function testInactiveByDefault(): void
    {
        $this->assertFalse(SimulationGuard::isActive());
    }

    public function testRunActivatesDuringTheCallbackAndRestoresAfter(): void
    {
        $during = false;
        $returned = SimulationGuard::run(function () use (&$during) {
            $during = SimulationGuard::isActive();
            return 'result';
        });

        $this->assertTrue($during);
        $this->assertFalse(SimulationGuard::isActive());
        $this->assertSame('result', $returned);
    }

    public function testRunRestoresEvenWhenTheCallbackThrows(): void
    {
        try {
            SimulationGuard::run(function () { throw new \RuntimeException('boom'); });
            $this->fail('expected the exception to propagate');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse(SimulationGuard::isActive());
    }

    public function testNestedRunsStayActiveUntilTheOutermostExits(): void
    {
        SimulationGuard::run(function () {
            SimulationGuard::run(fn() => null);
            $this->assertTrue(SimulationGuard::isActive(), 'an inner exit must not lift the outer guard');
        });

        $this->assertFalse(SimulationGuard::isActive());
    }

    public function testWritesAreDetectedAndReadsAreNot(): void
    {
        $this->assertTrue(Db::isWriteStatement('INSERT INTO t (a) VALUES (1)'));
        $this->assertTrue(Db::isWriteStatement("\n        UPDATE players SET pf = 1"));
        $this->assertTrue(Db::isWriteStatement('delete from t where id = 1'));
        $this->assertTrue(Db::isWriteStatement('REPLACE INTO t VALUES (1)'));

        $this->assertFalse(Db::isWriteStatement('SELECT * FROM t'));
        $this->assertFalse(Db::isWriteStatement("   \n   SELECT id FROM players"));
    }
}
