<?php

namespace Tests\Action\Mock;

use App\Enum\EquipResult;
use App\Interface\ActorInterface;
use Classes\Item;

class PlayerMock implements ActorInterface
{
    public $id;
    public $data;
    public $upgrades;
    public $caracs;
    public object $coords;
    public object $emplacements;
    private array $effects = [];
    private array $options = [];
    private array $remainingTraits = [];

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
            'energie' => 5,
        ];
        $this->caracs = (object) [
            'cc' => 5,
            'ct' => 5,
            'f' => 5,
            'e' => 5,
            'agi' => 5,
            'fm' => 5,
            'm' => 5,
            'r' => 5,
            'pm' => 10,
            'pv' => 20,
            'a' => 1,
            'mvt' => 3,
            'p' => 3
        ];
        $this->coords = (object) [
            'x' => 0,
            'y' => 0,
            'z' => 0,
            'plan' => 'test_plan'
        ];
        $this->emplacements = (object) [];
        $this->remainingTraits = [
            'pm' => 10,
            'pv' => 20,
            'a' => 1,
            'mvt' => 3,
            'energie' => 5
        ];
    }

    public function getId(): int {
        return $this->id;
    }

    public function setCoords(int $x, int $y, int $z, string $plan): void
    {
        $this->coords = (object) [
            'x' => $x,
            'y' => $y,
            'z' => $z,
            'plan' => $plan
        ];
    }

    public function setRemaining(string $trait, int $value): void
    {
        $this->remainingTraits[$trait] = $value;
    }

    public function equipWeapon(string $weaponType, string $location): void
    {
        $weaponData = (object) [
            'name' => ucfirst($weaponType) . ' Test',
            'subtype' => $weaponType,
            'emplacement' => $location
        ];
        
        $weapon = $this->createMockItem($weaponData);
        $this->emplacements->{$location} = $weapon;
    }

    public function equipItemWithSpellMalus(string $location, string $itemName): void
    {
        $itemData = (object) [
            'name' => $itemName,
            'emplacement' => $location,
            'spellMalus' => true
        ];
        
        $item = $this->createMockItem($itemData);
        $this->emplacements->{$location} = $item;
    }

    public function equipItemWithoutSpellMalus(string $location): void
    {
        $itemData = (object) [
            'name' => 'Normal Item',
            'emplacement' => $location
        ];
        
        $item = $this->createMockItem($itemData);
        $this->emplacements->{$location} = $item;
    }

    private function createMockItem($itemData): object
    {
        return (object) [
            'id' => rand(1000, 9999),
            'data' => $itemData,
            'row' => (object) ['name' => $itemData->name ?? 'Test Item']
        ];
    }

    public function removeEffect(string $effectName): void
    {
        unset($this->effects[$effectName]);
    }

    // Implémentation des méthodes de ActorInterface
    public function haveEffect(string $name): int
    {
        return isset($this->effects[$name]) && $this->effects[$name] > time() ? 1 : 0;
    }

    public function addEffect($name, $duration = 0): void
    {
        $endTime = $duration > 0 ? time() + $duration : time() + 3600;
        $this->effects[$name] = $endTime;
    }

    public function endEffect(string $name): void
    {
        unset($this->effects[$name]);
    }

    public function have_effects_to_purge(): bool
    {
        $currentTime = time();
        foreach ($this->effects as $endTime) {
            if ($endTime <= $currentTime && $endTime > 0) {
                return true;
            }
        }
        return false;
    }

    public function have_option(string $name): int
    {
        return isset($this->options[$name]) && $this->options[$name] ? 1 : 0;
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
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
        return $this->remainingTraits[$trait] ?? $this->caracs->{$trait} ?? 0;
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
        foreach ($bonus as $trait => $value) {
            if (isset($this->remainingTraits[$trait])) {
                $this->remainingTraits[$trait] += $value;
            }
        }
        return true;
    }

    public function put_malus($malus): void
    {
        $this->data->malus += $malus;
    }

    public function putEnergie($energie): void
    {
        $this->data->energie = max(0, $this->data->energie + $energie);
        $this->put_malus(1);
    }

    public function go($goCoords)
    {
        // Mock implementation
    }

    public function purge_effects()
    {
      // Mock implem
    }

    public function get_action_xp($target)
    {
        return 5; // Valeur par défaut pour les tests
    }

    public function get_data(bool $forceRefresh = true)
    {
        return $this->data;
    }

    public function get_upgrades()
    {
        return $this->upgrades ?? (object) ['a' => 0]; // Mock upgrades
    }

    public function setUpgrades($upgrades): void
    {
        $this->upgrades = $upgrades;
    }

    // Méthodes utilitaires pour les tests
    public function getEffects(): array
    {
        return $this->effects;
    }

    public function clearEffects(): void
    {
        $this->effects = [];
    }

    public function setCarac(string $trait, int $value): void
    {
        $this->caracs->{$trait} = $value;
    }

    public function put_xp(int $xp): void
    {
        $this->data->xp = ($this->data->xp ?? 0) + $xp;
    }

    public function put_assist($target, $damages): void
    {
        // Mock implementation for assists
    }

    public function add_action($name, $charges=false)
    {
        // Mock implementation for assists
    }
    public function add_option($name)
    {
        // Mock implementation for assists
    }
    public function get_options()
    {
        // Mock implementation for assists
    }

    public static function refresh_list()
    {
        // Mock implementation for assists
    }
    public function get_row()
    {
        // Mock implementation for assists
    }
    public function get_effects()
    {
        // Mock implementation for assists
    }
    public function get_actions()
    {
        // Mock implementation for assists
    }
    public function move_player($coords)
    {
        // Mock implementation for assists
    }
    public function check_missive_permission($target)
    {
        // Mock implementation for assists
    }
}
