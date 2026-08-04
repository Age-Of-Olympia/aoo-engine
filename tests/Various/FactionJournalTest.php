<?php

namespace Tests\Various;

use App\Service\ContainerService;
use App\Service\FactionLogService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The faction's journal: the house sees what happened to its things —
 * takings, locks turned — names resolved at the gesture, internal
 * theft plainly visible. A thing of nobody's writes nowhere.
 */
#[Group('action')]
class FactionJournalTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_j';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Journal de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, useChest)
             VALUES (?, 0, "Garde", 1, 1)',
            [$this->factionId]
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

    public function testTheHouseSeesTakingsAndLocks(): void
    {
        $chestId = $this->installExemplar('coffre_bois', 60, 30);
        $this->link->executeStatement(
            "UPDATE players SET owner_id = NULL, faction = ? WHERE id = ?",
            [self::CODE, $chestId]
        );

        $member = $this->createRealPlayer('GmJournal');
        $member->get_data();
        $coordsId = (int) View::get_coords_id(
            (object) ['x' => 61, 'y' => 30, 'z' => 0, 'plan' => 'gaia']
        );
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = 0, coords_id = ? WHERE id = ?',
            [self::CODE, $coordsId, $member->id]
        );
        $bois = $this->itemOrSkip('bois');
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 3, '', '')
             ON DUPLICATE KEY UPDATE n = 3",
            [$member->id, (int) $bois->id]
        );

        $service = new ContainerService();
        $service->depositStack($chestId, (int) $member->id, (int) $bois->id, 2);
        $service->withdrawStack($chestId, (int) $member->id, (int) $bois->id, 1);
        $service->toggleOpen($chestId, (int) $member->id, false);

        $messages = array_column((new FactionLogService())->listOf(self::CODE), 'message');

        $this->assertCount(3, $messages, 'three gestures, three lines');
        $this->assertStringContainsString('a fermé', $messages[0], 'newest first');
        $this->assertStringContainsString('a pris 1 × Bois dans', $messages[1]);
        $this->assertStringContainsString('a déposé 2 × Bois dans', $messages[2]);
        $this->assertStringContainsString($member->data->name, $messages[2], 'names resolved at the gesture');
    }

    public function testAThingOfNobodysWritesNowhere(): void
    {
        $chestId = $this->installExemplar('coffre_bois', 62, 30);
        $this->link->executeStatement(
            "UPDATE players SET owner_id = NULL, faction = '' WHERE id = ?",
            [$chestId]
        );

        $lone = $this->createRealPlayer('GmSeul');
        $coordsId = (int) View::get_coords_id(
            (object) ['x' => 63, 'y' => 30, 'z' => 0, 'plan' => 'gaia']
        );
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $lone->id]);
        $bois = $this->itemOrSkip('bois');
        $this->link->executeStatement(
            "INSERT INTO players_items (player_id, item_id, n, equiped, slot) VALUES (?, ?, 1, '', '')
             ON DUPLICATE KEY UPDATE n = 1",
            [$lone->id, (int) $bois->id]
        );

        $before = (int) $this->link->fetchOne('SELECT COUNT(*) FROM faction_logs');
        (new ContainerService())->depositStack($chestId, (int) $lone->id, (int) $bois->id, 1);

        $this->assertSame(
            $before,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM faction_logs'),
            'no house, no journal'
        );
    }
}
