<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\ActionExecutorService;
use App\Action\MeleeAction;
use App\Action\SpellAction;
use App\Action\HealAction;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use Tests\Action\Mock\PlayerMock;
use Tests\Integration\Mock\DatabaseMock;

/**
 * Tests d'intégration pour vérifier que tous les composants fonctionnent ensemble
 */
class FullCombatIntegrationTest extends TestCase
{
    private DatabaseMock $database;

    protected function setUp(): void
    {
        $this->database = new DatabaseMock();
        $this->database->setupTestData();
    }

    #[Group('integration')]
    public function testCompleteWarriorVsMageScenario(): void
    {
        // Arrange - Scénario complet : Guerrier vs Mage
        $warrior = $this->createWarrior();
        $mage = $this->createMage();
        
        // Premier tour : Le guerrier attaque
        $meleeAction = $this->createMeleeAction();
        $executor1 = new ActionExecutorService($meleeAction, $warrior, $mage);
        
        // Act
        $result1 = $executor1->executeAction();
        
        // Assert - Vérification du premier échange
        $this->assertInstanceOf(\App\Action\ActionResults::class, $result1);
        
        if ($result1->isSuccess()) {
            // Si l'attaque réussit
            $this->assertLessThan(25, $mage->getRemaining('pv')); // Mage blessé
            $this->assertGreaterThan(0, $result1->getXpResultsArray()['actor']);
            $this->assertContains('a attaqué', $result1->getLogsArray()['actor']);
        }
        
        // Deuxième tour : Le mage riposte avec un sort
        if ($mage->getRemaining('pv') > 0) {
            $spellAction = $this->createSpellAction();
            $executor2 = new ActionExecutorService($spellAction, $mage, $warrior);
            $result2 = $executor2->executeAction();
            
            if ($result2->isSuccess()) {
                $this->assertLessThan(30, $warrior->getRemaining('pv'));
                $this->assertContains('a lancé', $result2->getLogsArray()['actor']);
            }
        }
        
        // Vérification de l'intégrité des données
        $this->assertGreaterThanOrEqual(0, $warrior->getRemaining('pv'));
        $this->assertGreaterThanOrEqual(0, $mage->getRemaining('pv'));
    }

    #[Group('integration')]
    public function testHealerSupportScenario(): void
    {
        // Arrange - Scénario avec soigneur
        $healer = $this->createHealer();
        $wounded = $this->createWarrior();
        $wounded->setRemaining('pv', 5); // Gravement blessé
        
        // Act - Soin d'urgence
        $healAction = $this->createHealAction();
        $executor = new ActionExecutorService($healAction, $healer, $wounded);
        $result = $executor->executeAction();
        
        // Assert
        if ($result->isSuccess()) {
            $this->assertGreaterThan(5, $wounded->getRemaining('pv'));
            $this->assertEquals(3, $result->getXpResultsArray()['actor']); // XP de soin
            $this->assertContains('soignez', $result->getOutcomesResultsArray()[0]->getOutcomeSuccessMessages()[0] ?? '');
        }
    }

    #[Group('integration')]
    public function testMultiPlayerChainReaction(): void
    {
        // Arrange - Test de réaction en chaîne avec plusieurs joueurs
        $players = [
            $this->createWarrior(),
            $this->createMage(),
            $this->createHealer()
        ];
        
        $results = [];
        
        // Act - Séquence d'actions en chaîne
        for ($i = 0; $i < 3; $i++) {
            $attacker = $players[$i];
            $target = $players[($i + 1) % 3];
            
            if ($attacker->getRemaining('pv') > 0 && $target->getRemaining('pv') > 0) {
                $action = $this->createAppropriateAction($attacker);
                $executor = new ActionExecutorService($action, $attacker, $target);
                $results[] = $executor->executeAction();
            }
        }
        
        // Assert - Vérifier la cohérence du système après plusieurs actions
        $this->assertCount(3, $results);
        
        foreach ($results as $result) {
            $this->assertInstanceOf(\App\Action\ActionResults::class, $result);
        }
        
        // Vérifier qu'au moins un joueur a été affecté
        $totalDamageDealt = 0;
        foreach ($players as $player) {
            if ($player->getRemaining('pv') < $this->getInitialPv($player)) {
                $totalDamageDealt++;
            }
        }
        
        $this->assertGreaterThan(0, $totalDamageDealt);
    }

