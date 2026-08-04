<?php

namespace Tests\Various;

use App\Service\ContainerService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A container's contents are its children and its own stack rows —
 * nothing chest-specific. Using one asks: standing, not shut, one of
 * its people, within reach. Stack moves are guarded UPDATEs: the last
 * unit leaves once.
 */
#[Group('entities-structure')]
class ContainerServiceTest extends LegacyPlayerFixtureTestCase
{
    private const FACTION = 'faction_test_c';

    private ?int $factionId = null;

    protected function tearDown(): void
    {
        if ($this->factionId !== null) {
            $this->link->executeStatement("UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?", [self::FACTION]);
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
            \App\Service\FactionService::clearCache();
        }
        parent::tearDown();
    }

    /** A faction whose ladder splits on the useChest flag: Recrue 0 no, Garde 1 yes. */
    private function factionWithRanks(): string
    {
        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Coffres de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::FACTION]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::FACTION]);
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, useChest)
             VALUES (?, 0, "Recrue", 1, 0), (?, 1, "Garde", 0, 1)',
            [$this->factionId, $this->factionId]
        );
        \App\Service\FactionService::clearCache();

        return self::FACTION;
    }

    private function enrolled(int $playerId, int $position): void
    {
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [self::FACTION, $position, $playerId]
        );
    }

    private function chestAt(int $x, int $y): int
    {
        $chestId = $this->installExemplar('coffre_bois', $x, $y);

        // Ownerless and factionless: open to everyone — the baseline.
        $this->link->executeStatement(
            "UPDATE players SET owner_id = NULL, faction = '' WHERE id = ?",
            [$chestId]
        );

        return $chestId;
    }

    private function actorNextTo(int $x, int $y, string $prefix): int
    {
        $player = $this->createRealPlayer($prefix);
        $coordsId = (int) View::get_coords_id(
            (object) ['x' => $x + 1, 'y' => $y, 'z' => 0, 'plan' => 'gaia']
        );
        $this->link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [$coordsId, $player->id]
        );

        return (int) $player->id;
    }

    private function giveStack(int $playerId, string $itemName, int $n): int
    {
        $item = $this->itemOrSkip($itemName);
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, ?, '', '')
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [$playerId, (int) $item->id, $n]
        );

        return (int) $item->id;
    }

    private function stackOf(int $playerId, int $itemId): int
    {
        $n = $this->link->fetchOne(
            "SELECT n FROM players_items WHERE player_id = ? AND item_id = ? AND slot = ''",
            [$playerId, $itemId]
        );

        return $n === false ? 0 : (int) $n;
    }

    public function testAStackTravelsInAndOut(): void
    {
        $chest = $this->chestAt(30, 30);
        $actor = $this->actorNextTo(30, 30, 'GmCoffreA');
        $bois = $this->giveStack($actor, 'bois', 5);

        $service = new ContainerService();
        $service->depositStack($chest, $actor, $bois, 3);

        $this->assertSame(2, $this->stackOf($actor, $bois));
        $this->assertSame(3, $this->stackOf($chest, $bois), 'the chest owns the stack row');

        $service->withdrawStack($chest, $actor, $bois, 2);

        $this->assertSame(4, $this->stackOf($actor, $bois));
        $this->assertSame(1, $this->stackOf($chest, $bois));

        $contents = $service->contentsOf($chest);
        $this->assertSame(1, (int) $contents['stacks'][0]['n'], 'contentsOf reads the same rows');
    }

    public function testTheGiverIsDebitedOnlyWhileItHolds(): void
    {
        $chest = $this->chestAt(32, 30);
        $actor = $this->actorNextTo(32, 30, 'GmCoffreB');
        $bois = $this->giveStack($actor, 'bois', 2);

        try {
            (new ContainerService())->depositStack($chest, $actor, $bois, 3);
            $this->fail('an overdraft must refuse');
        } catch (\RuntimeException $e) {
            $this->assertSame('Vous n\'avez pas cela.', $e->getMessage());
        }

        $this->assertSame(2, $this->stackOf($actor, $bois), 'nothing moved');
        $this->assertSame(0, $this->stackOf($chest, $bois));
    }

    public function testAnExemplarTravelsInAndOut(): void
    {
        $chest = $this->chestAt(34, 30);
        $actor = $this->actorNextTo(34, 30, 'GmCoffreC');

        $gladius = $this->itemOrSkip('gladius');
        $instanceId = (new \App\Service\ItemInstanceService())
            ->create($actor, (int) $gladius->id, $actor, '');
        $swordId = (int) $this->link->fetchOne(
            'SELECT entity_id FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        $this->trackEntityId($swordId);

        $service = new ContainerService();
        $service->depositExemplar($chest, $actor, $instanceId);
        $this->assertSame(
            $chest,
            (int) $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$swordId]),
            'the sword is the chest\'s child'
        );

        $exemplars = $service->contentsOf($chest)['exemplars'];
        $this->assertSame($instanceId, (int) $exemplars[0]['instance_id']);

        $service->withdrawExemplar($chest, $actor, $instanceId);
        $this->assertSame(
            $actor,
            (int) $this->link->fetchOne('SELECT holder_id FROM players WHERE id = ?', [$swordId]),
            'back in the bag'
        );
    }

    public function testShutDeniesItsContents(): void
    {
        $chest = $this->chestAt(36, 30);
        $actor = $this->actorNextTo(36, 30, 'GmCoffreD');
        $bois = $this->giveStack($actor, 'bois', 1);
        $this->link->executeStatement('UPDATE players SET is_open = 0 WHERE id = ?', [$chest]);

        $this->expectExceptionMessage('Ce contenant est fermé volontairement.');
        (new ContainerService())->depositStack($chest, $actor, $bois, 1);
    }

    public function testAStrangerIsNotOneOfTheirs(): void
    {
        $chest = $this->chestAt(38, 30);
        $keeper = $this->createRealPlayer('GmGardien');
        $this->link->executeStatement(
            'UPDATE players SET owner_id = ? WHERE id = ?',
            [$keeper->id, $chest]
        );
        $stranger = $this->actorNextTo(38, 30, 'GmVoleur');
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$stranger]);
        $bois = $this->giveStack($stranger, 'bois', 1);

        $this->expectExceptionMessage('Vous n\'êtes pas des siens.');
        (new ContainerService())->depositStack($chest, $stranger, $bois, 1);
    }

    public function testFromAfarOneCannotReach(): void
    {
        $chest = $this->chestAt(40, 30);
        $actor = $this->createRealPlayer('GmLoin');
        $farId = (int) View::get_coords_id(
            (object) ['x' => 45, 'y' => 30, 'z' => 0, 'plan' => 'gaia']
        );
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$farId, $actor->id]);
        $bois = $this->giveStack((int) $actor->id, 'bois', 1);

        $this->expectExceptionMessage('Approchez-vous pour l\'ouvrir.');
        (new ContainerService())->depositStack($chest, (int) $actor->id, $bois, 1);
    }

    public function testAFactionChestFollowsTheRank(): void
    {
        $code = $this->factionWithRanks();
        $chest = $this->chestAt(44, 30);
        $this->link->executeStatement('UPDATE players SET faction = ? WHERE id = ?', [$code, $chest]);

        $guard = $this->actorNextTo(44, 30, 'GmGardeC');
        $this->enrolled($guard, 1);
        $recruit = $this->actorNextTo(44, 30, 'GmRecrueC');
        $this->enrolled($recruit, 0);
        $bois = $this->giveStack($guard, 'bois', 2);
        $this->giveStack($recruit, 'bois', 2);

        $service = new ContainerService();
        $service->depositStack($chest, $guard, $bois, 1);
        $this->assertSame(1, $this->stackOf($chest, $bois), 'the flagged rank uses the chest');

        $this->assertFalse($service->mayUse($chest, $recruit), 'seeing inside follows the same rule');

        $this->expectExceptionMessage('Votre rang n\'use pas des coffres de la faction.');
        $service->depositStack($chest, $recruit, $bois, 1);
    }

    public function testTheFactionLockFollowsTheSameRank(): void
    {
        $code = $this->factionWithRanks();
        $chest = $this->chestAt(46, 30);
        $this->link->executeStatement('UPDATE players SET faction = ? WHERE id = ?', [$code, $chest]);

        $guard = $this->actorNextTo(46, 30, 'GmGardeL');
        $this->enrolled($guard, 1);
        $recruit = $this->actorNextTo(46, 30, 'GmRecrueL');
        $this->enrolled($recruit, 0);

        $service = new ContainerService();
        $service->toggleOpen($chest, $guard, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$chest]),
            'the flagged rank turns the faction lock'
        );
        $service->toggleOpen($chest, $guard, true);

        $this->expectExceptionMessage('Votre rang n\'use pas des coffres de la faction.');
        $service->toggleOpen($chest, $recruit, false);
    }

    public function testTheLockKnowsItsPeopleAlone(): void
    {
        $chest = $this->chestAt(42, 30);
        $keeper = $this->actorNextTo(42, 30, 'GmClef');
        $this->link->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$keeper, $chest]);

        $service = new ContainerService();
        $service->toggleOpen($chest, $keeper, false);
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT is_open FROM players WHERE id = ?', [$chest]),
            'the owner turns their own lock'
        );
        $service->toggleOpen($chest, $keeper, true);

        $stranger = $this->actorNextTo(42, 30, 'GmSansClef');
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$stranger]);

        $this->expectExceptionMessage('Cette serrure ne vous connaît pas.');
        $service->toggleOpen($chest, $stranger, false);
    }
}
