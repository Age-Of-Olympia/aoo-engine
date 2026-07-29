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
