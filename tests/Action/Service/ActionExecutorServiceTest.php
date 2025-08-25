<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\ActionExecutorService;
use App\Action\MeleeAction;
use App\Action\SpellAction;
use App\Action\HealAction;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\PlayerServiceMock;
use Tests\Action\Mock\ActionMock;

class ActionExecutorServiceTest extends TestCase
{
    private MeleeAction $meleeAction;
    private PlayerMock $actor;
    private PlayerMock $target;
    private PlayerServiceMock $playerService;

    protected function setUp(): void
    {
        $this->meleeAction = new MeleeAction();
        $this->meleeAction->setName('test_melee');
        $this->meleeAction->setDisplayName('Test Melee');
        $this->meleeAction->setText('Test melee attack');
        
        $this->actor = new PlayerMock(1, 'Attacker');
        $this->target = new PlayerMock(2, 'Defender');
        $this->playerService = new PlayerServiceMock(1);
        
        // Configuration de base pour un combat
        $this->actor->setCarac('cc', 8);
        $this->actor->setCarac('f', 10);
        $this->actor->setRemaining('a', 1);
        
        $this->target->setCarac('cc', 6);
        $this->target->setCarac('agi', 5);
        $this->target->setCarac('e', 4);
        $this->target->setRemaining('pv', 20);
        
        // Position à distance de mêlée
        $this->actor->setCoords(0, 0, 0, 'test');
        $this->target->setCoords(1, 0, 0, 'test');
    }

    #[Group('executor')]
    public function testSuccessfulMeleeAttack(): void
    {
        // Arrange
        $this->addBasicMeleeConditions();
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isBlocked());
        $this->assertGreaterThan(0, count($result->getOutcomesResultsArray()));
        $this->assertArrayHasKey('actor', $result->getXpResultsArray());
        $this->assertArrayHasKey('target', $result->getXpResultsArray());
        
        // Vérifier que les PV du défenseur ont diminué
        $this->assertLessThan(20, $this->target->getRemaining('pv'));
    }

    #[Group('executor')]
    public function testFailedAttackDueToDistance(): void
    {
        // Arrange
        $this->target->setCoords(5, 5, 0, 'test'); // Trop loin pour mêlée
        $this->addBasicMeleeConditions();
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        $this->assertEquals(20, $this->target->getRemaining('pv')); // PV inchangés
    }

    #[Group('executor')]
    public function testBlockedByInsufficientActions(): void
    {
        // Arrange
        $this->actor->setRemaining('a', 0); // Pas d'actions disponibles
        $this->addBasicMeleeConditions();
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
    }

    #[Group('executor')]
    public function testCostsAreApplied(): void
    {
        // Arrange
        $this->actor->setRemaining('pm', 10);
        $this->addBasicMeleeConditions();
        $this->addMagicCostCondition(); // Ajouter un coût en PM
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertLessThan(10, $this->actor->getRemaining('pm'));
        $this->assertContains('Vous avez dépensé', $result->getCostsResultsArray()[0]);
    }

    #[Group('executor')]
    public function testPvTrackingForScreenshot(): void
    {
        // Arrange
        $initialPv = $this->target->getRemaining('pv');
        $this->addBasicMeleeConditions();
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertEquals($initialPv, $executor->getInitialTargetPv());
        $this->assertLessThan($initialPv, $executor->getFinalTargetPv());
    }

    private function addBasicMeleeConditions(): void
    {
        // Distance max 1
        $distanceCondition = new ActionCondition();
        $distanceCondition->setConditionType('RequiresDistance');
        $distanceCondition->setParameters(['max' => 1]);
        $distanceCondition->setExecutionOrder(1);
        $this->meleeAction->addCondition($distanceCondition);
        
        // Requiert 1 action
        $actionCondition = new ActionCondition();
        $actionCondition->setConditionType('RequiresTraitValue');
        $actionCondition->setParameters(['a' => 1]);
        $actionCondition->setExecutionOrder(2);
        $this->meleeAction->addCondition($actionCondition);
        
        // Calcul de mêlée
        $computeCondition = new ActionCondition();
        $computeCondition->setConditionType('MeleeCompute');
        $computeCondition->setParameters([
            'actorRollType' => 'cc',
            'targetRollType' => 'cc/agi'
        ]);
        $computeCondition->setExecutionOrder(10);
        $this->meleeAction->addCondition($computeCondition);
    }

    private function addMagicCostCondition(): void
    {
        $pmCondition = new ActionCondition();
        $pmCondition->setConditionType('RequiresTraitValue');
        $pmCondition->setParameters(['pm' => 3]);
        $pmCondition->setExecutionOrder(3);
        $this->meleeAction->addCondition($pmCondition);
    }

    private function addBasicMeleeOutcomes(): void
    {
        // Outcome de succès : dégâts
        $successOutcome = new ActionOutcome();
        $successOutcome->setOnSuccess(true);
        $this->meleeAction->addOutcome($successOutcome);
        
        // Note: Dans un test complet, on ajouterait les OutcomeInstructions
        // mais cela nécessiterait un mock plus complexe du système de base de données
    }
}

