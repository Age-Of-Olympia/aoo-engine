<?php

namespace Tests\Player;

use App\Service\Map\TileOccupancyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/** A chest is an object, no longer a building type. */
#[Group('entities-structure')]
class ChestIsAnObjectBaselineTest extends LegacyPlayerFixtureTestCase
{
    /** Durability per material. */
    private const LIFE = [
        'coffre_bois' => 40,
        'coffre_bois_petrifie' => 70,
        'coffre_metal' => 100,
        'coffre_humain' => 25,
    ];

    public function testNoChestRemainsInTheRaces(): void
    {
        $left = $this->link->fetchFirstColumn("SELECT name FROM races WHERE name LIKE 'coffre%'");

        $this->assertSame([], $left, 'races de coffre restantes : ' . implode(', ', $left));
    }

    public function testEveryChestTypeCarriesItsMaterialsLife(): void
    {
        $found = $this->link->fetchAllKeyValue(
            "SELECT name, durability_max FROM items WHERE name LIKE 'coffre%'"
        );

        foreach (self::LIFE as $name => $durability) {
            $this->assertArrayHasKey($name, $found, "'{$name}' n'a pas de type d'objet");
            $this->assertSame(
                $durability,
                (int) $found[$name],
                "'{$name}' n'encaisse pas ce que sa matière dit"
            );
        }
    }

    /** Proof the catalogue is picked by discriminator: the name still reads
     *  'coffre_bois' while no race row remains. */
    public function testAChestStillObstructsWithoutARace(): void
    {
        $mover = $this->createRealPlayer('GmCogneur');
        $chestId = $this->installExemplar('coffre_bois', 2, 9);

        $coordsId = (int) $this->link->fetchOne(
            'SELECT coords_id FROM players WHERE id = ?',
            [$chestId]
        );

        $this->assertSame(
            'coffre_bois',
            $this->link->fetchOne('SELECT race FROM players WHERE id = ?', [$chestId]),
            'le nom est resté, c\'est le catalogue qui a changé'
        );

        $this->assertNotNull(
            (new TileOccupancyService($this->link))->stepRefusal($coordsId, (int) $mover->id, true),
            'un coffre posé barre le pas, race ou pas'
        );
    }

    /** A chest is built under the same tile rule as any construction. */
    public function testAChestIsNotBuiltOnAnOccupiedTile(): void
    {
        [$x, $y] = $this->farTile();
        $this->installExemplar('coffre_bois', $x, $y);
        $coordsId = $this->coordsIdOn('gaia', $x, $y);
        $itemId = (int) $this->link->fetchOne("SELECT id FROM items WHERE name = 'coffre_bois'");

        $this->expectException(\InvalidArgumentException::class);
        (new \App\Service\ItemInstanceService())->installFromCatalogAt($itemId, $coordsId);
    }

    /** A built chest reads its catalogue label, never its code ("Coffre_bois"). */
    public function testABuiltChestIsNamedByItsLabel(): void
    {
        [$x, $y] = $this->farTile();
        $item = $this->link->fetchAssociative("SELECT id, name, label FROM items WHERE name = 'coffre_bois'");
        $id = (new \App\Service\ItemInstanceService())
            ->installFromCatalogAt((int) $item['id'], $this->coordsIdOn('gaia', $x, $y));
        $this->trackEntityId($id);

        $expected = (string) $item['label'] !== '' ? (string) $item['label'] : 'Coffre bois';
        $this->assertSame($expected, $this->link->fetchOne('SELECT name FROM players WHERE id = ?', [$id]));
    }
}
