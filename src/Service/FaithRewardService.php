<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/** Catalogue of objects available as rewards in the faith system. */
final class FaithRewardService
{
    public const MONTHLY_REWARD_COUNT_SETTING = 'faith_monthly_reward_count';
    public const DEFAULT_MONTHLY_REWARD_COUNT = 1;

    private Connection $connection;

    public function __construct()
    {
        $this->connection = EntityManagerFactory::getEntityManager()->getConnection();
    }

    public function monthlyRewardCount(): int
    {
        return max(0, (int) (new AdminSettingsService())->get(
            self::MONTHLY_REWARD_COUNT_SETTING,
            (string) self::DEFAULT_MONTHLY_REWARD_COUNT
        ));
    }

    public function setMonthlyRewardCount(int $count): void
    {
        if ($count < 0 || $count > 1000) {
            throw new \InvalidArgumentException('Le nombre de récompenses doit être compris entre 0 et 1 000.');
        }

        (new AdminSettingsService())->set(self::MONTHLY_REWARD_COUNT_SETTING, (string) $count);
    }

    /** @return list<array{id:int,name:string,label:string,type:string,quantity:int}> */
    public function rewards(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
            'type' => (string) $row['type'],
            'quantity' => (int) $row['quantity'],
        ], $this->connection->fetchAllAssociative(
            "SELECT i.id, i.name, COALESCE(NULLIF(i.label, ''), i.name) AS label, i.type, r.quantity
               FROM faith_reward_items r
               JOIN items i ON i.id = r.item_id
              ORDER BY label, i.name, i.id"
        ));
    }

    /** @return list<string> distinct catalogue types except the default */
    public function additionalTypes(): array
    {
        return array_values(array_filter(
            array_map('strval', $this->connection->fetchFirstColumn(
                'SELECT DISTINCT type FROM items ORDER BY type'
            )),
            static fn (string $type): bool => $type !== 'consommable'
        ));
    }

    /** @param list<string> $includeTypes
     *  @return list<array{id:int,name:string,label:string,type:string}>
     */
    public function availableItems(array $includeTypes, string $query = ''): array
    {
        $includeTypes = array_values(array_intersect(array_unique($includeTypes), $this->additionalTypes()));
        $types = array_merge(['consommable'], $includeTypes);
        $marks = implode(', ', array_fill(0, count($types), '?'));
        $params = $types;
        $where = "i.type IN ({$marks})";
        if ($query !== '') {
            $where .= " AND (i.name LIKE ? OR i.label LIKE ? OR i.id = ?)";
            $like = '%' . addcslashes($query, '%_\\') . '%';
            array_push($params, $like, $like, preg_match('/^\d+$/', $query) ? (int) $query : 0);
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'label' => (string) $row['label'],
            'type' => (string) $row['type'],
        ], $this->connection->fetchAllAssociative(
            "SELECT i.id, i.name, COALESCE(NULLIF(i.label, ''), i.name) AS label, i.type
               FROM items i
              WHERE {$where}
                AND NOT EXISTS (SELECT 1 FROM faith_reward_items r WHERE r.item_id = i.id)
              ORDER BY label, i.name, i.id",
            $params
        ));
    }

    /** @param list<string> $includeTypes */
    public function add(int $itemId, array $includeTypes, int $quantity = 1): void
    {
        $this->validateQuantity($quantity);
        $item = $this->connection->fetchAssociative('SELECT type FROM items WHERE id = ?', [$itemId]);
        if ($item === false) {
            throw new \InvalidArgumentException('Objet inconnu.');
        }
        $type = (string) $item['type'];
        $allowed = ['consommable', ...array_intersect(array_unique($includeTypes), $this->additionalTypes())];
        if (!in_array($type, $allowed, true)) {
            throw new \InvalidArgumentException('Ce type d’objet n’est pas activé dans les options de recherche.');
        }

        $this->connection->executeStatement(
            'INSERT IGNORE INTO faith_reward_items (item_id, quantity) VALUES (?, ?)',
            [$itemId, $quantity]
        );
    }

    public function setQuantity(int $itemId, int $quantity): bool
    {
        $this->validateQuantity($quantity);
        if ($this->connection->fetchOne('SELECT 1 FROM faith_reward_items WHERE item_id = ?', [$itemId]) === false) {
            return false;
        }

        $this->connection->update(
            'faith_reward_items',
            ['quantity' => $quantity],
            ['item_id' => $itemId]
        );

        return true;
    }

    public function remove(int $itemId): bool
    {
        return $this->connection->delete('faith_reward_items', ['item_id' => $itemId]) > 0;
    }

    private function validateQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > 2147483647) {
            throw new \InvalidArgumentException('La quantité doit être comprise entre 1 et 2 147 483 647.');
        }
    }
}
