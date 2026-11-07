<?php

namespace Tests\Action\OutcomeInstruction;

use App\Action\Condition\ConditionObject;
use App\Action\OutcomeInstruction\TeleportOutcomeInstruction;
use App\Action\StealAction;
use App\Entity\ActionOutcome;
use App\Service\Map\EntityLocationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A fixed-destination teleport toward a plan nobody declared fails instead of
 * creating that plan on the fly (go() would insert its first coords).
 */
#[Group('action')]
class TeleportOutcomeUnknownPlanTest extends LegacyPlayerFixtureTestCase
{
    private const UNKNOWN_PLAN = 'plan_tp_inexistant_test';

    protected function tearDown(): void
    {
        $this->link?->executeStatement('DELETE FROM coords WHERE plan = ?', [self::UNKNOWN_PLAN]);
        parent::tearDown();
    }

    private function teleportTo(string $coords): TeleportOutcomeInstruction
    {
        $instruction = new TeleportOutcomeInstruction();
        $instruction->setParameters(['coords' => $coords]);
        $action = new StealAction();
        $action->setName('tp_test');
        $instruction->setOutcome((new ActionOutcome())->setAction($action));

        return $instruction;
    }

    private function cellOf(int $entityId): ?int
    {
        return (new EntityLocationService($this->link))->cellOf($entityId);
    }

    public function testAnUnknownPlanFailsWithoutMovingNorCreatingThePlan(): void
    {
        $actor = $this->createRealPlayer('GmTpInconnu');
        $actor->getCoords();
        $before = $this->cellOf((int) $actor->id);
        // A server-side trace points at the faulty action.
        $this->expectErrorLog();

        $result = $this->teleportTo('0,0,0,' . self::UNKNOWN_PLAN)
            ->execute($actor, $actor, new ConditionObject());

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString(self::UNKNOWN_PLAN, implode(' ', $result->getOutcomeFailureMessages()));
        $this->assertSame(
            $before,
            $this->cellOf((int) $actor->id),
            'le personnage reste où il était'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::UNKNOWN_PLAN]),
            'aucune coord fantôme pour le plan inconnu'
        );
    }

    public function testAKnownPlanStillTeleports(): void
    {
        $actor = $this->createRealPlayer('GmTpConnu');
        $actor->getCoords();
        [$x, $y] = $this->farTile();

        $result = $this->teleportTo($x . ',' . $y . ',0,gaia')
            ->execute($actor, $actor, new ConditionObject());

        $this->assertTrue($result->isSuccess());
        $this->assertSame(
            $this->coordsIdOn('gaia', $x, $y),
            $this->cellOf((int) $actor->id)
        );
    }
}
