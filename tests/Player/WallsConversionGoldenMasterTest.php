<?php

namespace Tests\Player;

use App\Factory\PlayerFactory;
use App\Service\PlayerService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Invariants de la conversion murs→entités (Version20260719280000,
 * docs/design-walls-to-entities.md) : après migrations, il ne reste en
 * map_resources que les RESSOURCES, les autels et les plans de tutoriel —
 * et un obstacle converti vit et meurt comme tout bâtiment.
 */
#[Group('entities-structure')]
class WallsConversionGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    /** Types RESSOURCE gelés par la migration (RESOURCES_PV < 0). */
    private const RESOURCE_PREFIXES = [
        'arbre', 'cendre', 'cuir', 'cuivre', 'etain', 'fer', 'nickel',
        'salpetre', 'tourbe', 'mana', 'bronze', 'herbe', 'jungle',
        'pierre', 'rocher_desert',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();
    }

    public function testOnlyResourcesAltarsAndTutorialWallsRemain(): void
    {
        $leftovers = $this->link->fetchAllAssociative(
            "SELECT w.name, c.plan FROM map_resources w JOIN coords c ON c.id = w.coords_id"
        );

        $violations = [];
        foreach ($leftovers as $row) {
            $name = (string) $row['name'];
            $plan = (string) $row['plan'];

            if ($plan === 'tutorial' || str_starts_with($plan, 'tut_')) {
                continue;
            }
            if (str_starts_with($name, 'altar') || str_starts_with($name, 'autel') || str_starts_with($name, 'unique_')) {
                continue;
            }
            $isResource = false;
            foreach (self::RESOURCE_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $isResource = true;
                    break;
                }
            }
            if (!$isResource) {
                $violations[] = $name . '@' . $plan;
            }
        }

        $this->assertSame(
            [],
            $violations,
            'map_resources ne doit plus porter d\'obstacles convertibles : ' . implode(', ', $violations)
        );
    }

    public function testAConvertedObstacleHasSpriteRaceAndDiesLikeABuilding(): void
    {
        $converted = $this->link->fetchAssociative(
            'SELECT a.entity_id, a.name AS wall_name FROM map_walls_archive a
             JOIN players p ON p.id = a.entity_id LIMIT 1'
        );
        if ($converted === false) {
            $this->markTestSkipped('aucune conversion archivée dans cet environnement.');
        }

        $entity = PlayerFactory::legacy((int) $converted['entity_id']);
        $entity->get_data();
        $entity->get_caracs();

        $this->assertSame('building', (string) ($entity->data->player_type ?? ''), 'converted wall is a building');
        $this->assertSame(
            'img/walls/' . $converted['wall_name'] . '.png',
            (string) $entity->data->avatar,
            'l\'image du mur est copiée telle quelle (donnée, pas résolue)'
        );
        $this->assertGreaterThan(0, (int) $entity->caracs->pv, 'sa pseudo-race porte des PV');

        // Mort de bâtiment : on ne sacrifie PAS un mur du monde — un
        // CLONE jetable de la même race convertie meurt à sa place,
        // par le même chemin que tout bâtiment (tombstone limbes).
        $cloneId = $this->placeStructure((string) $entity->data->race, 0, 3);
        $clone = PlayerFactory::legacy($cloneId);
        $clone->get_data();
        $clone->get_caracs();
        $this->snapshotBloodAt((int) $clone->data->coords_id);
        $clone->putBonus(['pv' => -((int) $clone->caracs->pv)]);

        $attacker = $this->createRealPlayer('GmWrecker');
        $attacker->get_data();
        $target = PlayerFactory::legacy($cloneId);
        $target->get_data();
        $target->get_caracs();

        ob_start();
        try {
            PlayerService::ProcessTargetDeath($attacker, $target);
        } finally {
            ob_end_clean();
        }

        $shelved = $this->link->fetchAssociative(
            'SELECT coords_id FROM players WHERE id = ?',
            [$cloneId]
        );
        $this->assertNotFalse($shelved, 'sa ligne survit à sa destruction');
        $this->assertNull(
            $shelved['coords_id'],
            'un obstacle converti meurt comme un bâtiment : disparu, remisé nulle part'
        );
    }
}
