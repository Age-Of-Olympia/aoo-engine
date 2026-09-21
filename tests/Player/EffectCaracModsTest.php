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
        $this->link->executeStatement("DELETE FROM effects WHERE name IN ('brasier_test', 'flamme_test', 'braise_test')");
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

    public function testAnEffectSaysWhatItDoesWhenItLands(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, icon, apply_text, carac_mods, loss_mods) VALUES ('flamme_test', 'Flamme', 'ra-fire', '{cible} prend feu par {acteur} <b>', '{\"e\":-1,\"f\":2}', '{\"pv\":-10}')"
        );
        EffectService::clearCache();

        $service = new EffectService();
        $this->assertSame(
            'Dorna prend feu par Cradek &lt;b&gt; (x3, pour 2 tours, E −3, F +6, PV −30)',
            $service->landingMessage('flamme_test', 'Dorna', 'Cradek', 2, 3),
            'placeholders filled, template escaped, caracs and losses × intensity'
        );
        $this->assertStringStartsWith('L\'effet feu', $service->landingMessage('feu', 'Dorna', 'Cradek', 1), 'no text = the generic line');
    }

    public function testAnEffectHurtsEachTimeItLands(): void
    {
        $this->link->executeStatement(
            "INSERT INTO effects (name, label, loss_mods) VALUES ('braise_test', 'Braise', '{\"pv\":-3}')"
        );
        EffectService::clearCache();

        $player = $this->createRealPlayer('GmBraise');
        $player->get_caracs();
        $before = $player->getRemaining('pv');

        $player->add_effect('braise_test', 1);
        $this->assertSame($before - 3, $player->getRemaining('pv'), 'the effect takes its PV when it lands');

        $player->add_effect('braise_test', 1);
        $this->assertSame($before - 6, $player->getRemaining('pv'), 'and again at the next application');
    }
}
