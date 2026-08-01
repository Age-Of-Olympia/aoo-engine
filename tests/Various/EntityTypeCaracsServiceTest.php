<?php

namespace Tests\Various;

use App\Entity\EntityManagerFactory;
use App\Entity\Item;
use App\Service\EntityTypeCaracsService;
use App\Service\RaceService;
use PHPUnit\Framework\TestCase;

/**
 * A type answers what it gives; the reader never asks which catalogue it is.
 *
 * The case that matters is the breastplate: `items.pv` is what an item lends
 * its bearer, so reading it as owned would make armour die in one hit. An
 * item's own life is its durability, and only that.
 */
class EntityTypeCaracsServiceTest extends TestCase
{
    private function service(): EntityTypeCaracsService
    {
        return new EntityTypeCaracsService();
    }

    protected function setUp(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/constants.php';
            EntityManagerFactory::getEntityManager()->getConnection()->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('DB unreachable: ' . $e->getMessage());
        }
    }

    /** A race gives all sixteen of its caracs, as before. */
    public function testARaceStillGivesItsWholeStatBlock(): void
    {
        $block = $this->service()->ownCaracs('real', 'nain');

        $this->assertIsObject($block);
        $this->assertSame(
            (int) ((new RaceService())->getRaceData('nain')->pv),
            (int) $block->pv,
            'la vie d\'une race ne change pas de source'
        );
    }

    /** An item gives its durability as life, and nothing else. */
    public function testAnItemGivesItsDurabilityAsLifeAndNothingElse(): void
    {
        $item = EntityManagerFactory::getEntityManager()
            ->getRepository(Item::class)
            ->findOneBy(['name' => 'gladius']);

        if ($item === null) {
            $this->markTestSkipped('items catalog not seeded (no gladius row).');
        }

        $block = $this->service()->ownCaracs(EntityTypeCaracsService::ITEM_TYPE, 'gladius');

        $this->assertIsObject($block);
        $this->assertSame($item->getDurabilityMax(), (int) $block->pv, 'sa vie est sa durabilité');

        foreach (\App\Enum\Caracs::KEYS as $key) {
            if ($key === 'pv') {
                continue;
            }
            $this->assertSame(0, (int) $block->$key, "l'objet ne se donne pas « {$key} », il le prête");
        }
    }

    /**
     * The trap this contract exists for: an item whose catalogue `pv` is not
     * zero must NOT take it as its own life.
     */
    public function testAnItemThatLendsLifeDoesNotTakeItAsItsOwn(): void
    {
        $conn = EntityManagerFactory::getEntityManager()->getConnection();

        $lender = $conn->fetchAssociative(
            'SELECT name, pv, durability_max FROM items WHERE pv > 0 AND durability_max > 0 LIMIT 1'
        );

        if ($lender === false) {
            $this->markTestSkipped('no item lending pv in this catalog.');
        }

        $block = $this->service()->ownCaracs(
            EntityTypeCaracsService::ITEM_TYPE,
            (string) $lender['name']
        );

        $this->assertIsObject($block);
        $this->assertSame(
            (int) $lender['durability_max'],
            (int) $block->pv,
            'sa vie vient de sa durabilité, pas de la vie qu\'il prête'
        );
        $this->assertNotSame(
            (int) $lender['pv'],
            (int) $block->pv,
            'lire items.pv comme sien rendrait une cuirasse plus fragile que le tissu'
        );
    }

    /** An unknown type answers null, so the caller can warn instead of guessing. */
    public function testAnUnknownTypeAnswersNull(): void
    {
        $this->assertNull($this->service()->ownCaracs('real', 'race_qui_nexiste_pas'));
        $this->assertNull($this->service()->ownCaracs(EntityTypeCaracsService::ITEM_TYPE, 'objet_qui_nexiste_pas'));
        $this->assertNull($this->service()->ownCaracs(EntityTypeCaracsService::ITEM_TYPE, ''));
    }
}
