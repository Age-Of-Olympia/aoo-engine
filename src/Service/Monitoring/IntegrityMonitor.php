<?php

namespace App\Service\Monitoring;

use App\Enum\EntityCategory;
use App\Enum\MonitoringStatus;
use App\Factory\EntityManagerFactory;
use App\Service\Map\TileOccupancyService;
use App\Service\RaceService;
use Doctrine\DBAL\Connection;

/**
 * Values the game should never produce: each check lists the rows that
 * break an invariant, so an empty list is the only healthy answer.
 */
final class IntegrityMonitor
{
    private const SECTION = 'Données';

    /** Character counters that are never negative nor NULL, and where they live. */
    private const COUNTERS = ['pi' => 'COALESCE(g.pi, 0)', 'pr' => 'p.pr', 'xp' => 'COALESCE(g.xp, 0)', 'pf' => 'p.pf', 'malus' => 'p.malus', 'energie' => 'p.energie'];

    /** Tables keyed by a player id that must exist. */
    private const PLAYER_TABLES = [
        'players_options', 'players_items', 'players_items_bank', 'players_effects',
        'players_actions', 'players_passives', 'turns', 'entity_cells',
    ];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @return list<\Closure(): MonitoringResult> */
    public function checks(): array
    {
        return [
            $this->impossibleCounters(...),
            $this->emptyStacks(...),
            $this->duplicateOptions(...),
            $this->missingPositions(...),
            $this->outOfSeasonPositions(...),
            $this->onBlockedCells(...),
            $this->orphans(...),
        ];
    }

    /** A player with a broken counter is an error; an NPC, run by the team, a warning. */
    private function impossibleCounters(): MonitoringResult
    {
        $where = implode(' OR ', array_map(static fn (string $c): string => "{$c} IS NULL OR {$c} < 0", self::COUNTERS));
        $columns = implode(', ', array_map(
            static fn (string $name, string $c): string => "{$c} AS {$name}",
            array_keys(self::COUNTERS),
            self::COUNTERS
        ));
        $lines = [];
        $count = ['real' => 0, 'npc' => 0];
        foreach ($this->conn->fetchAllAssociative(
            "SELECT p.id, p.name, p.player_type, {$columns}
               FROM players p LEFT JOIN progression g ON g.player_id = p.id
              WHERE p.player_type IN (" . EntityCategory::addressableSql() . ") AND ({$where}) ORDER BY p.player_type DESC, p.id"
        ) as $row) {
            $bad = [];
            foreach (array_keys(self::COUNTERS) as $counter) {
                if ($row[$counter] === null || (int) $row[$counter] < 0) {
                    $bad[] = $counter . ' = ' . ($row[$counter] ?? 'NULL');
                }
            }
            $npc = $row['player_type'] === 'npc';
            $count[$npc ? 'npc' : 'real']++;
            $lines[] = MonitoringResult::line($npc ? 'PNJ' : 'joueur', self::who($row) . ' : ' . implode(', ', $bad),
                $npc ? MonitoringStatus::Warning : MonitoringStatus::Error);
        }

        if ($lines === []) {
            return new MonitoringResult(self::SECTION, 'Compteurs impossibles', MonitoringStatus::Ok,
                'Aucun compteur (PI, PR, XP, PF, malus, énergie) négatif ou vide.');
        }

        return new MonitoringResult(self::SECTION, 'Compteurs impossibles',
            $count['real'] > 0 ? MonitoringStatus::Error : MonitoringStatus::Warning,
            $count['real'] . ' joueur(s) et ' . $count['npc'] . ' PNJ avec un compteur négatif ou vide.', $lines);
    }

    private function emptyStacks(): MonitoringResult
    {
        $offenders = [];
        foreach (['players_items' => 'player_id', 'players_items_bank' => 'player_id', 'map_items' => 'coords_id'] as $table => $owner) {
            foreach ($this->conn->fetchAllAssociative(
                "SELECT t.{$owner} AS owner, i.name, t.n FROM {$table} t LEFT JOIN items i ON i.id = t.item_id WHERE t.n <= 0"
            ) as $row) {
                $offenders[] = "{$table} : {$owner} {$row['owner']}, {$row['name']} × {$row['n']}";
            }
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Piles vides ou négatives', $offenders, MonitoringStatus::Error,
            'Aucune pile d\'objets à zéro ou en dessous (sacs, banques, sol).',
            '%d pile(s) à zéro ou négative(s).');
    }

