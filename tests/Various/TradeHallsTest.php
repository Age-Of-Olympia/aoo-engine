<?php

namespace Tests\Various;

use App\Service\BuildingService;
use App\Service\DialogService;
use App\Service\PlayerOptionsService;
use App\Service\RaceService;
use Classes\Market;
use Classes\WarSchool;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Les trois maisons à dialogue (échoppe, banque, école de guerre) et les
 * gardes d'accès qui les servent
 * (Version20260806150000_TradeHallsEnterTheWorld).
 *
 * Le rôle n'est plus une option de personne : un bâtiment est marchand
 * ou entraîneur parce que son DIALOGUE mène à l'écran
 * (DialogService::opensScreen) — isMerchant / isTrainer ont quitté le
 * jeu. Les autres règles sous test :
 *  - un bâtiment FERMÉ (porte close, ruine…) ne sert personne, même en
 *    frappant l'URL du comptoir directement ;
 *  - la distance se mesure à la case la plus proche de l'EMPRISE (les
 *    maisons font 2×2), pas au seul point d'ancrage.
 */
#[Group('entities-structure')]
class TradeHallsTest extends LegacyPlayerFixtureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireBuildingsOrSkip();

        if ((new RaceService())->getRaceByName('echoppe') === null) {
            $this->markTestSkipped("structure types 'echoppe'/'banque'/'ecole_guerre' not seeded (run migrations).");
        }
    }

    public function testSeededTypesAreLockableEdifices(): void
    {
        $raceService = new RaceService();

        foreach (['echoppe', 'banque', 'ecole_guerre'] as $name) {
            $race = $raceService->getRaceByName($name);
            $this->assertNotNull($race, $name);
            $this->assertTrue($race->isEdifice(), "$name est un édifice : il a une porte");
            $this->assertTrue($race->isLockable(), "$name se ferme — fermé, il ne sert personne");
        }
    }

    public function testSeededDialogsCarryTheCounters(): void
    {
        $service = new DialogService();

        // Le dialogue PORTE le rôle : la même réponse sert les gardes
        // d'accès et la carte de la case.
        $this->assertTrue($service->opensScreen('echoppe', 'merchant.php'), "l'échoppe mène au marché");
        $this->assertFalse($service->opensScreen('echoppe', 'warschool.php'));

        $this->assertTrue($service->opensScreen('banque', 'merchant.php'), 'la banque dépose et retire par le même écran');
        $this->assertFalse($service->opensScreen('banque', 'warschool.php'));

        $this->assertTrue($service->opensScreen('ecole_guerre', 'warschool.php'), "l'école mène aux six disciplines");
        $this->assertFalse($service->opensScreen('ecole_guerre', 'merchant.php'));

        $this->assertFalse($service->opensScreen('', 'merchant.php'), 'sans dialogue, pas de comptoir');

        // Onglet par onglet : la banque dépose et retire, l'échoppe tient
        // les étals — chacune est sourde au comptoir de l'autre.
        $this->assertTrue($service->opensScreen('banque', 'merchant.php', 'bank'));
        $this->assertFalse(
            $service->opensScreen('banque', 'merchant.php', 'inventory'),
            'le coffre à deux volets dépose ET retire : un seul onglet'
        );
        $this->assertFalse($service->opensScreen('banque', 'merchant.php', 'bids'), 'pas d\'étal à la banque');
        $this->assertFalse($service->opensScreen('banque', 'merchant.php', 'exchanges'));

        $this->assertTrue($service->opensScreen('echoppe', 'merchant.php', 'bids'));
        $this->assertTrue($service->opensScreen('echoppe', 'merchant.php', 'asks'));
        $this->assertTrue($service->opensScreen('echoppe', 'merchant.php', 'exchanges'));
        $this->assertFalse($service->opensScreen('echoppe', 'merchant.php', 'bank'), 'pas de coffre-fort à l\'échoppe');

        $this->assertTrue($service->opensScreen('ecole_guerre', 'warschool.php', 'melee'));
        $this->assertTrue($service->opensScreen('ecole_guerre', 'warschool.php', 'survival'));
        $this->assertFalse($service->opensScreen('ecole_guerre', 'warschool.php', 'forgeron'), 'discipline inconnue');
    }

    public function testCounterOptionsLeftTheGame(): void
    {
        $this->assertNotContains('isMerchant', PlayerOptionsService::MANAGEABLE_OPTIONS);
        $this->assertNotContains('isTrainer', PlayerOptionsService::MANAGEABLE_OPTIONS);
    }

    /**
     * La garde, sur des instances relues à neuf : le harnais téléporte et
     * ferme par SQL direct, et le Player legacy met ses coordonnées en
     * cache — l'objet d'avant le changement mentirait.
     *
     * @phpstan-impure la réponse suit l'état de la base, pas les arguments
     */
    private function marketAccess(int $playerId, int $targetId): ?string
    {
        return Market::CheckMarketAccess(
            \App\Factory\PlayerFactory::legacy($playerId),
            \App\Factory\PlayerFactory::legacy($targetId)
        );
    }

    /**
     * @see marketAccess
     * @phpstan-impure
     */
    private function schoolAccess(int $playerId, int $targetId): ?string
    {
        return WarSchool::checkAccess(
            \App\Factory\PlayerFactory::legacy($playerId),
            \App\Factory\PlayerFactory::legacy($targetId)
        );
    }

    public function testMarketAccessFollowsTheBuildingDialog(): void
    {
        [$x, $y] = $this->farTile();
        $service = new BuildingService();
        $id = $this->placeStructure('echoppe', $x, $y);
        $chaland = $this->createRealPlayer('ChalandHalls');
        $this->movePlayerTo((int) $chaland->id, $x - 1, $y);

        $this->assertSame(
            'error not merchant',
            $this->marketAccess((int) $chaland->id, $id),
            'sans dialogue, pas de comptoir — le type seul ne suffit pas'
        );

        $service->setDialog($id, 'echoppe');
        $this->assertNull(
            $this->marketAccess((int) $chaland->id, $id),
            'le dialogue mène au marché : accès accordé'
        );

        /* L'emprise fait 2×2 : adjacent à la case (x+1, y), donc servi,
         * bien qu'à deux cases du point d'ancrage. */
        $this->movePlayerTo((int) $chaland->id, $x + 2, $y);
        $this->assertNull(
            $this->marketAccess((int) $chaland->id, $id),
            'une bâtisse sert par chacun de ses côtés'
        );

        $this->movePlayerTo((int) $chaland->id, $x + 5, $y);
        $this->assertSame(
            ERROR_DISTANCE,
            $this->marketAccess((int) $chaland->id, $id),
            'trop loin de toute case de l\'emprise'
        );

        $this->movePlayerTo((int) $chaland->id, $x - 1, $y);
        $service->setOpen($id, false);
        $notice = $this->marketAccess((int) $chaland->id, $id);
        $this->assertNotNull($notice);
        $this->assertStringContainsString(
            'Fermé',
            $notice,
            "fermé volontairement, le comptoir ne sert personne — l'URL directe ne contourne pas la fiche"
        );
    }

    public function testWarSchoolAccessFollowsTheBuildingDialog(): void
    {
        [$x, $y] = $this->farTile();
        $service = new BuildingService();
        $id = $this->placeStructure('ecole_guerre', $x, $y);
        $eleve = $this->createRealPlayer('EleveHalls');
        $this->movePlayerTo((int) $eleve->id, $x - 1, $y);

        $this->assertSame(
            'error not trainer',
            $this->schoolAccess((int) $eleve->id, $id),
            "sans dialogue, pas d'entraînement"
        );

        $service->setDialog($id, 'ecole_guerre');
        $this->assertNull(
            $this->schoolAccess((int) $eleve->id, $id),
            "le dialogue mène à l'école : accès accordé"
        );

        $this->assertSame(
            'error not merchant',
            $this->marketAccess((int) $eleve->id, $id),
            "un dialogue d'école n'ouvre pas le marché : chaque comptoir suit son écran"
        );

        $service->setOpen($id, false);
        $notice = $this->schoolAccess((int) $eleve->id, $id);
        $this->assertNotNull($notice);
        $this->assertStringContainsString('Fermé', $notice, "fermée, l'école n'enseigne à personne");
    }

    /** Une porte n'est pas un coffre : le comptoir ferme (lockable) mais
     *  ne s'ouvre pas en contenant — seule une capacité au TYPE (la tour
     *  de garde) ou un exemplaire d'objet (le coffre) fait un contenant. */
    public function testACounterHallHasADoorButNoChest(): void
    {
        [$x, $y] = $this->farTile();
        $banqueId = $this->placeStructure('banque', $x, $y);
        $service = new \App\Service\ContainerService();

        $this->assertFalse(
            $service->isContainer($banqueId),
            'capacité nulle : une porte, pas de coffre — le 0 « illimité » est le sac des personnages'
        );

        $client = $this->createRealPlayer('ClientHalls');
        $this->movePlayerTo((int) $client->id, $x - 1, $y);
        try {
            $service->assertUsable($banqueId, (int) $client->id);
            $this->fail('la banque ne s\'ouvre pas comme une malle publique');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('coffre', $e->getMessage());
        }

        [$x2, $y2] = $this->farTile();
        $tourId = $this->placeStructure('tour_garde', $x2, $y2);
        $this->assertTrue(
            $service->isContainer($tourId),
            'la tour de garde garde son sac de 10 : la capacité du type fait le contenant'
        );
    }

    /** La fiche parle depuis chaque case de l'emprise — même règle de
     *  distance que le bouton Parler et que les gardes d'accès. */
    public function testTheSheetSpeaksFromEveryCellOfTheFootprint(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure('echoppe', $x, $y);
        (new BuildingService())->setDialog($id, 'echoppe');

        $client = $this->createRealPlayer('BadaudSheet');
        $this->movePlayerTo((int) $client->id, $x + 2, $y);
        $legacy = \App\Factory\PlayerFactory::legacy((int) $client->id);
        $legacy->get_data();
        $legacy->getCoords();

        $entity = \App\Factory\EntityManagerFactory::getEntityManager()
            ->find(\App\Entity\GameEntity::class, $id);
        $this->assertInstanceOf(\App\Entity\Structure::class, $entity);

        ob_start();
        \App\View\StructureSheetView::render($legacy, $entity, hudPanel: true);
        $html = (string) ob_get_clean();

        $this->assertStringNotContainsString(
            'directement à côté',
            $html,
            'adjacent à la case (x+1, y) de l\'emprise : le tenancier répond'
        );
        $this->assertStringContainsString('ui-dialog', $html, 'le dialogue est rendu');
    }

    /** Plus aucune personne ne tient de comptoir — même une option
     *  résiduelle en base reste lettre morte. */
    public function testACharacterNeverServesTheCounters(): void
    {
        [$x, $y] = $this->farTile();
        $marchand = $this->createRealPlayer('ExMarchandHalls');
        $this->movePlayerTo((int) $marchand->id, $x, $y);
        (new PlayerOptionsService())->addOption((int) $marchand->id, 'isMerchant');

        $chaland = $this->createRealPlayer('ChalandPnj');
        $this->movePlayerTo((int) $chaland->id, $x + 1, $y);

        $this->assertSame(
            'error not merchant',
            $this->marketAccess((int) $chaland->id, (int) $marchand->id),
            "l'option est morte : le rôle vit sur le dialogue d'un bâtiment"
        );
    }

    /** Le retrait d'un bâtiment emporte ses lignes d'options — la FK
     *  players_options bloquait le démontage. */
    public function testRemovingABuildingClearsItsLingeringOptions(): void
    {
        [$x, $y] = $this->farTile();
        $id = $this->placeStructure('echoppe', $x, $y);
        (new PlayerOptionsService())->addOption($id, 'incognitoMode');

        $this->assertTrue((new BuildingService())->remove($id));
        $this->assertFalse(
            $this->link->fetchOne('SELECT id FROM players WHERE id = ?', [$id]),
            'la ligne players est démontée'
        );
        $this->assertSame(
            0,
            (int) $this->link->fetchOne('SELECT COUNT(*) FROM players_options WHERE player_id = ?', [$id]),
            'les lignes d\'options partent avec lui'
        );
    }
}
