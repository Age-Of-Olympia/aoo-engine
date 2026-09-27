<?php

namespace Tests\Various;

use App\Service\FactionChestService;
use App\Service\FactionLogService;
use App\Service\ItemInstanceService;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * What a faction decides about chests: take back a public one where its
 * bank stands, give one to a member, abandon one, close floors to its
 * members' chests. The owner may entrust a personal chest to the house.
 *
 * Every scene has its own plan: bank plans are read whole.
 */
#[Group('action')]
class FactionChestServiceTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_c';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Coffres de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, useChest, manageChests)
             VALUES (?, 0, "Garde", 1, 1, 0), (?, 1, "Chef", 0, 1, 1)',
            [$this->factionId, $this->factionId]
        );
        \App\Service\FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        if ($this->factionId !== null) {
            $this->link->executeStatement("UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?", [self::CODE]);
            $this->link->executeStatement('DELETE FROM faction_logs WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
        }
        \App\Service\FactionService::clearCache();
        parent::tearDown();
    }

    private function scenePlan(): string
    {
        return 'p_coffres_' . bin2hex(random_bytes(3));
    }

    private function member(string $name, int $rank): int
    {
        $player = $this->createRealPlayer($name);
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [self::CODE, $rank, $player->id]
        );

        return (int) $player->id;
    }

    private function bankOn(string $plan): void
    {
        [$x, $y] = $this->farTile();
        $bankId = $this->placeStructure('banque', $x, $y, $plan);
        $this->link->executeStatement('UPDATE players SET faction = ? WHERE id = ?', [self::CODE, $bankId]);
    }

    private function chestOn(string $plan, ?int $ownerId = null, string $faction = ''): int
    {
        [$x, $y] = $this->farTile();
        $item = $this->itemOrSkip('coffre_bois');
        $id = (new ItemInstanceService())
            ->installFromCatalogAt((int) $item->id, $this->coordsIdOn($plan, $x, $y), null, $ownerId, $faction);
        $this->trackEntityId($id);

        return $id;
    }

    /** @return array{owner_id: mixed, faction: string} */
    private function ownershipOf(int $id): array
    {
        return $this->link->fetchAssociative('SELECT owner_id, faction FROM players WHERE id = ?', [$id]);
    }

    public function testAPublicChestIsTakenBackOnlyWhereTheBankStands(): void
    {
        $plan = $this->scenePlan();
        $this->bankOn($plan);
        $chief = $this->member('GmChefCoffres', 1);
        $service = new FactionChestService();

        $here = $this->chestOn($plan);
        $elsewhere = $this->chestOn($this->scenePlan());

        $this->assertSame([$here], array_column($service->claimableOf(self::CODE), 'id'));

        $service->claim($here, $chief);
        $this->assertSame(self::CODE, (string) $this->ownershipOf($here)['faction']);

        $this->expectException(RuntimeException::class);
        $service->claim($elsewhere, $chief);
    }

    public function testOnlyTheRankThatManagesMovesTheChests(): void
    {
        $plan = $this->scenePlan();
        $this->bankOn($plan);
        $guard = $this->member('GmGardeCoffres', 0);

        $this->expectExceptionMessage('Votre rang ne gère pas les coffres');
        (new FactionChestService())->claim($this->chestOn($plan), $guard);
    }

    /** Faction → member → faction → public: every state reachable, every step journaled. */
    public function testAChestTravelsBetweenTheHouseAndItsMembers(): void
    {
        $plan = $this->scenePlan();
        $chief = $this->member('GmChefTour', 1);
        $guard = $this->member('GmGardeTour', 0);
        $chest = $this->chestOn($plan, null, self::CODE);
        $service = new FactionChestService();

        $service->offer($chest, $chief, $guard);
        $this->assertSame((int) $guard, (int) $this->ownershipOf($chest)['owner_id']);
        $this->assertSame('', (string) $this->ownershipOf($chest)['faction']);

        $this->assertTrue($service->mayEntrust($chest, $guard));
        $service->entrust($chest, $guard);
        $this->assertNull($this->ownershipOf($chest)['owner_id']);
        $this->assertSame(self::CODE, (string) $this->ownershipOf($chest)['faction']);

        $service->abandon($chest, $chief);
        $this->assertNull($this->ownershipOf($chest)['owner_id']);
        $this->assertSame('', (string) $this->ownershipOf($chest)['faction']);

        $this->assertCount(3, (new FactionLogService())->listOf(self::CODE));
    }

    public function testAChestIsOfferedOnlyToAMember(): void
    {
        $chief = $this->member('GmChefOffre', 1);
        $stranger = (int) $this->createRealPlayer('GmPassantOffre')->id;
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$stranger]);
        $chest = $this->chestOn($this->scenePlan(), null, self::CODE);

        $this->expectExceptionMessage('pas de la faction');
        (new FactionChestService())->offer($chest, $chief, $stranger);
    }

    public function testAClosedFloorRefusesOnlyTheFactionsMembers(): void
    {
        $plan = $this->scenePlan();
        $this->bankOn($plan);
        $chief = $this->member('GmChefNiveau', 1);
        $service = new FactionChestService();

        $service->setFloorOpen($chief, $plan, 0, false);
        $this->assertFalse($service->floorOpenFor(self::CODE, $plan, 0));
        $this->assertTrue($service->floorOpenFor('', $plan, 0), 'sans faction, seul l\'admin décide');
        $this->assertFalse($service->floorsOf(self::CODE)[0]['open']);

        $service->setFloorOpen($chief, $plan, 0, true);
        $this->assertTrue($service->floorOpenFor(self::CODE, $plan, 0));

        $this->expectExceptionMessage('pas de banque sur ce plan');
        $service->setFloorOpen($chief, $this->scenePlan(), 0, false);
    }
}