namespace Tests\Action;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\AttackAction;
use App\Action\HealAction;
use App\Action\MeleeAction;
use App\Action\SpellAction;
use App\Action\TechniqueAction;
use App\Service\ActionExecutorService;
use Tests\Action\Mock\PlayerMock;

class AttackActionTest extends TestCase
{
    private MeleeAction $meleeAction;
    private PlayerMock $actor;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->meleeAction = new MeleeAction();
        $this->actor = new PlayerMock(1, 'Attacker');
        $this->target = new PlayerMock(2, 'Defender');
        
        // Configuration des rangs pour les tests XP
        $this->actor->data->rank = 3;
        $this->target->data->rank = 2;
    }

    #[Group('attack-action')]
    public function testCalculateActorXpSuccess(): void
    {
        // Act
        $xpResult = $this->meleeAction->calculateXp(true, $this->actor, $this->target);
        
        // Assert
        $this->assertArrayHasKey('actor', $xpResult);
        $this->assertArrayHasKey('target', $xpResult);
        $this->assertGreaterThan(0, $xpResult['actor']); // Attaquant gagne XP
        $this->assertEquals(0, $xpResult['target']); // Défenseur ne gagne pas XP
    }

    #[Group('attack-action')]
    public function testCalculateXpFailure(): void
    {
        // Act
        $xpResult = $this->meleeAction->calculateXp(false, $this->actor, $this->target);
        
        // Assert
        $this->assertEquals(0, $xpResult['actor']); // Attaquant rate
        $this->assertEquals(2, $xpResult['target']); // Défenseur gagne XP défensif
    }

    #[Group('attack-action')]
    public function testSameFactionXpReduction(): void
    {
        // Arrange
        $this->actor->data->faction = 'alliance';
        $this->target->data->faction = 'alliance';
        
        // Act
        $xpResult = $this->meleeAction->calculateXp(true, $this->actor, $this->target);
        
        // Assert
        $this->assertEquals(1, $xpResult['actor']); // XP réduit pour même faction
    }

    #[Group('attack-action')]
    public function testInactiveTargetXpReduction(): void
    {
        // Arrange
        $this->target->data->isInactive = true;
        
        // Act
        $xpResult = $this->meleeAction->calculateXp(true, $this->actor, $this->target);
        
        // Assert
        $this->assertEquals(1, $xpResult['actor']); // XP réduit pour cible inactive
    }

    #[Group('attack-action')]
    public function testRankDifferenceXp(): void
    {
        // Arrange - Attaquant beaucoup plus fort
        $this->actor->data->rank = 10;
        $this->target->data->rank = 1;
        
        // Act
        $xpResult = $this->meleeAction->calculateXp(true, $this->actor, $this->target);
        
        // Assert
        $this->assertEquals(0, $xpResult['actor']); // Pas d'XP si différence trop grande
    }

    #[Group('attack-action')]
    public function testUpgradeXpReduction(): void
    {
        // Arrange - Mock d'upgrades d'actions
        $mockUpgrades = (object) ['a' => 3]; // 3 upgrades d'actions
        $this->actor->setUpgrades($mockUpgrades);
        
        // Act
        $xpResult = $this->meleeAction->calculateXp(true, $this->actor, $this->target);
        
        // Assert
        // XP devrait être réduit à cause des upgrades (XP dégressif)
        $this->assertGreaterThanOrEqual(2, $xpResult['actor']); // Minimum 2
    }

    #[Group('attack-action')]
    public function testGetLogMessages(): void
    {
        // Arrange
        $this->actor->equipWeapon('melee', 'main1');
        
        // Act
        $logs = $this->meleeAction->getLogMessages($this->actor, $this->target);
        
        // Assert
        $this->assertArrayHasKey('actor', $logs);
        $this->assertArrayHasKey('target', $logs);
        $this->assertContains('Attacker', $logs['actor']);
        $this->assertContains('Defender', $logs['target']);
        $this->assertContains('a attaqué', $logs['actor']);
    }

    #[Group('attack-action')]
    public function testAntiBerserkActivation(): void
    {
        // Act & Assert
        $this->assertTrue($this->meleeAction->activateAntiBerserk());
    }
}

class SpellActionTest extends TestCase
{
    private SpellAction $spellAction;
    private PlayerMock $caster;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->spellAction = new SpellAction();
        $this->spellAction->setDisplayName('Boule de Feu');
        
