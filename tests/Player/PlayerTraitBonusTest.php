<?php

namespace Tests\Player;

use App\Factory\EntityManagerFactory;
use Classes\Player;
use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Player::traitBonus() is the single reader of hook passives (push, sight,
 * threshold, rest…): a passive contributes when it carries the trait, sits on
 * an accepted side, and its conditions hold.
 */
#[Group('passives')]
class PlayerTraitBonusTest extends LegacyPlayerFixtureTestCase
{
    /** @var int[] action_passives ids created here, removed in tearDown */
    private array $passiveIds = [];

    protected function tearDown(): void
    {
        if ($this->link !== null && $this->passiveIds !== []) {
            $this->link->executeStatement('DELETE FROM players_passives WHERE passive_id IN (?)', [$this->passiveIds], [ArrayParameterType::INTEGER]);
            $this->link->executeStatement('DELETE FROM action_passives WHERE id IN (?)', [$this->passiveIds], [ArrayParameterType::INTEGER]);
        }
        parent::tearDown();
    }

    private function grant(Player $player, string $trait, string $type, float $value, ?string $conditions = null): void
    {
        $this->link->insert('action_passives', [
            'name' => 'test_' . bin2hex(random_bytes(3)), 'traits' => json_encode([$trait]), 'type' => $type,
            'carac' => 'fixed', 'value' => $value, 'conditions' => $conditions, 'level' => 1,
            'display_name' => 'Test', 'text' => 'test',
        ]);
        $id = (int) $this->link->lastInsertId();
        $this->passiveIds[] = $id;
        $this->link->insert('players_passives', ['player_id' => $player->getId(), 'passive_id' => $id]);
        EntityManagerFactory::getEntityManager()->clear();
    }

    public function testSumsPassivesOnTheTraitAndSide(): void
    {
        $player = $this->createRealPlayer('bonus');
        $this->grant($player, 'poussee', 'att', 2);
        $this->grant($player, 'poussee', 'att', 3);
        $this->grant($player, 'poussee', 'def', 5);

        $this->assertSame(5, $player->traitBonus('poussee', ['att', 'mixte']));
        $this->assertSame(5, $player->traitBonus('poussee', ['def', 'mixte']));
        $this->assertSame(10, $player->traitBonus('poussee'));
        $this->assertSame(0, $player->traitBonus('vue'));
    }

    public function testCategoryConditionNeedsAnAction(): void
    {
        $player = $this->createRealPlayer('bonus');
        $this->grant($player, 'vol', '', 1, '{"category":["stealth"]}');

        $this->assertSame(0, $player->traitBonus('vol'));
    }
}
