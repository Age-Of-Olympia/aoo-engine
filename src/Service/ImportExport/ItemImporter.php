<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Importe des bundles d'objets ({@see ItemExporter}) en
 * create-or-update par clé naturelle (`name`), tout-ou-rien.
 */
final class ItemImporter implements ObjectImporter
{
    private ?Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    public function objectType(): string
    {
        return 'item';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->collect($objects, $report);

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $payloads = $this->collect($objects, $report);

        if ($report->hasRejections()) {
            return $report;
        }

        $conn = $this->connection ??= EntityManagerFactory::getEntityManager()->getConnection();

        try {
            $conn->transactional(function (Connection $conn) use ($payloads): void {
                foreach ($payloads as $payload) {
                    $this->apply($conn, $payload);
                }
            });
        } catch (Throwable $e) {
            $report->reject('lot', 'écriture échouée : ' . $e->getMessage());
        }

        return $report;
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<int, array<string, mixed>>
     */
    private function collect(array $objects, ImportReport $report): array
    {
        $conn = $this->connection ??= EntityManagerFactory::getEntityManager()->getConnection();
        $payloads = [];
        $seen = [];

        foreach ($objects as $index => $object) {
            if (!is_array($object) || !isset($object['name']) || trim((string) $object['name']) === '') {
                $report->reject('objet #' . $index, 'payload sans nom');
                continue;
            }
            $name = strtolower(trim((string) $object['name']));
            if (isset($seen[$name])) {
                $report->reject($name, 'doublon dans le bundle');
                continue;
            }
            $seen[$name] = true;

            $exists = $conn->fetchOne('SELECT id FROM items WHERE name = ?', [$name]) !== false;
            if ($exists) {
                $report->addUpdated($name);
            } else {
                $report->addCreated($name);
            }
            $object['name'] = $name;
            $payloads[] = $object;
        }

        return $payloads;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function apply(Connection $conn, array $payload): void
    {
        $name = (string) $payload['name'];

        if ($conn->fetchOne('SELECT id FROM items WHERE name = ?', [$name]) === false) {
            $conn->executeStatement('INSERT INTO items (name) VALUES (?)', [$name]);
        }

        $set = [];
        $params = [];

        foreach ([
            'private', 'enchanted', 'vorpal', 'cursed', 'is_deprecated', 'is_bankable',
            'wear_rate',
        ] as $col) {
            $set[] = "`{$col}` = ?";
            $params[] = (int) ($payload[$col] ?? 0);
        }
        // Un objet importé est par définition sourcé en base : sans ce
        // forçage, un payload sans la clé retomberait à 0 et le jeu
        // chercherait un JSON legacy qui n'existe pas ici.
        $set[] = '`stats_in_db` = 1';
        foreach (['element', 'spell', 'exotique', 'wear_triggers'] as $col) {
            $set[] = "`{$col}` = ?";
            $params[] = (string) ($payload[$col] ?? '');
        }
        foreach (ItemStatsSeeder::SCALAR_KEYS as $key) {
            $set[] = "`{$key}` = ?";
            $value = $payload[$key] ?? (in_array($key, ['text', 'emplacement', 'type', 'subtype', 'race'], true) ? '' : 0);
            $params[] = is_numeric($value) ? $value : (string) $value;
        }
        foreach (['munitions' => 'munitions', 'addEffects' => 'add_effects', 'forbid' => 'forbid', 'extra' => 'extra'] as $key => $col) {
            $set[] = "`{$col}` = ?";
            $params[] = isset($payload[$key])
                ? json_encode($payload[$key], JSON_UNESCAPED_UNICODE)
                : null;
        }

        $params[] = $name;
        $conn->executeStatement('UPDATE items SET ' . implode(', ', $set) . ' WHERE name = ?', $params);
    }
}
