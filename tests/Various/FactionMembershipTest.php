<?php

namespace Tests\Various;

use App\Service\FactionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Faction management by ROLE FLAGS: the game finally reads what the
 * admin edits in faction_roles. Every gesture is guarded server-side —
 * the actor's rank allows it, the target is one of theirs — and every
 * refusal speaks the words the player reads.
 */
#[Group('action')]
class FactionMembershipTest extends LegacyPlayerFixtureTestCase
{
    private const CODE = 'faction_test_b';

    private ?int $factionId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->link->executeStatement(
            "INSERT INTO factions (code, name) VALUES (?, 'Faction de test')
             ON DUPLICATE KEY UPDATE name = VALUES(name)",
            [self::CODE]
        );
        $this->factionId = (int) $this->link->fetchOne('SELECT id FROM factions WHERE code = ?', [self::CODE]);

        // The ladder ascends: position 0 is the recruit (default landing
        // role, no flag), position 1 the chief (every flag, ladder included).
        $this->link->executeStatement(
            'INSERT INTO faction_roles (faction_id, position, name, defaultRole, addMember, editRole, kickMember, initRole)
             VALUES (?, 0, "Recrue", 1, 0, 0, 0, 0), (?, 1, "Chef", 0, 1, 1, 1, 1)',
            [$this->factionId, $this->factionId]
        );
        FactionService::clearCache();
    }

    protected function tearDown(): void
    {
        if ($this->factionId !== null) {
            $this->link->executeStatement("UPDATE players SET faction = '', factionRole = 0 WHERE faction = ?", [self::CODE]);
            $this->link->executeStatement('DELETE FROM faction_roles WHERE faction_id = ?', [$this->factionId]);
            $this->link->executeStatement('DELETE FROM factions WHERE id = ?', [$this->factionId]);
            $this->factionId = null;
        }
        FactionService::clearCache();
        parent::tearDown();
    }

    private function enroll(int $playerId, int $position): void
    {
        $this->link->executeStatement(
            'UPDATE players SET faction = ?, factionRole = ? WHERE id = ?',
            [self::CODE, $position, $playerId]
        );
    }

    public function testTheChiefRecruitsAFactionlessCharacterAtTheDefaultRole(): void
    {
        $chief = $this->createRealPlayer('GmChef');
        $this->enroll($chief->id, 1);
        $lone = $this->createRealPlayer('GmSansMaison');
        $lone->get_data();
        // A nain is born into forge_sacree: the race copies its faction.
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$lone->id]);

        (new FactionService())->addMember($chief->id, $lone->data->name);

        $row = $this->link->fetchAssociative('SELECT faction, factionRole FROM players WHERE id = ?', [$lone->id]);
        $this->assertSame(self::CODE, $row['faction']);
        $this->assertSame(0, (int) $row['factionRole'], 'a newcomer lands at the default role');
    }

    public function testARecruitMayNotRecruit(): void
    {
        $recruit = $this->createRealPlayer('GmRecrue');
        $this->enroll($recruit->id, 0);
        $lone = $this->createRealPlayer('GmDehors');
        $lone->get_data();
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$lone->id]);

        $this->expectExceptionMessage('Votre rang ne permet pas de recruter.');
        (new FactionService())->addMember($recruit->id, $lone->data->name);
    }

    public function testNobodyIsRecruitedAwayFromTheirFaction(): void
    {
        $chief = $this->createRealPlayer('GmChef2');
        $this->enroll($chief->id, 1);
        $taken = $this->createRealPlayer('GmDejaPris');
        $taken->get_data();
        $this->link->executeStatement("UPDATE players SET faction = 'autre' WHERE id = ?", [$taken->id]);

        $this->expectExceptionMessage('appartient déjà à une faction');
        (new FactionService())->addMember($chief->id, $taken->data->name);
    }

    public function testTheChiefKicksAMemberButNeverThemself(): void
    {
        $chief = $this->createRealPlayer('GmChef3');
        $this->enroll($chief->id, 1);
        $member = $this->createRealPlayer('GmMembre');
        $this->enroll($member->id, 0);

        $service = new FactionService();
        $service->kickMember($chief->id, $member->id);
        $this->assertSame('', (string) $this->link->fetchOne('SELECT faction FROM players WHERE id = ?', [$member->id]));

        $this->expectExceptionMessage('On ne se bannit pas soi-même.');
        $service->kickMember($chief->id, $chief->id);
    }

    public function testRolesMoveOnlyWithinTheFactionAndItsLadder(): void
    {
        $chief = $this->createRealPlayer('GmChef4');
        $this->enroll($chief->id, 1);
        $member = $this->createRealPlayer('GmPromu');
        $this->enroll($member->id, 0);

        $service = new FactionService();
        $service->assignRole($chief->id, $member->id, 0);
        $this->assertSame(0, (int) $this->link->fetchOne('SELECT factionRole FROM players WHERE id = ?', [$member->id]));

        $this->expectExceptionMessage('Ce rang n\'existe pas.');
        $service->assignRole($chief->id, $member->id, 9);
    }

    public function testAStrangerIsNotYours(): void
    {
        $chief = $this->createRealPlayer('GmChef5');
        $this->enroll($chief->id, 1);
        $stranger = $this->createRealPlayer('GmEtranger');

        $this->expectExceptionMessage('Cette personne n\'est pas des vôtres.');
        (new FactionService())->kickMember($chief->id, $stranger->id);
    }
}
