<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Classes\Forum;
use Doctrine\DBAL\Connection;

/**
 * The one gateway to reputation points (PR). `players.pr` stores tenths of a
 * PR so that small gains (a cookie is worth 0.3) stay whole numbers; that
 * scale never leaves this class. Everything public speaks real PR.
 */
final class ReputationService
{
    /** Stored units per PR. */
    private const SCALE = 10;

    /** Gains, in PR. */
    public const PER_POST = 1.0;
    public const PER_COOKIE = 0.3;
    public const PER_COOKIE_SAME_FACTION = 0.1;

    /** A forum reward is drawn each time the balance reaches a multiple of this many PR. */
    public const REWARD_EVERY = 5;

    /** Lower bound (PR) of each title, highest first. */
    private const TITLES = [
        2500 => 'Divin',
        1250 => 'Mythologique',
        500 => 'Légendaire',
        250 => 'Héroïque',
        100 => 'Populaire',
        25 => 'Connu',
    ];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** A raw `players.pr` value (SQL rows, `$player->data->pr`, Character::getPr()) in PR. */
    public static function fromStored(int $stored): float
    {
        return $stored / self::SCALE;
    }

    /** Rounded, not truncated: 0.7 * 10 is 6.999… in floating point. */
    private static function toStored(float $pr): int
    {
        return (int) round($pr * self::SCALE);
    }

    public function of(int $playerId): float
    {
        return self::fromStored((int) $this->conn->fetchOne('SELECT pr FROM players WHERE id = ?', [$playerId]));
    }

    public function set(int $playerId, float $pr): void
    {
        if (SimulationGuard::blocksWrite()) {
            return;
        }

        $this->conn->executeStatement('UPDATE players SET pr = ? WHERE id = ?', [self::toStored($pr), $playerId]);
    }

    /**
     * Credit (or, negative, debit) PR. Every reward milestone crossed upward
     * draws a forum reward; the milestone the balance already stands on was
     * rewarded when it was reached.
     */
    public function add(int $playerId, float $pr): void
    {
        $delta = self::toStored($pr);
        if ($delta === 0 || SimulationGuard::blocksWrite()) {
            return;
        }

        $before = $this->conn->transactional(function (Connection $conn) use ($playerId, $delta): int {
            $before = (int) $conn->fetchOne('SELECT pr FROM players WHERE id = ? FOR UPDATE', [$playerId]);
            $conn->executeStatement('UPDATE players SET pr = pr + ? WHERE id = ?', [$delta, $playerId]);

            return $before;
        });

        // Outside the transaction: the reward goes through the legacy connection
        $step = self::REWARD_EVERY * self::SCALE;
        for ($n = max(0, intdiv($before, $step)) + 1; $n * $step <= $before + $delta; $n++) {
            Forum::put_reward($playerId);
        }
    }

    public function remove(int $playerId, float $pr): void
    {
        $this->add($playerId, -$pr);
    }

    /** Whole PR shown to players: floored, never below 0. */
    public static function format(float $pr): string
    {
        return (string) max(0, (int) floor($pr));
    }

    public static function title(float $pr): string
    {
        foreach (self::TITLES as $threshold => $title) {
            if ($pr >= $threshold) {
                return $title;
            }
        }

        return 'Inconnu';
    }
}