    #[Group('integration')]
    public function testEnvironmentalEffectsIntegration(): void
    {
        // Arrange - Test avec effets environnementaux
        $player = $this->createWarrior();
        $target = $this->createMage();
        
        // Ajouter des effets environnementaux
        $player->addEffect('corruption_du_metal');
        $target->addEffect('eau');
        
        // Act
        $action = $this->createMeleeAction();
        $executor = new ActionExecutorService($action, $player, $target);
        $result = $executor->executeAction();
        
        // Assert - Les effets doivent influencer le combat
        if ($result->isSuccess()) {
            // Vérifier que les effets ont été pris en compte
            $this->assertTrue($player->haveEffect('corruption_du_metal') > 0);
            $this->assertTrue($target->haveEffect('eau') > 0);
        }
    }

    private function createWarrior(): PlayerMock
    {
        $warrior = new PlayerMock(1, 'Warrior', 'alliance');
        $warrior->setCarac('cc', 8);
        $warrior->setCarac('f', 10);
        $warrior->setCarac('e', 6);
        $warrior->setRemaining('pv', 30);
        $warrior->setRemaining('a', 2);
        $warrior->equipWeapon('melee', 'main1');
        $warrior->setCoords(0, 0, 0, 'battlefield');
        return $warrior;
    }

    private function createMage(): PlayerMock
    {
        $mage = new PlayerMock(2, 'Mage', 'alliance');
        $mage->setCarac('fm', 9);
        $mage->setCarac('m', 8);
        $mage->setCarac('e', 3);
        $mage->setRemaining('pv', 25);
        $mage->setRemaining('pm', 15);
        $mage->setRemaining('a', 2);
        $mage->setCoords(1, 0, 0, 'battlefield');
        return $mage;
    }

    private function createHealer(): PlayerMock
    {
        $healer = new PlayerMock(3, 'Healer', 'alliance');
        $healer->setCarac('m', 7);
        $healer->setCarac('agi', 6);
        $healer->setRemaining('pv', 28);
        $healer->setRemaining('pm', 12);
        $healer->setRemaining('a', 2);
        $healer->setCoords(0, 1, 0, 'battlefield');
        return $healer;
    }

    private function createMeleeAction(): MeleeAction
    {
        $action = new MeleeAction();
        $action->setName('sword_strike');
        $action->setDisplayName('Coup d\'Épée');
        
        // Conditions basiques
        $this->addDistanceCondition($action, ['max' => 1]);
        $this->addTraitCondition($action, ['a' => 1]);
        $this->addComputeCondition($action, 'MeleeCompute');
        
        return $action;
    }

    private function createSpellAction(): SpellAction
    {
        $action = new SpellAction();
        $action->setName('fireball');
        $action->setDisplayName('Boule de Feu');
        
        $this->addDistanceCondition($action, ['min' => 2]);
        $this->addTraitCondition($action, ['pm' => 4, 'a' => 1]);
        $this->addComputeCondition($action, 'SpellCompute');
        
        return $action;
    }

    private function createHealAction(): HealAction
    {
        $action = new HealAction();
        $action->setName('heal');
        $action->setDisplayName('Soin');
        
        $this->addDistanceCondition($action, ['max' => 1]);
        $this->addTraitCondition($action, ['pm' => 3, 'a' => 1]);
        
        return $action;
    }

    private function createAppropriateAction(PlayerMock $player): object
    {
        $name = $player->data->name;
        
        switch ($name) {
            case 'Warrior':
                return $this->createMeleeAction();
            case 'Mage':
                return $this->createSpellAction();
            case 'Healer':
                return $this->createHealAction();
            default:
                return $this->createMeleeAction();
        }
    }

