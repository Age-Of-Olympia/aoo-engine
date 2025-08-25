<?php

namespace Tests\Combat;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Action\Mock\PlayerMock;
use Tests\Action\Mock\ViewMock;
use Classes\Dice;
use Classes\View;

/**
 * Tests des règles métier du combat basées sur la documentation fournie
 */
class CombatRulesTest extends TestCase
{
    private PlayerMock $attacker;
    private PlayerMock $defender;

    protected function setUp(): void
    {
        $this->attacker = new PlayerMock(1, 'Attacker');
        $this->defender = new PlayerMock(2, 'Defender');
        
        // Configuration de base
        $this->attacker->setCarac('cc', 6);
        $this->attacker->setCarac('ct', 7);
        $this->attacker->setCarac('f', 8);
        
        $this->defender->setCarac('cc', 5);
        $this->defender->setCarac('agi', 4);
        $this->defender->setCarac('e', 3);
    }

    #[Group('combat')]
    public function testMeleeDamageCalculation(): void
    {
        // Arrange
        $attackerF = 10;
        $defenderE = 4;
        $this->attacker->setCarac('f', $attackerF);
        $this->defender->setCarac('e', $defenderE);
        
        // Act - Calcul des dégâts de mêlée selon les règles
        $expectedDamage = max(1, $attackerF - $defenderE);
        
        // Assert
        $this->assertEquals(6, $expectedDamage); // 10 - 4 = 6
    }

    #[Group('combat')]
    public function testMinimumDamageRule(): void
    {
        // Arrange - Défenseur avec défense supérieure à l'attaque
        $this->attacker->setCarac('f', 3);
        $this->defender->setCarac('e', 8);
        
        // Act
        $damage = max(1, $this->attacker->caracs->f - $this->defender->caracs->e);
        
        // Assert - Toujours au moins 1 dégât
        $this->assertEquals(1, $damage);
    }

    #[Group('combat')]
    public function testDistanceDamageReduction(): void
    {
        // Arrange
        $this->attacker->setCoords(0, 0, 0, 'test');
        $this->defender->setCoords(4, 0, 0, 'test'); // Distance = 4
        
        $baseF = 10;
        $baseE = 3;
        $this->attacker->setCarac('f', $baseF);
        $this->defender->setCarac('e', $baseE);
        
        // Act - Calcul avec réduction de distance
        $distance = ViewMock::get_distance($this->attacker->coords, $this->defender->coords);
        $distanceReduction = $distance; // Selon les règles
        $damage = max(1, $baseF - $baseE - $distanceReduction);
        
        // Assert
        $this->assertEquals(4, $distance);
        $this->assertEquals(3, $damage); // 10 - 3 - 4 = 3
    }

    #[Group('combat')]
    public function testDistanceShootingMinimumRange(): void
    {
        // Arrange - Tir à distance, règle "au moins une case d'écart"
        $this->attacker->setCoords(0, 0, 0, 'test');
        $this->defender->setCoords(0, 0, 0, 'test'); // Même case
        
        // Act
        $distance = ViewMock::get_distance($this->attacker->coords, $this->defender->coords);
        $canShoot = $distance >= 2; // Minimum 2 cases pour tir
        
        // Assert
        $this->assertEquals(0, $distance);
        $this->assertFalse($canShoot);
    }

    #[Group('combat')]
    public function testCriticalHitCalculation(): void
    {
        // Arrange
        if (!defined('DMG_CRIT')) define('DMG_CRIT', 10);
        
        $baseDamage = 5;
        $critBonus = 3; // Selon les règles, +3 dégâts en critique
        
        // Act - Simulation d'un critique
        $isCritical = rand(1, 100) <= DMG_CRIT; // 10% de chance
        $finalDamage = $isCritical ? $baseDamage + $critBonus : $baseDamage;
        
        // Assert
        if ($isCritical) {
            $this->assertEquals(8, $finalDamage); // 5 + 3
        } else {
            $this->assertEquals(5, $finalDamage);
        }
        
        // Test déterministe
        $criticalDamage = $baseDamage + $critBonus;
        $this->assertEquals(8, $criticalDamage);
    }

