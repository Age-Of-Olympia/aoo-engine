<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A repair is a dose of the TYPE's repair recipe, in one of two modes: raw
 * materials or gold alone. A dose is paid whole and restores a share of the
 * max PV. Recipes are keyed by type name like entity_type_footprints: a
 * placed chest answers through its item's name.
 *
 * Seeded from what the database already says, for every type the old rule
 * made repairable and every placed storage item: materials are the
 * construction recipe flattened to raw resources, scaled to one dose
 * (max(1, round(qty × repair_full_share × dose))); gold is the recipe's
 * worth on the same scale, with the artisan's margin, rounded to 5. Types
 * without a construction recipe borrow an obvious neighbour's, or the
 * team's école de guerre dose; the rest stay unrepairable. Insert-only: a
 * recipe already set is kept. The old races.repairable flag is no longer
 * read; it stays for the code still deployed.
 *
 * New tables only: safe to run before the code. The action switch is
 * Version20261008150000_ReparerIsNotAHeal, which ships with it.
 */
final class Version20261008140000_RepairRecipesPerType extends AbstractMigration
{
    /** Share of the max PV one seeded dose restores. */
    private const DOSE_PERCENT = 15;

    /** Types without a construction recipe that borrow another's. */
    private const BORROWS = [
        'carreaux' => 'route',
        'sentier' => 'route',
        'coffre_bois_petrifie' => 'coffre_bois',
        'coffre_metal' => 'coffre_bois',
        'coffre_humain' => 'coffre_bois',
    ];

    /** One dose of materials given outright (the trade halls, after the team's école de guerre). */
    private const DOSES = [
        'ecole_guerre' => ['bois' => 1, 'pierre' => 1, 'adonis' => 1],
        'banque' => ['bois' => 1, 'pierre' => 1, 'adonis' => 1],
        'echoppe' => ['bois' => 1, 'pierre' => 1, 'adonis' => 1],
    ];

    /** Named by the team as never mended. */
    private const NEVER = ['mur_blanc', 'mur_pierre_bleue', 'vache1', 'vache2'];

    /** @var array<string, array{yield: int, ingredients: array<string, float>}> */
    private array $recipes = [];

    /** @var array<string, int> */
    private array $prices = [];

