<?php

namespace Tests\Various;

use App\Service\AdminMenuAccessService;
use Classes\CommandFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Console commands take their level from the admin registry, so the panel and
 * the console share one setting per right. An undeclared key would fall back
 * to super-admin without anyone noticing: every key must be declared.
 */
#[Group('action')]
class ConsoleAccessKeyTest extends TestCase
{
    public function testEveryConsoleKeyIsDeclaredInTheRegistry(): void
    {
        $registry = new AdminMenuAccessService();
        $playerActions = ['create', 'edit', 'additem', 'addbank', 'addxp', 'addupgrade', 'removeupgrade',
            'unequip', 'checkpi', 'msg', 'purge', 'addlog', 'deletelastlog'];

        foreach (CommandFactory::initCommmandFactory()->getCommands() as $command) {
            $argSets = $command->getName() === 'player'
                ? array_map(fn (string $a): array => [$a, 'Orcrist', 'elfe'], $playerActions)
                : [['x', 'y', 'z']];
            foreach ($argSets as $args) {
                $key = $command->accessKey($args);
                if ($key !== null) {
                    $this->assertTrue($registry->isDeclared($key), "{$command->getName()} → {$key} is not declared");
                }
            }
        }
    }

    public function testPlayerSubCommandsFollowTheirPanelCounterpart(): void
    {
        $player = CommandFactory::initCommmandFactory()->getCommands()['player'];

        $this->assertSame('pnjs.php', $player->accessKey(['create', 'Ocyrhoée', 'elfe,pnj']));
        $this->assertSame('console:player', $player->accessKey(['create', 'Orcrist', 'olympien']));
        $this->assertSame('player-rank-removal', $player->accessKey(['removeupgrade', 'Orcrist', 'A']));
        $this->assertSame('players.php', $player->accessKey(['addupgrade', 'Orcrist', 'A']));
    }
}
