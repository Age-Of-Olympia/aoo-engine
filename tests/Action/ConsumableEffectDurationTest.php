<?php

namespace Tests\Action;

use App\Service\InventoryService;
use App\Service\PlayerEffectService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * How long a consumable's effect lasts.
 *
 * The duration used to be decided in code — one turn for a visible effect,
 * endless for a hidden one — with nothing in the admin able to say otherwise.
 * An item may now carry `extra.effetDuree`, a map keyed by effect name. It
 * sits BESIDE `extra.effet` rather than inside it, so the historical name
 * list keeps its shape and a reader that ignores durations still works.
 */
#[Group('items-baseline')]
class ConsumableEffectDurationTest extends LegacyPlayerFixtureTestCase
{
    private function drinkerWith(string $itemName, array $extra): array
    {
        $drinker = $this->createRealPlayer('GmDuree');
        $drinker->getCoords();
        $drinker->get_caracs();

        /* stats_in_db is what the admin save always writes: it is the flag
           that makes get_data() rebuild from the columns, `extra` decoded. */
        $item = $this->sowCatalogItem($itemName, [
            'type' => 'consommable',
            'stats_in_db' => 1,
            'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
        ]);
        $item->get_data();

        return [$drinker, $item];
    }

    private function effectTurnsOf(int $playerId, string $effect): ?int
    {
        $row = $this->link->fetchAssociative(
            'SELECT endTime FROM players_effects WHERE player_id = ? AND name = ?',
            [$playerId, $effect]
        );

        return $row === false ? null : (int) $row['endTime'];
    }

    public function testAConfiguredDurationIsUsed(): void
    {
        [$drinker, $item] = $this->drinkerWith('potion_duree', [
            'effet' => ['regeneration'],
            'effetDuree' => ['regeneration' => 5],
        ]);

        InventoryService::applyConsumablePayload($drinker, $item);

        $this->assertSame(5, $this->effectTurnsOf((int) $drinker->id, 'regeneration'));
    }

    /** Without a configured duration, the historical default stands. */
    public function testAVisibleEffectStillDefaultsToOneTurn(): void
    {
        [$drinker, $item] = $this->drinkerWith('potion_sans_duree', [
            'effet' => ['regeneration'],
        ]);

        InventoryService::applyConsumablePayload($drinker, $item);

        $this->assertSame(1, $this->effectTurnsOf((int) $drinker->id, 'regeneration'));
    }

    /** A hidden effect is not cured by waiting: endless unless told otherwise. */
    public function testAHiddenEffectStillDefaultsToEndless(): void
    {
        [$drinker, $item] = $this->drinkerWith('potion_poison', [
            'effet' => ['poison'],
        ]);

        InventoryService::applyConsumablePayload($drinker, $item);

        $turns = $this->effectTurnsOf((int) $drinker->id, 'poison');
        $this->assertNotNull($turns);
        $this->assertTrue(
            PlayerEffectService::isInfinite($turns),
            'a hidden effect with no configured duration never expires on its own'
        );
    }

    /** And a configured duration overrides even that default. */
    public function testAConfiguredDurationOverridesTheHiddenDefault(): void
    {
        [$drinker, $item] = $this->drinkerWith('potion_poison_bref', [
            'effet' => ['poison'],
            'effetDuree' => ['poison' => 3],
        ]);

        InventoryService::applyConsumablePayload($drinker, $item);

        $this->assertSame(3, $this->effectTurnsOf((int) $drinker->id, 'poison'));
    }
}