        $this->caster = new PlayerMock(1, 'Mage');
        $this->target = new PlayerMock(2, 'Target');
    }

    #[Group('spell-action')]
    public function testSpellLogMessages(): void
    {
        // Act
        $logs = $this->spellAction->getLogMessages($this->caster, $this->target);
        
        // Assert
        $this->assertContains('Mage a lancé Boule de Feu sur Target', $logs['actor']);
        $this->assertContains('Target a été attaqué par Mage avec Boule de Feu', $logs['target']);
    }

    #[Group('spell-action')]
    public function testSpellInheritsFromTechniqueAction(): void
    {
        // Assert
        $this->assertInstanceOf(TechniqueAction::class, $this->spellAction);
    }
}

// Tests d'intégration pour des scénarios complets
class ActionIntegrationTest extends TestCase
{
    #[Group('integration')]
    public function testCompleteSuccessfulAttack(): void
    {
        // Arrange
        $attacker = new PlayerMock(1, 'Warrior');
        $defender = new PlayerMock(2, 'Target');
        
        $attacker->setCarac('cc', 10);
        $attacker->setCarac('f', 8);
        $attacker->setRemaining('a', 1);
        $attacker->setCoords(0, 0, 0, 'battlefield');
        
        $defender->setCarac('cc', 6);
        $defender->setCarac('agi', 4);
        $defender->setCarac('e', 3);
        $defender->setRemaining('pv', 15);
        $defender->setCoords(1, 0, 0, 'battlefield');
        
        $meleeAction = new MeleeAction();
        $meleeAction->setName('sword_strike');
        $meleeAction->setDisplayName('Coup d\'Épée');
        
        // Act
        $executor = new ActionExecutorService($meleeAction, $attacker, $defender);
        $result = $executor->executeAction();
        
        // Assert - Tests de haut niveau
        $this->assertInstanceOf(\App\Action\ActionResults::class, $result);
        $this->assertNotEmpty($result->getLogsArray());
        
        if ($result->isSuccess()) {
            // Si l'attaque réussit
            $this->assertLessThan(15, $defender->getRemaining('pv'));
            $this->assertGreaterThan(0, $result->getXpResultsArray()['actor']);
        } else {
            // Si l'attaque échoue
            $this->assertEquals(15, $defender->getRemaining('pv')); // PV inchangés
            $this->assertGreaterThan(0, $result->getXpResultsArray()['target']); // Défenseur gagne XP
        }
        
        // Dans tous les cas, l'attaquant doit avoir dépensé une action
        $this->assertEquals(0, $attacker->getRemaining('a'));
    }

    #[Group('integration')]
    public function testHealingAction(): void
    {
        // Arrange
        $healer = new PlayerMock(1, 'Cleric');
        $wounded = new PlayerMock(2, 'Patient');
        
        $healer->setCarac('m', 8);
        $healer->setRemaining('pm', 10);
        $healer->setRemaining('a', 1);
        $healer->setCoords(0, 0, 0, 'temple');
        
        $wounded->setRemaining('pv', 5); // Blessé
        $wounded->setCoords(1, 0, 0, 'temple');
        
        $healAction = new HealAction();
        $healAction->setName('heal');
        $healAction->setDisplayName('Soin');
        
        // Act
        $executor = new ActionExecutorService($healAction, $healer, $wounded);
        $result = $executor->executeAction();
        
        // Assert
        if ($result->isSuccess()) {
            $this->assertGreaterThan(5, $wounded->getRemaining('pv'));
            $this->assertEquals(3, $result->getXpResultsArray()['actor']); // XP fixe pour heal
            $this->assertEquals(0, $result->getXpResultsArray()['target']);
        }
        
        // Le soigneur devrait avoir dépensé des ressources
        $this->assertEquals(0, $healer->getRemaining('a'));
    }

    #[Group('integration')]
    public function testBlockedActionDueToBerserkLimit(): void
    {
        // Arrange
        $player = new PlayerMock(1, 'Warrior');
        $target = new PlayerMock(2, 'Dummy');
        
        // Simuler un joueur en période anti-berserk
        $player->data->antiBerserkTime = time() + 3600; // 1h dans le futur
        
        $action = new MeleeAction();
        
        // Act
        $executor = new ActionExecutorService($action, $player, $target);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isBlocked());
        
        $errorMessages = [];
        foreach ($result->getConditionsResultsArray() as $conditionResult) {
            $errorMessages = array_merge($errorMessages, $conditionResult->getConditionFailureMessages());
        }
        
        $this->assertContains('Mesure anti-Berserk!', $errorMessages);
    }
}