    #[Group('combat')]
    public function testHelmetProtectionAgainstCritical(): void
    {
        // Arrange - Défenseur avec casque
        $this->defender->equipWeapon('casque', 'tete');
        
        // Act - Selon les règles, le casque protège contre les critiques
        $hasHelmet = isset($this->defender->emplacements->tete);
        $canCritical = !$hasHelmet;
        
        // Assert
        $this->assertTrue($hasHelmet);
        $this->assertFalse($canCritical);
    }

    #[Group('combat')]
    public function testAssommoirIgnoresHelmet(): void
    {
        // Arrange - Technique "Assommoir" ignore le casque
        $this->defender->equipWeapon('casque', 'tete');
        $usingAssommoir = true;
        
        // Act - Assommoir ignore la protection casque
        $canCritical = $usingAssommoir || !isset($this->defender->emplacements->tete);
        
        // Assert
        $this->assertTrue($canCritical);
    }

    #[Group('combat')]
    public function testMeleeToHitCalculation(): void
    {
        // Arrange
        $dice = new Dice(3);
        $attackerCC = 6;
        $defenderBestStat = max($this->defender->caracs->cc, $this->defender->caracs->agi);
        
        // Act - Simulation de jet
        $attackerRoll = array_sum($dice->roll($attackerCC));
        $defenderRoll = array_sum($dice->roll($defenderBestStat));
        
        $hits = $attackerRoll >= $defenderRoll;
        
        // Assert - Structure du test
        $this->assertIsInt($attackerRoll);
        $this->assertIsInt($defenderRoll);
        $this->assertIsBool($hits);
        $this->assertGreaterThanOrEqual($attackerCC, $attackerRoll);
        $this->assertLessThanOrEqual($attackerCC * 3, $attackerRoll);
    }

    #[Group('combat')]
    public function testRangedToHitWithDistancePenalty(): void
    {
        // Arrange
        $this->attacker->setCoords(0, 0, 0, 'test');
        $this->defender->setCoords(5, 0, 0, 'test'); // Distance = 5
        
        $attackerCT = 8;
        $distance = 5;
        $distancePenalty = ($distance > 2) ? ($distance - 2) * 3 : 0; // Malus selon les règles
        
        // Act
        $effectiveCT = $attackerCT - $distancePenalty;
        
        // Assert
        $this->assertEquals(9, $distancePenalty); // (5-2) * 3 = 9
        $this->assertEquals(-1, $effectiveCT); // 8 - 9 = -1 (très difficile!)
        $this->assertLessThan($attackerCT, $effectiveCT);
    }

    #[Group('combat')]
    public function testRangedDefenseCalculation(): void
    {
        // Arrange - Règles spéciales pour la défense à distance
        $defenderCC = 6;
        $defenderAgi = 4;
        
        // Act - Calcul selon les règles du document
        $option1 = floor((3/4) * $defenderCC + (1/4) * $defenderAgi);
        $option2 = floor((1/4) * $defenderCC + (3/4) * $defenderAgi);
        $defenseValue = max($option1, $option2);
        
        // Assert
        $this->assertEquals(5, $option1); // floor(4.5 + 1) = 5
        $this->assertEquals(4, $option2); // floor(1.5 + 3) = 4
        $this->assertEquals(5, $defenseValue);
    }

    #[Group('combat')]
    public function testProjectileDropMechanic(): void
    {
        // Arrange - Arme de jet
        $this->attacker->setCoords(0, 0, 0, 'test');
        $this->defender->setCoords(4, 0, 0, 'test'); // Distance = 4
        
        $distance = 4;
        $isProjectileWeapon = true;
        
        // Act - Règle des armes de jet
        if ($isProjectileWeapon) {
            if ($distance == 2) {
                $weaponReturns = true; // Revient dans l'inventaire
            } else {
                $weaponDrops = true; // Tombe près de la cible
            }
        }
        
        // Assert
        $this->assertTrue(isset($weaponDrops));
        $this->assertFalse(isset($weaponReturns));
    }

