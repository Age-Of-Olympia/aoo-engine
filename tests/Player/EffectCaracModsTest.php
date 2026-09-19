<?php

namespace Tests\Player;

use App\Service\EffectService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * One effect moves several caracs at once: each entry of carac_mods is a
 * signed multiplier of the value the effect was applied with.
 */
#[Group('database')]
class EffectCaracModsTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        $this->link->executeStatement("DELETE FROM effects WHERE name = 'brasier_test'");
        EffectService::clearCache();
        parent::tearDown();
    }

    public function testOneEffectMovesSeveralCaracs(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, carac_mods) VALUES ('brasier_test', 'Brasier', '{\"e\":-1,\"f\":2,\"p\":0}')"
        );
        EffectService::clearCache();

        $player = $this->createRealPlayer('GmBrasier');
        $player->get_caracs();
        $e = $player->caracs->e;
        $f = $player->caracs->f;
        $p = $player->caracs->p;

        $player->add_effect('brasier_test', 3, 3);
        $player->get_caracs();

        $this->assertSame($e - 3, $player->caracs->e, 'E: -1 × 3');
        $this->assertSame($f + 6, $player->caracs->f, 'F: +2 × 3');
        $this->assertSame($p, $player->caracs->p, 'a zero entry leaves the carac alone');
        $this->assertSame('brasier_test', $player->debuffs->e);
        $this->assertSame('brasier_test', $player->buffs->f);
    }
}
