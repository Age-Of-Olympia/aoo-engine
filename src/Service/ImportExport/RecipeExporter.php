<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Exporte les recettes d'artisanat en payloads à clé naturelle
 * (`name`). Ingrédients, résultats et races voyagent par NOMS d'objets
 * et de races — les ids sont propres à chaque environnement.
 */
final class RecipeExporter implements ObjectExporter
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

    public function exportAll(): array
    {
        $names = $this->connection()->fetchFirstColumn('SELECT name FROM craft_recipes ORDER BY name');

        return array_map(fn (string $name): array => $this->exportOne($name), $names);
    }

    public function exportOne(string $name): array
    {
        $conn = $this->connection();

        $id = $conn->fetchOne('SELECT id FROM craft_recipes WHERE name = ?', [$name]);
        if ($id === false) {
            throw new InvalidArgumentException("Recette inconnue : « {$name} ».");
        }

        return [
            'name' => $name,
            'ingredients' => $conn->fetchAllAssociative(
                'SELECT i.name AS item, ri.count FROM craft_recipes_ingredients ri
                 JOIN items i ON i.id = ri.item_id WHERE ri.recipe_id = ? ORDER BY i.name',
                [(int) $id]
            ),
            'results' => $conn->fetchAllAssociative(
                'SELECT i.name AS item, rr.count FROM craft_recipes_results rr
                 JOIN items i ON i.id = rr.item_id WHERE rr.recipe_id = ? ORDER BY i.name',
                [(int) $id]
            ),
            'races' => $conn->fetchFirstColumn(
                'SELECT ra.name FROM race_recipes r JOIN races ra ON ra.id = r.race_id
                 WHERE r.recipe_id = ? ORDER BY ra.name',
                [(int) $id]
            ),
        ];
    }

    /**
     * @param object $entity stdClass row of craft_recipes (name suffit)
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!isset($entity->name)) {
            throw new InvalidArgumentException('RecipeExporter::toArray attend une ligne craft_recipes.');
        }

        return $this->exportOne((string) $entity->name);
    }

    private function connection(): Connection
    {
        return $this->connection ??= EntityManagerFactory::getEntityManager()->getConnection();
    }
}
