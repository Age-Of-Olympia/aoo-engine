<?php

namespace Tests\Tutorial;

use App\Tutorial\TutorialNpcRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Tutorial\Mock\TutorialIntegrationTestCase;

/**
 * Un PNJ décrit deux fois n'apparaît qu'une fois.
 *
 * Le re-jeu d'une migration de seed consolidée a doublé `tutorial_npcs`
 * une fois : chaque session voyait alors DEUX Gaïa sur (1,0). La donnée
 * a été dédoublonnée par migration ; ce test verrouille la défense côté
 * lecture — le dépôt écarte les lignes de configuration identiques, et
 * garde les différences voulues (autre case, autre étape de spawn).
 */
#[Group('tutorial')]
class TutorialNpcRosterDedupeTest extends TutorialIntegrationTestCase
{
    private const VERSION = 'test-dedupe-9.9.9';

    public function testIdenticalRowsCollapseToOneNpc(): void
    {
        $this->seedNpc('guide', 'template', 'Gaïa', 1, 0);
        $this->seedNpc('guide', 'template', 'Gaïa', 1, 0);

        $repo = new TutorialNpcRepository($this->conn);
        $list = $repo->listActive(self::VERSION, 'template');

        $this->assertCount(1, $list, 'deux lignes identiques doivent produire UN spawn');
        $this->assertSame('Gaïa', $list[0]['name']);
    }

    public function testDistinctPlacementsBothStand(): void
    {
        $this->seedNpc('guide', 'template', 'Gaïa', 1, 0);
        $this->seedNpc('guide', 'template', 'Gaïa', -1, 0);

        $repo = new TutorialNpcRepository($this->conn);

        $this->assertCount(
            2,
            $repo->listActive(self::VERSION, 'template'),
            'deux cases différentes sont deux PNJ voulus'
        );
    }

    public function testOldestRowWinsOnCollapse(): void
    {
        $first = $this->seedNpc('enemy', 'dynamic', 'Âme', 2, 1);
        $this->seedNpc('enemy', 'dynamic', 'Âme', 2, 1);

        $repo = new TutorialNpcRepository($this->conn);
        $list = $repo->listActive(self::VERSION, 'dynamic');

        $this->assertCount(1, $list);
        $this->assertSame($first, $list[0]['id'], 'la ligne la plus ancienne porte le spawn');
    }

    private function seedNpc(string $role, string $spawnMode, string $name, int $x, int $y): int
    {
        $this->conn->insert('tutorial_npcs', [
            'version'    => self::VERSION,
            'role'       => $role,
            'spawn_mode' => $spawnMode,
            'x'          => $x,
            'y'          => $y,
            'name'       => $name,
            'race'       => 'dieu',
            'avatar'     => 'img/avatars/dieu/25.png',
            'portrait'   => 'img/portraits/dieu/1.jpeg',
            'is_active'  => 1,
        ]);

        return (int) $this->conn->lastInsertId();
    }
}
