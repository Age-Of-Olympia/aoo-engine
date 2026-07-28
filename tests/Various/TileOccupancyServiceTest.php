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

        /* Les cases posées à la main par ces cas : la contrainte sur `coords`
         * est en RESTRICT, une case encore référencée bloquerait le ménage. */
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    /**
     * Donne à une entité une case de plus, avec le rôle voulu.
     *
     * `EntityCellService` ne sait poser que l'ancre — c'est tout ce que L3
     * demandait. Les emprises viendront de la conversion des décors ; d'ici
     * là, ces cas les écrivent à la main pour fixer ce que l'occupation doit
     * en faire.
     */
    private function giveCell(int $entityId, int $x, int $y, string $role): int
    {
        $coordsId = $this->coordsId($x, $y);

        $this->link->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role)",
            [$entityId, $coordsId, self::PLAN, $x, $y, $role]
        );

        return $coordsId;
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
    /**
     * LE point du lot : une entité barre TOUTES les cases qu'elle tient.
     *
     * L'occupation se lisait sur `players.coords_id` — une entité, une case.
     * Un bâtiment de 2×2 ne bloquait donc qu'un quart de lui-même, et on lui
     * entrait dedans par trois côtés.
     */
    public function testAnEntityBlocksEveryTileItHolds(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 20, 0, self::PLAN);

        $spread = $this->giveCell($wall, 21, 0, 'anchor');

        $this->assertNotNull(
            $this->service()->stepRefusal($spread, 1, true),
            'la seconde case du mur barre le chemin comme la première'
        );
    }

    /**
     * Le rôle de la case prime sur la nature du type.
     *
     * C'est ce qui permet à la base d'un décor de barrer le chemin pendant que
     * sa partie haute se traverse, et à une porte de s'ouvrir dans un mur qui
     * bloque partout ailleurs. Sans cela, une emprise ne saurait dire qu'une
     * seule chose de toutes ses cases.
     */
    public function testAnOpenCellIsWalkableThroughABlockingType(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 22, 0, self::PLAN);

        $doorway = $this->giveCell($wall, 23, 0, 'door');

        $this->assertNull(
            $this->service()->stepRefusal($doorway, 1, true),
            'une porte se franchit, quoi que fasse le mur qui la porte'
        );
    }

    /**
     * Et réciproquement : une case marquée bloquante barre le chemin même
     * quand son type se traverse. C'est ce que la page d'administration des
     * décors enregistre — un décor est franchissable par nature, sauf là où
     * un animateur a dit le contraire.
     */
    public function testABlockingCellStopsAPassableType(): void
    {
        $this->requireBuildingsOrSkip();
        $decor = $this->placeStructure('mur_pierre', 24, 0, self::PLAN);

        /* Aucune structure franchissable n'est seedée, et ce cas ne peut pas
         * s'en remettre au contenu du catalogue sous peine d'être ignoré
         * partout. On rend donc le type franchissable le temps du cas, et on
         * le repose.
         *
         * Par le domaine et non en SQL : Doctrine garde la race en mémoire, et
         * une écriture brute lui échapperait — le service continuerait de lire
         * l'ancienne valeur. */
        $entityManager = \App\Entity\EntityManagerFactory::getEntityManager();
        $race = $entityManager->getRepository(\App\Entity\Race::class)->findOneBy(['name' => 'mur_pierre']);

        if ($race === null) {
            $this->markTestSkipped('type mur_pierre absent du catalogue.');
        }

        $race->setBlocksPassage(false);
        $entityManager->flush();

        try {
            $this->assertNull(
                $this->service()->stepRefusal($this->coordsId(24, 0), 1, true),
                'le type se traverse, sans quoi le cas ne prouverait rien'
            );

            $solid = $this->giveCell($decor, 25, 0, 'block');

            $this->assertNotNull(
                $this->service()->stepRefusal($solid, 1, true),
                'la case dite bloquante barre le chemin malgré son type'
            );
        } finally {
            $race->setBlocksPassage(true);
            $entityManager->flush();
        }
    }

    /**
     * Les trois verbes tiennent la même emprise.
     *
     * Chacun demandait à `players.coords_id` si une entité était là. On
     * pouvait donc bâtir dans le corps d'un bâtiment dont on ne pouvait pas
     * franchir la façade — trois réponses pour une même question.
     */
    public function testTheThreeVerbsAgreeOnTheWholeFootprint(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 30, 0, self::PLAN);

        $body = $this->giveCell($wall, 31, 0, 'anchor');
        $service = $this->service();

        $this->assertNotNull($service->stepRefusal($body, 1, true), 'on n\'y entre pas');
        $this->assertFalse($service->isVacant($body), 'on n\'y atterrit pas');
        $this->assertSame(
            'Case occupée par une entité.',
            $service->buildRefusal($body),
            'et on n\'y bâtit pas'
        );
    }

    /**
     * Le rôle ne perce pas la discrétion : bloquer, c'est être vu.
     *
     * Un rôle `block` prime sur la NATURE du type, pas sur la visibilité —
     * sinon une emprise trahirait un personnage discret, et la règle que ce
     * service porte depuis son extraction ne tiendrait plus.
     */
    public function testABlockingCellDoesNotBetrayAHiddenCharacter(): void
    {
        $ghost = $this->createRealPlayer('GmOmbre');
        $cell = $this->giveCell((int) $ghost->id, 28, 0, 'block');

        $this->link->executeStatement(
            "INSERT INTO players_options (player_id, name) VALUES (?, 'invisibleMode')",
            [$ghost->id]
        );

        $this->assertNull(
            $this->service()->stepRefusal($cell, 1, true),
            'discret : sa case bloquante ne le dénonce pas'
        );
    }

    /**
     * L'ancre reste lue sur `players.coords_id`, même sans case.
     *
     * Une entité déplacée sans que `syncAnchor()` soit appelé garde ses cases
     * à son ancienne position. Les deux sources s'ajoutent précisément pour
     * cela : une dérive ne peut pas rendre un mur traversable, elle peut au
     * pire le faire bloquer à deux endroits — ce qui se voit et se répare.
     */
    public function testADriftedEntityStillBlocksWhereItStands(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 26, 0, self::PLAN);

        $moved = $this->coordsId(27, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$moved, $wall]);

        $this->assertNotNull(
            $this->service()->stepRefusal($moved, 1, true),
            'là où le mur se trouve vraiment'
        );
        $this->assertNotNull(
            $this->service()->stepRefusal($this->coordsId(26, 0), 1, true),
            'et là où ses cases le croient encore : jamais moins que la vérité'
        );
    }

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