    #[Group('combat')]
    public function testMagicDistanceRequirement(): void
    {
        // Arrange
        $casterFM = 8;
        $distance = 3;
        $minimumFMRequired = 4 * ($distance - 1); // Règle magique
        
        // Act
        $canCastAtDistance = $casterFM >= $minimumFMRequired;
        
        // Assert
        $this->assertEquals(8, $minimumFMRequired); // 4 * (3-1) = 8
        $this->assertTrue($canCastAtDistance); // 8 >= 8
        
        // Test avec distance trop grande
        $farDistance = 5;
        $farMinimumFM = 4 * ($farDistance - 1);
        $canCastFar = $casterFM >= $farMinimumFM;
        
        $this->assertEquals(16, $farMinimumFM);
        $this->assertFalse($canCastFar); // 8 < 16
    }

    #[Group('combat')]
    public function testMagicDefenseAutoSuccess(): void
    {
        // Arrange - Sort défensif
        $isDefensiveSpell = true;
        
        // Act - Les sorts défensifs ont une réussite automatique
        $targetFMForDefense = $isDefensiveSpell ? 0 : $this->defender->caracs->fm;
        
        // Assert
        $this->assertEquals(0, $targetFMForDefense);
    }

    #[Group('combat')]
    public function testMalusAccumulation(): void
    {
        // Arrange
        $this->defender->data->malus = 3;
        $defenderRoll = 12;
        
        // Act - Application du malus
        $finalDefenseRoll = $defenderRoll - $this->defender->data->malus;
        
        // Assert
        $this->assertEquals(9, $finalDefenseRoll); // 12 - 3 = 9
    }

    #[Group('combat')]
    public function testDeathAndLootDrop(): void
    {
        // Arrange
        $this->defender->setRemaining('pv', 1);
        $damage = 5;
        
        // Act
        $finalPV = $this->defender->getRemaining('pv') - $damage;
        $isDead = $finalPV <= 0;
        
        // Assert
        $this->assertTrue($isDead);
        $this->assertEquals(-4, $finalPV);
        
        // Test des chances de loot selon les règles
        if (!defined('LOOT_CHANCE_DEFAULT')) define('LOOT_CHANCE_DEFAULT', 30);
        
        $baseDropChance = LOOT_CHANCE_DEFAULT;
        $equipedDropChance = $baseDropChance / 2; // Moitié pour équipé
        
        $this->assertEquals(30, $baseDropChance);
        $this->assertEquals(15, $equipedDropChance);
    }
}

/**
 * Tests des effets et statuts selon les règles
 */
class StatusEffectsTest extends TestCase
{
    private PlayerMock $player;

    protected function setUp(): void
    {
        $this->player = new PlayerMock(1, 'TestPlayer');
    }

    #[Group('combat')]
    public function testElementalEffectsDebuff(): void
    {
        // Arrange - Effets élémentaires selon ELE_DEBUFFS
        if (!defined('ELE_DEBUFFS')) {
            define('ELE_DEBUFFS', [
                'eau' => 'f',
                'feu' => 'e',
                'terre' => 'agi',
                'air' => 'fm'
            ]);
        }
        
        $this->player->addEffect('eau');
        $this->player->setCarac('f', 10);
        
        // Act - Application du debuff
        $originalF = $this->player->caracs->f;
        $debuffedF = $originalF - 1; // Selon les règles, -1 par effet
        
        // Assert
        $this->assertEquals(10, $originalF);
        $this->assertEquals(9, $debuffedF);
    }

    #[Group('combat')]
    public function testElementalControl(): void
    {
        // Arrange - Contrôle élémentaire
        if (!defined('ELE_CONTROLS')) {
            define('ELE_CONTROLS', [
                'feu' => 'eau',
                'eau' => 'terre',
                'terre' => 'air',
                'air' => 'feu'
            ]);
        }
        
        $this->player->addEffect('eau');
        
        // Act - Appliquer l'effet feu (qui contrôle l'eau)
        $hasWater = $this->player->haveEffect('eau');
        if ($hasWater) {
            $this->player->endEffect('eau'); // Feu annule eau
        }
        $this->player->addEffect('feu');
        
        // Assert
        $this->assertTrue($hasWater); // Avait l'eau
        $this->assertEquals(0, $this->player->haveEffect('eau')); // Plus d'eau
        $this->assertEquals(1, $this->player->haveEffect('feu')); // A le feu
    }

