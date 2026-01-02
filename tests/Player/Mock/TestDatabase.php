<?php
namespace Tests\Player\Mock;

use PDO;

/**
 * Mock database for testing player ID system
 * Uses SQLite in-memory database to simulate Doctrine DBAL Connection
 * This is NOT extending Connection, just mimicking its interface
 */
class TestDatabase
{
    private PDO $pdo;
    private static ?TestDatabase $instance = null;

    public function __construct()
    {
        // SQLite en mémoire
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTables();
    }

    public static function getInstance(): TestDatabase
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function createTables(): void
    {
        // Table players avec les colonnes ID system
        $this->pdo->exec("
            CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                player_type VARCHAR(20) DEFAULT 'real',
                display_id INTEGER NOT NULL DEFAULT 0,
                name VARCHAR(255),
                race VARCHAR(50),
                coords_id INTEGER,
                xp INTEGER DEFAULT 0,
                pi INTEGER DEFAULT 0,
                energie INTEGER DEFAULT 100,
                avatar VARCHAR(255),
                portrait VARCHAR(255),
                faction VARCHAR(50),
                nextTurnTime INTEGER,
                registerTime INTEGER,
                psw VARCHAR(255) DEFAULT '',
                mail VARCHAR(255) DEFAULT '',
                plain_mail VARCHAR(255) DEFAULT '',
                text TEXT DEFAULT ''
            )
        ");

        // Table coords
        $this->pdo->exec("
            CREATE TABLE coords (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                x INTEGER DEFAULT 0,
                y INTEGER DEFAULT 0,
                z INTEGER DEFAULT 0,
                plan VARCHAR(50) DEFAULT 'gaia'
            )
        ");

        // Insert default coords
        $this->pdo->exec("
            INSERT INTO coords (id, x, y, z, plan) VALUES (1, 0, 0, 0, 'gaia')
        ");
    }

    /**
     * Execute a query and return a Doctrine-like Result
     */
    public function executeQuery(string $sql, array $params = [], $types = []): MockResult
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return new MockResult($stmt);
    }

    /**
     * Execute a statement (INSERT, UPDATE, DELETE)
     */
    public function executeStatement(string $sql, array $params = [], array $types = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Insert a row
     */
    public function insert(string $table, array $data, array $types = []): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return $this->executeStatement($sql, array_values($data));
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId($name = null): string
    {
        return $this->pdo->lastInsertId();
    }

    // Helper methods for testing
    public function insertPlayer(array $data): int
    {
        $defaults = [
            'name' => 'Test Player',
            'race' => 'Humain',
            'coords_id' => 1,
            'player_type' => 'real',
            'display_id' => 1,
            'xp' => 0,
            'pi' => 0,
            'energie' => 100,
            'nextTurnTime' => time(),
            'registerTime' => time()
        ];

        $data = array_merge($defaults, $data);
        $this->insert('players', $data);

        return (int) $this->pdo->lastInsertId();
    }

    public function getPlayerCount(string $type = null): int
    {
        if ($type === null) {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM players");
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM players WHERE player_type = ?");
            $stmt->execute([$type]);
        }

        return (int) $stmt->fetchColumn();
    }

    public function getPlayer(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (object) $row : null;
    }

    public function clearPlayers(): void
    {
        $this->pdo->exec("DELETE FROM players");
    }
}

/**
 * Mock Result object that mimics Doctrine DBAL Result
 * This is NOT extending Result, just mimicking its interface
 */
class MockResult
{
    private $stmt;

    public function __construct($stmt)
    {
        $this->stmt = $stmt;
    }

    public function fetchAssociative(): array|false
    {
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchOne(): mixed
    {
        return $this->stmt->fetchColumn();
    }

    public function fetchAllAssociative(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    public function columnCount(): int
    {
        return $this->stmt->columnCount();
    }

    public function free(): void
    {
        $this->stmt->closeCursor();
    }

    public function fetchNumeric(): array|false
    {
        return $this->stmt->fetch(PDO::FETCH_NUM);
    }

    public function fetchAllNumeric(): array
    {
        return $this->stmt->fetchAll(PDO::FETCH_NUM);
    }
}
