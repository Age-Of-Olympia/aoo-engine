<?php

namespace Tests\Tutorial;

use App\Service\Map\TileOccupancyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Sur un plan qui masque les personnages, un PNJ barre quand même le pas.
 *
 * L'isolation du tutoriel (`player_visibility: false`) masque les AUTRES
 * joueurs ; les PNJ, eux, restent dessinés (View.php n'écarte que id > 0).
 * La règle « bloquer, c'est être vu » appliquée sans cette nuance laissait
 * le joueur marcher SUR la case de Gaïa — et rendait fausse la chorégraphie
 * du tutoriel, qui contourne la guide pour rejoindre l'ennemi.
 */
#[Group('tutorial')]
class TutorialNpcBlocksStepTest extends TutorialIntegrationTestCase
{
    public function testAnNpcBlocksTheStepEvenOnAHiddenPlan(): void
    {
        [$tile, $npcId] = $this->seedCharacterOnTile('npc');
        $moverId = $this->seedMover();

        $blocked = (new TileOccupancyService($this->conn))
            ->blockedForStep([$tile], $moverId, false);

        $this->assertArrayHasKey($tile, $blocked, 'le PNJ visible doit barrer le pas, plan masqué ou non');
    }

    public function testAnotherPlayerDoesNotBlockOnAHiddenPlan(): void
    {
        [$tile, ] = $this->seedCharacterOnTile('real');
        $moverId = $this->seedMover();

        $blocked = (new TileOccupancyService($this->conn))
            ->blockedForStep([$tile], $moverId, false);

        $this->assertArrayNotHasKey($tile, $blocked, 'un joueur masqué par le plan ne barre pas le pas');
    }

    public function testAnotherPlayerBlocksWhenThePlanShowsCharacters(): void
    {
        [$tile, ] = $this->seedCharacterOnTile('real');
        $moverId = $this->seedMover();

        $blocked = (new TileOccupancyService($this->conn))
            ->blockedForStep([$tile], $moverId, true);

        $this->assertArrayHasKey($tile, $blocked, 'un joueur vu barre le pas');
    }

    /** @return array{0: int, 1: int} [coords id, players id] */
    private function seedCharacterOnTile(string $playerType): array
    {
        $tile = $this->seedTile();

        $this->conn->insert('players', [
            'name'        => 'BlocksStep_' . bin2hex(random_bytes(4)),
            'race'        => $playerType === 'npc' ? 'dieu' : 'nain',
            'player_type' => $playerType,
            'coords_id'   => $tile,
        ]);

        return [$tile, (int) $this->conn->lastInsertId()];
    }

    private function seedMover(): int
    {
        $this->conn->insert('players', [
            'name'        => 'BlocksMover_' . bin2hex(random_bytes(4)),
            'race'        => 'nain',
            'player_type' => 'tutorial',
            'coords_id'   => $this->seedTile(),
        ]);

        return (int) $this->conn->lastInsertId();
    }
}
