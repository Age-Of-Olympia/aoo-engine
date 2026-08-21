<?php

namespace Tests\Tutorial;

use App\Factory\EntityManagerFactory;
use App\Tutorial\TutorialMapInstance;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Une instance de tutoriel naît avec TOUT ce que porte son modèle.
 *
 * Le modèle est fait d'entités depuis le chantier ressources : arbres,
 * murs d'enceinte, plantes. La copie a cassé une fois en silence — la
 * payload des plantes, appliquée par le réconciliateur des ressources,
 * effaçait l'arbre juste posé, et chaque session démarrait sans rien à
 * récolter. Ce test rejoue la création complète et exige chaque famille
 * sur l'instance, satellite compris.
 *
 * Tourne sur la base de la suite (celle que db() sert) : le
 * réconciliateur résout ses cases par le lien legacy, et une autre
 * connexion ne verrait pas les lignes de cette transaction.
 */
#[Group('tutorial')]
class TutorialMapInstanceCloneTest extends TestCase
{
    private Connection $conn;

    private mixed $previousLink = null;

    private string $templatePlan;

    private string $sessionId;


    protected function setUp(): void
    {
        try {
            $this->conn = EntityManagerFactory::getEntityManager()->getConnection();
            $this->conn->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('suite DB unreachable: ' . $e->getMessage());
        }

        if ($this->conn->fetchOne("SELECT COUNT(*) FROM races WHERE name = 'arbre1'") == 0) {
            $this->markTestSkipped('races catalog not seeded — rebuild the suite database');
        }

        // db() (donc View::get_coords_id) doit voir NOTRE transaction.
        $this->previousLink = $GLOBALS['link'] ?? null;
        $GLOBALS['link'] = $this->conn;

        $suffix = bin2hex(random_bytes(4));
        $this->templatePlan = 'tpl_' . $suffix;
        $this->sessionId = $suffix . '-clone-test';

        $this->conn->beginTransaction();

        // Dans la transaction : le rollback du tearDown emporte la config
        // du modèle comme celle de l'instance clonée.
        $this->writePlanConfig($this->templatePlan);
    }

    protected function tearDown(): void
    {
        if (isset($this->conn) && $this->conn->isTransactionActive()) {
            $this->conn->rollBack();
        }
        $GLOBALS['link'] = $this->previousLink;

        // Les caches de lecture et l'identity map survivent au rollback.
        \App\Service\PlanService::forget();
        EntityManagerFactory::getEntityManager()->clear();
    }

