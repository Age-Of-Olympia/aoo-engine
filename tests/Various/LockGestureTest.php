<?php

namespace Tests\Various;

use App\Action\Condition\ConditionObject;
use App\Action\Condition\RequiresLockControlCondition;
use App\Action\OutcomeInstruction\TurnLockOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Factory\PlayerFactory;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The lock as a GESTURE of the action engine: `fermer` shuts what is
 * open, `ouvrir` opens what is shut — for the hand that controls the
 * thing, and the display conditions say exactly where each button
 * appears.
 */
#[Group('action')]
class LockGestureTest extends LegacyPlayerFixtureTestCase
{
    private function conditionWith(int $producesOpen): ActionCondition
    {
        $condition = new ActionCondition();
        $condition->setConditionType('RequiresLockControl');
        $condition->setParameters(['open' => $producesOpen]);

        return $condition;
    }

    /** @return array{0: int, 1: Player} chest entity id, its owner */
    private function ownedChest(int $x, int $y): array
    {
        $owner = $this->createRealPlayer('GmClefG');
        $chestId = $this->installExemplar('coffre_bois', $x, $y, (int) $owner->id);
        $this->link->executeStatement(
            "UPDATE players SET owner_id = ?, faction = '' WHERE id = ?",
            [$owner->id, $chestId]
        );

        return [$chestId, $owner];
    }

    public function testTheGestureTurnsTheLockBothWays(): void
    {
        [$chestId, $owner] = $this->ownedChest(50, 30);
        $chest = PlayerFactory::legacy($chestId);
        $chest->get_data();

        $close = new TurnLockOutcomeInstruction();
        $close->setParameters(['open' => 0]);
        $result = $close->execute($owner, $chest, new ConditionObject());

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$chestId])
        );

        $open = new TurnLockOutcomeInstruction();
        $open->setParameters(['open' => 1]);
        $this->assertTrue($open->execute($owner, $chest, new ConditionObject())->isSuccess());
        $this->assertSame(
            1,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$chestId])
        );
    }

    public function testTheConditionShowsEachButtonWhereItServes(): void
    {
        [$chestId, $owner] = $this->ownedChest(52, 30);
        $chest = PlayerFactory::legacy($chestId);
        $chest->get_data();

        $condition = new RequiresLockControlCondition();

        // Open chest: `fermer` (produces shut) passes, `ouvrir` refuses.
        $this->assertTrue(
            $condition->check($owner, $chest, $this->conditionWith(0), new ConditionObject())->isSuccess()
        );
        $result = $condition->check($owner, $chest, $this->conditionWith(1), new ConditionObject());
        $this->assertFalse($result->isSuccess());
        $this->assertContains('C\'est déjà ouvert.', $result->getConditionFailureMessages());
    }

    public function testAStrangersHandNeverReachesTheLock(): void
    {
        [$chestId] = $this->ownedChest(54, 30);
        $chest = PlayerFactory::legacy($chestId);
        $chest->get_data();
        $stranger = $this->createRealPlayer('GmSansMain');

        $result = (new RequiresLockControlCondition())
            ->check($stranger, $chest, $this->conditionWith(0), new ConditionObject());

        $this->assertFalse($result->isSuccess());
        $this->assertContains('Cette serrure ne vous connaît pas.', $result->getConditionFailureMessages());
    }

    public function testAJammedLockAnswersNoHand(): void
    {
        [$chestId, $owner] = $this->ownedChest(58, 30);

        // Wounded below the closure threshold: the STATE shuts it.
        $this->link->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', -30)
             ON DUPLICATE KEY UPDATE n = -30",
            [$chestId]
        );
        $chest = PlayerFactory::legacy($chestId);
        $chest->get_data();

        $result = (new RequiresLockControlCondition())
            ->check($owner, $chest, $this->conditionWith(0), new ConditionObject());
        $this->assertFalse($result->isSuccess(), 'display_context: no button on a wreck');

        $this->expectExceptionMessage('La serrure ne répond plus');
        (new \App\Service\ContainerService())->toggleOpen($chestId, (int) $owner->id, false);
    }

    public function testTheGestureMintsNoExperience(): void
    {
        $action = \App\Factory\EntityManagerFactory::getEntityManager()
            ->getRepository(\App\Entity\Action::class)->findOneBy(['name' => 'fermer']);
        if ($action === null) {
            $this->markTestSkipped('actions fermer/ouvrir not seeded (run migrations).');
        }

        $this->assertInstanceOf(
            \App\Action\GestureAction::class,
            $action,
            'the lock is a gesture: the type no XP rule binds to'
        );

        $anyone = $this->createRealPlayer('GmSansXp');
        $xp = (new \App\Service\Action\ActionXpResolver())->calculate($action, true, $anyone, $anyone);
        $this->assertSame(['actor' => 0, 'target' => 0], $xp, 'an unlimited gesture mints nothing');
    }

    public function testWhatCannotBeShutHasNoLockToTurn(): void
    {
        $owner = $this->createRealPlayer('GmSansSerrure');
        $swordId = $this->installExemplar('gladius', 56, 30, (int) $owner->id);
        $sword = PlayerFactory::legacy($swordId);
        $sword->get_data();

        $result = (new RequiresLockControlCondition())
            ->check($owner, $sword, $this->conditionWith(0), new ConditionObject());

        $this->assertFalse($result->isSuccess());
        $this->assertContains('Cela ne se ferme pas.', $result->getConditionFailureMessages());
    }
}
