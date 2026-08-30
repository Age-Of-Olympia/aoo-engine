<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wear engine — two clocks, one table.
 *
 * # The turn, for what wears by being used
 *
 * Boots and tools keep the original model
 * (docs/design-items-instances.md §3.4): an event during the turn ARMS
 * (arm(): wear_pending = 1 on the worn exemplars whose catalogue
 * declares the trigger), and the decrement lands ONCE at new-turn
 * processing (applyNewTurnWear()). Ten steps in a turn wear the boots
 * once. The surviving triggers are `move` and `usage`.
 *
 * # The event, for combat
 *
 * Striking, taking a hit and dying wear RIGHT AWAY: a die roll cannot be
 * deferred to the turn boundary without storing the roll, and a death
 * does not wait. The three rules (decided with the team):
 *
 *   - striking wears the attacker's WEAPON;
 *   - taking a hit scatters 1D3 points at random over the PROTECTIONS;
 *   - dying costs 1D3 to EVERY object still worn, ammunition excepted —
 *     after the loot has fallen, so on what is left.
 *
 * These rules apply BY DEFAULT, with nothing to configure: what an
 * object is reads from its slot (see profileExpression()), and a special
 * object opts out by carrying `wear_profile = 'none'`.
 *
 * `wear_rate` no longer says "per turn" but HOW MUCH this object loses
 * per wear point received: the gladius costs 3 a swing where an ordinary
 * blade costs 1. Its floor is 1 — not wearing at all is said by
 * `wear_profile = 'none'`, not by a zero multiplier.
 *
 * Thresholds (décision équipe): 0 = brisé — the item stays worn but
 * stops contributing caracs (get_caracs skips it); < 0 = détruit —
 * possible only through bigger immediate decrements (DamageObject),
 * never through wear, which floors at 0.
 */
class WearService extends BaseService
{
    /** What the object is as far as combat goes. '' = read from the slot. */
    public const PROFILE_AUTO = '';
    public const PROFILE_WEAPON = 'weapon';
    public const PROFILE_PROTECTION = 'protection';
    public const PROFILE_NEUTRAL = 'neutral';
    public const PROFILE_NONE = 'none';

    /** @var list<string> values the workbench accepts, in display order */
    public const PROFILES = [
        self::PROFILE_AUTO,
        self::PROFILE_WEAPON,
        self::PROFILE_PROTECTION,
        self::PROFILE_NEUTRAL,
        self::PROFILE_NONE,
    ];

    /**
     * Slots that make a weapon when the catalogue says nothing. `main2` is
     * not among them: it holds a shield today. An off-hand weapon, when
     * two-handed fighting lands, will declare itself `weapon`.
     */
    private const AUTO_WEAPON_SLOTS = ['main1', 'deuxmains'];

    /** Neither protection nor weapon: worn by death alone. */
    private const AUTO_NEUTRAL_SLOTS = ['doigt'];

    /**
     * Outside every combat wear rule. The model already guarantees it — a
     * quiver stays a STACK in players_items and has no exemplar to take
     * durability from — but the rule says so, so the rule is written: the
     * day a munition becomes an exemplar, it stays excepted.
     */
    private const AUTO_NONE_SLOTS = ['munition'];

    private EntityManagerInterface $entityManager;

    /** Injectable die — same injection point as PlantsService. */
    private static ?\Classes\Dice $dice = null;

    public static function setDiceForTests(?\Classes\Dice $dice): void
    {
        self::$dice = $dice;
    }

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /** One roll of a $sides-sided die. */
    private static function roll(int $sides): int
    {
        $dice = self::$dice ?? new \Classes\Dice(max(1, $sides));

        return (int) $dice->roll(1)[0];
    }