    public function testInstanceCarriesEveryEntityFamilyOfTheTemplate(): void
    {
        $this->seedEntity('resource', 'arbre1', $this->seedTemplateTile(0, 1));
        $this->seedEntity('building', 'mur_pierre', $this->seedTemplateTile(1, 1));
        $this->seedEntity('plant', 'adonis', $this->seedTemplateTile(-1, 0));
        $this->seedTemplateTile(0, 0); // spawn

        // Une surcharge de rendement du modèle (jamais épuisé) doit suivre.
        $this->conn->insert('race_harvest', [
            'plan'    => $this->templatePlan,
            'race_id' => (int) $this->conn->fetchOne("SELECT id FROM races WHERE name = 'arbre1'"),
            'item'    => 'bois',
            'exhaust' => 0,
        ]);

        $templateRows = $this->entityCount($this->templatePlan);

        $instance = (new TutorialMapInstance($this->conn))
            ->createInstance($this->sessionId, $this->templatePlan);

        $plan = $instance['plan_name'];

        $this->assertSame(
            ['building' => 1, 'plant' => 1, 'resource' => 1],
            $this->entityCount($plan),
            'chaque famille du modèle doit se retrouver sur l\'instance'
        );

        $this->assertSame(
            '0,1',
            (string) $this->conn->fetchOne("
                SELECT CONCAT(c.x, ',', c.y) FROM players p
                JOIN coords c ON c.id = p.coords_id
                WHERE p.player_type = 'resource' AND c.plan = ?
            ", [$plan]),
            'l\'arbre se pose sur la même case que dans le modèle'
        );

        $this->assertSame(
            'built',
            (string) $this->conn->fetchOne("
                SELECT b.build_state FROM buildings b
                JOIN players p ON p.id = b.player_id
                JOIN coords c ON c.id = p.coords_id
                WHERE p.player_type = 'building' AND c.plan = ?
            ", [$plan]),
            'un mur cloné porte son satellite buildings, à l\'état construit'
        );

        $this->assertSame(
            $templateRows,
            $this->entityCount($this->templatePlan),
            'le modèle ne doit ni perdre ni gagner d\'entités'
        );

        $this->assertSame(
            $this->conn->fetchOne(
                'SELECT id FROM coords WHERE plan = ? AND x = 0 AND y = 0 AND z = 0',
                [$plan]
            ),
            $instance['starting_coords_id'],
            'la position de départ est la case (0,0) de l\'instance'
        );

        $yield = $this->conn->fetchAssociative(
            'SELECT item, exhaust FROM race_harvest WHERE plan = ?',
            [$plan]
        );
        $this->assertNotFalse($yield, 'la surcharge de rendement du modèle doit suivre l\'instance');
        $this->assertSame('bois', $yield['item']);
        $this->assertSame(0, (int) $yield['exhaust'], 'l\'arbre d\'instance ne s\'épuise jamais');

        // La minimap du HUD vit de ces champs : la copie de la config doit
        // les transporter tels quels.
        $instanceConfig = (new \App\Service\PlanConfigService())->readFull($plan);
        $this->assertSame(
            [['z' => 0, 'z-name' => 'Niveau 0', 'visibleBoundsMinX' => -5, 'visibleBoundsMaxX' => 5, 'visibleBoundsMinY' => -5, 'visibleBoundsMaxY' => 5]],
            $instanceConfig['z_levels'] ?? null,
            'l\'instance hérite des z_levels du modèle (carte locale du HUD)'
        );
        $this->assertSame('eryn_dolen', $instanceConfig['bg'] ?? null, 'l\'instance hérite du fond de carte');
    }

    public function testATypeAbsentFromTheCatalogAbortsCreationLoudly(): void
    {
        $this->seedEntity('resource', 'type_fantome_' . bin2hex(random_bytes(3)), $this->seedTemplateTile(0, 1));
        $this->seedTemplateTile(0, 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/races catalog/');

        (new TutorialMapInstance($this->conn))
            ->createInstance($this->sessionId, $this->templatePlan);
    }

    /* ---------------------- helpers ---------------------- */

    private function seedTemplateTile(int $x, int $y): int
    {
        $this->conn->insert('coords', ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => $this->templatePlan]);

        return (int) $this->conn->lastInsertId();
    }

    private function seedEntity(string $type, string $race, int $coordsId): void
    {
        // La ligne minimale suffit : le réconciliateur ne relit du modèle
        // que la race et la case.
        $this->conn->insert('players', [
            'player_type' => $type,
            'race'        => $race,
            'name'        => $race,
            'coords_id'   => $coordsId,
        ]);
    }

    /** @return array<string, int> rows per entity family, keyed and sorted */
    private function entityCount(string $plan): array
    {
        $counts = $this->conn->fetchAllKeyValue("
            SELECT p.player_type, COUNT(*) FROM players p
            JOIN coords c ON c.id = p.coords_id
            WHERE c.plan = ? AND p.player_type IN ('resource', 'building', 'plant')
            GROUP BY p.player_type
        ", [$plan]);

        ksort($counts);

        return array_map('intval', $counts);
    }

    private function writePlanConfig(string $plan): void
    {
        $this->conn->insert('plans', [
            'slug'              => $plan,
            'name'              => 'Clone test',
            'player_visibility' => 0,
            'bg'                => 'eryn_dolen',
        ]);
        $this->conn->insert('plan_z_levels', [
            'plan_id'              => (int) $this->conn->lastInsertId(),
            'z'                    => 0,
            'name'                 => 'Niveau 0',
            'visible_bounds_min_x' => -5,
            'visible_bounds_max_x' => 5,
            'visible_bounds_min_y' => -5,
            'visible_bounds_max_y' => 5,
        ]);
        \App\Service\PlanService::forget($plan);
    }
}
