<?php

namespace Tests\Action;

use App\Factory\ActionFactory;
use App\Action\OutcomeInstruction\ResourceOutcomeInstruction;
use App\Factory\PlayerFactory;
use App\Service\ActionExecutorService;
use App\Service\ResourceService;
use Classes\View;
use PHPUnit\Framework\Attributes\Group;
use Tests\Action\Mock\ScriptedDice;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * `fouiller` de bout en bout — test étalon au sens du glossaire : une
 * photographie du comportement actuel, figée avant que le modèle de
 * ressources ne bouge.
 *
 * La règle du jeu est une récolte de ZONE : l'action ne vise personne, elle
 * compte les lignes récoltables des neuf cases accessibles, les agrège par
 * rendement, et tire 1dN où N est ce compte. Le rendement est donc une pure
 * fonction de la géométrie — ce que ce test épingle case par case, y compris
 * les positions à N élevé que toute règle « un occupant par case » écraserait.
 *
 * Le monde de fixture est un plan dédié, avec son propre JSON de biomes :
 * gaia ne doit pas être touché, et les taux réels ne doivent pas décider du
 * résultat des assertions.
 */
#[Group('items-golden-master')]
class FouillerGoldenMasterTest extends LegacyPlayerFixtureTestCase
{
    /** Haut de la plage des ressources : jamais un identifiant converti. */
    private const FIXTURE_ID_FLOOR = 59990000;

    private int $fixtureResources = 0;

    private const PLAN = 'plan_test_fouille';

    /** @var array<string, array<string, mixed>|null> rendement des types touchés, avant */
    private array $typeYieldBackup = [];

    protected function tearDown(): void
    {
        ResourceOutcomeInstruction::setDiceForTests(null);
        ResourceService::setDiceForTests(null);

        $link = $this->link;

        /* Le catalogue est partagé : rendre chaque type comme on l'a trouvé. */
        foreach ($this->typeYieldBackup as $type => $before) {
            $link->executeStatement(
                'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE name = ?',
                [$before['harvest_item'] ?? null, $before['harvest_exhaust'] ?? null, $before['harvest_regrow'] ?? null, $type]
            );
        }
        $this->typeYieldBackup = [];

        /* Les ressources de fixture sont des entités : leur satellite et leur
           emprise d'abord, les deux clés étant intransigeantes. */
        foreach ($link->fetchFirstColumn(
            "SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND p.player_type = 'resource'",
            [self::PLAN]
        ) as $resourceId) {
            $link->executeStatement('DELETE FROM resources WHERE player_id = ?', [(int) $resourceId]);
            $link->executeStatement('DELETE FROM entity_cells WHERE player_id = ?', [(int) $resourceId]);
            \App\Service\BuildingService::deleteEntityRows($link, (int) $resourceId);
        }

        /* Le parent D'ABORD : il supprime les joueurs de fixture, qui sont
         * posés sur les cases de ce plan. Les cases ne peuvent partir
         * qu'ensuite, sinon players_ibfk_1 refuse. Il remet aussi $this->link
         * à null — d'où la référence gardée au-dessus. */
        parent::tearDown();

        $link->executeStatement('DELETE FROM coords WHERE plan = ?', [self::PLAN]);

        if (file_exists($this->jsonPath())) {
            unlink($this->jsonPath());
        }
        json()->forget('plans', self::PLAN);
    }

    /**
     * Même résolution que Classes\Json::decode() — racine du dépôt, pas
     * DOCUMENT_ROOT, qui est vide en ligne de commande.
     */
    private function jsonPath(): string
    {
        return dirname(__DIR__, 2) . '/datas/private/plans/' . self::PLAN . '.json';
    }

    /**
     * Écrit le JSON de plan et vide la mémoïsation : `json()` garde ses
     * décodages, un fichier posé après un premier accès resterait invisible.
     *
     * @param list<array<string, mixed>> $biomes
     */
    private function writePlan(array $biomes): void
    {
        file_put_contents($this->jsonPath(), json_encode([
            'name' => 'Plan de test fouille',
            'biomes' => $biomes,
        ], JSON_UNESCAPED_UNICODE));

        json()->forget('plans', self::PLAN);

        /* Le monde se règle en base : le jeu ne lit plus le JSON de plan pour
           les rendements, il lit `race_harvest`. Le JSON reste la source du
           VERSEMENT, donc la fixture verse ce qu'elle vient d'écrire. */
        $this->link->executeStatement('DELETE FROM race_harvest WHERE plan = ?', [self::PLAN]);
        (new \App\Service\Map\HarvestCatalogService($this->link))->seed();
    }

