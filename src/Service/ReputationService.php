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

    private Connection $conn;
    private ReputationSettingsService $settings;

    public function __construct(?Connection $conn = null, ?ReputationSettingsService $settings = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
        $this->settings = $settings ?? new ReputationSettingsService($this->conn);
    }

    /** What a forum post earns its author. */
    public function perPost(): float
    {
        return $this->settings->perPost();
    }

    /** What a cookie earns the author of the post; less from a faction mate. */
    public function perCookie(bool $sameFaction): float
    {
        return $sameFaction ? $this->settings->perCookieSameFaction() : $this->settings->perCookie();
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
        $step = $this->settings->rewardEvery() * self::SCALE;
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

    public function title(float $pr): string
    {
        return $this->settings->title($pr);
    }
}
