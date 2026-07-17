<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use Throwable;

/**
 * Importe des bundles de recettes ({@see RecipeExporter}) en
 * create-or-update par clé naturelle (`name`), tout-ou-rien. Les
 * objets et races sont résolus par NOM — une référence inconnue dans
 * cet environnement rejette le payload (importer d'abord le bundle
 * d'objets).
 */
final class RecipeImporter implements ObjectImporter
{
    private ?Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        $this->connection = $connection;
    }

    public function objectType(): string
    {
        return 'recipe';
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
            if (!is_array($object) || trim((string) ($object['name'] ?? '')) === '') {
                $report->reject('recette #' . $index, 'payload sans nom');
                continue;
            }
            $name = trim((string) $object['name']);
            if (isset($seen[$name])) {
                $report->reject($name, 'doublon dans le bundle');
                continue;
            }
            $seen[$name] = true;

            $rejected = false;
            foreach (['ingredients', 'results'] as $listKey) {
                foreach ((array) ($object[$listKey] ?? []) as $line) {
                    $itemName = (string) ($line['item'] ?? '');
                    if ($conn->fetchOne('SELECT id FROM items WHERE name = ?', [$itemName]) === false) {
                        $report->reject($name, "objet inconnu : « {$itemName} » (importer d'abord le bundle d'objets)");
                        $rejected = true;
                        break 2;
                    }
                }
            }
            foreach ((array) ($object['races'] ?? []) as $raceName) {
                if ($conn->fetchOne('SELECT id FROM races WHERE name = ?', [(string) $raceName]) === false) {
                    $report->reject($name, "race inconnue : « {$raceName} »");
                    $rejected = true;
                    break;
                }
            }
            if ($rejected) {
                continue;
            }
            if ((array) ($object['ingredients'] ?? []) === [] || (array) ($object['results'] ?? []) === []) {
                $report->reject($name, 'au moins un ingrédient et un résultat requis');
                continue;
            }

            $exists = $conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [$name]) !== false;
            $exists ? $report->addUpdated($name) : $report->addCreated($name);
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

        $id = $conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [$name]);
        if ($id === false) {
            $conn->executeStatement('INSERT INTO craft_recipes (name) VALUES (?)', [$name]);
            $id = $conn->lastInsertId();
        }
        $id = (int) $id;

        foreach (['craft_recipes_ingredients', 'craft_recipes_results', 'race_recipes'] as $table) {
            $conn->executeStatement("DELETE FROM {$table} WHERE recipe_id = ?", [$id]);
        }
        foreach ((array) $payload['ingredients'] as $line) {
            $conn->executeStatement(
                'INSERT INTO craft_recipes_ingredients (count, recipe_id, item_id)
                 SELECT ?, ?, id FROM items WHERE name = ?',
                [max(1, (int) ($line['count'] ?? 1)), $id, (string) $line['item']]
            );
        }
        foreach ((array) $payload['results'] as $line) {
            $conn->executeStatement(
                'INSERT INTO craft_recipes_results (count, recipe_id, item_id)
                 SELECT ?, ?, id FROM items WHERE name = ?',
                [max(1, (int) ($line['count'] ?? 1)), $id, (string) $line['item']]
            );
        }
        foreach ((array) ($payload['races'] ?? []) as $raceName) {
            $conn->executeStatement(
                'INSERT IGNORE INTO race_recipes (race_id, recipe_id)
                 SELECT id, ? FROM races WHERE name = ?',
                [$id, (string) $raceName]
            );
        }
    }
}
