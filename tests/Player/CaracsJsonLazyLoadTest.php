<?php

namespace Tests\Player;

use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * get_caracsJson() loads the caracs it promises.
 *
 * The constructor pre-creates `$caracs` as an empty object, so the getter's
 * `isset()` guard never fired: it handed back the empty object, every carac
 * read as null, and callers comparing against one took the wrong branch in
 * silence — the character sheet found every neighbour out of perception
 * range and dropped their effects, wound veil, message of the day and
 * equipment.
 *
 * A getter that never loads has no visible symptom of its own, so the pin
 * is here rather than in the views it broke.
 */
#[Group('items-baseline')]
class CaracsJsonLazyLoadTest extends LegacyPlayerFixtureTestCase
{
    public function testAFreshPlayerGetsItsCaracsWithoutAnyOtherCallFirst(): void
    {
        $player = $this->createRealPlayer('GmCaracs');

        $caracs = $player->get_caracsJson();

        $this->assertNotSame([], (array) $caracs, 'the getter loaded something');
        $this->assertGreaterThan(0, (int) $caracs->p, 'perception is a real value, not null');
    }

    /** Called twice, it loads once and keeps the same object. */
    public function testTheSecondCallReusesTheLoadedCaracs(): void
    {
        $player = $this->createRealPlayer('GmCaracs');

        $first = $player->get_caracsJson();
        $first->p = 999;

        $this->assertSame(999, (int) $player->get_caracsJson()->p, 'no reload wipes the first result');
    }
}
