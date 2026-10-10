<?php

namespace Tests\Various;

use App\Service\Map\EntityRekindService;
use App\Service\Map\ResourceStateService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A type moved to another family takes its placed exemplars with it:
 * discriminator and satellite rows, both ways.
 */
class EntityRekindTest extends LegacyPlayerFixtureTestCase
{
    private const TYPE = 'zz_arbre_mal_type';

    public function testExemplarsFollowTheirTypeFamily(): void
    {
        $this->sowStructureType(self::TYPE);
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure(self::TYPE, $x, $y);
        $service = new EntityRekindService();

        $this->setFamily('resource', 'ressource');
        $this->assertSame([$id], array_column($service->mismatches(self::TYPE), 'id'));

        $this->assertSame(1, $service->rekindType(self::TYPE));
        $this->assertSame('resource', $this->playerType($id));
        $this->assertFalse($this->link->fetchOne('SELECT 1 FROM buildings WHERE player_id = ?', [$id]));
        $this->assertNotFalse($this->link->fetchOne('SELECT 1 FROM entity_cells WHERE player_id = ?', [$id]));
        $this->assertSame([], $service->mismatches(self::TYPE));
        $this->assertSame(0, $service->rekindType(self::TYPE), 'idempotent');

        (new ResourceStateService($this->link))->exhaust([$id]);
        $this->setFamily('building', 'edifice');

        $this->assertSame(1, $service->rekind([$id]));
        $this->assertSame('building', $this->playerType($id));
        $this->assertSame('built', $this->link->fetchOne('SELECT build_state FROM buildings WHERE player_id = ?', [$id]));
        $this->assertFalse($this->link->fetchOne('SELECT 1 FROM resources WHERE player_id = ?', [$id]));
    }

    private function setFamily(string $kind, string $nature): void
    {
        $this->link->executeStatement(
            'UPDATE races SET type_kind = ?, structure_nature = ? WHERE name = ?',
            [$kind, $nature, self::TYPE]
        );
        $this->refreshRaceCatalog();
    }

    private function playerType(int $id): string
    {
        return (string) $this->link->fetchOne('SELECT player_type FROM players WHERE id = ?', [$id]);
    }
}
