<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Hints for a human to look at — a bot, a bug being exploited, a right
 * granted by mistake. None of them is proof, hence warnings, not errors.
 */
final class BehaviourMonitor
{
    private const SECTION = 'Comportements';

    /** Log types written for the player who acts. */
    private const OWN_ACTION_LOGS = ['action', 'hidden_action', 'move', 'use', 'loot'];

    /** Active in this many of the last 24 hours: nobody sleeps that little. */
    private const BOT_HOURS = 20;

    /** A gain is unusual above this many times the median gain… */
    private const GAIN_FACTOR = 10;

    /** …and above this floor, so a quiet day does not flag small gains. */
    private const GAIN_FLOORS = ['xp' => 200, 'gold' => 1000];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        $snapshots = new MonitoringSnapshotService($this->conn);
        $last = $snapshots->history(1)[0] ?? null;
        $live = $snapshots->measure();

        return [
            $this->botLike(...),
            fn (): MonitoringResult => $this->gains($live, $last, 'xp', 'Gains d\'XP inhabituels'),
            fn (): MonitoringResult => $this->gains($live, $last, 'gold', 'Gains d\'or inhabituels'),
            fn (): MonitoringResult => $this->adminRights($live, $last),
        ];
    }

    private function botLike(): MonitoringResult
    {
        $in = implode(',', array_fill(0, count(self::OWN_ACTION_LOGS), '?'));
        $offenders = array_map(
            static fn (array $row): string => IntegrityMonitor::who($row) . ' : actif ' . $row['hours'] . ' h sur 24',
            $this->conn->fetchAllAssociative(
                "SELECT p.id, p.name, COUNT(DISTINCT FLOOR(l.time / 3600)) AS hours
                   FROM players_logs l JOIN players p ON p.id = l.player_id
                  WHERE l.time >= ? AND l.type IN ({$in}) AND p.player_type = 'real'
                  GROUP BY p.id, p.name HAVING hours >= ? ORDER BY hours DESC",
                [time() - 86400, ...self::OWN_ACTION_LOGS, self::BOT_HOURS]
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Rythme de robot', $offenders, MonitoringStatus::Warning,
            'Personne n\'a joué ' . self::BOT_HOURS . ' heures ou plus sur les dernières 24.',
            '%d joueur(s) actif(s) au moins ' . self::BOT_HOURS . ' heures sur les dernières 24.');
    }

    /**
     * @param array{players: array<int, array{name: string, xp: int, gold: int}>} $live
     * @param array{taken_at: string, metrics: array{players: array<int, array{xp: int, gold: int}>}}|null $last
     */
    private function gains(array $live, ?array $last, string $key, string $title): MonitoringResult
    {
        if ($last === null) {
            return new MonitoringResult(self::SECTION, $title, MonitoringStatus::Warning, self::noSnapshot());
        }

        $gains = [];
        foreach ($live['players'] as $id => $now) {
            if (isset($last['metrics']['players'][$id])) {
                $gains[$id] = $now[$key] - $last['metrics']['players'][$id][$key];
            }
        }
        $flagged = self::unusualGains($gains, self::GAIN_FLOORS[$key]);
        $gains = array_filter($gains, static fn (int $g): bool => $g > 0);
        arsort($gains);

        $lines = [];
        foreach (array_slice($gains, 0, 5, true) as $id => $gain) {
            $lines[] = MonitoringResult::line(
                IntegrityMonitor::who(['id' => $id, 'name' => $live['players'][$id]['name']]),
                '+' . $gain,
                in_array($id, $flagged, true) ? MonitoringStatus::Warning : null
            );
        }

        $since = 'depuis le relevé du ' . $last['taken_at'];
        if ($gains === []) {
            return new MonitoringResult(self::SECTION, $title, MonitoringStatus::Ok, 'Aucun gain ' . $since . '.');
        }

        return new MonitoringResult(self::SECTION, $title,
            $flagged === [] ? MonitoringStatus::Ok : MonitoringStatus::Warning,
            ($flagged === [] ? 'Rien d\'anormal ' : count($flagged) . ' gain(s) hors norme ') . $since . ' — les 5 plus gros :',
            $lines);
    }

    /**
     * Ids whose gain stands out: above the floor and above GAIN_FACTOR times
     * the median of the positive gains.
     *
     * @param array<int, int> $gains player id => gain
     * @return list<int>
     */
    public static function unusualGains(array $gains, int $floor): array
    {
        $positive = array_values(array_filter($gains, static fn (int $g): bool => $g > 0));
        if ($positive === []) {
            return [];
        }
        sort($positive);
        $median = $positive[intdiv(count($positive), 2)];
        $threshold = max($floor, self::GAIN_FACTOR * $median);

        return array_keys(array_filter($gains, static fn (int $g): bool => $g > $threshold));
    }

    /**
     * @param array{admins: list<int>} $live
     * @param array{taken_at: string, metrics: array{admins: list<int>}}|null $last
     */
    private function adminRights(array $live, ?array $last): MonitoringResult
    {
        $lines = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT p.id, p.name, GROUP_CONCAT(o.name ORDER BY o.name) AS rights
               FROM players_options o JOIN players p ON p.id = o.player_id
              WHERE o.name IN (?, ?) GROUP BY p.id, p.name ORDER BY p.id',
            MonitoringSnapshotService::ADMIN_OPTIONS
        ) as $row) {
            $lines[] = MonitoringResult::line(IntegrityMonitor::who($row), (string) $row['rights']);
        }

        // Incognito is a PNJ mode: a player (positive id) with it is invisible to everyone.
        $incognito = $this->conn->fetchAllAssociative(
            "SELECT p.id, p.name FROM players_options o JOIN players p ON p.id = o.player_id
              WHERE o.name = 'incognitoMode' AND p.id > 0"
        );
        foreach ($incognito as $row) {
            $lines[] = MonitoringResult::line(IntegrityMonitor::who($row), 'incognitoMode sur un joueur', MonitoringStatus::Error);
        }

        $added = $last === null ? [] : array_diff($live['admins'], $last['metrics']['admins']);
        $removed = $last === null ? [] : array_diff($last['metrics']['admins'], $live['admins']);

        if ($incognito !== []) {
            return new MonitoringResult(self::SECTION, 'Droits d\'administration', MonitoringStatus::Error,
                count($incognito) . ' joueur(s) en mode incognito, réservé aux PNJ.', $lines);
        }
        if ($added !== [] || $removed !== []) {
            return new MonitoringResult(self::SECTION, 'Droits d\'administration', MonitoringStatus::Warning,
                'Changé depuis le relevé du ' . $last['taken_at'] . ' : '
                . ($added === [] ? '' : 'ajoutés #' . implode(', #', $added) . ' ')
                . ($removed === [] ? '' : 'retirés #' . implode(', #', $removed)), $lines);
        }

        return new MonitoringResult(self::SECTION, 'Droits d\'administration', MonitoringStatus::Ok,
            count($lines) . ' compte(s) avec des droits d\'administration, inchangé.', $lines);
    }

    public static function noSnapshot(): string
    {
        return 'Aucun relevé : le script daily/90_monitoring_snapshot.php n\'a pas encore tourné.';
    }
}
