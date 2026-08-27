<?php

namespace Tests\Action\Mock;

use App\Enum\EquipResult;
use App\Interface\ActorInterface;
use App\Service\PlayerPassiveService;
use Classes\Item;

class PlayerMock implements ActorInterface
{
  public $id;
  public $data;
  public $caracs;
  public $coords;
  public $playerPassiveService;

  /** @var array<string, int> */
  public array $effects = [];

  /** @var array<string, int> remaining points per trait; falls back to 10 */
  public array $remaining = [];

  /** @var array<int, object> passives returned by getPassives() */
  public array $passivesList = [];

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
      'malus' => 0,
      'antiBerserkTime' => 0,
    ];
    $this->caracs = (object) [];
    $this->coords = (object) ['x' => 0, 'y' => 0, 'z' => 0, 'plan' => 'test_plan'];
    $this->playerPassiveService = new PassiveServiceStub();
  }

  public function getEffectValue(string $name): ?int
  {
    return $this->effects[$name] ?? null;
  }

  /** @return list<\App\Entity\PlayerEffect> même forme que Player::getEffects() */
  public function getEffects(): array
  {
    $entries = [];
    foreach ($this->effects as $name => $value) {
      $entry = new \App\Entity\PlayerEffect();
      $entry->setName((string) $name);
      $entry->setValue((int) $value);
      $entries[] = $entry;
    }

    return $entries;
  }

  public function getId(): int {
    return $this->id;
  }

  public function isSimulated(): bool {
    return false;
  }

  // Méthodes requises par ActorInterface
  public function have_effect(string $name): int
  {
    return 0;
  }

  public function add_effect($name, $duration = 0): void
  {
    // Implémentation vide pour le mock
  }

  public function end_effect(string $name): void
  {
    // Implémentation vide pour le mock
  }

  public function have_effects_to_purge(): bool{
    return false;
  }

  public function have_option(string $name): int
  {
    return 0;
  }

  public function get_caracs(bool $nude = false): bool
  {
    return true;
  }

  public function getCoords(bool $refresh = true): object
  {
    return $this->coords;
  }

  public function getRemaining(string $trait): int
  {
    return $this->remaining[$trait] ?? 10;
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

  public function putEnergie($energie): void{

  }

  public function go($goCoords)
  {
    // Implémentation vide pour le mock
  }

  public function get_action_xp($target)
  {

  }

  public function get_data(bool $forceRefresh=true)
  {

  }

  public function get_upgrades()
  {

  }

  public function getPassives(int $id): array
  {
    return $this->passivesList;
  }

  public function getPlayerPassiveService(): PlayerPassiveService
  {
        return new PlayerPassiveService();
  }
    
  public function getEquipedItems(): array
  {
    return Item::get_equiped_list($this);
  }

  public function hasMagicalItemEquipped(): bool
  {
    return false;
  }

  /** @var array */
public array $mockedEffects = [];

public function getEquipedItemsEffects(): array
{
    return $this->mockedEffects;
}
}
