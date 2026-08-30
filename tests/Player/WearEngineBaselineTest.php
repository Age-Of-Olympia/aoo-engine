<?php

namespace Tests\Player;

use App\Service\WearService;
use Classes\Item;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The PER-TURN wear engine (docs/design-items-instances.md §3.4):
 * « le tour est l'unité d'usure ».
 *
 *   - arm() flags ONLY the WORN exemplars whose catalogue declares the
 *     trigger — wrong trigger, unequipped, other player, broken: none
 *     arm;
 *   - arming ten times in a turn is one flag — ten steps wear the boots
 *     once;
 *   - applyNewTurnWear() decrements by wear_rate, floors at 0 (brisé),
 *     clears the flag and narrates;
 *   - inert by default: with no trigger, nothing wears per turn.
 *
 * These tests used to run on « attack », which left the vocabulary:
 * combat now wears immediately (WearRulesTest). They run on « move »,
 * the boots' trigger — the machinery pinned is the same, and it is the
 * one that remains.
 */
#[Group('items-baseline')]
class WearEngineBaselineTest extends LegacyPlayerFixtureTestCase
{
    /** @var array{0:string,1:int}|null previous gladius wear config */
    private ?array $previousWear = null;

    private ?int $gladiusId = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT wear_rate FROM items LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('wear columns unavailable (run migrations): ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->previousWear !== null && $this->gladiusId !== null) {
            $this->link->executeStatement(
                'UPDATE items SET wear_triggers = ?, wear_rate = ? WHERE id = ?',
                [$this->previousWear[0], $this->previousWear[1], $this->gladiusId]
            );
        }
        parent::tearDown();
    }

    private function wearingGladius(int $rate = 5, string $trigger = 'move'): array
    {
        $item = Item::get_item_by_name('gladius');
        if ($item === false || $item === null) {
            $this->markTestSkipped("items catalog not seeded (no 'gladius' row).");
        }
        $item->get_data();
        $this->gladiusId = (int) $item->id;

        $prev = $this->link->fetchAssociative('SELECT wear_triggers, wear_rate FROM items WHERE id = ?', [$item->id]);
        $this->previousWear = [(string) $prev['wear_triggers'], (int) $prev['wear_rate']];
        $this->link->executeStatement(
            'UPDATE items SET wear_triggers = ?, wear_rate = ? WHERE id = ?',
            [$trigger, $rate, $item->id]
        );

        $player = $this->createRealPlayer('GmVeteran');
        $item->add_item($player, 1);
        $player->get_caracs();
        $player->equip($item);

        $instanceId = $this->instanceHeldBy((int) $player->id, (int) $item->id);
        $this->assertGreaterThan(0, $instanceId, 'equip must have created the instance');

        return [$player, $item, $instanceId];
    }

    private function durabilityOf(int $instanceId): int
    {
        return $this->remainingLifeOf($instanceId);
    }

    public function testArmFlagsOnlyTheDeclaredTriggerOnEquippedInstances(): void
    {
        [$player, , $instanceId] = $this->wearingGladius();
        $wear = new WearService();

        $wear->arm($player->id, 'usage');
        $this->assertSame(0, (int) $this->link->fetchOne('SELECT wear_pending FROM item_instances WHERE id = ?', [$instanceId]), 'an undeclared trigger must not arm');

        $wear->arm($player->id, 'move');
        $this->assertSame(1, (int) $this->link->fetchOne('SELECT wear_pending FROM item_instances WHERE id = ?', [$instanceId]), 'the declared trigger arms');

        $other = $this->createRealPlayer('GmBystander');
        $wear->arm($other->id, 'move');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne(
                'SELECT COUNT(*) FROM item_instances i JOIN players_items_instances l ON l.instance_id = i.id
                 WHERE l.player_id = ? AND i.wear_pending = 1',
                [$other->id]
            ),
            'arming a player without wearing gear is a no-op'
        );
    }

    public function testTenStepsInATurnWearTheGearOnce(): void
    {
        [$player, , $instanceId] = $this->wearingGladius(rate: 5);
        $wear = new WearService();

        for ($i = 0; $i < 10; $i++) {
            $wear->arm($player->id, 'move');
        }
        $recap = $wear->applyNewTurnWear($player->id);

        $this->assertSame(95, $this->durabilityOf($instanceId), 'one decrement per turn, whatever the event count');
        $this->assertCount(1, $recap);
        $this->assertStringContainsString('s\'use (−5)', $recap[0]);

        $this->assertSame([], $wear->applyNewTurnWear($player->id), 'nothing armed → next turn wears nothing');
        $this->assertSame(95, $this->durabilityOf($instanceId));
    }

    public function testWearFloorsAtZeroBreaksAndBrokenNoLongerArms(): void
    {
        [$player, , $instanceId] = $this->wearingGladius(rate: 5);
        $wear = new WearService();

        $this->setRemainingLife($instanceId, 3);
        $wear->arm($player->id, 'move');
        $recap = $wear->applyNewTurnWear($player->id);

        $this->assertSame(0, $this->durabilityOf($instanceId), 'per-turn wear floors at 0 — détruit needs bigger, immediate hits');
        $this->assertStringContainsString('brisé', $recap[0]);

        $wear->arm($player->id, 'move');
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT wear_pending FROM item_instances WHERE id = ?', [$instanceId]),
            'a broken item no longer arms'
        );
    }
}
