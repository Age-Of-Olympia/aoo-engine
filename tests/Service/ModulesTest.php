<?php

namespace Tests\Service;

use App\Factory\EntityManagerFactory;
use App\Module\Module;
use App\Module\Modules;
use PHPUnit\Framework\TestCase;

class ModulesTest extends TestCase
{
    /** @var array<string, mixed>|false the tutorial row before the test */
    private array|false $saved = false;

    protected function setUp(): void
    {
        $this->saved = $this->db()->fetchAssociative("SELECT * FROM modules WHERE name = 'tutorial'");
        Modules::reset();
    }

    protected function tearDown(): void
    {
        $this->db()->executeStatement("DELETE FROM modules WHERE name = 'tutorial'");
        if ($this->saved !== false) {
            $this->db()->insert('modules', $this->saved);
        }
        Modules::reset();
    }

    public function testNoRowMeansOff(): void
    {
        $this->db()->executeStatement("DELETE FROM modules WHERE name = 'tutorial'");

        $this->assertFalse(Modules::on(Module::Tutorial));
        $this->assertFalse(Modules::on(Module::Tutorial, 42));
        $this->assertSame([], Modules::players(Module::Tutorial));
    }

    public function testSavedStateIsReadBack(): void
    {
        Modules::save(Module::Tutorial, false, [42, 7]);

        $this->assertFalse(Modules::on(Module::Tutorial));
        $this->assertTrue(Modules::on(Module::Tutorial, 42), 'a tester gets the module while it is off');
        $this->assertFalse(Modules::on(Module::Tutorial, 8));
        $this->assertSame([42, 7], Modules::players(Module::Tutorial));

        Modules::save(Module::Tutorial, true, []);

        $this->assertTrue(Modules::on(Module::Tutorial));
        $this->assertTrue(Modules::on(Module::Tutorial, 8));
    }

    public function testForceWinsOverTheTableUntilReset(): void
    {
        Modules::save(Module::Tutorial, false, []);
        Modules::force(Module::Tutorial, true);

        $this->assertTrue(Modules::on(Module::Tutorial));

        Modules::reset();

        $this->assertFalse(Modules::on(Module::Tutorial));
    }

    public function testParsePlayersKeepsPositiveIdsOnce(): void
    {
        $this->assertSame([12, 40, 7], Modules::parsePlayers(" 12, 40 7;12\n-3, abc, 0"));
        $this->assertSame([], Modules::parsePlayers(''));
    }

    private function db(): \Doctrine\DBAL\Connection
    {
        return EntityManagerFactory::getEntityManager()->getConnection();
    }
}
