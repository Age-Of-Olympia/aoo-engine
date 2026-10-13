<?php

namespace Tests\Action\WarSchool;

use App\Service\WarSchool\SpellPriceService;
use PHPUnit\Framework\TestCase;

/**
 * Le barème des sorts et sa validation, sans base : tally() ne touche qu'aux
 * listes, c'est pay() qui parle à l'inventaire.
 */
class SpellPriceServiceTest extends TestCase
{
    public function testEachLevelAsksForItsBill(): void
    {
        $this->assertSame(['commune' => 2], SpellPriceService::costFor(1));
        $this->assertSame(['commune' => 1, 'raciale' => 1], SpellPriceService::costFor(2));
        $this->assertSame(['rare' => 1], SpellPriceService::costFor(3));
        $this->assertSame(['rare' => 1, 'raciale' => 2], SpellPriceService::costFor(4));
        $this->assertSame(['rare' => 1, 'lointaine' => 1], SpellPriceService::costFor(5));
    }

    /** Une facture aberrante vaut mieux qu'un sort gratuit. */
    public function testLevelOutOfRangeFallsBackInside(): void
    {
        $this->assertSame(SpellPriceService::costFor(1), SpellPriceService::costFor(0));
        $this->assertSame(SpellPriceService::costFor(1), SpellPriceService::costFor(-3));
        $this->assertSame(SpellPriceService::costFor(5), SpellPriceService::costFor(9));
    }

    /** Un menu déroulant par ressource due, dans l'ordre du barème. */
    public function testSlotsRepeatTheTierOncePerResource(): void
    {
        $this->assertSame(['commune', 'commune'], SpellPriceService::slots(1));
        $this->assertSame(['rare', 'raciale', 'raciale'], SpellPriceService::slots(4));
        $this->assertCount(2, SpellPriceService::slots(5));
    }

    public function testTallyCountsTheChosenResources(): void
    {
        $this->assertSame(['pierre' => 1, 'bois' => 1], SpellPriceService::tally(1, ['pierre', 'bois']));
        $this->assertSame(['etain' => 1, 'ivoire' => 1], SpellPriceService::tally(5, ['etain', 'ivoire']));
    }

    /** Deux fois la même ressource est un choix valide : c'est deux exemplaires. */
    public function testTheSameResourceTwiceCountsTwice(): void
    {
        $this->assertSame(['pierre' => 2], SpellPriceService::tally(1, ['pierre', 'pierre']));
    }

    /**
     * Un POST forgé ne doit pas passer : mauvais compte, ressource prise
     * dans une autre famille, ou inconnue au bataillon.
     */
    public function testTallyRefusesAnythingOffTheBill(): void
    {
        $this->assertNull(SpellPriceService::tally(1, ['pierre']), 'une ressource de moins');
        $this->assertNull(SpellPriceService::tally(1, ['pierre', 'bois', 'adonis']), 'une de trop');
        $this->assertNull(SpellPriceService::tally(1, ['pierre', 'etain']), 'une rare au prix du commun');
        $this->assertNull(SpellPriceService::tally(3, ['bois']), 'un commun au prix du rare');
        $this->assertNull(SpellPriceService::tally(1, ['pierre', 'or']), 'objet hors barème');
        $this->assertNull(SpellPriceService::tally(1, []), 'rien du tout');
    }

    /** L'ordre des menus est celui de slots() : rare d'abord au niveau 4. */
    public function testTallyReadsChoicesInSlotOrder(): void
    {
        $this->assertSame(
            ['nickel' => 1, 'fer' => 2],
            SpellPriceService::tally(4, ['nickel', 'fer', 'fer'])
        );
        $this->assertNull(
            SpellPriceService::tally(4, ['fer', 'fer', 'nickel']),
            'la raciale en première position ne satisfait pas la rare'
        );
    }

    /** Les quatre familles sont disjointes : une ressource a une famille. */
    public function testTiersDoNotOverlap(): void
    {
        $all = array_merge(...array_values(SpellPriceService::TIERS));

        $this->assertSame(count($all), count(array_unique($all)));
        $this->assertSame(array_keys(SpellPriceService::TIERS), array_keys(SpellPriceService::TIER_LABELS));
    }
}