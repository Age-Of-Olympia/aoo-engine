<?php

namespace Tests\Service;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Service\ActionExecutorService;
use App\Action\MeleeAction;
use App\Action\HealAction;
use App\Action\OutcomeInstruction\LifeLossOutcomeInstruction;
use App\Entity\ActionCondition;
use App\Entity\ActionOutcome;
use App\Interface\OutcomeInstructionServiceInterface;
use App\Interface\PlayerServiceInterface;
use Tests\Action\Mock\OutcomeInstructionServiceMock;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\PlayerServiceMock;

class ActionExecutorServiceTest extends TestCase
{
    private MeleeAction $meleeAction;
    private PlayerMock $actor;
    private PlayerMock $target;
    private PlayerServiceInterface $playerService;
    private OutcomeInstructionServiceInterface $outcomeInstructionService;

    protected function setUp(): void
    {
        $this->meleeAction = new MeleeAction();
        $this->meleeAction->setName('test_melee');
        $this->meleeAction->setDisplayName('Test Melee');
        $this->meleeAction->setText('Test melee attack');
        
        $this->actor = new PlayerMock(1, 'Attacker');
        $this->target = new PlayerMock(2, 'Defender');
        $this->playerService = new PlayerServiceMock(1);
        $this->outcomeInstructionService = new OutcomeInstructionServiceMock();
        
        // Configuration de base pour un combat
        $this->actor->setCarac('cc', 10);
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
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target, $this->playerService, $this->outcomeInstructionService);
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
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target, $this->playerService);
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
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target, $this->playerService);
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
        $this->addMagicCostCondition(); // Ajouter un coût en PM (3)
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target, $this->playerService, $this->outcomeInstructionService);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertLessThan(10, $this->actor->getRemaining('pm'));
        $this->assertContains('Vous avez dépensé 3 PM.', $result->getCostsResultsArray());
    }

    #[Group('executor')]
    public function testPvTrackingForScreenshot(): void
    {
        // Arrange
        $initialPv = $this->target->getRemaining('pv');
        $this->addBasicMeleeConditions();
        $this->addBasicMeleeOutcomes();
        
        // Act
        $executor = new ActionExecutorService($this->meleeAction, $this->actor, $this->target, $this->playerService, $this->outcomeInstructionService);
        $result = $executor->executeAction();
        
        // Assert
        $this->assertTrue($result->isSuccess());
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
        $distanceCondition->setBlocking(true);
        $this->meleeAction->addCondition($distanceCondition);
        
        // Requiert 1 action
        $actionCondition = new ActionCondition();
        $actionCondition->setConditionType('RequiresTraitValue');
        $actionCondition->setParameters(['a' => 1]);
        $actionCondition->setExecutionOrder(2);
        $actionCondition->setBlocking(true);
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
        $successOutcome->setId(1);
        $this->meleeAction->addOutcome($successOutcome);
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
