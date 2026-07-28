<?php

namespace Tests\Various;

use App\Service\Map\EntityCellService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * L'emprise : les cases qu'une entité occupe, et comment elles s'y posent.
 *
 * L3 a posé la table et l'a remplie à l'identique de l'existant — une case
 * par entité, celle de `players.coords_id`, en rôle d'ancre. L'occupation la
 * lit désormais, et ce lot-ci la remplit vraiment : `syncFootprint()` étend
 * l'entité sur toute la découpe déclarée de son type.
 *
 * Deux familles de cas, donc.
 *
 * L'invariant d'abord, tant qu'il est simple à énoncer : toute entité posée a
 * exactement UNE ancre, à `players.coords_id`, et une emprise ne la lui
 * reprend pas. C'est sur elle que tout le reste s'appuie.
 *
 * La tenue de la table ensuite. Une table mal tenue ment sans bruit :
 * `syncAnchor()` est appelé derrière chaque écriture de `players.coords_id`,
 * `drift()` dit ce qui a échappé, et reposer une figure rétrécie rend les
 * cases abandonnées — sans quoi une emprise ne ferait que grandir, et
 * corriger une erreur en ajouterait une.
 */
#[Group('items-golden-master')]
class EntityCellServiceTest extends LegacyPlayerFixtureTestCase
{
    private const PLAN = 'plan_test_emprise';

    /** @var list<string> les types dont un cas a déclaré la découpe */
    private array $declaredTypes = [];

