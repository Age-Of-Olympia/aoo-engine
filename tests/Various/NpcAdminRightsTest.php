<?php

namespace Tests\Various;

use App\Service\AdminMenuAccessService;
use Classes\CommandFactory;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Character rights split by target: on a PNJ they resolve to npcs.sheet, so
 * an animateur can be given PNJs without players.
 */
#[Group('action')]
class NpcAdminRightsTest extends LegacyPlayerFixtureTestCase
{
    public function testCharacterRightsResolveToTheNpcTwinOnAPnj(): void
    {
        $access = new AdminMenuAccessService();
        $real = $this->createRealPlayer('RightsReal');
        $npcId = $this->createNpc();

        $this->assertSame('players.sheet', $access->forCharacter('players.sheet', (int) $real->id));
        $this->assertSame('npcs.sheet', $access->forCharacter('players.sheet', $npcId));
        $this->assertSame('npcs.sheet', $access->forCharacter('players.rank-removal', $npcId));
        $this->assertSame('players.block', $access->forCharacter('players.block', $npcId));
    }

    public function testConsoleKeysFollowTheTarget(): void
    {
        $real = $this->createRealPlayer('RightsReal');
        $npcId = $this->createNpc();
        $commands = CommandFactory::initCommmandFactory()->getCommands();

        $this->assertSame('npcs.sheet', $commands['tp']->accessKey([(string) $npcId, '0,0,0,gaia']));
        $this->assertSame('players.sheet', $commands['tp']->accessKey([(string) $real->id, '0,0,0,gaia']));
        $this->assertSame('players.sheet', $commands['tp']->accessKey(['everyone', '0,0,0,gaia']));
        $this->assertSame('npcs.sheet', $commands['player']->accessKey(['removeupgrade', (string) $npcId, 'A']));
        $this->assertSame('players.rank-removal', $commands['player']->accessKey(['removeupgrade', (string) $real->id, 'A']));
        $this->assertSame('npcs.sheet', $commands['player']->accessKey(['addbank', (string) $npcId, 'or', '10']));
        $this->assertSame('console.player', $commands['player']->accessKey(['addbank', (string) $real->id, 'or', '10']));
        $this->assertSame('console.player', $commands['player']->accessKey(['create', 'Orcrist', 'olympien']));
    }

    private function createNpc(): int
    {
        $id = Player::put_player('RightsNpc_' . bin2hex(random_bytes(4)), 'nain', true);
        $this->trackEntityId($id);

        return $id;
    }
}
