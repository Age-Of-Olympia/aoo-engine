<?php

namespace Tests\Action;

use App\Action\HealAction;
use PHPUnit\Framework\TestCase;
use Tests\Action\Mock\PlayerMock;
use PHPUnit\Framework\Attributes\Group;

class HealActionTest extends TestCase
{
  #[Group('action-xp')]
  public function testCalculateXpResultStructure()
  {
    // Vérifier la structure du résultat
    $actor = new PlayerMock(1, 'ActorPlayer', 'test_faction', '', false);
    $target = new PlayerMock(2, 'TargetPlayer', 'test_faction', '', false);
    $healAction = new HealAction();
    
    $result = $healAction->calculateXp(true, $actor, $target);
    
    // Vérifier que le résultat est bien un tableau avec les clés attendues
    $this->assertArrayHasKey('actor', $result);
    $this->assertArrayHasKey('target', $result);
    $this->assertIsInt($result['actor']);
    $this->assertIsInt($result['target']);
  }

  #[Group('action-xp')]
  public function testCalculateXpWithSuccess()
  {
    $actor = new PlayerMock(1, 'ActorPlayer', 'test_faction', '', false);
    $target = new PlayerMock(2, 'TargetPlayer', 'test_faction', '', false);
    $healAction = new HealAction();
    $result = $healAction->calculateXp(true, $actor, $target);
    // Vérifier que l'XP est de 3 pour l'acteur et 0 pour la cible en cas de succès
    $this->assertEquals(3, $result['actor']);
    $this->assertEquals(0, $result['target']);
  }

  #[Group('action-xp')]
  public function testCalculateXpWithFailure()
  {
    $actor = new PlayerMock(1, 'ActorPlayer', 'test_faction', '', false);
    $target = new PlayerMock(2, 'TargetPlayer', 'test_faction', '', false);
    $healAction = new HealAction();
    $result = $healAction->calculateXp(false, $actor, $target);
    // Vérifier que l'XP est de 0 pour l'acteur et la cible en cas d'échec
    $this->assertEquals(0, $result['actor']);
    $this->assertEquals(0, $result['target']);
  }

  #[Group('action-xp')]
  public function testCalculateXpWithDifferentFactions()
  {
    $actor = new PlayerMock(1, 'ActorPlayer', 'faction_A', '', false);
    $target = new PlayerMock(2, 'TargetPlayer', 'faction_B', '', false);
    $healAction = new HealAction();

    $result = $healAction->calculateXp(true, $actor, $target);

    // Vérifier que l'XP est toujours correctement attribuée même entre factions différentes
    $this->assertEquals(3, $result['actor']);
    $this->assertEquals(0, $result['target']);
  }

  #[Group('action-xp')]
  public function testCalculateXpWithInactiveTarget()
  {
    $actor = new PlayerMock(1, 'ActorPlayer', 'test_faction', '', false);
    $target = new PlayerMock(2, 'InactiveTarget', 'test_faction', '', true);
    $healAction = new HealAction();

    $result = $healAction->calculateXp(true, $actor, $target);

    // Vérifier que l'XP est attribuée normalement même si la cible est inactive
    $this->assertEquals(3, $result['actor']);
    $this->assertEquals(0, $result['target']);
  }

  #[Group('action-xp')]
  public function testCalculateXpWithSameActor()
  {
    // Cas où l'acteur se soigne lui-même
    $actor = new PlayerMock(1, 'SelfHealer', 'test_faction', '', false);
    $healAction = new HealAction();

    $result = $healAction->calculateXp(true, $actor, $actor);

    // Vérifier que l'XP est correctement attribuée même en cas d'auto-soin
    $this->assertEquals(3, $result['actor']);
    $this->assertEquals(0, $result['target']);
  }
}
