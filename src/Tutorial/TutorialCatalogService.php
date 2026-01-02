<?php

namespace App\Tutorial;

use Classes\Db;
use InvalidArgumentException;

/**
 * Tutorial Catalog Service
 *
 * Manages the tutorial catalog - listing, creating, updating, and deleting tutorials.
 * Each tutorial in the catalog represents a distinct tutorial version with its own steps.
 */
class TutorialCatalogService
{
    private Db $db;

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
    }

    /**
     * Get all tutorials from the catalog with step counts
     *
     * @return array List of tutorials with step_count field
     */
    public function getAllTutorials(): array
    {
        $result = $this->db->exe("
            SELECT
                tc.*,
                (SELECT COUNT(*) FROM tutorial_steps ts
                 WHERE ts.version = tc.version AND ts.is_active = 1) as step_count
            FROM tutorial_catalog tc
            ORDER BY tc.display_order, tc.name
        ");

        $tutorials = [];
        while ($row = $result->fetch_assoc()) {
            $tutorials[] = $row;
        }
        return $tutorials;
    }

    /**
     * Get active tutorials only (for player-facing lists)
     *
     * @return array List of active tutorials
     */
    public function getActiveTutorials(): array
    {
        $result = $this->db->exe("
            SELECT
                tc.*,
                (SELECT COUNT(*) FROM tutorial_steps ts
                 WHERE ts.version = tc.version AND ts.is_active = 1) as step_count
            FROM tutorial_catalog tc
            WHERE tc.is_active = 1
            ORDER BY tc.display_order, tc.name
        ");

        $tutorials = [];
        while ($row = $result->fetch_assoc()) {
            $tutorials[] = $row;
        }
        return $tutorials;
    }

    /**
     * Get available versions for dropdowns (version => name mapping)
     *
     * @return array Associative array of version => name
     */
    public function getVersionsForSelect(): array
    {
        $result = $this->db->exe("
            SELECT version, name
            FROM tutorial_catalog
            ORDER BY display_order, name
        ");

        $versions = [];
        while ($row = $result->fetch_assoc()) {
            $versions[] = [
                'version' => $row['version'],
                'name' => $row['name']
            ];
        }
        return $versions;
    }

    /**
     * Get a tutorial by its ID
     *
     * @param int $id Tutorial catalog ID
     * @return array|null Tutorial data or null if not found
     */
    public function getById(int $id): ?array
    {
        $result = $this->db->exe("SELECT * FROM tutorial_catalog WHERE id = ?", [$id]);
        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Get a tutorial by its version string
     *
     * @param string $version Version string (e.g., "1.0.0")
     * @return array|null Tutorial data or null if not found
     */
    public function getByVersion(string $version): ?array
    {
        $result = $this->db->exe("SELECT * FROM tutorial_catalog WHERE version = ?", [$version]);
        $row = $result->fetch_assoc();
        return $row ?: null;
    }

    /**
     * Create a new tutorial in the catalog
     *
     * @param array $data Tutorial data
     * @return int The new tutorial ID
     * @throws InvalidArgumentException if required fields are missing
     */
    public function create(array $data): int
    {
        $this->validateData($data);

        $this->db->exe("
            INSERT INTO tutorial_catalog
            (version, name, description, icon, difficulty, estimated_minutes,
             prerequisites, plan, spawn_x, spawn_y, is_active, display_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $data['version'],
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? 'ra-book',
            $data['difficulty'] ?? 'beginner',
            $data['estimated_minutes'] ?? 10,
            $data['prerequisites'] ?? null,
            $data['plan'] ?? 'tutorial',
            $data['spawn_x'] ?? 0,
            $data['spawn_y'] ?? 0,
            $data['is_active'] ?? 1,
            $data['display_order'] ?? 0
        ]);

        $result = $this->db->exe("SELECT LAST_INSERT_ID() as id");
        return (int)$result->fetch_assoc()['id'];
    }

    /**
     * Update an existing tutorial
     *
     * @param int $id Tutorial ID
     * @param array $data Updated data
     * @throws InvalidArgumentException if required fields are missing
     */
    public function update(int $id, array $data): void
    {
        $this->validateData($data);

        $this->db->exe("
            UPDATE tutorial_catalog SET
                version = ?,
                name = ?,
                description = ?,
                icon = ?,
                difficulty = ?,
                estimated_minutes = ?,
                prerequisites = ?,
                plan = ?,
                spawn_x = ?,
                spawn_y = ?,
                is_active = ?,
                display_order = ?
            WHERE id = ?
        ", [
            $data['version'],
            $data['name'],
            $data['description'] ?? null,
            $data['icon'] ?? 'ra-book',
            $data['difficulty'] ?? 'beginner',
            $data['estimated_minutes'] ?? 10,
            $data['prerequisites'] ?? null,
            $data['plan'] ?? 'tutorial',
            $data['spawn_x'] ?? 0,
            $data['spawn_y'] ?? 0,
            $data['is_active'] ?? 1,
            $data['display_order'] ?? 0,
            $id
        ]);
    }

    /**
     * Delete a tutorial from the catalog
     *
     * Note: This does NOT delete associated steps. Use with caution.
     *
     * @param int $id Tutorial ID
     */
    public function delete(int $id): void
    {
        $this->db->exe("DELETE FROM tutorial_catalog WHERE id = ?", [$id]);
    }

    /**
     * Check if a version string is already in use
     *
     * @param string $version Version to check
     * @param int|null $excludeId Exclude this ID from the check (for updates)
     * @return bool True if version exists
     */
    public function versionExists(string $version, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM tutorial_catalog WHERE version = ?";
        $params = [$version];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $result = $this->db->exe($sql, $params);
        return (int)$result->fetch_assoc()['cnt'] > 0;
    }

    /**
     * Validate tutorial data
     *
     * @param array $data Data to validate
     * @throws InvalidArgumentException if validation fails
     */
    private function validateData(array $data): void
    {
        if (empty($data['version'])) {
            throw new InvalidArgumentException('Version is required');
        }
        if (empty($data['name'])) {
            throw new InvalidArgumentException('Name is required');
        }
        if (!empty($data['difficulty']) && !in_array($data['difficulty'], ['beginner', 'intermediate', 'advanced'])) {
            throw new InvalidArgumentException('Invalid difficulty level');
        }
    }
}
