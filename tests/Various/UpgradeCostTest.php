<?php

namespace Tests\Various;

use App\Service\PlayerCaracsService;
use PHPUnit\Framework\TestCase;

/**
 * The rank grid: full fare for the first, degressive after. The same
 * calculation serves the upgrades table, reassignment and the endpoints
 * that charge — it is worth holding with cases.
 */
class UpgradeCostTest extends TestCase
{
    public function testTheFirstRankPaysFullFare(): void
    {
        $service = new PlayerCaracsService();

        $this->assertSame(4, $service->returnCost('pv', 0));
        $this->assertSame(110, $service->returnCost('ct', 0));
    }

    public function testTheGridDegradesAtTheSecondAndFourthRank(): void
    {
        $service = new PlayerCaracsService();

        /* pv = [4, 2, 1]: 4, then two ranks at 2, then 1 per rank. */
        $this->assertSame(6, $service->returnCost('pv', 1));
        $this->assertSame(8, $service->returnCost('pv', 2));
        $this->assertSame(9, $service->returnCost('pv', 3));
        $this->assertSame(10, $service->returnCost('pv', 4));
    }

    /**
     * Ae is read from the equipment and has no line in the grid. Asking
     * for its price must cost nothing — and must not throw, which reading
     * the first cell of an absent grid does.
     */
    public function testACaracThatIsNotSoldCostsNothing(): void
    {
        $service = new PlayerCaracsService();

        $this->assertNull($service->getUpgradeProgress('ae'));
        $this->assertSame(0, $service->returnCost('ae', 0));
        $this->assertSame(0, $service->returnCost('carac-inexistante', 3));
    }

    public function testEveryBoughtCaracHasItsGrid(): void
    {
        $service = new PlayerCaracsService();

        foreach (array_keys(CARACS) as $carac) {

            if ($carac === 'ae') {

                continue;
            }

            $this->assertNotNull(
                $service->getUpgradeProgress($carac),
                'carac ' . $carac . ' is bought, so it has a price'
            );
        }
    }
}
