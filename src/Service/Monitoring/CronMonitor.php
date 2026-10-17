<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use DateTimeImmutable;

/**
 * Status of the scheduled cron groups, read back from the audit lines
 * CronService writes. Only scheduled runs count (no user_id): a manual run
 * from the admin must not hide a night the crontab skipped.
 */
final class CronMonitor
{
    /** Longest normal gap between two scheduled runs, in seconds. */
    private const EXPECTED_EVERY = [
        'hourly' => 2 * 3600,
        'daily'  => 26 * 3600,
    ];

    /** A run without "done" younger than this is still going. */
    private const RUNNING_FOR = 3600;

    private const CRONS_DIR = __DIR__ . '/../../../scripts/crons';

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        return array_map(fn (string $group): \Closure => fn (): MonitoringResult => $this->lastRun($group), $this->groups());
    }

    private function lastRun(string $group): MonitoringResult
    {
        $connection = EntityManagerFactory::getEntityManager()->getConnection();
        // ponytail: LIKE scans the audit table backwards from the newest row; index details if it grows too big
        $start = $connection->fetchAssociative(
            'SELECT id, audit_key, timestamp FROM audit
             WHERE details LIKE ? AND user_id IS NULL ORDER BY id DESC LIMIT 1',
            ['Cron ' . $group . ' started%']
        );
        $lines = $start === false ? [] : $connection->fetchAllAssociative(
            'SELECT timestamp, details FROM audit WHERE id >= ? AND audit_key = ? ORDER BY id',
            [$start['id'], $start['audit_key'] ?? $start['id']]
        );

        return self::analyse(
            $group,
            $start === false ? null : new DateTimeImmutable((string) $start['timestamp']),
            array_map(static fn (array $row): array => [
                'label' => (string) $row['timestamp'],
                'text' => (string) $row['details'],
            ], $lines),
            new DateTimeImmutable()
        );
    }

    /**
     * Verdict on the last scheduled run of a group.
     *
     * @param list<array{label: string, text: string}> $lines audit lines of that run, in order
     */
    public static function analyse(string $group, ?DateTimeImmutable $startedAt, array $lines, DateTimeImmutable $now): MonitoringResult
    {
        $title = 'Cron ' . $group;
        if ($startedAt === null) {
            return new MonitoringResult('Crons', $title, MonitoringStatus::Error, 'Aucune exécution planifiée dans l\'audit.');
        }

        $age = $now->getTimestamp() - $startedAt->getTimestamp();
        $done = false;
        $failures = 0;
        $interrupted = null;
        $marked = [];
        foreach ($lines as $line) {
            $status = null;
            if (str_contains($line['text'], ' FAILED ')) {
                $failures++;
                $status = MonitoringStatus::Error;
            } elseif (str_contains($line['text'], ' INTERRUPTED ')) {
                $interrupted = $line['text'];
                $status = MonitoringStatus::Error;
            } elseif (preg_match('/^cron \S+ done /i', $line['text'])) {
                $done = true;
            }
            $marked[] = $line + ['status' => $status];
        }

        $since = 'lancé le ' . $startedAt->format('d/m/Y H:i');
        $expected = self::EXPECTED_EVERY[$group] ?? null;
        if ($expected !== null && $age > $expected) {
            $status = MonitoringStatus::Error;
            $summary = 'Pas lancé depuis ' . self::duration($age) . ' (dernier ' . $since . ').';
        } elseif ($interrupted !== null) {
            $status = MonitoringStatus::Error;
            $summary = 'Interrompu, ' . $since . '.';
        } elseif (!$done && $age < self::RUNNING_FOR) {
            $status = MonitoringStatus::Running;
            $summary = 'En cours, ' . $since . '.';
        } elseif (!$done) {
            $status = MonitoringStatus::Error;
            $summary = 'Arrêté sans trace de fin, ' . $since . '.';
        } elseif ($failures > 0) {
            $status = MonitoringStatus::Warning;
            $summary = $failures . ' script(s) en échec, ' . $since . '.';
        } else {
            $status = MonitoringStatus::Ok;
            $summary = 'Terminé sans erreur, ' . $since . '.';
        }

        return new MonitoringResult('Crons', $title, $status, $summary, $marked);
    }

    /** @return list<string> cron groups, i.e. subdirectories of scripts/crons */
    private function groups(): array
    {
        $groups = array_values(array_filter(
            scandir(self::CRONS_DIR) ?: [],
            static fn (string $entry): bool => $entry[0] !== '.' && is_dir(self::CRONS_DIR . '/' . $entry)
        ));
        sort($groups);

        return $groups;
    }

    private static function duration(int $seconds): string
    {
        return $seconds >= 86400
            ? intdiv($seconds, 86400) . ' j ' . intdiv($seconds % 86400, 3600) . ' h'
            : intdiv($seconds, 3600) . ' h ' . intdiv($seconds % 3600, 60) . ' min';
    }
}