    /**
     * The EFFECTIVE profile of an exemplar, in SQL: the column when it is
     * filled, the slot otherwise. Written once here because all three
     * rules read it, and a rule classifying objects differently from the
     * others is a bug waiting its turn.
     */
    private static function profileExpression(): string
    {
        $in = static fn(array $slots): string => "'" . implode("','", $slots) . "'";

        return "IF(it.wear_profile <> '', it.wear_profile,
                   CASE
                     WHEN e.slot IN (" . $in(self::AUTO_WEAPON_SLOTS) . ") THEN '" . self::PROFILE_WEAPON . "'
                     WHEN e.slot IN (" . $in(self::AUTO_NEUTRAL_SLOTS) . ") THEN '" . self::PROFILE_NEUTRAL . "'
                     WHEN e.slot IN (" . $in(self::AUTO_NONE_SLOTS) . ") THEN '" . self::PROFILE_NONE . "'
                     ELSE '" . self::PROFILE_PROTECTION . "'
                   END)";
    }

    /**
     * A trigger event happened for this player during the current turn:
     * arm every EQUIPPED instance whose catalog declares the trigger.
     * Cheap and idempotent within a turn (flag write).
     *
     * NOT routed through Classes\Db, so simulation must be guarded by
     * the CALLER (the executor's outcome hooks check isSimulated()).
     */
    public function arm(int $playerId, string $trigger): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE item_instances i
             JOIN players e ON e.id = i.entity_id
             JOIN items it ON it.id = i.item_id
             " . ItemInstanceService::WEAR_JOIN . "
             SET i.wear_pending = 1
             WHERE e.holder_id = ?
               AND e.slot != ''
               AND e.slot NOT IN (" . ItemInstanceService::heldElsewhereSlots() . ")
               AND i.destroyed = 0
               AND it.durability_max + COALESCE(wear.n, 0) > 0
               AND FIND_IN_SET(?, it.wear_triggers)",
            [$playerId, $trigger]
        );
    }

    /**
     * The WORN exemplars still whole, with their combat profile.
     *
     * @param list<string> $profiles the profiles wanted
     * @return list<array<string, mixed>>
     */
    private function wearableEquipment(int $playerId, array $profiles): array
    {
        if ($profiles === []) {
            return [];
        }

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT i.id, i.entity_id, " . ItemInstanceService::WEAR_SELECT . ",
                    i.custom_name, it.name AS catalog_name, it.wear_rate
             FROM item_instances i
             JOIN players e ON e.id = i.entity_id
             JOIN items it ON it.id = i.item_id
             " . ItemInstanceService::WEAR_JOIN . "
             WHERE e.holder_id = ?
               AND e.slot != ''
               AND e.slot NOT IN (" . ItemInstanceService::heldElsewhereSlots() . ")
               AND i.destroyed = 0
               AND it.durability_max + COALESCE(wear.n, 0) > 0
               AND " . self::profileExpression() . " IN (?)
             ORDER BY i.id",
            [$playerId, $profiles],
            [1 => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }

    /**
     * Take $points wear points off an exemplar and return the narrative
     * line, or null if it lost nothing. One point costs `wear_rate`: the
     * catalogue says the fragility, the rule says the frequency.
     *
     * @param array<string, mixed> $row
     */
    private function spend(array $row, int $points): ?string
    {
        $before = (int) $row['durability'];
        $after = max(0, $before - $points * (int) $row['wear_rate']);

        if ($after === $before) {
            return null;
        }

        /* Wear is a deficit, like every other wound: the row carries how
         * far below its maximum the exemplar sits. */
        $this->entityManager->getConnection()->executeStatement(
            "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)
             ON DUPLICATE KEY UPDATE n = VALUES(n)",
            [(int) $row['entity_id'], $after - (int) $row['durability_max']]
        );

        $label = ItemInstanceService::label($row['custom_name'], (string) $row['catalog_name']);

        if ($after === 0) {
            return $label . ' <span class="ra ra-shattered-sword"></span> s\'est <b>brisé</b> !';
        }

        return $label . ' s\'use (−' . ($before - $after) . ').';
    }

    /**
     * Striking wears the weapon. One blow, one point — the pace is that of
     * the blows landed, not that of the turns: this is what tells a
     * swordsman from an armed stroller.
     *
     * @return string[] narrative lines
     */
    public function wearWeaponOnAttack(int $playerId): array
    {
        $recap = [];

        foreach ($this->wearableEquipment($playerId, [self::PROFILE_WEAPON]) as $row) {
            $line = $this->spend($row, 1);

            if ($line !== null) {
                $recap[] = $line;
            }
        }

        return $recap;
    }

    /**
     * Taking a hit wears the protections: 1D3 points SCATTERED one by one,
     * each onto a piece drawn at random.
     *
     * Scattering rather than hitting every piece is what the rule says,
     * and what makes a full harness protective: three points over six
     * pieces dilute, the same three on a bare chest go through.
     *
     * @return string[] narrative lines
     */
    public function wearProtectionOnHit(int $playerId): array
    {
        $worn = $this->wearableEquipment($playerId, [self::PROFILE_PROTECTION]);

        if ($worn === []) {
            return [];
        }

        /* Points accumulate per piece BEFORE writing: two points landing
         * on the same helm make one "−2" line, not two writes whose second
         * ignores the first. */
        $points = [];

        /* The roll is made ONCE: leaving it in the loop condition rolls
         * again every pass, and the point count becomes that of the last
         * roll rather than the first. */
        $toScatter = self::roll(3);

        for ($i = 0; $i < $toScatter; $i++) {
            $index = self::roll(count($worn)) - 1;
            $points[$index] = ($points[$index] ?? 0) + 1;
        }

        $recap = [];

        foreach ($points as $index => $n) {
            $line = $this->spend($worn[$index], $n);

            if ($line !== null) {
                $recap[] = $line;
            }
        }

        return $recap;
    }

    /**
     * Dying wears everything still worn — ammunition excepted, and each
     * with its own 1D3 roll.
     *
     * To be called AFTER the loot has fallen: the rule speaks of what is
     * left, and what lies on the ground is no longer the dead player's.
     *
     * @return string[] narrative lines
     */
    public function wearEverythingOnDeath(int $playerId): array
    {
        $recap = [];

        $worn = $this->wearableEquipment($playerId, [
            self::PROFILE_WEAPON,
            self::PROFILE_PROTECTION,
            self::PROFILE_NEUTRAL,
        ]);

        foreach ($worn as $row) {
            $line = $this->spend($row, self::roll(3));

            if ($line !== null) {
                $recap[] = $line;
            }
        }

        return $recap;
    }

    /**
     * New-turn pass: apply the pending wear of every armed instance the
     * player owns, floor at 0 (brisé), clear the flags.
     *
     * @return string[] recap lines for the new-turn screen / log
     */
    public function applyNewTurnWear(int $playerId): array
    {
        $conn = $this->entityManager->getConnection();

        $armed = $conn->fetchAllAssociative(
            "SELECT i.id, i.entity_id, " . ItemInstanceService::WEAR_SELECT . ",
                    i.custom_name, it.name AS catalog_name, it.wear_rate
             FROM item_instances i
             JOIN players e ON e.id = i.entity_id
             JOIN items it ON it.id = i.item_id
             " . ItemInstanceService::WEAR_JOIN . "
             WHERE e.holder_id = ? AND i.wear_pending = 1 AND i.destroyed = 0",
            [$playerId]
        );

        $recap = [];
        foreach ($armed as $row) {
            $before = (int) $row['durability'];
            $after = max(0, $before - (int) $row['wear_rate']);

            /* Wear is a deficit now, like every other wound: the row carries
             * how far below its maximum the exemplar sits. */
            $conn->executeStatement(
                "INSERT INTO players_bonus (player_id, name, n) VALUES (?, 'pv', ?)
                 ON DUPLICATE KEY UPDATE n = VALUES(n)",
                [(int) $row['entity_id'], $after - (int) $row['durability_max']]
            );
            $conn->executeStatement(
                'UPDATE item_instances SET wear_pending = 0 WHERE id = ?',
                [(int) $row['id']]
            );

            $label = ItemInstanceService::label($row['custom_name'], (string) $row['catalog_name']);

            if ($after === 0 && $before > 0) {
                $recap[] = $label . ' <span class="ra ra-shattered-sword"></span> s\'est <b>brisé</b> !';
            } else {
                $recap[] = $label . ' s\'use (−' . ($before - $after) . ').';
            }
        }

        return $recap;
    }
}
