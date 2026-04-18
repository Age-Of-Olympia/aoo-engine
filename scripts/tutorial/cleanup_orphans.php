<?php
/**
 * Cleanup abandoned tutorial sessions older than a threshold.
 *
 * Intended for daily cron:
 *
 *   0 3 * * * /usr/bin/php /var/www/html/scripts/tutorial/cleanup_orphans.php
 *
 * Removes, for every tutorial_progress row with completed=0 and
 * created_at older than the threshold:
 *  - the progress row
 *  - tutorial_enemies for the session
 *  - tutorial_players row + the underlying players row + FK references
 *
 * Map-instance cleanup (the `tut_<uuid>` plans + their coords) is handled by
 * the existing scripts/tutorial/cleanup_orphaned_instances.php; recommend
 * scheduling both jobs (this one first, instances second).
 *
 * Without this job abandoned sessions accumulate forever — players that
 * close the browser mid-tutorial, lose the tab, etc. — and the
 * tutorial_progress / tutorial_players tables grow unbounded.
 *
 * Idempotent. Outputs one JSON line to stdout for log scraping. Exits 0
 * on success, 1 if any session failed to clean (per-session errors are
 * isolated; one bad session does not block the rest).
 *
 * Options:
 *   --hours=N    threshold in hours, default 24
 *   --dry-run    list what would be cleaned without doing it
 *   --quiet      suppress per-session log lines (final JSON still printed)
 */

define('NO_LOGIN', true);
require_once __DIR__ . '/../../config.php';

use App\Entity\EntityManagerFactory;
use App\Tutorial\TutorialEnemyCleanup;
use App\Tutorial\TutorialPlayerCleanup;
use Classes\Db;

$opts = getopt('', ['hours::', 'dry-run', 'quiet']);
$thresholdHours = isset($opts['hours']) ? max(1, (int) $opts['hours']) : 24;
$dryRun = isset($opts['dry-run']);
$quiet = isset($opts['quiet']);

$logLine = static function (string $msg) use ($quiet): void {
    if (!$quiet) {
        fwrite(STDERR, $msg . "\n");
    }
};

$db = new Db();
$conn = EntityManagerFactory::getEntityManager()->getConnection();
$enemyCleanup = new TutorialEnemyCleanup($conn);
$playerCleanup = new TutorialPlayerCleanup($conn);

$sql = '
    SELECT tutorial_session_id, player_id, tutorial_version, created_at
    FROM tutorial_progress
    WHERE completed = 0
      AND created_at < (NOW() - INTERVAL ? HOUR)
';

$result = $db->exe($sql, [$thresholdHours]);

$sessionsFound = 0;
$sessionsCleaned = 0;
$sessionsFailed = 0;
$failures = [];

while ($row = $result->fetch_assoc()) {
    $sessionsFound++;
    $sessionId = $row['tutorial_session_id'];
    $progressPlayerId = (int) $row['player_id'];

    $logLine(sprintf(
        '[orphan] session=%s player=%d version=%s created=%s',
        $sessionId,
        $progressPlayerId,
        $row['tutorial_version'],
        $row['created_at']
    ));

    if ($dryRun) {
        continue;
    }

    try {
        /* Per-session try/catch: one bad session must not block the rest.
         * Order matters because of FK constraints — enemies before players,
         * progress row last. */

        $enemyCleanup->removeBySessionId($sessionId);

        $tpResult = $db->exe(
            'SELECT id, player_id FROM tutorial_players WHERE tutorial_session_id = ?',
            [$sessionId]
        );
        if ($tpResult && $tpResult->num_rows > 0) {
            $tpRow = $tpResult->fetch_assoc();
            $playerCleanup->deleteTutorialPlayer(
                (int) $tpRow['id'],
                (int) $tpRow['player_id']
            );
        }

        $db->exe(
            'DELETE FROM tutorial_progress WHERE tutorial_session_id = ?',
            [$sessionId]
        );

        $sessionsCleaned++;
    } catch (\Throwable $e) {
        $sessionsFailed++;
        $failures[] = [
            'session' => $sessionId,
            'error' => $e->getMessage(),
        ];
        error_log(sprintf(
            '[cleanup_orphans] session %s failed: %s',
            $sessionId,
            $e->getMessage()
        ));
    }
}

$summary = [
    'event' => 'tutorial_cleanup_orphans',
    'threshold_hours' => $thresholdHours,
    'dry_run' => $dryRun,
    'sessions_found' => $sessionsFound,
    'sessions_cleaned' => $sessionsCleaned,
    'sessions_failed' => $sessionsFailed,
    'failures' => $failures,
];
echo json_encode($summary) . "\n";

exit($sessionsFailed > 0 ? 1 : 0);