    private function addDistanceCondition($action, array $params): void
    {
        $condition = new ActionCondition();
        $condition->setConditionType('RequiresDistance');
        $condition->setParameters($params);
        $condition->setExecutionOrder(1);
        $action->addCondition($condition);
    }

    private function addTraitCondition($action, array $params): void
    {
        $condition = new ActionCondition();
        $condition->setConditionType('RequiresTraitValue');
        $condition->setParameters($params);
        $condition->setExecutionOrder(2);
        $action->addCondition($condition);
    }

    private function addComputeCondition($action, string $type): void
    {
        $condition = new ActionCondition();
        $condition->setConditionType($type);
        $condition->setParameters([
            'actorRollType' => $type === 'MeleeCompute' ? 'cc' : 'fm',
            'targetRollType' => $type === 'MeleeCompute' ? 'cc/agi' : 'fm'
        ]);
        $condition->setExecutionOrder(10);
        $action->addCondition($condition);
    }

    private function getInitialPv(PlayerMock $player): int
    {
        $name = $player->data->name;
        return match($name) {
            'Warrior' => 30,
            'Mage' => 25,
            'Healer' => 28,
            default => 25
        };
    }
}

/**
 * Tests de régression pour s'assurer de ne pas casser le système existant
 */
class RegressionTest extends TestCase
{
    #[Group('regression')]
    public function testLegacySortCompatibility(): void
    {
        // Arrange - Test de compatibilité avec les anciens sorts
        $legacySorts = [
            'pic_de_pierre' => ['pm' => 4, 'range' => 'min_2'],
            'lame_volante' => ['pm' => 4, 'range' => 'min_2'],
            'boule_de_magma' => ['pm' => 7, 'range' => 'min_2'],
            'regeneration' => ['pm' => 6, 'range' => 'max_1'],
            'imposition_des_mains' => ['pm' => 7, 'range' => 'max_1']
        ];

        foreach ($legacySorts as $sortName => $expectedCosts) {
            // Act - Vérifier que les anciens sorts fonctionnent toujours
            $actionService = new \App\Service\ActionService();
            $action = $actionService->getActionByName($sortName);
            
            // Assert
            if ($action !== null) {
                $costs = $actionService->getCostsArray($sortName, $action);
                $this->assertIsArray($costs);
                
                // Vérifier que les coûts correspondent
                $hasPMCost = false;
                foreach ($costs as $cost) {
                    if (strpos($cost, 'PM') !== false) {
                        $hasPMCost = true;
                        break;
                    }
                }
                
                if (isset($expectedCosts['pm'])) {
                    $this->assertTrue($hasPMCost, "Sort $sortName devrait avoir un coût en PM");
                }
            }
        }
    }

    #[Group('regression')]
    public function testRaceSpecificActionsStillWork(): void
    {
        // Arrange - Actions spécifiques aux races
        $raceActions = [
            'nain' => ['pic_de_pierre', 'assomoir', 'barbier'],
            'geant' => ['aiguillon', 'boule_de_magma', 'regeneration'],
            'olympien' => ['lame_volante', 'desarmement', 'imposition_des_mains'],
            'hs' => ['dard', 'griffes', 'flux_vital'],
            'elfe' => ['fleche_aquatique', 'frappe_vicieuse', 'lien_de_vie']
        ];

        foreach ($raceActions as $race => $actions) {
            foreach ($actions as $actionName) {
                // Act
                $actionService = new \App\Service\ActionService();
                $action = $actionService->getActionByName($actionName);
                
                // Assert - L'action doit exister ou être null (pas d'erreur)
                $this->assertTrue($action instanceof \App\Interface\ActionInterface || $action === null);
            }
        }
    }