    public function getDescription(): string
    {
        return 'recettes de réparation par type (matériaux, or), semées depuis les recettes de construction ; passe avant le code';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS entity_type_repairs (
                type_name VARCHAR(255) NOT NULL COMMENT 'Nom du type (players.race) : type du catalogue ou objet posé',
                mode VARCHAR(16) NOT NULL COMMENT 'materials | gold',
                percent TINYINT UNSIGNED NOT NULL COMMENT 'PV rendus par dose, en % des PV max',
                PRIMARY KEY (type_name, mode)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Recettes de réparation par type et par mode : sans ligne, pas de réparation'
        ");
        $this->addSql("
            CREATE TABLE IF NOT EXISTS entity_type_repair_costs (
                type_name VARCHAR(255) NOT NULL,
                mode VARCHAR(16) NOT NULL,
                item_id INT NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                PRIMARY KEY (type_name, mode, item_id),
                KEY k_item (item_id),
                CONSTRAINT fk_type_repair_costs_recipe FOREIGN KEY (type_name, mode)
                    REFERENCES entity_type_repairs (type_name, mode) ON DELETE CASCADE,
                CONSTRAINT fk_type_repair_costs_item FOREIGN KEY (item_id)
                    REFERENCES items (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
              COMMENT='Ce que coûte une dose de réparation (l''or est l''objet or)'
        ");

        $this->seedRecipes();
    }

    private function seedRecipes(): void
    {
        $settings = $this->connection->fetchAllKeyValue(
            "SELECT name, value FROM admin_settings WHERE name IN ('repair_full_share', 'repair_gold_margin')"
        );
        $doseShare = (float) ($settings['repair_full_share'] ?? 25) / 100 * self::DOSE_PERCENT / 100;
        $margin = (float) ($settings['repair_gold_margin'] ?? 150) / 100;

        $this->prices = array_map('intval', $this->connection->fetchAllKeyValue('SELECT name, price FROM items'));
        $this->loadRecipes();

        // What the old rule mended (races.repairable, else the family), and placed storage.
        $types = $this->connection->fetchFirstColumn(
            "SELECT name FROM races
              WHERE kind = 'structure'
                AND COALESCE(repairable, type_kind IN ('building', 'scenery', 'route'))
             UNION
             SELECT name FROM items WHERE lockable = 1"
        );

        foreach ($types as $type) {
            $type = (string) $type;
            if (in_array($type, self::NEVER, true) || str_starts_with($type, 'cadavre')) {
                continue;
            }

            if (isset(self::DOSES[$type])) {
                $materials = self::DOSES[$type];
                $doseWorth = 0;
                foreach ($materials as $name => $quantity) {
                    $doseWorth += ($this->prices[$name] ?? 0) * $quantity;
                }
            } else {
                $raw = $this->flatten(self::BORROWS[$type] ?? $type);
                if ($raw === []) {
                    continue; // nothing to build it from: not available by craft
                }
                $materials = [];
                $doseWorth = 0.0;
                foreach ($raw as $name => $count) {
                    $doseWorth += ($this->prices[$name] ?? 0) * $count * $doseShare;
                    if ($name !== 'or') {
                        $materials[$name] = max(1, (int) round($count * $doseShare));
                    }
                }
            }

            $this->seed($type, 'materials', $materials);
            $this->seed($type, 'gold', ['or' => max(5, (int) round($doseWorth * $margin / 5) * 5)]);
        }
    }

    /** @param array<string, int> $costs */
    private function seed(string $type, string $mode, array $costs): void
    {
        if ($costs === []) {
            return;
        }
        $this->addSql(
            'INSERT INTO entity_type_repairs (type_name, mode, percent)
             SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM entity_type_repairs WHERE type_name = ? AND mode = ?)',
            [$type, $mode, self::DOSE_PERCENT, $type, $mode]
        );
        // One statement, so a recipe already set keeps every line of its own.
        $cases = '';
        $params = [$type, $mode];
        foreach ($costs as $name => $quantity) {
            $cases .= ' WHEN ? THEN ?';
            array_push($params, $name, $quantity);
        }
        $names = array_keys($costs);
        $this->addSql(
            'INSERT INTO entity_type_repair_costs (type_name, mode, item_id, quantity)
             SELECT ?, ?, i.id, CASE i.name' . $cases . ' END FROM items i
              WHERE i.name IN (' . implode(', ', array_fill(0, count($names), '?')) . ')
                AND NOT EXISTS (SELECT 1 FROM entity_type_repair_costs c WHERE c.type_name = ? AND c.mode = ?)',
            [...$params, ...$names, $type, $mode]
        );
    }

    /** The recipe of each result: the one named like it, else the first. */
    private function loadRecipes(): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT r.id, r.name AS recipe, i.name AS result, rr.count AS yield
               FROM craft_recipes r
               JOIN craft_recipes_results rr ON rr.recipe_id = r.id
               JOIN items i ON i.id = rr.item_id
              ORDER BY (r.name = i.name) DESC, r.id'
        );
        $ingredients = [];
        foreach ($this->connection->fetchAllAssociative(
            'SELECT ci.recipe_id, i.name, ci.count FROM craft_recipes_ingredients ci JOIN items i ON i.id = ci.item_id'
        ) as $row) {
            $ingredients[(int) $row['recipe_id']][(string) $row['name']] = (float) $row['count'];
        }
        foreach ($rows as $row) {
            $this->recipes[(string) $row['result']] ??= [
                'yield' => max(1, (int) $row['yield']),
                'ingredients' => $ingredients[(int) $row['id']] ?? [],
            ];
        }
    }

    /**
     * One item's recipe down to raw resources, per unit made — the
     * RecipeWorthService reading, from rows instead of services.
     *
     * @param array<string, true> $seen
     * @return array<string, float> resource => count
     */
    private function flatten(string $item, array $seen = []): array
    {
        $recipe = $this->recipes[$item] ?? null;
        if ($recipe === null) {
            return [];
        }
        $seen[$item] = true;
        $raw = [];
        foreach ($recipe['ingredients'] as $name => $count) {
            $count /= $recipe['yield'];
            $parts = isset($seen[$name]) ? [] : $this->flatten($name, $seen);
            if ($parts === []) {
                $raw[$name] = ($raw[$name] ?? 0) + $count;
                continue;
            }
            foreach ($parts as $part => $partCount) {
                $raw[$part] = ($raw[$part] ?? 0) + $partCount * $count;
            }
        }

        return $raw;
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS entity_type_repair_costs');
        $this->addSql('DROP TABLE IF EXISTS entity_type_repairs');
    }
}
