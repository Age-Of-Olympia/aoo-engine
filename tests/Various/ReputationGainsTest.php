<?php

namespace Tests\Various;

use App\Service\InventoryService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Callers hand the reputation service real PR: an item worth 1 PR stores 10
 * and says « +1 PR », not the stored tenths.
 */
#[Group('items-baseline')]
class ReputationGainsTest extends LegacyPlayerFixtureTestCase
{
    public function testAConsumableCreditsItsPrInRealUnits(): void
    {
        $drinker = $this->createRealPlayer('GmReputDrinker');
        $before = (int) $this->link->fetchOne('SELECT pr FROM players WHERE id = ?', [$drinker->id]);

        $item = $this->sowCatalogItem('gm_elixir_renom', ['type' => 'consommable', 'pr' => 1]);
        $details = InventoryService::applyConsumablePayload($drinker, $item);

        $this->assertSame($before + 10, (int) $this->link->fetchOne('SELECT pr FROM players WHERE id = ?', [$drinker->id]));
        $this->assertSame($before + 10, (int) $drinker->data->pr, 'the legacy row is refreshed');
        $this->assertContains('+1 PR', $details);
    }
}