    /** players_options has no unique key; scalar subqueries on it break on a duplicate. */
    private function duplicateOptions(): MonitoringResult
    {
        $offenders = array_map(
            static fn (array $row): string => IntegrityMonitor::who($row) . ' : ' . $row['option_name'] . ' × ' . $row['n'],
            $this->conn->fetchAllAssociative(
                'SELECT p.id, p.name, o.name AS option_name, COUNT(*) AS n FROM players_options o
                   JOIN players p ON p.id = o.player_id GROUP BY p.id, p.name, o.name HAVING n > 1 ORDER BY p.id'
            )
        );

        return MonitoringResult::fromOffenders(self::SECTION, 'Options en double', $offenders, MonitoringStatus::Error,
            'Aucune option enregistrée deux fois pour le même joueur.',
            '%d option(s) en double.');
    }

    private function missingPositions(): MonitoringResult
    {
        $offenders = array_map(self::who(...), $this->conn->fetchAllAssociative(
            'SELECT p.id, p.name FROM players p LEFT JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type IN (' . EntityCategory::addressableSql() . ') AND c.id IS NULL ORDER BY p.id'
        ));

        return MonitoringResult::fromOffenders(self::SECTION, 'Personnages sans case', $offenders, MonitoringStatus::Error,
            'Chaque personnage est sur une case qui existe.',
            '%d personnage(s) sur une case inexistante.');
    }

    private function outOfSeasonPositions(): MonitoringResult
    {
        $inSeason = array_keys(plans()->forSeason());
        $offenders = [];
        foreach ($this->conn->fetchAllAssociative(
            'SELECT c.plan, COUNT(*) AS n FROM players p JOIN coords c ON c.id = p.coords_id
              WHERE p.player_type IN (' . EntityCategory::addressableSql() . ') GROUP BY c.plan ORDER BY c.plan'
        ) as $row) {
            if (!in_array((string) $row['plan'], $inSeason, true)) {
                $offenders[] = "{$row['plan']} : {$row['n']} personnage(s)";
            }
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Personnages hors saison', $offenders, MonitoringStatus::Warning,
            'Tous les personnages sont sur un plan de la saison en cours.',
            '%d plan(s) hors saison ou inconnus portent des personnages.');
    }

    private function onBlockedCells(): MonitoringResult
    {
        $characters = $this->conn->fetchAllAssociative(
            'SELECT id, name, coords_id FROM players WHERE player_type IN (' . EntityCategory::addressableSql() . ')'
        );
        $blocked = (new TileOccupancyService($this->conn))->permanentlyBlocked(array_column($characters, 'coords_id'));
        // A doorway is walked through when open, and may close on whoever stands in it.
        $doors = (new RaceService())->getDoorRaceNames();

        $offenders = [];
        foreach ($characters as $row) {
            $blocker = $blocked[(int) $row['coords_id']] ?? null;
            if ($blocker !== null && !in_array($blocker, $doors, true)) {
                $offenders[] = self::who($row) . ' sous « ' . $blocker . ' » (coords ' . $row['coords_id'] . ')';
            }
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Personnages dans un obstacle', $offenders, MonitoringStatus::Warning,
            'Aucun personnage sur une case qui bloque le passage.',
            '%d personnage(s) sur une case qui bloque le passage.');
    }

    private function orphans(): MonitoringResult
    {
        $offenders = [];
        foreach (self::PLAYER_TABLES as $table) {
            $n = (int) $this->conn->fetchOne(
                "SELECT COUNT(*) FROM {$table} t WHERE NOT EXISTS (SELECT 1 FROM players p WHERE p.id = t.player_id)"
            );
            if ($n > 0) {
                $offenders[] = "{$table} : {$n} ligne(s)";
            }
        }

        return MonitoringResult::fromOffenders(self::SECTION, 'Lignes orphelines', $offenders, MonitoringStatus::Error,
            'Aucune ligne rattachée à un joueur ou une entité disparue.',
            '%d table(s) gardent des lignes d\'entités disparues.');
    }

    /** @param array<string, mixed> $row with id and name */
    public static function who(array $row): string
    {
        return $row['name'] . ' (#' . $row['id'] . ')';
    }
}
