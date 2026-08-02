<?php

namespace Tests\Action\Combat;

use App\Factory\ActionFactory;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\ItemInstanceService;
use App\Service\Map\EntityLocationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A chest is broken open by the ordinary attack, and spills.
 *
 * The pieces were verified apart: it takes damage like a structure, it dies
 * by vanishing, it spills what it holds. This drives them from one gesture.
 */
#[Group('entities-baseline')]
#[Group('items-baseline')]
class ChestBreaksUnderAttackBaselineTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    public function testAnAttackWoundsAChestThroughTheOneLife(): void
    {
        $action = ActionFactory::getAction('melee');
        if ($action === null) {
            $this->markTestSkipped("actions catalog not seeded (no 'melee' row).");
        }

        $actor = $this->createRealPlayer('GmCasseur');
        $chestId = $this->installExemplar('coffre_bois', 8, 1);

        $this->movePlayerTo((int) $actor->id, 8, 0);
        $actor->getCoords();
        $actor->get_caracs();

        $chest = PlayerFactory::legacy($chestId);
        $chest->get_caracs();

        $results = (new ActionExecutorService($action, $actor, $chest))->executeAction();

        $this->assertFalse($results->isBlocked(), 'an installed chest is a legal melee target');

        $this->assertLessThan(
            40,
            PlayerFactory::legacy($chestId)->getRemaining('pv'),
            'the blow lands on the chest own life, capped by its material'
        );
    }

    /** Broken open, it spills — the same path a dying player takes. */
    public function testBreakingItOpenSpillsWhatItHeld(): void
    {
        $owner = $this->createRealPlayer('GmCoffrier2');
        $chestId = $this->installExemplar('coffre_bois', 9, 1, (int) $owner->id);

        $gladius = $this->itemOrSkip('gladius');
        $service = new ItemInstanceService();
        $instanceId = $service->create((int) $owner->id, (int) $gladius->id, (int) $owner->id, '');
        $swordId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        $this->trackEntityId($swordId);

        $location = new EntityLocationService($this->link);
        $location->putInside($swordId, $chestId);

        $coordsId = (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$chestId]);

        // Contents take the loot roll, so the chance is pinned.
        $itemId = (int) $gladius->id;
        $before = $this->link->fetchOne('SELECT lootChance FROM items WHERE id = ?', [$itemId]);
        $this->link->executeStatement('UPDATE items SET lootChance = 100 WHERE id = ?', [$itemId]);

        try {
            $chest = PlayerFactory::legacy($chestId);
            $chest->get_caracs();
            $chest->putBonus(['pv' => -40]);

            $breaker = $this->createRealPlayer('GmCasseur2');
            $breaker->get_data();
            ob_start();
            try {
                \App\Service\PlayerService::ProcessTargetDeath($breaker, $chest);
            } finally {
                ob_end_clean();
            }

            $this->assertSame(
                $coordsId,
                (int) $this->link->fetchOne('SELECT coords_id FROM players WHERE id = ?', [$swordId]),
                'the sword lies where the chest stood'
            );
            $this->assertNull(
                $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$swordId]),
                'and nobody holds it any more'
            );
        } finally {
            $this->link->executeStatement('UPDATE items SET lootChance = ? WHERE id = ?', [$before, $itemId]);
        }
    }
}
