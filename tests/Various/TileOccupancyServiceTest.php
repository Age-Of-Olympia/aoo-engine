<?php

namespace Tests\Various;

use App\Service\Map\TileOccupancyService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * La règle du PAS, enfin testable.
 *
 * Elle vivait dans `go.php`, en trois morceaux qui se refusaient chacun par
 * un `alert()` suivi d'un `exit()` — le test étalon du blocage a dû laisser
 * ce prédicat de côté pour cette raison, alors que c'est le plus important
 * des cinq.
 *
 * Ces cas fixent la règle extraite, y compris le point où elle CORRIGE le
 * comportement d'origine : une entité bloquait le pas seulement quand le plan
 * possédait un fichier JSON.
 */
#[Group('items-golden-master')]
class TileOccupancyServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_pas';

    protected function tearDown(): void
    {
        $link = $this->link;

        foreach (['map_resources', 'map_triggers'] as $layer) {
            $link->executeStatement(
                "DELETE l FROM {$layer} l JOIN coords c ON c.id = l.coords_id WHERE c.plan = ?",
                [self::PLAN]
            );
        }

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    private function service(): TileOccupancyService
    {
        return new TileOccupancyService();
    }

    public function testAnEmptyTileIsWalkable(): void
    {
        $this->assertNull($this->service()->stepRefusal($this->coordsId(0, 0), 1, true));
    }

    public function testAResourceBlocksTheStep(): void
    {
        $id = $this->coordsId(1, 0);
        $this->link->executeStatement(
            'INSERT INTO map_resources (name, coords_id, damages) VALUES (?, ?, -1)',
            ['arbre1', $id]
        );

        $this->assertSame('Quelque chose obstrue ton chemin.', $this->service()->stepRefusal($id, 1, true));
    }

    /** Une ressource ÉPUISÉE barre le passage comme une autre — d'origine. */
    public function testAnExhaustedResourceStillBlocks(): void
    {
        $id = $this->coordsId(2, 0);
        $this->link->executeStatement(
            'INSERT INTO map_resources (name, coords_id, damages) VALUES (?, ?, -2)',
            ['arbre1', $id]
        );

        $this->assertNotNull($this->service()->stepRefusal($id, 1, true));
    }

    public function testAForbiddenTriggerBlocksTheStep(): void
    {
        $id = $this->coordsId(3, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('forbidden', ?, '')",
            [$id]
        );

        $this->assertSame('Impossible de se rendre à cet endroit.', $this->service()->stepRefusal($id, 1, true));
    }

    /** Un autre déclencheur — un téléporteur — ne barre rien. */
    public function testANonForbiddenTriggerDoesNotBlock(): void
    {
        $id = $this->coordsId(4, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('tp', ?, '')",
            [$id]
        );

        $this->assertNull($this->service()->stepRefusal($id, 1, true));
    }

    /**
     * LE correctif : une structure bloque même quand le plan n'a pas de JSON.
     *
     * `go.php` ne construisait sa sous-requête d'entités que dans le
     * `if ($planJson = …)`. Sur les vingt plans sans fichier, on traversait
     * donc les murs — 2 819 entités concernées en production.
     */
    public function testAStructureBlocksEvenWhenCharactersAreHidden(): void
    {
        $this->requireBuildingsOrSkip();
        $this->placeStructure('mur_pierre', 5, 0, self::PLAN);
        $id = $this->coordsId(5, 0);

        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, true),
            'un mur bloque, plan visible'
        );
        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, false),
            'et il bloque AUSSI quand les personnages sont cachés — c\'est le décor'
        );
    }

    /**
     * Un personnage, lui, suit la visibilité : invisible, il ne barre rien.
     * C'est l'autre moitié de « bloquer, c'est être vu » — et ce qui interdit
     * le correctif naïf, qui aurait produit des murs invisibles.
     */
    public function testACharacterOnlyBlocksWhenVisible(): void
    {
        $other = $this->createRealPlayer('GmObstacle');
        $id = $this->coordsId(6, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $other->id]);

        $this->assertNotNull(
            $this->service()->stepRefusal($id, 1, true),
            'personnage visible : il barre'
        );
        $this->assertNull(
            $this->service()->stepRefusal($id, 1, false),
            'personnages cachés sur ce plan : il ne barre plus'
        );
    }

    /** Le mode discret retire aussi du passage. */
    public function testAnInvisibleCharacterDoesNotBlock(): void
    {
        $other = $this->createRealPlayer('GmDiscret');
        $id = $this->coordsId(7, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $other->id]);
        $this->link->executeStatement(
            "INSERT INTO players_options (player_id, name) VALUES (?, 'invisibleMode')",
            [$other->id]
        );

        $this->assertNull($this->service()->stepRefusal($id, 1, true));
    }

    /** On ne se bloque pas soi-même. */
    public function testTheMoverDoesNotBlockHimself(): void
    {
        $me = $this->createRealPlayer('GmMoi');
        $id = $this->coordsId(8, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $me->id]);

        $this->assertNull($this->service()->stepRefusal($id, (int) $me->id, true));
    }

    /**
     * Les trois verbes ne répondent PAS à la même question, et c'est voulu.
     *
     * Un déclencheur — ici un téléporteur — rend la case impropre à
     * l'atterrissage, laisse passer le pas, et n'empêche pas de bâtir. Cette
     * dernière asymétrie n'a été décidée par personne : elle tombe d'une
     * absence de filtre dans `place()`. Elle est épinglée telle quelle,
     * l'aligner étant un changement de règle et non une extraction.
     */
    public function testTheThreeVerbsAnswerDifferentQuestions(): void
    {
        $id = $this->coordsId(9, 0);
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('tp', ?, '')",
            [$id]
        );

        $service = $this->service();

        $this->assertNull($service->stepRefusal($id, 1, true), 'on marche sur un téléporteur');
        $this->assertFalse($service->isVacant($id), 'on n\'y atterrit pas');
        $this->assertNull($service->buildRefusal($id), 'et on peut y bâtir — divergence gelée');
    }

    /**
     * La forme en lot répond exactement comme la forme unitaire.
     *
     * C'est ce que le damier interroge pour marquer ses cases — six cent
     * vingt-cinq d'un coup. Si les deux formes divergeaient, l'écran
     * annoncerait un refus que `go.php` n'appliquerait pas, ou l'inverse :
     * précisément le défaut que la déduction en JavaScript entretenait.
     */
    public function testTheBatchFormAgreesWithTheSingleOne(): void
    {
        $free = $this->coordsId(11, 0);
        $withResource = $this->coordsId(12, 0);
        $forbidden = $this->coordsId(13, 0);

        $this->link->executeStatement(
            'INSERT INTO map_resources (name, coords_id, damages) VALUES (?, ?, -1)',
            ['arbre1', $withResource]
        );
        $this->link->executeStatement(
            "INSERT INTO map_triggers (name, coords_id, params) VALUES ('forbidden', ?, '')",
            [$forbidden]
        );

        $service = $this->service();
        $ids = [$free, $withResource, $forbidden];
        $batch = $service->blockedForStep($ids, 1, true);

        $this->assertSame(
            [$withResource, $forbidden],
            array_values(array_filter($ids, static fn (int $id): bool => isset($batch[$id]))),
            'seules les deux cases occupées ressortent du lot'
        );

        foreach ($ids as $id) {
            $this->assertSame(
                $service->stepRefusal($id, 1, true),
                $batch[$id] ?? null,
                'même verdict, même motif, pour la case '. $id
            );
        }
    }

    /** Un lot vide ne pose aucune question à la base. */
    public function testTheBatchFormAcceptsAnEmptyList(): void
    {
        $this->assertSame([], $this->service()->blockedForStep([], 1, true));
    }

    /** Une case vraiment vide l'est pour les trois. */
    public function testAnEmptyTileSatisfiesTheThreeVerbs(): void
    {
        $id = $this->coordsId(10, 0);
        $service = $this->service();

        $this->assertNull($service->stepRefusal($id, 1, true));
        $this->assertTrue($service->isVacant($id));
        $this->assertNull($service->buildRefusal($id));
    }

    /** La visibilité de plan se lit comme au rendu : pas de JSON = cachés. */
    public function testCharacterVisibilityMatchesTheRenderRule(): void
    {
        $this->assertFalse(TileOccupancyService::charactersVisibleOn(null), 'pas de JSON : cachés');
        $this->assertTrue(TileOccupancyService::charactersVisibleOn((object) []), 'JSON sans la clé : visibles');
        $this->assertTrue(
            TileOccupancyService::charactersVisibleOn((object) ['player_visibility' => true])
        );
        $this->assertFalse(
            TileOccupancyService::charactersVisibleOn((object) ['player_visibility' => false])
        );
    }
}
