<?php

namespace Tests\Various;

use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * Ce qu'un collage de zone fait au push.
 *
 * Copier une zone dans Tiled et la coller sur elle-même envoie deux fois la
 * même chose sur la même case ; map_elements, dont la clé primaire est
 * (name, coords_id), refusait la seconde et emportait tout le push.
 *
 * Et un collage un peu large en apporte des milliers : ligne à ligne, chacune
 * valait une requête d'insertion et deux ou trois de plus pour naître sa case.
 * PHP y laissait sa limite de temps, sans corps de réponse — l'extension n'en
 * montrait qu'une « réponse illisible ».
 *
 * DB-backed ; skip propre quand la base est inaccessible, plan préfixé
 * plan_test_ nettoyé par clé naturelle.
 */
class TiledPushBatchingTest extends TestCase
{
    private const PLAN = 'plan_test_tiled_batch';

    protected function setUp(): void
    {
        $this->bootstrapOrSkip();
        $this->cleanupFixtures();

        \Classes\View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);
    }

    protected function tearDown(): void
    {
        $this->cleanupFixtures();
    }

    /** Deux fois la même chose sur la même case, c'est une fois. */
    public function testAZonePastedOverItselfIsPushedOnce(): void
    {
        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $result = $service->importPlan(self::PLAN, 0, [
            'elements' => [
                ['x' => 1, 'y' => 1, 'name' => 'eau'],
                ['x' => 1, 'y' => 1, 'name' => 'eau'],
                ['x' => 2, 'y' => 1, 'name' => 'eau'],
            ],
        ], $export['version']);

        $this->assertSame(2, $result['layers']['elements']['inserted'], 'le doublon ne compte pas deux fois');
        $this->assertSame(
            2,
            (int) $this->link()->fetchOne(
                'SELECT COUNT(*) FROM map_elements m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::PLAN]
            )
        );
    }

    /**
     * Un collage large arrive entier, cases comprises.
     *
     * Mille cinq cents lignes sur des cases qui n'existent pas : c'est le
     * geste qui mourait en silence, et c'est aussi ce qui vérifie que les
     * cases créées en lot portent bien les lignes qu'on y pose.
     */
    public function testAWideZoneLandsWholeWithItsCells(): void
    {
        $rows = [];
        for ($x = 10; $x < 60; $x++) {
            for ($y = 10; $y < 40; $y++) {
                $rows[] = ['x' => $x, 'y' => $y, 'name' => 'herbe'];
            }
        }

        $service = new TiledMapService();
        $export = $service->exportPlan(self::PLAN, 0);

        $result = $service->importPlan(self::PLAN, 0, ['tiles' => $rows], $export['version']);

        $this->assertSame(1500, $result['layers']['tiles']['inserted']);
        $this->assertSame(
            1500,
            (int) $this->link()->fetchOne(
                'SELECT COUNT(*) FROM map_tiles m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
                [self::PLAN]
            )
        );
        $this->assertSame(
            1501, // les 1500 collées, plus la coord d'amorce
            (int) $this->link()->fetchOne('SELECT COUNT(*) FROM coords WHERE plan = ?', [self::PLAN])
        );

        // Et le geste inverse : la zone repart d'un coup
        $export = $service->exportPlan(self::PLAN, 0);
        $result = $service->importPlan(self::PLAN, 0, ['tiles' => []], $export['version']);

        $this->assertSame(1500, $result['layers']['tiles']['deleted']);
    }

    private function cleanupFixtures(): void
    {
        $link = $this->link();

        foreach (['tiles', 'elements'] as $layer) {
            $link->executeStatement(
                "DELETE m FROM map_{$layer} m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?",
                [self::PLAN]
            );
        }

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
        $link->executeStatement('DELETE FROM plans WHERE slug = ?', [self::PLAN]);
        \App\Service\PlanService::forget(self::PLAN);
    }

    private function link(): Connection
    {
        global $link;

        return $link;
    }

    private function bootstrapOrSkip(): void
    {
        try {
            require_once __DIR__ . '/../../config/bootstrap.php';
            require_once __DIR__ . '/../../config/functions.php';
            require_once __DIR__ . '/../../config/constants.php';
        } catch (\Throwable $e) {
            $this->markTestSkipped('Legacy bootstrap failed: ' . $e->getMessage());
        }

        if (empty($_SERVER['DOCUMENT_ROOT'])) {
            $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
        }

        global $link;
        if (!isset($link) || !$link instanceof Connection) {
            $this->markTestSkipped('Global $link not populated by bootstrap.');
        }

        try {
            $link->executeQuery('SELECT 1 FROM coords LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('coords table unreachable: ' . $e->getMessage());
        }
    }
}
