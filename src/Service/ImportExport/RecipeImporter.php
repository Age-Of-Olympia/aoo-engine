<?php

namespace App\Service\ImportExport;

use Doctrine\DBAL\Connection;

/**
 * Importe des bundles de recettes ({@see RecipeExporter}) en
 * create-or-update par clé naturelle (`name`), tout-ou-rien
 * (squelette {@see AbstractDbalImporter}). Les objets et races sont
 * résolus par NOM — une référence inconnue dans cet environnement
 * rejette le payload (importer d'abord le bundle d'objets).
 */
final class RecipeImporter extends AbstractDbalImporter
{
    public function objectType(): string
    {
        return 'recipe';
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
            if (!is_array($object) || trim((string) ($object['name'] ?? '')) === '') {
                $report->reject('recette #' . $index, 'payload sans nom');
                continue;
            }
            // Normalisation comme ItemImporter : la colonne est comparée en
            // casse-insensible (utf8mb4_general_ci), « Potion » et « potion »
            // entreraient en collision à l'écriture sans ceci.
            $name = mb_strtolower(trim((string) $object['name']));
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
            $workshop = (string) ($object['workshop'] ?? '');
            if ($workshop !== '' && $conn->fetchOne(
                "SELECT id FROM races WHERE name = ? AND type_kind = 'building'",
                [$workshop]
            ) === false) {
                $report->reject($name, "type de bâtiment inconnu : « {$workshop} »");
                $rejected = true;
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
    protected function apply(Connection $conn, array $payload): void
    {
        $name = (string) $payload['name'];

        $id = $conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [$name]);
        if ($id === false) {
            $conn->executeStatement('INSERT INTO craft_recipes (name) VALUES (?)', [$name]);
            $id = $conn->lastInsertId();
        }
        $id = (int) $id;

        $workshop = (string) ($payload['workshop'] ?? '');
        $conn->executeStatement(
            'UPDATE craft_recipes SET workshop = ? WHERE id = ?',
            [$workshop !== '' ? $workshop : null, $id]
        );

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
