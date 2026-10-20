<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Factory\PlayerFactory;
use Doctrine\DBAL\Connection;

/**
 * A special event published by the team to many characters at once: one
 * `special` line in each recipient's events, written as the team wrote them
 * by hand (author and target the character, no plan, no cell, so only the
 * character sees it), then optional PV damage and an optional status.
 */
final class SpecialEventService
{
    public const RECIPIENTS_ACTIVE = 'active';
    public const RECIPIENTS_PLAYERS = 'players';
    public const RECIPIENTS_ALL = 'all';
    private const MAX_MATRICULES = 10000;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Ids of the characters concerned: every criterion given narrows them.
     *
     * $plan: a plan slug, or '' for the whole game.
     * $who: active players (logged in within INACTIVE_TIME), every player,
     * or every player and PNJ.
     * $area: a square on $plan, centre x/y on level z, radius in cells.
     * $matricules: matricules shown in game (display_id).
     *
     * @param ?array{x: int, y: int, z: int, radius: int} $area
     * @param list<int> $matricules
     * @return list<int>
     */
    public function recipients(string $plan, string $who, ?array $area = null, array $matricules = []): array
    {
        $types = $who === self::RECIPIENTS_ALL ? ['real', 'npc'] : ['real'];
        $sql = 'SELECT p.id FROM players p
                  LEFT JOIN accounts a ON a.player_id = p.id
                  LEFT JOIN coords c ON c.id = p.coords_id
                 WHERE p.player_type IN (?) ';
        $params = [$types];
        $paramTypes = [\Doctrine\DBAL\ArrayParameterType::STRING];

        if ($who === self::RECIPIENTS_ACTIVE) {
            $sql .= 'AND COALESCE(a.last_login_time, 0) >= ? ';
            $params[] = time() - INACTIVE_TIME;
            $paramTypes[] = \Doctrine\DBAL\ParameterType::INTEGER;
        }
        if ($plan !== '') {
            $sql .= 'AND c.plan = ? ';
            $params[] = $plan;
            $paramTypes[] = \Doctrine\DBAL\ParameterType::STRING;

            if ($area !== null) {
                $sql .= 'AND c.z = ? AND c.x BETWEEN ? AND ? AND c.y BETWEEN ? AND ? ';
                array_push($params, $area['z'], $area['x'] - $area['radius'], $area['x'] + $area['radius'],
                    $area['y'] - $area['radius'], $area['y'] + $area['radius']);
                array_push($paramTypes, ...array_fill(0, 5, \Doctrine\DBAL\ParameterType::INTEGER));
            }
        }
        if ($matricules !== []) {
            $sql .= 'AND p.display_id IN (?) ';
            $params[] = $matricules;
            $paramTypes[] = \Doctrine\DBAL\ArrayParameterType::INTEGER;
        }

        return array_map('intval', $this->conn->fetchFirstColumn($sql . 'ORDER BY p.id', $params, $paramTypes));
    }

    /**
     * Matricules typed as « 12, 15, 20-30 »: null when the text is not such a
     * list, or names more than MAX_MATRICULES of them.
     *
     * @return ?list<int>
     */
    public static function parseMatricules(string $text): ?array
    {
        $ids = [];
        foreach (preg_split('/[\s,;]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            if (!preg_match('/^(\d+)(?:-(\d+))?$/', $part, $m)) {
                return null;
            }
            $from = (int) $m[1];
            $to = isset($m[2]) ? (int) $m[2] : $from;
            if ($to < $from || $to - $from >= self::MAX_MATRICULES) {
                return null;
            }
            array_push($ids, ...range($from, $to));
        }
        $ids = array_values(array_unique($ids));

        return count($ids) > self::MAX_MATRICULES ? null : $ids;
    }

    /**
     * Writes the event to every recipient, then the damage and the status.
     *
     * $damage: PV taken, a number or, with $damageIsPercent, a share of the
     * character's max PV. Unless $mayKill, a character keeps at least 1 PV
     * (the status's own losses included); with it, one left at 0 PV dies.
     *
     * @param list<int> $recipients
     * @return array{published: int, deaths: int}
     */
    public function publish(
        array $recipients,
        string $text,
        int $damage = 0,
        bool $damageIsPercent = false,
        bool $mayKill = false,
        string $effect = '',
        int $effectTurns = 1
    ): array {
        if ($recipients === []) {
            return ['published' => 0, 'deaths' => 0];
        }

        $this->conn->executeStatement(
            "INSERT INTO players_logs (player_id, target_id, text, hiddenText, type, plan, time, coords_id, coords_computed)
             SELECT id, id, ?, '', 'special', '-', ?, NULL, NULL FROM players WHERE id IN (?)",
            [$text, time(), $recipients],
            [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        $deaths = 0;
        if ($damage > 0 || $effect !== '') {
            foreach ($recipients as $id) {
                $deaths += $this->strike($id, $damage, $damageIsPercent, $mayKill, $effect, $effectTurns, $text) ? 1 : 0;
            }
        }

        return ['published' => count($recipients), 'deaths' => $deaths];
    }

    /** Damage and status on one character; true when it died of them. */
    private function strike(int $id, int $damage, bool $damageIsPercent, bool $mayKill, string $effect, int $effectTurns, string $text): bool
    {
        $player = PlayerFactory::legacy($id);
        $player->get_data();
        $player->get_caracs();

        if ($damage > 0) {
            $taken = $damageIsPercent ? (int) ceil($player->caracs->pv * $damage / 100) : $damage;
            if (!$mayKill) {
                $taken = min($taken, max(0, $player->getRemaining('pv') - 1));
            }
            if ($taken > 0) {
                $player->putBonus(['pv' => -$taken]);
            }
        }

        if ($effect !== '') {
            $player->add_effect($effect, $effectTurns);
        }

        $player->get_caracs();
        $left = $player->getRemaining('pv');
        if ($left >= 1) {
            return false;
        }
        if (!$mayKill) {
            $player->putBonus(['pv' => 1 - $left]);
            return false;
        }

        PlayerService::processSelfDeath($player, 'lors de l\'évènement « ' . mb_strimwidth($text, 0, 60, '…') . ' »');

        return true;
    }
}
