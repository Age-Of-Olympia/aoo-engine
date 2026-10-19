<?php

namespace App\Service\Monitoring;

use App\Enum\EntityCategory;
use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Players the game has left in a state they cannot get out of by playing.
 */
final class StuckPlayersMonitor
{
    private const SECTION = 'Joueurs bloqués';

    /** A next turn further than this is a lock, not a delay. */
    private const MAX_TURN_WAIT = 2 * 86400;

    /** A tutorial instance this old should have been swept by daily/45. */
    private const TUTORIAL_STALE_HOURS = 72;

    /** Account deletions waiting longer than this. */
    private const DELETION_WAIT_DAYS = 30;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        return [
            $this->farTurns(...),
            $this->deathScreens(...),
            $this->tutorialLeftovers(...),
            $this->pendingDeletions(...),
        ];
    }

    private function farTurns(): MonitoringResult
    {
        $offenders = array_map(
            static fn (array $row): string => IntegrityMonitor::who($row) . ' : prochain tour le ' . date('d/m/Y H:i', (int) $row['next_turn_time']),
            $this->conn->fetchAllAssociative(
                'SELECT p.id, p.name, t.next_turn_time FROM turns t JOIN players p ON p.id = t.player_id
                  WHERE p.player_type IN (' . EntityCategory::addressableSql() . ') AND t.next_turn_time > ? ORDER BY p.id',
                [time() + self::MAX_TURN_WAIT]
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Tours trop lointains', $offenders, MonitoringStatus::Error,
            'Aucun prochain tour à plus de 2 jours.',
            '%d personnage(s) avec un prochain tour à plus de 2 jours.');
    }

    /**
     * The page is disarmed by one click: a player who logged in a day after
     * dying and still has it armed cannot get rid of it. The death time is the
     * latest of the last kill on record and the last death with no killer
     * (PlayerService::processSelfDeath writes no kill, only its log).
     */
    private function deathScreens(): MonitoringResult
    {
        $offenders = array_map(
            static fn (array $row): string => IntegrityMonitor::who($row) . ' : mort le ' . date('d/m/Y H:i', (int) $row['died'])
                . ', connecté le ' . date('d/m/Y H:i', (int) $row['lastLoginTime']),
            $this->conn->fetchAllAssociative(
                "SELECT * FROM (
                    SELECT p.id, p.name, COALESCE(a.last_login_time, 0) AS lastLoginTime, GREATEST(
                        COALESCE((SELECT MAX(k.time) FROM players_kills k WHERE k.target_id = p.id), 0),
                        COALESCE((SELECT MAX(l.time) FROM players_logs l
                                   WHERE l.player_id = p.id AND l.target_id = p.id AND l.type = 'kill'
                                     AND l.text LIKE '%a succombé%'), 0)
                    ) AS died
                    FROM players_options o JOIN players p ON p.id = o.player_id
                    LEFT JOIN accounts a ON a.player_id = p.id
                   WHERE o.name = 'deathScreenPending'
                 ) d
                 WHERE d.died > 0 AND d.lastLoginTime > d.died + 86400 ORDER BY d.id"
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Écran de mort coincé', $offenders, MonitoringStatus::Error,
            'Aucun écran de mort encore armé un jour après une connexion.',
            '%d joueur(s) revoient l\'écran de mort plus d\'un jour après leur mort.');
    }

    private function tutorialLeftovers(): MonitoringResult
    {
        $offenders = [];
        foreach ($this->conn->fetchFirstColumn(
            "SELECT slug FROM plans WHERE slug LIKE 'tut\\_%' UNION SELECT DISTINCT plan FROM coords WHERE plan LIKE 'tut\\_%'"
        ) as $plan) {
            $recent = (int) $this->conn->fetchOne(
                'SELECT COUNT(*) FROM tutorial_players WHERE is_active = 1 AND deleted_at IS NULL
                    AND SUBSTRING(tutorial_session_id, 1, 10) = ? AND created_at >= NOW() - INTERVAL ? HOUR',
                [substr((string) $plan, strlen('tut_')), self::TUTORIAL_STALE_HOURS]
            );
            if ($recent === 0) {
                $offenders[] = 'instance ' . $plan . ' sans session de moins de ' . self::TUTORIAL_STALE_HOURS . ' h';
            }
        }
        foreach ($this->conn->fetchAllAssociative(
            "SELECT p.id, p.name FROM players p WHERE p.player_type = 'tutorial' AND NOT EXISTS (
                SELECT 1 FROM tutorial_players tp WHERE tp.player_id = p.id AND tp.is_active = 1 AND tp.deleted_at IS NULL)"
        ) as $row) {
            $offenders[] = 'personnage de tutoriel ' . IntegrityMonitor::who($row) . ' sans session active';
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Restes de tutoriel', $offenders, MonitoringStatus::Warning,
            'Aucune instance ni session de tutoriel à l\'abandon.',
            '%d reste(s) de tutoriel que le nettoyage quotidien aurait dû ramasser.');
    }

    private function pendingDeletions(): MonitoringResult
    {
        $offenders = array_map(
            static fn (array $row): string => IntegrityMonitor::who($row) . ' : demandée le ' . $row['deletion_asked'],
            $this->conn->fetchAllAssociative(
                "SELECT id, name, deletion_asked FROM players
                  WHERE player_type = 'real' AND deletion_asked < NOW() - INTERVAL ? DAY ORDER BY deletion_asked",
                [self::DELETION_WAIT_DAYS]
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Suppressions de compte en attente', $offenders, MonitoringStatus::Warning,
            'Aucune demande de suppression de plus de ' . self::DELETION_WAIT_DAYS . ' jours.',
            '%d demande(s) de suppression de plus de ' . self::DELETION_WAIT_DAYS . ' jours.');
    }
}