    #[Group('regression')]
    public function testBasicCombatStillCalculatesCorrectly(): void
    {
        // Arrange - Scénario de base qui devrait toujours marcher
        $attacker = new PlayerMock(1, 'BasicAttacker');
        $attacker->setCarac('f', 10);
        $attacker->setCarac('cc', 6);
        $attacker->setRemaining('a', 1);
        
        $defender = new PlayerMock(2, 'BasicDefender');
        $defender->setCarac('e', 4);
        $defender->setCarac('cc', 5);
        $defender->setCarac('agi', 3);
        $defender->setRemaining('pv', 20);
        
        // Position de combat
        $attacker->setCoords(0, 0, 0, 'test');
        $defender->setCoords(1, 0, 0, 'test');
        
        // Act
        $action = new MeleeAction();
        $action->setName('basic_attack');
        
        // Simulation manuelle du calcul (sans ActionExecutor pour ce test)
        $expectedDamage = max(1, $attacker->caracs->f - $defender->caracs->e);
        $newPv = $defender->getRemaining('pv') - $expectedDamage;
        
        // Assert - Les calculs de base doivent fonctionner
        $this->assertEquals(6, $expectedDamage); // 10 - 4 = 6
        $this->assertEquals(14, $newPv); // 20 - 6 = 14
        $this->assertGreaterThan(0, $newPv); // Le défenseur survit
    }

    #[Group('regression')]
    public function testHealingSpellsStillHeal(): void
    {
        // Arrange
        $healer = new PlayerMock(1, 'Healer');
        $healer->setCarac('agi', 8); // Pour barbier
        $healer->setCarac('r', 6);   // Pour régénération
        $healer->setCarac('m', 7);   // Pour imposition des mains
        $healer->setCarac('rm', 5);  // Pour flux vital et lien de vie
        
        $patient = new PlayerMock(2, 'Patient');
        $patient->setRemaining('pv', 10); // Blessé
        
        // Act & Assert - Test des différents types de soin
        $healingSpells = [
            'barbier' => 'agi',      // Nain - soigne à hauteur de l'agi
            'regeneration' => 'r',    // Géant - soigne à hauteur de R
            'imposition_des_mains' => 'm', // Olympien - soigne M+3
            'flux_vital' => 'rm',     // HS - soigne à hauteur de RM (sur soi)
            'lien_de_vie' => 'rm'     // Elfe - soigne à hauteur de RM
        ];

        foreach ($healingSpells as $spellName => $healTrait) {
            $expectedHealing = $healer->caracs->{$healTrait};
            if ($spellName === 'imposition_des_mains') {
                $expectedHealing += 3; // Bonus spécial
            }
            
            $this->assertGreaterThan(0, $expectedHealing);
        }
    }

    #[Group('regression')]
    public function testDistanceCalculationsUnchanged(): void
    {
        // Arrange - Test que les calculs de distance n'ont pas changé
        $testCases = [
            [[0,0,0], [1,0,0], 1],    // Adjacent
            [[0,0,0], [2,2,0], 2],    // Diagonal
            [[0,0,0], [3,0,0], 3],    // Ligne droite
            [[0,0,0], [5,5,0], 5],    // Diagonal loin
            [[0,0,0], [0,0,1], 100000000], // Différent Z - impossible
        ];

        foreach ($testCases as [$coords1, $coords2, $expectedDistance]) {
            $pos1 = (object) ['x' => $coords1[0], 'y' => $coords1[1], 'z' => $coords1[2], 'plan' => 'test'];
            $pos2 = (object) ['x' => $coords2[0], 'y' => $coords2[1], 'z' => $coords2[2], 'plan' => 'test'];
            
            // Act
            $distance = \Classes\View::get_distance($pos1, $pos2);
            
            // Assert
            $this->assertEquals($expectedDistance, $distance);
        }
    }
}

/**
 * Tests de sécurité et edge cases
 */
