<?php

namespace Tests\Player;

use App\Service\WearService;
use Classes\Dice;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The three immediate wear rules, decided with the team:
 *
 *   - striking wears the weapon;
 *   - taking a hit scatters 1D3 points over the protections;
 *   - dying costs 1D3 to every object still worn, ammunition excepted.
 *
 * They apply with NO configuration — that is what tells them apart from
 * the per-turn engine, which stayed opt-in (WearEngineBaselineTest).
 * What each object is reads from its slot, and `wear_profile` exists
 * only for the cases where the slot lies, or to exempt an object.
 *
 * The die is injected: "at random" cannot be verified, but the spread a
 * given roll produces can.
 */
#[Group('items-baseline')]
class WearRulesTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        WearService::setDiceForTests(null);
        parent::tearDown();
    }

    /**
     * Wear the pieces and return [player, exemplars]. The slot decides the
     * combat role whenever `wear_profile` is left empty.
     */
    private function wearing(string $prefix, array $pieces): array
    {
        $player = $this->createRealPlayer($prefix);
        $player->get_caracs();

        $instances = [];

        foreach ($pieces as $label => $spec) {
            $item = $this->sowCatalogItem(
                'zz_' . $label . '_' . bin2hex(random_bytes(3)),
                array_merge([
                    'type' => 'equipement',
                    'stats_in_db' => 1,
                    'durability_max' => 100,
                ], $spec)
            );
            $item->get_data();
            $item->add_item($player, 1);
            $player->equip($item);

            $id = $this->instanceHeldBy((int) $player->id, (int) $item->id, true);
            $this->assertGreaterThan(0, $id, 'equipping ' . $label . ' must create the instance');
            $instances[$label] = $id;
        }

        return [$player, $instances];
    }

    public function testStrikingWearsTheWeaponWithNoConfigurationAtAll(): void
    {
        [$player, $worn] = $this->wearing('GmFrappeur', [
            'epee' => ['emplacement' => 'main1'],
        ]);

        (new WearService())->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(99, $this->remainingLifeOf($worn['epee']), 'un coup, un point');
    }

    /** Two blows, two points: the pace is the blows', not the turns'. */
    public function testEachStrikeCosts(): void
    {
        [$player, $worn] = $this->wearing('GmBretteur', [
            'epee' => ['emplacement' => 'main1'],
        ]);

        $wear = new WearService();
        $wear->wearWeaponOnAttack((int) $player->id);
        $wear->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(98, $this->remainingLifeOf($worn['epee']));
    }

    /** wear_rate is no longer a pace but a fragility. */
    public function testWearRateMultipliesWhatOnePointCosts(): void
    {
        [$player, $worn] = $this->wearing('GmGladius', [
            'epee' => ['emplacement' => 'main1', 'wear_rate' => 3],
        ]);

        (new WearService())->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(97, $this->remainingLifeOf($worn['epee']), 'trois fois plus fragile');
    }

    /** A helm is not a weapon: striking does not wear it. */
    public function testStrikingSparesTheProtections(): void
    {
        [$player, $worn] = $this->wearing('GmCasque', [
            'epee'   => ['emplacement' => 'main1'],
            'casque' => ['emplacement' => 'tete'],
        ]);

        (new WearService())->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(100, $this->remainingLifeOf($worn['casque']));
    }

    /**
     * Taking a hit scatters the points: the die gives 3 points, then picks
     * pieces 1, 1 and 2 — so −2 on the first and −1 on the second.
     */
    public function testTakingAHitScattersTheRollAcrossProtections(): void
    {
        [$player, $worn] = $this->wearing('GmEncaisse', [
            'casque'  => ['emplacement' => 'tete'],
            'plastron' => ['emplacement' => 'tronc'],
        ]);

        WearService::setDiceForTests(new LoadedDice([3, 1, 1, 2]));

        (new WearService())->wearProtectionOnHit((int) $player->id);

        $this->assertSame(98, $this->remainingLifeOf($worn['casque']), 'deux points sur le casque');
        $this->assertSame(99, $this->remainingLifeOf($worn['plastron']), 'un sur le plastron');
    }

    /** A shield in main2 takes the blows: it is not a weapon. */
    public function testAShieldInTheOffHandTakesHits(): void
    {
        [$player, $worn] = $this->wearing('GmBouclier', [
            'bouclier' => ['emplacement' => 'main2'],
        ]);

        WearService::setDiceForTests(new LoadedDice([2, 1, 1]));

        (new WearService())->wearProtectionOnHit((int) $player->id);

        $this->assertSame(98, $this->remainingLifeOf($worn['bouclier']));
    }

    /**
     * An off-hand weapon, when two-handed fighting lands, declares itself
     * `weapon` and stops taking blows — that is what the column is for.
     */
    public function testAnOffHandDeclaredWeaponStopsTakingHits(): void
    {
        [$player, $worn] = $this->wearing('GmMainGauche', [
            'dague' => ['emplacement' => 'main2', 'wear_profile' => WearService::PROFILE_WEAPON],
        ]);

        $wear = new WearService();
        WearService::setDiceForTests(new LoadedDice([3, 1, 1, 1]));
        $wear->wearProtectionOnHit((int) $player->id);

        $this->assertSame(100, $this->remainingLifeOf($worn['dague']), 'déclarée arme, elle n\'encaisse pas');

        $wear->wearWeaponOnAttack((int) $player->id);
        $this->assertSame(99, $this->remainingLifeOf($worn['dague']), 'mais elle s\'use en frappant');
    }

    /**
     * A ring does not parry: blows spare it, and everything lands on the
     * breastplate.
     *
     * Ammunition is not tested here because it CANNOT wear: it stays a
     * stack in players_items (equipping a quiver equips the lot, see
     * Player::equip) and so has no exemplar to take durability from. The
     * exception the rule names is already guaranteed by the model.
     */
    public function testRingsAreSparedByBlows(): void
    {
        [$player, $worn] = $this->wearing('GmAnneau', [
            'anneau'   => ['emplacement' => 'doigt'],
            'plastron' => ['emplacement' => 'tronc'],
        ]);

        WearService::setDiceForTests(new LoadedDice([3, 1, 1, 1]));

        (new WearService())->wearProtectionOnHit((int) $player->id);

        $this->assertSame(100, $this->remainingLifeOf($worn['anneau']), 'un anneau ne pare pas');
        $this->assertSame(97, $this->remainingLifeOf($worn['plastron']), 'tout est tombé sur le plastron');
    }

    /**
     * Dying: one 1D3 PER object still worn. The ring goes through it —
     * spared by blows, it is not spared by death, and that is exactly why
     * a third role exists beside "weapon" and "protection".
     */
    public function testDyingWearsEverythingLeftIncludingRings(): void
    {
        [$player, $worn] = $this->wearing('GmMort', [
            'epee'   => ['emplacement' => 'main1'],
            'casque' => ['emplacement' => 'tete'],
            'anneau' => ['emplacement' => 'doigt'],
        ]);

        WearService::setDiceForTests(new LoadedDice([3, 2, 1]));

        (new WearService())->wearEverythingOnDeath((int) $player->id);

        $this->assertSame(97, $this->remainingLifeOf($worn['epee']));
        $this->assertSame(98, $this->remainingLifeOf($worn['casque']));
        $this->assertSame(99, $this->remainingLifeOf($worn['anneau']), 'un anneau meurt avec son porteur');
    }

    /** The exemption exists for special objects: it holds against all three rules. */
    public function testAnExemptObjectNeverWearsFromCombat(): void
    {
        [$player, $worn] = $this->wearing('GmRelique', [
            'relique' => ['emplacement' => 'tronc', 'wear_profile' => WearService::PROFILE_NONE],
            'epee'    => ['emplacement' => 'main1', 'wear_profile' => WearService::PROFILE_NONE],
        ]);

        $wear = new WearService();
        WearService::setDiceForTests(new LoadedDice([3, 1, 1, 1]));

        $wear->wearWeaponOnAttack((int) $player->id);
        $wear->wearProtectionOnHit((int) $player->id);
        $wear->wearEverythingOnDeath((int) $player->id);

        $this->assertSame(100, $this->remainingLifeOf($worn['relique']));
        $this->assertSame(100, $this->remainingLifeOf($worn['epee']));
    }

    /**
     * A SPARE weapon in the bag does not wear: the rule speaks of what is
     * worn, and two sheathed swords do not blunt with every blow struck
     * with the third.
     */
    public function testASpareWeaponInTheBagIsSpared(): void
    {
        [$player, $worn] = $this->wearing('GmRechange', [
            'epee' => ['emplacement' => 'main1'],
        ]);

        $spare = $this->sowCatalogItem('zz_rechange_' . bin2hex(random_bytes(3)), [
            'type' => 'equipement', 'emplacement' => 'main1',
            'stats_in_db' => 1, 'durability_max' => 100,
        ]);
        $spare->get_data();
        $spare->add_item($player, 1);

        (new WearService())->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(99, $this->remainingLifeOf($worn['epee']), 'celle qui frappe s\'use');

        $spareInstance = $this->instanceHeldBy((int) $player->id, (int) $spare->id);
        if ($spareInstance > 0) {
            $this->assertSame(100, $this->remainingLifeOf($spareInstance), 'celle du sac, non');
        }
    }

    /** The floor stays 0: brisé, never détruit, through these rules. */
    public function testWearFloorsAtBroken(): void
    {
        [$player, $worn] = $this->wearing('GmBrise', [
            'epee' => ['emplacement' => 'main1', 'wear_rate' => 50],
        ]);

        $this->setRemainingLife($worn['epee'], 2);

        $recap = (new WearService())->wearWeaponOnAttack((int) $player->id);

        $this->assertSame(0, $this->remainingLifeOf($worn['epee']));
        $this->assertStringContainsString('brisé', $recap[0]);
    }
}

/** Scripted die: returns the given values in order, then 1. */
final class LoadedDice extends Dice
{
    /** @var list<int> */
    private array $queue;

    /** @param list<int> $values */
    public function __construct(array $values)
    {
        parent::__construct(max(1, max($values)));
        $this->queue = $values;
    }

    public function roll($d)
    {
        return [array_shift($this->queue) ?? 1];
    }
}