    /**
     * Pose une ressource récoltable et rend l'id de sa case.
     *
     * Une ENTITÉ, comme le monde en porte depuis la conversion : `damages` a
     * disparu, l'épuisement vit sur le satellite `resources`.
     */
    private function putResource(string $name, int $x, int $y, int $damages = -1): int
    {
        $coordsId = (int) View::get_coords_id((object) ['x' => $x, 'y' => $y, 'z' => 0, 'plan' => self::PLAN]);
        $id = self::FIXTURE_ID_FLOOR + $this->fixtureResources++;

        $this->link->executeStatement(
            "INSERT INTO players (id, name, race, coords_id, player_type)
             VALUES (?, ?, ?, ?, 'resource')",
            [$id, ucfirst($name), $name, $coordsId]
        );
        $this->link->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (?, ?, ?, 0, ?, ?, 0, 'block')",
            [$id, $coordsId, self::PLAN, $x, $y]
        );

        if ($damages === -2) {
            (new \App\Service\Map\ResourceStateService($this->link))->exhaust([$id]);
        }

        return $coordsId;
    }

    private function harvesterAtOrigin(string $prefix): \Classes\Player
    {
        $player = $this->createRealPlayer($prefix);
        $coordsId = View::get_coords_id((object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => self::PLAN]);
        $this->link->executeStatement('UPDATE players SET coords_id = ? WHERE id = ?', [$coordsId, $player->id]);
        /* The harvester changes PLAN: without a resync its cell stays on
         * gaia and every distance about it becomes infinite. */
        (new \App\Service\Map\EntityCellService($this->link))->syncCells((int) $player->id);
        $player->getCoords();
        $player->get_caracs();

        return $player;
    }

    private function actionOrSkip(): \App\Interface\ActionInterface
    {
        $action = ActionFactory::getAction('fouiller');
        if ($action === null) {
            $this->markTestSkipped("catalogue d'actions non seedé (pas de ligne 'fouiller').");
        }

        return $action;
    }

    /**
     * Le dé de récolte est un 1dN où N est le NOMBRE de cases voisines
     * portant ce rendement — pas une caractéristique du joueur, pas une
     * propriété du type. Trois voisines : le joueur ramasse entre 1 et 3.
     */
    public function testYieldIsOneDieOfTheNeighbouringCount(): void
    {
        $this->writePlan([['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]]);
        $bois = $this->itemOrSkip('bois');

        $this->putResource('arbre1', 0, 1);
        $this->putResource('arbre1', 1, 0);
        $this->putResource('arbre1', 1, 1);

        $player = $this->harvesterAtOrigin('GmFouille3');
        $before = $bois->get_n($player);

        // 1d3 rend 3 ; le second jet est celui de l'épuisement (d100).
        ResourceOutcomeInstruction::setDiceForTests(new ScriptedDice([[3]]));
        ResourceService::setDiceForTests(new ScriptedDice([[100], [100], [100]]));

        $results = (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $this->assertFalse($results->isBlocked(), 'trois ressources autour : la fouille doit passer');
        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($before + 3, $bois->get_n($fresh), 'le joueur ramasse le résultat du 1d3');
    }

    /**
     * Une seule voisine : le dé est un 1d1, donc le rendement est de 1,
     * toujours. C'est le cas MÉDIAN du monde réel.
     */
    public function testASingleNeighbourAlwaysYieldsExactlyOne(): void
    {
        $this->writePlan([['wall' => 'pierre1', 'ressource' => 'pierre', 'exhaust' => 75, 'regrow' => 20]]);
        $pierre = $this->itemOrSkip('pierre');

        $this->putResource('pierre1', 0, 1);

        $player = $this->harvesterAtOrigin('GmFouille1');
        $before = $pierre->get_n($player);

        ResourceOutcomeInstruction::setDiceForTests(new ScriptedDice([[1]]));
        ResourceService::setDiceForTests(new ScriptedDice([[100]]));

        (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($before + 1, $pierre->get_n($fresh));
    }

    /**
     * Les cases voisines s'AGRÈGENT par rendement, pas par type de ressource :
     * deux essences d'arbre qui donnent toutes deux du bois font un 1d2, pas
     * deux jets de 1d1.
     */
    public function testTwoWallTypesSharingAYieldAggregateIntoOneDie(): void
    {
        $this->writePlan([
            ['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20],
            ['wall' => 'arbre2', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20],
        ]);
        $bois = $this->itemOrSkip('bois');

        $this->putResource('arbre1', 0, 1);
        $this->putResource('arbre2', 1, 0);

        $player = $this->harvesterAtOrigin('GmFouilleMix');
        $before = $bois->get_n($player);

        ResourceOutcomeInstruction::setDiceForTests(new ScriptedDice([[2]]));
        ResourceService::setDiceForTests(new ScriptedDice([[100], [100]]));

        (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($before + 2, $bois->get_n($fresh), 'un seul dé pour les deux essences');
    }

    /**
     * Le type répond même quand le plan ne dit rien de lui.
     *
     * C'était l'inverse : une ressource absente du biome du plan était
     * invisible à la récolte — les lignes « inertes ». Arbitrage du lead :
     * ajouter un type doit suffire à ce qu'il rende quelque chose, sans le
     * déclarer plan par plan.
     */
    public function testATypeWithADefaultIsHarvestableThoughThePlanIgnoresIt(): void
    {
        $this->setTypeYield('arbre1', 'bois', 75, 20);
        $this->writePlan([['wall' => 'pierre1', 'ressource' => 'pierre', 'exhaust' => 75, 'regrow' => 20]]);

        $this->putResource('arbre1', 0, 1);

        $player = $this->harvesterAtOrigin('GmFouilleDefaut');

        ResourceOutcomeInstruction::setDiceForTests(new ScriptedDice([[2]]));
        ResourceService::setDiceForTests(new ScriptedDice([[100], [100]]));

        $results = (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $this->assertFalse($results->isBlocked(), 'le type porte son rendement : la case se fouille');
    }

    /**
     * Rien sur le type, rien sur le plan : la case ne se fouille pas.
     *
     * Ce que garantissait l'ancien test, là où il vaut encore — un type
     * réellement muet, et non un type que le plan a seulement oublié.
     */
    public function testATypeMuteEverywhereIsNotHarvestable(): void
    {
        $this->setTypeYield('arbre1', null, null, null);
        $this->writePlan([['wall' => 'pierre1', 'ressource' => 'pierre', 'exhaust' => 75, 'regrow' => 20]]);

        $this->putResource('arbre1', 0, 1);

        $player = $this->harvesterAtOrigin('GmFouilleInerte');
        $maxA = (int) $player->caracs->a;

        $results = (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $this->assertTrue($results->isBlocked(), 'sans rendement nulle part, la case ne se fouille pas');
        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($maxA, $fresh->getRemaining('a'), 'une fouille refusée ne coûte pas de point d\'action');
    }

    /**
     * Règle le rendement d'un TYPE le temps du test, et note son état d'avant
     * pour le rendre : le catalogue est partagé, une fixture ne le garde pas.
     */
    private function setTypeYield(string $type, ?string $item, ?int $exhaust = null, ?int $regrow = null): void
    {
        if (!array_key_exists($type, $this->typeYieldBackup)) {
            $this->typeYieldBackup[$type] = $this->link->fetchAssociative(
                'SELECT harvest_item, harvest_exhaust, harvest_regrow FROM races WHERE name = ?',
                [$type]
            ) ?: null;
        }

        $this->link->executeStatement(
            'UPDATE races SET harvest_item = ?, harvest_exhaust = ?, harvest_regrow = ? WHERE name = ?',
            [$item, $exhaust, $regrow, $type]
        );
    }

    /**
     * Une ressource déjà épuisée (damages = -2) ne compte pas : seul l'état
     * « récoltable » entre dans le dé.
     */
    public function testExhaustedResourceIsIgnored(): void
    {
        $this->writePlan([['wall' => 'arbre1', 'ressource' => 'bois', 'exhaust' => 75, 'regrow' => 20]]);
        $bois = $this->itemOrSkip('bois');

        $this->putResource('arbre1', 0, 1);              // récoltable
        $this->putResource('arbre1', 1, 0, -2);          // épuisée
        $this->putResource('arbre1', 1, 1, 0);           // ni l'un ni l'autre

        $player = $this->harvesterAtOrigin('GmFouilleEpuisee');
        $before = $bois->get_n($player);

        // Si les trois comptaient, le dé serait un 1d3 et ScriptedDice
        // rendrait quand même 1 — on vérifie donc l'épuisement en base.
        ResourceOutcomeInstruction::setDiceForTests(new ScriptedDice([[1]]));
        ResourceService::setDiceForTests(new ScriptedDice([[1]]));

        (new ActionExecutorService($this->actionOrSkip(), $player, $player))->executeAction();

        $fresh = PlayerFactory::legacy($player->id);
        $this->assertSame($before + 1, $bois->get_n($fresh));

        $depleted = (int) $this->link->fetchOne(
            "SELECT COUNT(*) FROM resources s
               JOIN players p ON p.id = s.player_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND s.exhausted_at IS NOT NULL",
            [self::PLAN]
        );
        $this->assertSame(
            2,
            $depleted,
            'la récoltable s\'épuise (dé de 1 contre un taux de 75) et l\'épuisée le reste ; celle à 0 est hors du cycle'
        );
    }
}
