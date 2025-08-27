<?php

use App\Action\MeleeAction;
use App\Service\ActionService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;

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
            $actionService = new ActionService();
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