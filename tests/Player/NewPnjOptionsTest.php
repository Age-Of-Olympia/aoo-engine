<?php

namespace Tests\Player;

use App\Service\PlayerOptionsService;
use Classes\Player;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A new PNJ gets the same display defaults as a player, and is anonymous
 * rather than invisible: animators want to see it on the map.
 */
class NewPnjOptionsTest extends LegacyPlayerFixtureTestCase
{
    public function testANewPnjHasTheNewHudAndIsAnonymousNotIncognito(): void
    {
        $pnj = (int) Player::put_player('PnjNeuf_' . bin2hex(random_bytes(4)), 'nain', true);
        $this->createdPlayerIds[] = $pnj;
        $options = new PlayerOptionsService();

        foreach (['newHud', 'showActionDetails', 'hideStructureBorders', 'anonymeMode'] as $option) {
            $this->assertTrue((bool) $options->hasOption($pnj, $option), "{$option} attendu");
        }
        $this->assertFalse((bool) $options->hasOption($pnj, 'incognitoMode'));
    }
}