    protected function tearDown(): void
    {
        $link = $this->link;

        foreach ($this->declaredTypes as $type) {
            $link->executeStatement('DELETE FROM entity_type_footprints WHERE type_name = ?', [$type]);
        }

        $this->declaredTypes = [];

        /* Les cases partent avec les entités (ON DELETE CASCADE), mais le
         * ménage des coords vient après : la contrainte sur coords est en
         * RESTRICT, une case encore référencée bloquerait la suppression. */
        $link->executeStatement(
            'DELETE ec FROM entity_cells ec JOIN coords c ON c.id = ec.coords_id WHERE c.plan = ?',
            [self::PLAN]
        );

        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);
    }

    private function coordsId(int $x, int $y): int
    {
        return (int) View::get_coords_id(
            (object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]
        );
    }

    private function service(): EntityCellService
    {
        return new EntityCellService();
    }

    /** Une entité posée a une ancre, à la case que `players` déclare. */
    public function testAPlacedEntityGetsItsAnchor(): void
    {
        $player = $this->createRealPlayer('GmEmprise');
        $id = $this->coordsId(0, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);

        $this->service()->syncAnchor((int) $player->id);

        $cells = $this->service()->cellsOf((int) $player->id);
        $this->assertCount(1, $cells);
        $this->assertSame($id, (int) $cells[0]['coords_id']);
        $this->assertSame('anchor', $cells[0]['role']);
        $this->assertSame(self::PLAN, $cells[0]['plan'], 'les colonnes chaudes sont recopiées');
    }

    /**
     * L'ancre SUIT le pas — elle ne s'ajoute pas.
     *
     * La clé primaire étant (player_id, coords_id), se contenter d'insérer
     * laisserait deux ancres derrière chaque déplacement.
     */
    public function testTheAnchorFollowsAndDoesNotAccumulate(): void
    {
        $player = $this->createRealPlayer('GmMarcheur');
        $service = $this->service();

        foreach ([[0, 1], [0, 2], [3, 3]] as [$x, $y]) {
            $id = $this->coordsId($x, $y);
            $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);
            $service->syncAnchor((int) $player->id);

            $cells = $service->cellsOf((int) $player->id);
            $this->assertCount(1, $cells, 'une seule ancre après un pas en ('. $x .','. $y .')');
            $this->assertSame($id, (int) $cells[0]['coords_id']);
            $this->assertSame($x, (int) $cells[0]['x']);
            $this->assertSame($y, (int) $cells[0]['y']);
        }
    }

    /** Rappeler la synchronisation ne change rien : elle est idempotente. */
    public function testSyncingTwiceChangesNothing(): void
    {
        $player = $this->createRealPlayer('GmIdem');
        $id = $this->coordsId(4, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);

        $service = $this->service();
        $service->syncAnchor((int) $player->id);
        $service->syncAnchor((int) $player->id);

        $this->assertCount(1, $service->cellsOf((int) $player->id));
    }

    /**
     * Plusieurs entités peuvent occuper la MÊME case, et c'est voulu.
     *
     * L'empilement sert aux animateurs et aux administrateurs. Une clé
     * primaire sur `coords_id` seul l'aurait interdit — et aurait cassé la
     * superposition décor + déclencheur, qui est l'usage normal du monde.
     */
    public function testTwoEntitiesMayShareOneTile(): void
    {
        $one = $this->createRealPlayer('GmEmpile1');
        $two = $this->createRealPlayer('GmEmpile2');
        $id = $this->coordsId(5, 5);

        foreach ([$one, $two] as $p) {
            $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $p->id]);
            $this->service()->syncAnchor((int) $p->id);
        }

        $occupants = array_column($this->service()->occupantsOf($id), 'player_id');
        $this->assertContains((int) $one->id, array_map('intval', $occupants));
        $this->assertContains((int) $two->id, array_map('intval', $occupants));
    }

    /**
     * Une entité a TOUJOURS une case — le schéma l'impose.
     *
     * `players.coords_id` est NOT NULL et porte une clé étrangère vers
     * `coords` : la détacher est impossible, et `syncAnchor()` n'a donc pas à
     * gérer une entité posée nulle part. Le cas épinglé ici est l'autre, le
     * seul atteignable : une entité qui n'existe pas.
     */
    public function testSyncingAnUnknownEntityRefusesInsteadOfGuessing(): void
    {
        $absent = -999123;

        $this->assertFalse($this->service()->syncAnchor($absent), 'le refus est explicite');
        $this->assertSame([], $this->service()->cellsOf($absent));
    }

    /**
     * La dérive se voit, et se répare.
     *
     * C'est le filet de ce lot : tant que rien ne lit l'emprise, une ancre
     * perdue ne casse rien à l'écran — elle ferait juste démarrer L4 d'une
     * carte fausse.
     */
    public function testDriftIsVisibleAndRepairable(): void
    {
        $player = $this->createRealPlayer('GmDerive');
        $id = $this->coordsId(7, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$id, $player->id]);
        $this->service()->syncAnchor((int) $player->id);

        /* Une écriture qui aurait oublié d'appeler le service */
        $elsewhere = $this->coordsId(8, 0);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$elsewhere, $player->id]);

        $drifted = array_column($this->service()->drift(), 'player_id');
        $this->assertContains((int) $player->id, array_map('intval', $drifted), 'la dérive est signalée');

        $this->service()->reconcile();

        $drifted = array_column($this->service()->drift(), 'player_id');
        $this->assertNotContains((int) $player->id, array_map('intval', $drifted), 'et réparée');
        $this->assertSame($elsewhere, (int) $this->service()->cellsOf((int) $player->id)[0]['coords_id']);
    }

    /**
     * L'emprise s'en va avec l'entité, et la case ne s'en va pas sous elle.
     *
     * Les deux règles sont dans le schéma plutôt que dans du code : une
     * entité supprimée emporte ses cases (CASCADE), et une case encore
     * occupée refuse de disparaître (RESTRICT) — sans quoi on obtiendrait
     * des emprises pointant dans le vide.
     *
     * Vérifié sur le schéma et non par une suppression : retirer une ligne
     * `players` demande de démonter au préalable une dizaine de tables
     * satellites, ce qui n'éprouverait que MariaDB.
     */
    /**
     * Déclare une découpe pour un type, et la retire au démontage.
     *
     * @param array<int, array{0:int,1:int}> $offsets
     * @param array<int, string> $roles
     */
    private function declareFootprint(string $type, int $w, int $h, array $offsets, array $roles = []): void
    {
        $this->declaredTypes[] = $type;
        (new \App\Service\Map\EntityTypeFootprintService($this->link))
            ->declare($type, $w, $h, $offsets, $roles);
    }

    /**
     * L'emprise se pose depuis la découpe du type.
     *
     * Sans cela, un animateur pouvait dessiner une figure de 3×3 dans la page
     * d'administration sans que rien ne l'écrive : la découpe restait un
     * dessin, et l'occupation ne voyait qu'une case.
     */
    public function testAFootprintSpreadsTheEntityOverItsCells(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 40, 40, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 2, [
            0 => [0, 0], 1 => [1, 0], 2 => [0, -1], 3 => [1, -1],
        ]);

        $this->assertSame(3, $this->service()->syncFootprint($wall), 'trois cases autour de l\'ancre');

        $held = array_map(
            static fn(array $cell): string => $cell['x'] . ',' . $cell['y'],
            $this->service()->cellsOf($wall)
        );
        sort($held);

        $this->assertSame(['40,39', '40,40', '41,39', '41,40'], $held);
    }

    /** L'ancre garde son rôle : une entité n'en a jamais deux. */
    public function testTheAnchorKeepsItsRoleWithinAFootprint(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 42, 42, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);
        $this->service()->syncFootprint($wall);

        $roles = array_count_values(array_column($this->service()->cellsOf($wall), 'role'));

        $this->assertSame(1, $roles['anchor'] ?? 0, 'une ancre, et une seule');
        $this->assertSame(1, $roles[\App\Service\Map\EntityCellService::ROLE_PART] ?? 0);
    }

    /**
     * Un morceau marqué garde son rôle ; les autres n'en prennent aucun.
     *
     * `part` est l'absence d'avis : c'est le type qui tranche le passage,
     * comme pour l'ancre. Résoudre le rôle à l'écriture aurait figé une
     * réponse qui change avec `races.blocks_passage`.
     */
    public function testADeclaredRoleSurvivesWhileTheRestStaysUndecided(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 44, 44, self::PLAN);

        $this->declareFootprint(
            'mur_pierre',
            3,
            1,
            [0 => [0, 0], 1 => [1, 0], 2 => [2, 0]],
            [1 => 'door']
        );

        $this->service()->syncFootprint($wall);

        $byCell = [];

        foreach ($this->service()->cellsOf($wall) as $cell) {
            $byCell[$cell['x'] . ',' . $cell['y']] = $cell['role'];
        }

        $this->assertSame('door', $byCell['45,44'], 'le morceau marqué garde son rôle');
        $this->assertSame(\App\Service\Map\EntityCellService::ROLE_PART, $byCell['46,44']);
    }

    /**
     * Reposer une figure rétrécie retire les cases devenues inutiles.
     *
     * C'est ce qui rend une correction visible : sans cela l'emprise ne
     * ferait que grandir, et un animateur qui rectifie une erreur en
     * ajouterait une.
     */
    public function testShrinkingAFootprintReleasesTheCellsItDropped(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 46, 46, self::PLAN);

        $this->declareFootprint('mur_pierre', 3, 1, [0 => [0, 0], 1 => [1, 0], 2 => [2, 0]]);
        $this->service()->syncFootprint($wall);
        $this->assertCount(3, $this->service()->cellsOf($wall));

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);
        $this->service()->syncFootprint($wall);

        $this->assertCount(2, $this->service()->cellsOf($wall), 'la case abandonnée est rendue');
    }

    /** Un type sans découpe ne tient qu'une case, comme avant. */
    public function testATypeWithoutAFootprintKeepsASingleCell(): void
    {
        $this->requireBuildingsOrSkip();
        $wall = $this->placeStructure('mur_pierre', 48, 48, self::PLAN);

        $this->assertSame(0, $this->service()->syncFootprint($wall));
        $this->assertCount(1, $this->service()->cellsOf($wall));
    }

    /** Corriger une figure reprend les exemplaires déjà sur la carte. */
    public function testCorrectingAFootprintTakesUpThePlacedCopies(): void
    {
        $this->requireBuildingsOrSkip();
        $first = $this->placeStructure('mur_pierre', 50, 50, self::PLAN);
        $second = $this->placeStructure('mur_pierre', 55, 55, self::PLAN);

        $this->declareFootprint('mur_pierre', 2, 1, [0 => [0, 0], 1 => [1, 0]]);

        $reapplied = $this->service()->reapplyForType('mur_pierre');

        $this->assertGreaterThanOrEqual(2, $reapplied, 'les deux exemplaires au moins');
        $this->assertCount(2, $this->service()->cellsOf($first));
        $this->assertCount(2, $this->service()->cellsOf($second));
    }

    public function testTheSchemaCarriesTheLifecycleRules(): void
    {
        $rules = [];

        foreach ($this->link->fetchAllAssociative(
            "SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE
               FROM information_schema.REFERENTIAL_CONSTRAINTS rc
              WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'entity_cells'"
        ) as $row) {
            $rules[$row['CONSTRAINT_NAME']] = $row['DELETE_RULE'];
        }

        $this->assertSame('CASCADE', $rules['fk_entity_cells_player'] ?? null, 'l\'entité emporte ses cases');
        $this->assertSame('RESTRICT', $rules['fk_entity_cells_coords'] ?? null, 'une case occupée ne disparaît pas');
    }
}
