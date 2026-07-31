<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Seeds the per-plan yields from the legacy plan JSONs into `race_harvest`.
 *
 * Same shape as DialogSeedService, and for the same reason: a migration runs
 * from the git checkout where `datas/` does not exist, so the seed is offered
 * from the web root instead — admin → Cartes → Rendements.
 *
 * Read through the game's own decoder, not a raw JSON parse: plan files carry
 * comments, and the seed must see exactly what the game sees.
 *
 * A plan it cannot read is REPORTED, never guessed at — two plan files are
 * zero bytes on the real data, and inventing rates for them would put silent
 * numbers into the world.
 */
final class HarvestCatalogService
{
    private ?Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** Plan names on disk, from both the public and the private folders. */
    public function planNames(): array
    {
        $root = dirname(__DIR__, 3);
        $names = [];

        foreach (['public', 'private'] as $where) {
            foreach (glob($root . '/datas/' . $where . '/plans/*.json') ?: [] as $file) {
                $names[basename($file, '.json')] = true;
            }
        }

        $names = array_keys($names);
        sort($names);

        return $names;
    }

    /**
     * What the plan files say, resolved against the type catalogue. Reads
     * only — nothing is written.
     *
     * @return array{
     *     rows: list<array{plan: string, race_id: int, item: string, exhaust: ?int, regrow: ?int}>,
     *     unreadable: list<string>,
     *     unknown: list<string>
     * }
     */
    public function preview(): array
    {
        $types = $this->typesByName();
        $rows = [];
        $unreadable = [];
        $unknown = [];

        foreach ($this->planNames() as $plan) {
            $json = json()->decode('plans', $plan);

            if ($json === false) {
                $unreadable[] = $plan;

                continue;
            }

            foreach ($json->biomes ?? [] as $biome) {
                $wall = (string) ($biome->wall ?? '');
                $item = (string) ($biome->ressource ?? '');

                if ($wall === '' || $item === '') {
                    continue;
                }

                if (!isset($types[$wall])) {
                    $unknown[$wall] = true;

                    continue;
                }

                /* One row per (plan, type): a plan naming the same wall twice
                 * keeps the first, as the game does — its biome map is keyed
                 * by wall name too. */
                $key = $plan . '/' . $wall;

                $rows[$key] ??= [
                    'plan' => $plan,
                    'race_id' => $types[$wall],
                    'item' => $item,
                    'exhaust' => isset($biome->exhaust) ? (int) $biome->exhaust : null,
                    'regrow' => isset($biome->regrow) ? (int) $biome->regrow : null,
                ];
            }
        }

        $unknown = array_keys($unknown);
        sort($unknown);

        return ['rows' => array_values($rows), 'unreadable' => $unreadable, 'unknown' => $unknown];
    }

    /**
     * Pours what preview() found. Re-runnable: a row already there is updated
     * to what the plan says, so a corrected JSON can be poured again.
     *
     * @return array{written: int, plans: int, types: int, unreadable: list<string>, unknown: list<string>}
     */
    public function seed(): array
    {
        $read = $this->preview();
        $written = $this->write($read['rows']);

        return [
            'written' => $written,
            'plans' => count(array_unique(array_column($read['rows'], 'plan'))),
            'types' => count(array_unique(array_column($read['rows'], 'race_id'))),
            'unreadable' => $read['unreadable'],
            'unknown' => $read['unknown'],
        ];
    }

    /**
     * @param list<array{plan: string, race_id: int, item: string, exhaust: ?int, regrow: ?int}> $rows
     * @return int rows written
     */
    private function write(array $rows): int
    {
        $written = 0;

        foreach ($rows as $row) {
            $this->conn()->executeStatement(
                'INSERT INTO race_harvest (plan, race_id, item, exhaust, regrow)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE item = VALUES(item), exhaust = VALUES(exhaust), regrow = VALUES(regrow)',
                [$row['plan'], $row['race_id'], $row['item'], $row['exhaust'], $row['regrow']]
            );
            $written++;
        }

        return $written;
    }

    /**
     * The rows as configured, plan by plan, for the admin screen.
     *
     * @return list<array{plan: string, race_id: int, type: string, item: string, exhaust: ?int, regrow: ?int}>
     */
    public function configured(): array
    {
        $rows = $this->conn()->fetchAllAssociative(
            'SELECT h.plan, h.race_id, r.name AS type, h.item, h.exhaust, h.regrow
               FROM race_harvest h JOIN races r ON r.id = h.race_id
              ORDER BY h.plan, r.name'
        );

        return array_map(
            static fn(array $row): array => [
                'plan' => (string) $row['plan'],
                'race_id' => (int) $row['race_id'],
                'type' => (string) $row['type'],
                'item' => (string) $row['item'],
                'exhaust' => $row['exhaust'] === null ? null : (int) $row['exhaust'],
                'regrow' => $row['regrow'] === null ? null : (int) $row['regrow'],
            ],
            $rows
        );
    }

    /**
     * Saves what the screen holds. An empty rate is stored as NULL — "no rate"
     * and "a rate of zero" both mean never, and the plan files use the absence.
     *
     * @param array<int, array<string, mixed>> $rows keyed "plan|race_id"
     * @return int rows updated
     */
    public function save(array $rows): int
    {
        $saved = 0;

        foreach ($rows as $key => $row) {
            [$plan, $raceId] = array_pad(explode('|', (string) $key, 2), 2, '');

            if ($plan === '' || (int) $raceId === 0) {
                continue;
            }

            $rate = static function ($value): ?int {
                $value = trim((string) $value);

                return $value === '' ? null : max(0, (int) $value);
            };

            $saved += $this->conn()->executeStatement(
                'UPDATE race_harvest SET item = ?, exhaust = ?, regrow = ?
                  WHERE plan = ? AND race_id = ?',
                [
                    trim((string) ($row['item'] ?? '')),
                    $rate($row['exhaust'] ?? ''),
                    $rate($row['regrow'] ?? ''),
                    $plan,
                    (int) $raceId,
                ]
            );
        }

        return $saved;
    }