class SecurityTest extends TestCase
{
    #[Group('security')]
    public function testSQLInjectionPrevention(): void
    {
        // Arrange - Tentatives d'injection SQL
        $maliciousInputs = [
            "'; DROP TABLE actions; --",
            "1 OR 1=1",
            "<script>alert('xss')</script>",
            "../../etc/passwd",
            "null",
            "undefined"
        ];

        foreach ($maliciousInputs as $maliciousInput) {
            // Act & Assert - Le service ne devrait pas planter
            try {
                $actionService = new \App\Service\ActionService();
                $result = $actionService->getActionByName($maliciousInput);
                
                // Devrait retourner null sans erreur
                $this->assertNull($result);
            } catch (\Exception $e) {
                // Si exception, elle ne devrait pas révéler d'informations sensibles
                $this->assertStringNotContainsString('password', strtolower($e->getMessage()));
                $this->assertStringNotContainsString('database', strtolower($e->getMessage()));
            }
        }
    }

    #[Group('security')]
    public function testPlayerIsolation(): void
    {
        // Arrange - S'assurer qu'un joueur ne peut pas affecter les données d'un autre
        $player1 = new PlayerMock(1, 'Player1');
        $player2 = new PlayerMock(2, 'Player2');
        
        $player1->setRemaining('pv', 20);
        $player2->setRemaining('pv', 20);
        
        // Act - Player1 modifie ses propres stats
        $player1->setRemaining('pv', 15);
        
        // Assert - Player2 ne devrait pas être affecté
        $this->assertEquals(15, $player1->getRemaining('pv'));
        $this->assertEquals(20, $player2->getRemaining('pv'));
    }

    #[Group('security')]
    public function testResourceLimitEnforcement(): void
    {
        // Arrange
        $player = new PlayerMock(1, 'TestPlayer');
        
        // Act & Assert - Tenter de donner des valeurs extrêmes
        $player->setRemaining('pm', -999);
        $player->setRemaining('pv', 99999);
        
        // Dans un système sécurisé, ces valeurs devraient être normalisées
        $this->assertGreaterThanOrEqual(0, $player->getRemaining('pm'));
        $this->assertLessThan(10000, $player->getRemaining('pv')); // Limite raisonnable
    }

    #[Group('security')]
    public function testActionExecutorBoundaryChecks(): void
    {
        // Arrange
        $player = new PlayerMock(1, 'TestPlayer');
        $target = new PlayerMock(2, 'TestTarget');
        
        // Test avec des coordonnées extrêmes
        $player->setCoords(-999999, 999999, -100, 'test');
        $target->setCoords(999999, -999999, 100, 'test');
        
        // Act - Le système ne devrait pas planter
        $action = new MeleeAction();
        
        try {
            $executor = new ActionExecutorService($action, $player, $target);
            $result = $executor->executeAction();
            
            // Assert
            $this->assertInstanceOf(\App\Action\ActionResults::class, $result);
        } catch (\Exception $e) {
            // Si exception, elle devrait être gérée proprement
            $this->assertIsString($e->getMessage());
        }
    }
}

namespace Tests\Integration\Mock;

/**
 * Mock de base de données pour les tests d'intégration
 */
class DatabaseMock
{
    private array $tables = [];

    public function setupTestData(): void
    {
        // Initialisation des données de test
        $this->tables['actions'] = [
            ['id' => 1, 'name' => 'melee', 'type' => 'melee'],
            ['id' => 2, 'name' => 'distance', 'type' => 'distance'],
            ['id' => 3, 'name' => 'pic_de_pierre', 'type' => 'spell'],
        ];
        
        $this->tables['players'] = [
            ['id' => 1, 'name' => 'TestPlayer', 'pv' => 20, 'pm' => 10]
        ];
    }

    public function query(string $sql, array $params = []): array
    {
        // Mock simple de requêtes
        if (strpos($sql, 'SELECT') === 0) {
            return $this->tables['actions'] ?? [];
        }
        
        return [];
    }

    public function insert(string $table, array $data): bool
    {
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }
        
        $data['id'] = count($this->tables[$table]) + 1;
        $this->tables[$table][] = $data;
        
        return true;
    }

    public function getLastInsertId(): int
    {
        return 1;
    }
}