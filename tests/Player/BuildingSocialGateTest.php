<?php

namespace Tests\Player;

use App\Factory\PlayerFactory;
use App\Service\FactionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Une structure n'est pas un acteur social (revue du 2026-07-19) : ni
 * missives, ni compte de membres de faction — un bâtiment porte une
 * faction et des options (isMerchant…) mais n'est pas un interlocuteur.
 */
#[Group('entities-structure')]
class BuildingSocialGateTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    public function testABuildingCanNeitherReceiveNorSendMissives(): void
    {
        $writer = $this->createRealPlayer('GmScribe');
        $writer->get_data();

        [$x, $y] = $this->farTile();
        $buildingId = $this->placeStructure('palissade', $x, $y);
        $building = PlayerFactory::legacy($buildingId);
        $building->get_data();

        $this->assertFalse(
            $writer->check_missive_permission($building),
            'a building must not be a missive recipient'
        );
        $this->assertFalse(
            $building->check_missive_permission($writer),
            'a building must not be a missive sender either'
        );
    }

    public function testFactionMemberCountsIgnoreStructures(): void
    {
        $service = new FactionService();
        $writer = $this->createRealPlayer('GmZealot');
        $writer->get_data();
        $faction = (string) $writer->data->faction;
        if ($faction === '') {
            $this->markTestSkipped('fixture player has no faction.');
        }

        $before = $service->countPlayersUsingFaction($faction)['members'];

        [$x, $y] = $this->farTile();
        $buildingId = $this->placeStructure('palissade', $x, $y);
        /* Le cas que ce test forçait est devenu le seul : la faction vit sur
         * l'entité, pour une forge comme pour un personnage. Porter une
         * faction n'est toujours pas en être MEMBRE — c'est ce qu'on vérifie. */
        $this->link->executeStatement('UPDATE players SET faction = ? WHERE id = ?', [$faction, $buildingId]);

        $this->assertSame(
            $before,
            $service->countPlayersUsingFaction($faction)['members'],
            'a faction-flagged building must not count as a member'
        );
    }
}
