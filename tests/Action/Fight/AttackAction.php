<?php

namespace Tests\Action;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use App\Action\MeleeAction;
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