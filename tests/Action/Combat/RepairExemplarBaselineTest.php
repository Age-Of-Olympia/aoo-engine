<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** Repair reaches a placed object: it is an entity below its max life. */
#[Group('entities-baseline')]
#[Group('items-baseline')]
class RepairExemplarBaselineTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    public function testRepairingAPlacedChestHealsItLikeAnyStructure(): void
    {
        $action = ActionFactory::getAction('reparer');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'reparer' row).");
        }

        $actor = $this->createRealPlayer('GmRepareur');
        $chestId = $this->installExemplar('coffre_bois', 0, 1);

        $actor->getCoords();
        $actor->get_caracs();
        $this->movePlayerTo((int) $actor->id, 0, 0);
        $actor->getCoords();

        $chest = PlayerFactory::legacy($chestId);
        $chest->get_caracs();
        $chest->putBonus(['pv' => -20]);

        $this->assertSame(
            20,
            (int) $this->link->fetchOne(
                "SELECT -n FROM players_bonus WHERE player_id = ? AND name = 'pv'",
                [$chestId]
            ),
            'the chest starts wounded'
        );

        $results = (new ActionExecutorService($action, $actor, $chest))->executeAction();

        $this->assertFalse($results->isBlocked(), 'a placed exemplar is a legal repair target');
        $this->assertTrue($results->isSuccess());

        // coffre_bois: durability_max 40, healed by the actor's F, clamped.
        $expected = min(40, 20 + (int) $actor->caracs->f);
        $this->assertSame(
            $expected,
            PlayerFactory::legacy($chestId)->getRemaining('pv'),
            'repair heals the object through the one life'
        );
    }

    /**
     * Settled rule: an object below its max life is repairable WHEREVER it is.
     * A dropped one lies on the tile without holding it — this pins whether
     * the board can still designate it.
     */
    public function testRepairingADroppedObject(): void
    {
        $action = ActionFactory::getAction('reparer');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'reparer' row).");
        }

        $actor = $this->createRealPlayer('GmRepareur2');
        $chestId = $this->installExemplar('coffre_bois', 3, 1);

        $coordsId = (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$chestId]);
        (new \App\Service\Map\EntityLocationService($this->link))->dropOnCell($chestId, $coordsId);

        $this->movePlayerTo((int) $actor->id, 3, 0);
        $actor->getCoords();
        $actor->get_caracs();

        $chest = PlayerFactory::legacy($chestId);
        $chest->get_caracs();
        $chest->putBonus(['pv' => -20]);

        $results = (new ActionExecutorService($action, $actor, $chest))->executeAction();

        $this->assertFalse(
            $results->isBlocked(),
            'refusé : ' . json_encode($results->getConditionsResultsArray())
        );
    }
}