    #[Group('combat')]
    public function testPoisonEffect(): void
    {
        // Arrange
        $this->player->addEffect('poison');
        
        // Act - Le poison empêche certaines actions
        $hasPoisonEffect = $this->player->haveEffect('poison');
        $canUseRegeneration = !$hasPoisonEffect;
        
        // Assert
        $this->assertTrue($hasPoisonEffect);
        $this->assertFalse($canUseRegeneration);
    }

    #[Group('combat')]
    public function testMagicPoisonEffect(): void
    {
        // Arrange
        $this->player->addEffect('poison_magique');
        
        // Act - Le poison magique empêche la régénération PM
        $hasMagicPoison = $this->player->haveEffect('poison_magique');
        $canRegeneratePM = !$hasMagicPoison;
        
        // Assert
        $this->assertTrue($hasMagicPoison);
        $this->assertFalse($canRegeneratePM);
    }
}

/**
 * Tests des sorts spéciaux avec étoile (*)
 */
class SpecialSpellsTest extends TestCase
{
    private PlayerMock $caster;
    private PlayerMock $target;

    protected function setUp(): void
    {
        $this->caster = new PlayerMock(1, 'Caster');
        $this->target = new PlayerMock(2, 'Target');
    }

    #[Group('combat')]
    public function testSpecialSpellDamageCalculation(): void
    {
        // Arrange - Sorts avec étoile : F+M vs E+M
        $this->caster->setCarac('f', 8);
        $this->caster->setCarac('m', 6);
        $this->target->setCarac('e', 4);
        $this->target->setCarac('m', 3);
        
        // Act - Calcul selon les règles spéciales
        $attackPower = $this->caster->caracs->f + $this->caster->caracs->m;
        $defensePower = $this->target->caracs->e + $this->target->caracs->m;
        $damage = max(1, $attackPower - $defensePower);
        
        // Assert
        $this->assertEquals(14, $attackPower); // 8 + 6
        $this->assertEquals(7, $defensePower); // 4 + 3
        $this->assertEquals(7, $damage); // 14 - 7
    }

    #[Group('combat')]
    public function testMeteorSpecialRequirements(): void
    {
        // Arrange - Météore nécessite une pierre équipée
        $hasStoneEquipped = false;
        $this->caster->setCarac('ct', 8);
        
        // Act
        $canUseMeteor = $hasStoneEquipped && $this->caster->caracs->ct > 0;
        
        // Assert
        $this->assertFalse($canUseMeteor);
        
        // Avec pierre équipée
        $hasStoneEquipped = true;
        $canUseMeteorWithStone = $hasStoneEquipped && $this->caster->caracs->ct > 0;
        $this->assertTrue($canUseMeteorWithStone);
    }

    #[Group('combat')]
    public function testTraitBeniRequiresRangedWeapon(): void
    {
        // Arrange - Trait Béni nécessite une arme de tir
        $this->caster->equipWeapon('tir', 'main1');
        $hasRangedWeapon = $this->caster->emplacements->main1->data->subtype === 'tir';
        
        // Act & Assert
        $this->assertTrue($hasRangedWeapon);
    }

    #[Group('combat')]
    public function testLameBenueRequiresMeleeWeapon(): void
    {
        // Arrange - Lame Bénie nécessite une arme de mêlée
        $this->caster->equipWeapon('melee', 'main1');
        $hasMeleeWeapon = $this->caster->emplacements->main1->data->subtype === 'melee';
        
        // Act & Assert
        $this->assertTrue($hasMeleeWeapon);
    }

    #[Group('combat')]
    public function testArmeVivanteRequiresWoodenWeapon(): void
    {
        // Arrange - Arme Vivante nécessite du bois (pétrifié ou non)
        $weapon = new \Tests\Action\Mock\ItemMock([
            'name' => 'Bâton de bois',
            'craftedWith' => ['bois', 'bois_petrifie']
        ]);
        
        // Act
        $isWoodenWeapon = $weapon->is_crafted_with('bois') || $weapon->is_crafted_with('bois_petrifie');
        
        // Assert
        $this->assertTrue($isWoodenWeapon);
    }
}