    /**
     * What each type yields on a plan: what the TYPE says, overridden by what
     * the plan says.
     *
     * Still no fallback to the plan JSON — a file nobody opens is a source
     * that rots, and that rule has not moved. A default on the type is the
     * opposite: it is edited, it is shown, and it is what makes a newly added
     * type work the moment it is posed, instead of yielding nothing until
     * someone declares it plan by plan.
     *
     * @return array<string, array{item: string, exhaust: ?int, regrow: ?int}>
     */
    public function yieldsFor(string $plan): array
    {
        $yields = [];

        foreach ($this->conn()->fetchAllAssociative(
            "SELECT name, harvest_item AS item, harvest_exhaust AS exhaust, harvest_regrow AS regrow
               FROM races
              WHERE harvest_item IS NOT NULL AND TRIM(harvest_item) <> ''"
        ) as $row) {
            $yields[(string) $row['name']] = [
                'item' => (string) $row['item'],
                'exhaust' => $row['exhaust'] === null ? null : (int) $row['exhaust'],
                'regrow' => $row['regrow'] === null ? null : (int) $row['regrow'],
            ];
        }

        foreach ($this->conn()->fetchAllAssociative(
            'SELECT r.name, h.item, h.exhaust, h.regrow
               FROM race_harvest h JOIN races r ON r.id = h.race_id
              WHERE h.plan = ?',
            [$plan]
        ) as $row) {
            $yields[(string) $row['name']] = [
                'item' => (string) $row['item'],
                'exhaust' => $row['exhaust'] === null ? null : (int) $row['exhaust'],
                'regrow' => $row['regrow'] === null ? null : (int) $row['regrow'],
            ];
        }

        return $yields;
    }

    /**
     * Resources standing on a plan with nothing to give — where fouiller
     * returns empty-handed.
     *
     * A type without a default AND without a row on this plan is mute. A plan
     * with no rows at all is no longer incomplete: since the type carries its
     * yield, saying nothing means "the catalogue answers", not "nobody poured
     * this one".
     *
     * Counted per TYPE, not per plan: a plan whose trees yield wood and whose
     * palms yield nothing is a plan where a player harvests in vain, and a
     * check for "any row on this plan" would call it settled.
     *
     * @return list<array{plan: string, resources: int, types: list<string>, inJson: int}>
     */
    public function plansMissingYields(): array
    {
        $rows = $this->conn()->fetchAllAssociative(
            "SELECT ec.plan, COUNT(*) AS resources, GROUP_CONCAT(DISTINCT p.race ORDER BY p.race) AS types
               FROM players p
               JOIN entity_cells ec ON ec.player_id = p.id
              WHERE p.player_type = 'resource'
                AND NOT EXISTS (
                    SELECT 1
                      FROM races d
                     WHERE d.name COLLATE utf8mb4_general_ci = p.race COLLATE utf8mb4_general_ci
                       AND d.harvest_item IS NOT NULL
                       AND TRIM(d.harvest_item) <> ''
                )
                AND NOT EXISTS (
                    SELECT 1
                      FROM race_harvest h
                      JOIN races r ON r.id = h.race_id
                     WHERE h.plan = ec.plan
                       AND r.name COLLATE utf8mb4_general_ci = p.race COLLATE utf8mb4_general_ci
                       AND TRIM(h.item) <> ''
                )
              GROUP BY ec.plan
              ORDER BY resources DESC"
        );

        return array_map(
            static fn(array $row): array => [
                'plan' => (string) $row['plan'],
                'resources' => (int) $row['resources'],
                'types' => array_values(array_filter(explode(',', (string) ($row['types'] ?? '')))),
                'inJson' => count(self::yieldsFromPlanJson((string) $row['plan'])),
            ],
            $rows
        );
    }

    /**
     * The legacy source — read by the SEED only, never at play time.
     *
     * @return array<string, array{item: string, exhaust: ?int, regrow: ?int}>
     */
    public static function yieldsFromPlanJson(string $plan): array
    {
        $json = json()->decode('plans', $plan);
        $yields = [];

        if ($json === false) {
            return $yields;
        }

        foreach ($json->biomes ?? [] as $biome) {
            $wall = (string) ($biome->wall ?? '');

            if ($wall === '' || $wall === '0') {
                continue;
            }

            $yields[$wall] ??= [
                'item' => (string) ($biome->ressource ?? ''),
                'exhaust' => isset($biome->exhaust) ? (int) $biome->exhaust : null,
                'regrow' => isset($biome->regrow) ? (int) $biome->regrow : null,
            ];
        }

        return $yields;
    }

    /**
     * Type name => races.id, compared on an explicit collation: the name
     * columns disagree across databases and a bare join errors 1267.
     *
     * @return array<string, int>
     */
    private function typesByName(): array
    {
        $types = [];

        foreach ($this->conn()->fetchAllAssociative(
            "SELECT id, name FROM races WHERE kind = 'structure'"
        ) as $row) {
            $types[(string) $row['name']] = (int) $row['id'];
        }

        return $types;
    }
}
