<?php

namespace Tests\Player;

use App\Service\Map\TileOccupancyService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Un coffre est un objet, et plus un type de bâtiment.
 *
 * Il était catalogué deux fois — une ligne `items` et une race de sorte
 * structure — ce qui est exactement ce qui permet à un nom de désigner deux
 * choses. La race est partie ; ce qui suit vérifie que rien ne la cherche
 * encore, et que le coffre répond toujours à ce qu'on lui demandait.
 */
#[Group('entities-structure')]
class ChestIsAnObjectGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    /** Ce que chaque matière encaisse, décidé plutôt qu'hérité. */
    private const LIFE = [
        'coffre_bois' => 40,
        'coffre_bois_petrifie' => 70,
        'coffre_metal' => 100,
        'coffre_humain' => 25,
    ];

    /** Plus aucune race ne type un coffre. */
    public function testNoChestRemainsInTheRaces(): void
    {
        $left = $this->link->fetchFirstColumn("SELECT name FROM races WHERE name LIKE 'coffre%'");

        $this->assertSame([], $left, 'races de coffre restantes : ' . implode(', ', $left));
    }

    /** Chaque coffre a un type d'objet, y compris celui qui n'en avait pas. */
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

    /**
     * Sans race, un coffre posé tient toujours sa case.
     *
     * La preuve que le catalogue se choisit au DISCRIMINANT : `players.race`
     * lit encore « coffre_bois », mais plus rien ne va le chercher dans
     * `races` — et l'obstruction répond quand même.
     */
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
}
