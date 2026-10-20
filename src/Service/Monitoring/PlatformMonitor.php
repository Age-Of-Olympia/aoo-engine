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
            $this->opcache(...),
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
            // Doctrine records the class name, which may differ from the file name.
            preg_match('/^(?:final\s+)?class\s+(\w+)/m', (string) file_get_contents($file), $match);
            $class = $match[1] ?? basename($file, '.php');
            if (!isset($executed['App\\Migrations\\' . $class])) {
                $pending[] = $class;
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

    /**
     * The web server's opcode cache, as this request sees it: without it PHP
     * compiles every included file on every request. The cached scripts are
     * grouped by their first folders, since every environment of the host
     * may share one cache.
     */
    private function opcache(): MonitoringResult
    {
        $status = function_exists('opcache_get_status') ? opcache_get_status(true) : false;
        if ($status === false || empty($status['opcache_enabled'])) {
            return new MonitoringResult(self::SECTION, 'Cache d\'opcodes (OPcache)', MonitoringStatus::Error,
                'OPcache inactif pour le site : PHP compile chaque fichier à chaque requête.');
        }

        $stats = $status['opcache_statistics'];
        $memory = $status['memory_usage'];
        $config = (opcache_get_configuration() ?: [])['directives'] ?? [];
        $hitRate = (float) $stats['opcache_hit_rate'];
        $restarts = (int) $stats['oom_restarts'] + (int) $stats['hash_restarts'];
        $keysFull = $stats['num_cached_keys'] >= 0.9 * $stats['max_cached_keys'];
        $mo = static fn (int|float $bytes): string => number_format($bytes / 1048576, 0, ',', ' ') . ' Mo';
        $date = static fn (int $time): string => $time > 0 ? date('d/m/Y H:i', $time) : 'jamais';

        $lines = [
            MonitoringResult::line('Taux de succès', number_format($hitRate, 2, ',', ' ') . ' % ('
                . number_format((int) $stats['hits'], 0, ',', ' ') . ' succès, '
                . number_format((int) $stats['misses'], 0, ',', ' ') . ' compilations)',
                $hitRate < 95 ? MonitoringStatus::Warning : null),
            MonitoringResult::line('Scripts en cache', $stats['num_cached_scripts'] . ' (clés : ' . $stats['num_cached_keys']
                . ' sur ' . $stats['max_cached_keys'] . ')', $keysFull ? MonitoringStatus::Warning : null),
            MonitoringResult::line('Mémoire', $mo($memory['used_memory']) . ' utilisés, ' . $mo($memory['free_memory'])
                . ' libres, ' . $mo($memory['wasted_memory']) . ' perdus'),
            MonitoringResult::line('Redémarrages', 'mémoire pleine : ' . $stats['oom_restarts'] . ', table des clés pleine : '
                . $stats['hash_restarts'] . ', manuels : ' . $stats['manual_restarts'], $restarts > 0 ? MonitoringStatus::Warning : null),
            MonitoringResult::line('Démarrage', $date((int) $stats['start_time']) . ', dernier redémarrage : '
                . $date((int) $stats['last_restart_time'])),
            MonitoringResult::line('Vérification des fichiers', !empty($config['opcache.validate_timestamps'])
                ? 'toutes les ' . ($config['opcache.revalidate_freq'] ?? '?') . ' s' : 'désactivée'),
        ];

        $folders = [];
        foreach (array_keys($status['scripts'] ?? []) as $path) {
            $folder = implode('/', array_slice(explode('/', $path), 0, 4));
            $folders[$folder] = ($folders[$folder] ?? 0) + 1;
        }
        arsort($folders);
        foreach (array_slice($folders, 0, 10, true) as $folder => $count) {
            $lines[] = MonitoringResult::line($folder, $count . ' scripts');
        }

        $warn = $hitRate < 95 || $restarts > 0 || $keysFull;

        return new MonitoringResult(self::SECTION, 'Cache d\'opcodes (OPcache)',
            $warn ? MonitoringStatus::Warning : MonitoringStatus::Ok,
            'Taux de succès ' . number_format($hitRate, 1, ',', ' ') . ' %, ' . $stats['num_cached_scripts']
                . ' scripts en cache, démarré le ' . $date((int) $stats['start_time']) . '.', $lines);
    }

    /** Informative: the biggest tables, and their growth over the stored week. */
    private function tableSizes(): MonitoringResult
    {
        $snapshots = new MonitoringSnapshotService($this->conn);
        $history = $snapshots->history(8);
        $weekAgo = $history === [] ? [] : end($history)['metrics']['tables'];
        $lines = [];
        foreach ($snapshots->tableSizes(10) as $name => ['bytes' => $bytes, 'rows' => $rows]) {
            $before = $weekAgo[$name] ?? null;
            $lines[] = MonitoringResult::line($name, number_format($bytes / 1048576, 1, ',', ' ') . ' Mo, ~'
                . number_format($rows, 0, ',', ' ') . ' lignes'
                . ($before === null ? '' : ', ' . sprintf('%+.1f', EconomyMonitor::percent($before, $bytes)) . ' % depuis le ' . end($history)['day']));
        }

        return new MonitoringResult(self::SECTION, 'Tailles des tables', MonitoringStatus::Ok, 'Les 10 plus grosses tables.', $lines);
    }
}
