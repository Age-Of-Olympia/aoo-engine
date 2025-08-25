<?php
namespace Tests\Logs\Mock;

use PDO;

class TestDatabaseLogs
{
    private PDO $pdo;

    public function __construct()
    {
        // SQLite en mémoire
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();
    }

    private function createTables(): void
    {
        // Table players_logs
        $this->pdo->exec("
            CREATE TABLE players_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER,
                target_id INTEGER,
                text TEXT,
                hiddenText TEXT DEFAULT '',
                type VARCHAR(50),
                plan VARCHAR(50),
                time INTEGER,
                coords_id INTEGER,
                coords_computed VARCHAR(100)
            )
        ");

        // Table coords
        $this->pdo->exec("
            CREATE TABLE coords (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                x INTEGER,
                y INTEGER,
                z INTEGER,
                plan VARCHAR(50)
            )
        ");

        // Ajouter quelques coordonnées par défaut
        $this->pdo->exec("
            INSERT INTO coords (id, x, y, z, plan) VALUES 
            (1, 0, 0, 0, 'test_plan'),
            (2, 5, 5, 0, 'test_plan'),
            (3, 10, 10, 0, 'test_plan')
        ");
    }

    public function exe(string $sql, array $params = [], bool $getAffectedRows = false)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($getAffectedRows) {
            return $stmt->rowCount();
        }
        
        return new TestDatabaseResult($stmt);
    }

    public function insert(string $table, array $values): bool
    {
        $columns = implode(', ', array_keys($values));
        $placeholders = ':' . implode(', :', array_keys($values));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function start_transaction(string $name): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit_transaction(string $name): void
    {
        $this->pdo->commit();
    }

    public function rollback_transaction(string $name): void
    {
        $this->pdo->rollBack();
    }

    // Méthodes utilitaires pour les tests
    public function insertLog(array $data): string|false
    {
        $defaults = [
            'player_id' => 1,
            'target_id' => 2,
            'text' => 'Test log',
            'hiddenText' => '',
            'type' => 'action',
            'plan' => 'test_plan',
            'time' => time(),
            'coords_id' => 1,
            'coords_computed' => '0_0_0_test_plan'
        ];
        
        $data = array_merge($defaults, $data);
        $this->insert('players_logs', $data);
        
        return $this->pdo->lastInsertId();
    }

    public function clearLogs(): void
    {
        $this->pdo->exec("DELETE FROM players_logs");
    }

    public function getLogCount(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM players_logs");
        return $stmt->fetchColumn();
    }
}

class TestDatabaseResult
{
    private $stmt;

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    public function fetch_object(): ?object
    {
        $row = $this->stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return (object) $row;
    }
}