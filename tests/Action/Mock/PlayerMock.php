<?php

namespace Tests\Action\Mock;

use App\Enum\EquipResult;
use App\Interface\ActorInterface;
use Item;

class PlayerMock implements ActorInterface
{
  public $id;
  public $data;
  public $caracs;

  public function __construct(
    int $id = 1,
    string $name = 'MockPlayer',
    string $faction = 'test_faction',
    string $secretFaction = '',
    bool $isInactive = false
  ) {
    $this->id = $id;
    $this->data = (object) [
      'name' => $name,
      'rank' => 2,
      'faction' => $faction,
      'secretFaction' => $secretFaction,
      'isInactive' => $isInactive,
    ];
    $this->caracs = (object) [];
  }

  // Méthodes requises par ActorInterface
  public function haveEffect(string $name): int
  {
    return 0;
  }

  public function addEffect($name, $duration = 0): void
  {
    // Implémentation vide pour le mock
  }

  public function endEffect(string $name): void
  {
    // Implémentation vide pour le mock
  }

  public function have_option(string $name): int
  {
    return 0;
  }

  public function get_caracs(bool $nude = false): bool
  {
    return true;
  }

  public function getCoords(): object
  {
    return (object) [
      'x' => 0,
      'y' => 0,
      'z' => 0,
      'plan' => 'test_plan'
    ];
  }

  public function getRemaining(string $trait): int
  {
    return 10;
  }

  public function equip(Item $item, bool $doNotRefresh = false): EquipResult
  {
    return EquipResult::DoNothing;
  }

  public function getMunition(Item $object, bool $equiped = false): ?Item
  {
    return null;
  }

  public function putBonus($bonus): bool
  {
    return true;
  }

  public function put_malus($malus): void
  {
    // Implémentation vide pour le mock
  }

  public function putFat($bonus): void
  {
    // Implémentation vide pour le mock
  }

  public function go($goCoords)
  {
    // Implémentation vide pour le mock
  }
}
