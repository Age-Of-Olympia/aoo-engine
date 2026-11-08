<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\View\ActionResultsView;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What an outcome did on a missed attack reaches the results, and through
 * them the event details (#137): the malus a miss inflicts. A miss also
 * wears the attacker's weapon, never the target's protections.
 */
#[Group('action-combat')]
class MissOutcomeLinesShownTest extends LegacyPlayerFixtureTestCase
{
    public function testTheMalusOfAMissIsShown(): void
    {
        [$actor, $target] = $this->certainMiss('GmRate', 'GmEsquive');

        $results = $this->melee($actor, $target);
        $this->assertFalse($results->isSuccess(), 'forty ranks of agility: a miss');

        $this->assertStringContainsString('malus à ' . $target->data->name, (new ActionResultsView($results))->getActionResults());
    }

    public function testAMissWearsTheAttackersWeaponOnly(): void
    {
        [$actor, $target] = $this->certainMiss('GmManque', 'GmCasque');
        $sword = $this->wear($actor, 'main1', ['subtype' => 'melee']);
        $helm = $this->wear($target, 'tete');

        $results = $this->melee(PlayerFactory::legacy($actor->id), PlayerFactory::legacy($target->id));
        $this->assertFalse($results->isSuccess(), 'forty ranks of agility: a miss');

        $this->assertSame(99, $this->remainingLifeOf($sword), 'the swing wears the weapon');
        $this->assertSame(100, $this->remainingLifeOf($helm), 'a blow that does not land wears no protection');
    }

    /** @return array{0: \Classes\Player, 1: \Classes\Player} */
    private function certainMiss(string $actorPrefix, string $targetPrefix): array
    {
        $actor = $this->createRealPlayer($actorPrefix);
        $target = $this->createRealPlayer($targetPrefix);
        $this->movePlayerTo($target->id, 0, 1);
        // Forty ranks of agility make the miss certain.
        for ($i = 0; $i < 40; $i++) {
            $this->link->executeStatement("INSERT INTO players_upgrades (player_id, name, cost) VALUES (?, 'agi', 0)", [$target->id]);
        }

        return [PlayerFactory::legacy($actor->id), PlayerFactory::legacy($target->id)];
    }

    /**
     * Equips a fresh 100-durability piece in $slot and returns its exemplar id.
     *
     * @param array<string, mixed> $spec catalogue columns on top of the defaults
     */
    private function wear(\Classes\Player $player, string $slot, array $spec = []): int
    {
        $item = $this->sowCatalogItem('zz_' . $slot . '_' . bin2hex(random_bytes(3)), [
            'type' => 'equipement',
            'stats_in_db' => 1,
            'durability_max' => 100,
            'emplacement' => $slot,
        ] + $spec);
        $item->get_data();
        $item->add_item($player, 1);
        $player->equip($item);

        return $this->instanceHeldBy((int) $player->id, (int) $item->id, true);
    }

    private function melee(\Classes\Player $actor, \Classes\Player $target): \App\Action\ActionResults
    {
        $actor->getCoords(); $target->getCoords(); $actor->get_caracs(); $target->get_caracs();

        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        return (new ActionExecutorService($action, $actor, $target))->executeAction();
    }
}
