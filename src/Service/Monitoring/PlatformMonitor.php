<?php

namespace App\Service\Monitoring;

use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The server under the game: what code runs, whether the schema follows,
 * what failed in the audit, how big the tables get.
 */
final class PlatformMonitor
{
    private const SECTION = 'Technique';

    /** Written by scripts/deploy_code.sh once the copy succeeded. */
    public const VERSION_FILE = __DIR__ . '/../../../config/deployed_version.txt';

    private const MIGRATIONS_DIR = __DIR__ . '/../../Migrations';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        return [
            $this->lastDeploy(...),
            $this->pendingMigrations(...),
            $this->auditFailures(...),
            $this->tableSizes(...),
        ];
    }

    /**
     * admin/deploy.php groups an attempt under one audit_key, from
     * "Deploying attempt" to "Deploying attempt finished".
     */
    private function lastDeploy(): MonitoringResult
    {
        $version = is_file(self::VERSION_FILE) ? trim((string) file_get_contents(self::VERSION_FILE)) : null;
        $versionText = $version === null ? 'version déployée inconnue (déployé avant son écriture)' : 'version ' . $version;

        $start = $this->conn->fetchAssociative(
            "SELECT id, audit_key FROM audit WHERE details = 'Deploying attempt' ORDER BY id DESC LIMIT 1"
        );
        if ($start === false) {
            return new MonitoringResult(self::SECTION, 'Dernier déploiement', MonitoringStatus::Ok,
                ucfirst($versionText) . ', aucun déploiement dans l\'audit.');
        }

        $lines = [];
        $failed = false;
        $finished = false;
        foreach ($this->conn->fetchAllAssociative(
            'SELECT timestamp, details FROM audit WHERE id >= ? AND audit_key = ? ORDER BY id',
            [$start['id'], $start['audit_key'] ?? $start['id']]
        ) as $row) {
            $text = (string) $row['details'];
            $bad = str_starts_with($text, 'Refused') || str_contains($text, 'DEPLOY ABORT');
            $failed = $failed || $bad;
            $finished = $finished || $text === 'Deploying attempt finished';
            $lines[] = MonitoringResult::line((string) $row['timestamp'], mb_strimwidth(strip_tags($text), 0, 2000, '…'),
                $bad ? MonitoringStatus::Error : null);
        }
        $when = $lines[0]['label'];

        return match (true) {
            $failed    => new MonitoringResult(self::SECTION, 'Dernier déploiement', MonitoringStatus::Error,
                'Échec le ' . $when . ' : le serveur garde l\'ancien code (' . $versionText . ').', $lines),
            !$finished => new MonitoringResult(self::SECTION, 'Dernier déploiement', MonitoringStatus::Warning,
                'Commencé le ' . $when . ' sans trace de fin (' . $versionText . ').', $lines),
            default    => new MonitoringResult(self::SECTION, 'Dernier déploiement', MonitoringStatus::Ok,
                'Réussi le ' . $when . ', ' . $versionText . '.', $lines),
        };
    }

    private function pendingMigrations(): MonitoringResult
    {
        $executed = array_flip($this->conn->fetchFirstColumn('SELECT version FROM doctrine_migration_versions'));
        $pending = [];
        foreach (glob(self::MIGRATIONS_DIR . '/Version*.php') ?: [] as $file) {
            $class = 'App\\Migrations\\' . basename($file, '.php');
            if (!isset($executed[$class])) {
                $pending[] = basename($file, '.php');
            }
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Migrations en attente', $pending, MonitoringStatus::Error,
            'Toutes les migrations du code sont passées sur la base.',
            '%d migration(s) du code pas encore passée(s) sur la base.');
    }

    private function auditFailures(): MonitoringResult
    {
        // ponytail: LIKE over a day of audit without an index; index timestamp if the audit grows too big
        $offenders = array_map(
            static fn (array $row): string => $row['timestamp'] . ' — ' . $row['details'],
            $this->conn->fetchAllAssociative(
                "SELECT timestamp, details FROM audit WHERE timestamp >= NOW() - INTERVAL 1 DAY
                    AND (details LIKE '% FAILED %' OR details LIKE '% INTERRUPTED %') ORDER BY id DESC"
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Échecs des dernières 24 h', $offenders, MonitoringStatus::Warning,
            'Aucun échec ni interruption dans l\'audit depuis 24 h.',
            '%d échec(s) ou interruption(s) dans l\'audit depuis 24 h.');
    }

    /** Informative: the biggest tables, and their growth over the stored week. */
    private function tableSizes(): MonitoringResult
    {
        $history = (new MonitoringSnapshotService($this->conn))->history(8);
        $weekAgo = $history === [] ? [] : end($history)['metrics']['tables'];
        $lines = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT table_name AS name, table_rows AS row_count, data_length + index_length AS bytes FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_type = \'BASE TABLE\' ORDER BY bytes DESC LIMIT 10'
        ) as $row) {
            $bytes = (int) $row['bytes'];
            $before = $weekAgo[(string) $row['name']] ?? null;
            $lines[] = MonitoringResult::line((string) $row['name'], number_format($bytes / 1048576, 1, ',', ' ') . ' Mo, ~'
                . number_format((int) $row['row_count'], 0, ',', ' ') . ' lignes'
                . ($before === null ? '' : ', ' . sprintf('%+.1f', EconomyMonitor::percent($before, $bytes)) . ' % depuis le ' . end($history)['day']));
        }

        return new MonitoringResult(self::SECTION, 'Tailles des tables', MonitoringStatus::Ok, 'Les 10 plus grosses tables.', $lines);
    }
}
