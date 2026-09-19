<?php

namespace App\Service\ImportExport;

use App\Service\ItemEffectService;
use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Connection;

/**
 * Importe des bundles d'objets ({@see ItemExporter}) en
 * create-or-update par clé naturelle (`name`), tout-ou-rien
 * (squelette {@see AbstractDbalImporter}).
 */
final class ItemImporter extends AbstractDbalImporter
{
    public function objectType(): string
    {
        return 'item';
    }

    /**
     * @param array<int, mixed> $objects
     * @return array<int, array<string, mixed>>
     */
    protected function collect(array $objects, ImportReport $report): array
    {
        $conn = $this->connection();
        $payloads = [];
        $seen = [];

        foreach ($objects as $index => $object) {
            if (!is_array($object) || !isset($object['name']) || trim((string) $object['name']) === '') {
                $report->reject('objet #' . $index, 'payload sans nom');
                continue;
            }
            $name = strtolower(trim((string) $object['name']));
            if ($this->isDuplicate($report, $seen, $name)) {
                continue;
            }

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
    protected function apply(Connection $conn, array $payload, ImportReport $report): void
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
        $set[] = '`durability_max` = ?';
        $params[] = max(1, (int) ($payload['durability_max'] ?? 100));
        $set[] = '`label` = ?';
        $params[] = mb_substr(trim((string) ($payload['label'] ?? '')), 0, 100);
        // Un objet importé est par définition sourcé en base : sans ce
        // forçage, un payload sans la clé retomberait à 0 et le jeu
        // chercherait un JSON legacy qui n'existe pas ici.
        $set[] = '`stats_in_db` = 1';
        foreach (['spell', 'exotique', 'wear_triggers', 'wear_profile'] as $col) {
            $set[] = "`{$col}` = ?";
            $params[] = (string) ($payload[$col] ?? '');
        }
        foreach (ItemStatsSeeder::SCALAR_KEYS as $key) {
            $set[] = "`{$key}` = ?";
            $value = $payload[$key] ?? (in_array($key, ItemStatsSeeder::STRING_KEYS, true) ? '' : 0);
            $params[] = is_numeric($value) ? $value : (string) $value;
        }
        foreach (['munitions' => 'munitions', 'forbid' => 'forbid', 'extra' => 'extra'] as $key => $col) {
            $set[] = "`{$col}` = ?";
            $params[] = isset($payload[$key])
                ? json_encode($payload[$key], JSON_UNESCAPED_UNICODE)
                : null;
        }

        $params[] = $name;
        $conn->executeStatement('UPDATE items SET ' . implode(', ', $set) . ' WHERE name = ?', $params);

        // Strike effects: the table rows; an older bundle's addEffects list is read the same way.
        $strikeRows = [];
        foreach ((array) ($payload['strikeEffects'] ?? $payload['addEffects'] ?? []) as $entry) {
            $entry = (array) $entry;
            if (trim((string) ($entry['name'] ?? '')) === '') {
                continue;
            }
            $strikeRows[] = [
                'name' => (string) $entry['name'],
                'duration' => (int) ($entry['duration'] ?? 1),
                'outcome' => (string) ($entry['outcome'] ?? 'hit'),
                'target' => (string) ($entry['target'] ?? 'target'),
            ];
        }
        $itemId = (int) $conn->fetchOne('SELECT id FROM items WHERE name = ?', [$name]);
        (new ItemEffectService($conn))->replaceForItem($itemId, $strikeRows);
    }
}
