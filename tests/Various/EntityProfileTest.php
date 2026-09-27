<?php

namespace Tests\Various;

use App\View\Entity\EntityParts;
use App\View\Entity\EntityProfile;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** One profile per viewer and entity, read by both the tile card and the sheet. */
#[Group('action')]
class EntityProfileTest extends LegacyPlayerFixtureTestCase
{
    public function testACharacterBeyondPerceptionKeepsItsDetails(): void
    {
        [$x, $y] = $this->farTile();
        $viewer = $this->characterAt('GmRegard', $x, $y);
        $far = $this->characterAt('GmHorizon', $x + (int) $viewer->caracs->p + 1, $y);
        $this->link->executeStatement("UPDATE players SET text = 'Bonjour' WHERE id = ?", [$far->id]);

        $profile = EntityProfile::of($viewer, (int) $far->id);

        $this->assertFalse($profile->detailed);
        $this->assertNull($profile->pvPct, 'no PV veil');
        $this->assertSame([], $profile->effects());
        $this->assertStringContainsString('trop éloigné', $profile->textHtml());
    }

    public function testAChestShowsItsStateAndOwner(): void
    {
        [$x, $y] = $this->farTile();
        $owner = $this->characterAt('GmCoffrier', $x + 1, $y);
        $chest = $this->installExemplar('coffre_bois', $x, $y);
        $this->link->executeStatement("UPDATE players SET owner_id = ?, faction = '', is_open = 0 WHERE id = ?", [$owner->id, $chest]);

        $profile = EntityProfile::of($owner, $chest);

        $this->assertTrue($profile->isContainer);
        $this->assertStringContainsString('Fermé', EntityParts::statusHtml($profile));
        $this->assertStringContainsString('GmCoffrier', EntityParts::ownerHtml($profile));
        $this->assertSame('', EntityParts::contentsHtml($profile), 'shut: nothing shown');
    }
}
