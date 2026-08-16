<?php

namespace Tests\Various;

use App\Service\GoldService;
use Classes\Item;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The purse pays in one write: the balance is checked by the UPDATE, so
 * one spend too many finds no line to debit instead of digging the stack
 * into the negative.
 */
class GoldSpendTest extends LegacyPlayerFixtureTestCase
{
    public function testTheDebitTakesItsPriceAndNoMore(): void
    {
        $gold = $this->goldOrSkip();
        $client = $this->createRealPlayer('AcheteurBourse');
        $gold->add_item($client, 100);

        $this->assertTrue($client->spendGold(30), 'the purse covers the price');
        $this->assertSame(70, (int) $client->get_gold(), 'and paid only it');
    }

    public function testAPurseTooLightPaysNothing(): void
    {
        $gold = $this->goldOrSkip();
        $client = $this->createRealPlayer('DesargenteBourse');
        $gold->add_item($client, 10);

        $this->assertFalse($client->spendGold(11), 'one does not pay what one does not have');
        $this->assertSame(10, (int) $client->get_gold(), 'the purse is untouched');
    }

    /* Two payments of the same amount on a purse that covers one. It runs
     * sequentially here, but this is the guard concurrent requests meet. */
    public function testTheLastCoinIsTakenOnlyOnce(): void
    {
        $gold = $this->goldOrSkip();
        $client = $this->createRealPlayer('DoubleClicBourse');
        $gold->add_item($client, 40);

        $this->assertTrue($client->spendGold(40), 'the first payment goes through');
        $this->assertFalse($client->spendGold(40), 'the second finds nothing left');
        $this->assertSame(0, (int) $client->get_gold(), 'the purse is empty, not negative');
    }

    public function testAFreeThingIsBoughtWithoutAStatement(): void
    {
        $gold = $this->goldOrSkip();
        $client = $this->createRealPlayer('GratuitBourse');
        $gold->add_item($client, 5);

        $this->assertTrue((new GoldService())->spend((int) $client->id, 0), 'nothing to pay');
        $this->assertFalse((new GoldService())->spend((int) $client->id, -5), 'a negative price is no purchase');
        $this->assertSame(5, (int) $client->get_gold());
    }

    private function goldOrSkip(): Item
    {
        return $this->itemOrSkip('or');
    }
}
