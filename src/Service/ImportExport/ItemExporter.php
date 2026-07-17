<?php

namespace App\Service\ImportExport;

use App\Entity\EntityManagerFactory;
use App\Service\ItemStatsSeeder;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;

/**
 * Exporte le catalogue d'objets en payloads à clé naturelle (`name`).
 * Le payload porte tout ce que l'éditeur admin gère : flags, usure,
 * stats (colonnes JSON→DB), listes munitions/addEffects/forbid et le
 * fourre-tout extra — reservi verbatim, la garantie sans-perte suit
 * l'objet dans le bundle.
 */
final class ItemExporter implements ObjectExporter
{
    private ?Connection $connection;

    public function __construct(?Connection $connection = null)
    {
        // Lazy : l'instanciation ne doit pas ouvrir de connexion DB.
        $this->connection = $connection;
    }

    public function objectType(): string
    {
        return 'item';
    }

    public function exportAll(): array
    {
        $rows = $this->connection()->fetchAllAssociative('SELECT * FROM items ORDER BY name');

        return array_map(fn (array $row): array => $this->toArray((object) $row), $rows);
    }

    public function exportOne(string $name): array
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM items WHERE name = ?', [$name]);
        if ($row === false) {
            throw new InvalidArgumentException("Objet inconnu : « {$name} ».");
        }

        return $this->toArray((object) $row);
    }

    /**
     * @param object $entity stdClass row of the items table
     * @return array<string, mixed>
     */
    public function toArray(object $entity): array
    {
        if (!isset($entity->name)) {
            throw new InvalidArgumentException('ItemExporter::toArray attend une ligne items.');
        }

        $payload = [
            'name' => (string) $entity->name,
            'private' => (int) $entity->private,
            'enchanted' => (int) $entity->enchanted,
            'vorpal' => (int) $entity->vorpal,
            'cursed' => (int) $entity->cursed,
            'element' => (string) ($entity->element ?? ''),
            'spell' => (string) ($entity->spell ?? ''),
            'is_deprecated' => (int) $entity->is_deprecated,
            'is_bankable' => (int) $entity->is_bankable,
            'exotique' => (string) ($entity->exotique ?? ''),
            'wear_triggers' => (string) ($entity->wear_triggers ?? ''),
            'wear_rate' => (int) ($entity->wear_rate ?? 0),
            'stats_in_db' => (int) ($entity->stats_in_db ?? 0),
        ];

        foreach (ItemStatsSeeder::SCALAR_KEYS as $key) {
            $payload[$key] = is_numeric($entity->$key ?? null) ? $entity->$key + 0 : (string) ($entity->$key ?? '');
        }

        foreach (['munitions' => 'munitions', 'add_effects' => 'addEffects', 'forbid' => 'forbid', 'extra' => 'extra'] as $column => $key) {
            $payload[$key] = !empty($entity->$column) ? json_decode((string) $entity->$column, true) : null;
        }

        return $payload;
    }

    private function connection(): Connection
    {
        return $this->connection ??= EntityManagerFactory::getEntityManager()->getConnection();
    }
}
