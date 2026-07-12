<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Move race definitions from datas/[public|private]/races/*.json into the DB.
 *
 * Extends `races` with everything the JSON carried (display label, lore,
 * colors, faction, home plan, animator, the 16 CARACS stats) and adds two
 * child tables for the name lists:
 *  - race_starter_actions: pack granted at player creation (JSON `actions`)
 *  - race_spells:          spells learnable by the race   (JSON `spells`)
 * The JSON `actionsPack` field is NOT migrated: it was always the union of
 * the two lists above and is now computed (RaceService::getRaceData).
 *
 * Seeding reads whatever race JSON exists in THIS environment (dev and prod
 * ship different sets), then guarantees a row for every race the game names
 * in code (RACES / RACES_EXT snapshot below) so the historical "zero-filled
 * stats when the JSON is missing" behavior maps to a real zero-stat row.
 * Upserts key on the unique `code` column; `playable`/`hidden`/description
 * and portrait counters of existing rows are preserved.
 *
 * Idempotent (IF NOT EXISTS + ON DUPLICATE KEY) so it co-exists with
 * environments where parts were applied manually.
 */
final class Version20260710120000_RacesFromJson extends AbstractMigration
{
    /** Snapshot of the RACES constant (playable at registration). */
    private const PLAYABLE = ['nain', 'geant', 'olympien', 'hs', 'elfe'];

    /** Snapshot of RACES_EXT minus RACES (NPC/system races, never playable). */
    private const NON_PLAYABLE = ['lutin', 'humain', 'dieu', 'ame'];

    /**
     * PNJ-only race codes that never had JSON: they existed solely as
     * players.race values plus a color in ViewService's hardcoded map tables.
     */
    private const PNJ_RACES = [
        'animal', 'protocole', 'redoraan', 'saurien', 'triton', 'troglodyte', 'trotile',
    ];

    /**
     * Colors for races without JSON, snapshot of the hardcoded race→color
     * tables in ViewService::generate*PlayersLayer (the only place these
     * colors were defined). A race's own JSON bgColor always wins.
     */
    private const FALLBACK_BG_COLORS = [
        'geant' => '#661414',
        'olympien' => '#ff9933',
        'hs' => '#2e6650',
        'humain' => '#0000ff',
        'dieu' => '#000000',
        'animal' => '#D2B48C',
        'protocole' => '#0000ff',
        'redoraan' => '#D2B48C',
        'saurien' => '#661414',
        'triton' => '#661414',
        'troglodyte' => '#661414',
        'trotile' => '#D2B48C',
    ];

    /** The 16 stat keys (snapshot of the CARACS constant order). */
    private const CARAC_KEYS = [
        'a', 'mvt', 'p', 'pv', 'cc', 'ct', 'f', 'e',
        'agi', 'pm', 'fm', 'm', 'r', 'rm', 'spd', 'ae',
    ];

    public function getDescription(): string
    {
        return 'Extend races with JSON fields (label, colors, caracs, lists) and seed from datas/*/races/*.json';
    }

    public function up(Schema $schema): void
    {
        $this->addColumnsAndTables();
        $this->seed();
    }

    private function addColumnsAndTables(): void
    {
        $columns = [
            "label VARCHAR(100) NOT NULL DEFAULT ''",
            "bgColor VARCHAR(20) NOT NULL DEFAULT '#FFFFFF'",
            "color VARCHAR(20) NOT NULL DEFAULT 'black'",
            "faction VARCHAR(50) NOT NULL DEFAULT ''",
            "plan VARCHAR(50) NOT NULL DEFAULT ''",
            'animateurId INT DEFAULT NULL',
        ];
        foreach (self::CARAC_KEYS as $key) {
            $columns[] = "`{$key}` INT NOT NULL DEFAULT 0";
        }

        $this->addSql(
            'ALTER TABLE races ' . implode(', ', array_map(
                static fn (string $def): string => 'ADD COLUMN IF NOT EXISTS ' . $def,
                $columns
            ))
        );

        // `name` is the join key the whole game uses (players.race, recipes,
        // portraits) — make that contract explicit.
        $this->addSql('ALTER TABLE races ADD UNIQUE INDEX IF NOT EXISTS UNIQ_RACES_NAME (name)');

        foreach (['race_starter_actions', 'race_spells'] as $table) {
            $this->addSql(
                "CREATE TABLE IF NOT EXISTS {$table} (
                    id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                    race_id INT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    position INT NOT NULL DEFAULT 0,
                    UNIQUE KEY UNIQ_{$table}_race_name (race_id, name),
                    CONSTRAINT FK_{$table}_race FOREIGN KEY (race_id)
                        REFERENCES races (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    private function seed(): void
    {
        foreach ($this->collectRaces() as $name => $race) {
            $this->upsertRace($name, $race);
        }
    }

    /**
     * One entry per race the game knows: JSON files found in this environment,
     * plus code-referenced races without JSON (seeded with zero stats, exactly
     * what the old missing-file fallback produced at runtime).
     *
     * @return array<string, array{json: ?object, private: bool}>
     */
    private function collectRaces(): array
    {
        $races = [];

        foreach (['public', 'private'] as $visibility) {
            $dir = __DIR__ . '/../../datas/' . $visibility . '/races';
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $json = json_decode((string) file_get_contents($file));
                if (!is_object($json)) {
                    $this->warnIf(true, "races seed: skipping unreadable {$file}");
                    continue;
                }
                $races[basename($file, '.json')] = [
                    'json' => $json,
                    'private' => $visibility === 'private',
                ];
            }
        }

        foreach (array_merge(self::PLAYABLE, self::NON_PLAYABLE, self::PNJ_RACES) as $name) {
            $races[$name] ??= ['json' => null, 'private' => false];
        }

        return $races;
    }

    /**
     * @param array{json: ?object, private: bool} $race
     */
    private function upsertRace(string $name, array $race): void
    {
        $json = $race['json'];
        // A RACES race without JSON was never truly registrable (the select
        // skipped it and put_player would fail on the missing faction), so
        // only JSON-backed playable races keep the flag.
        $playable = in_array($name, self::PLAYABLE, true) && $json !== null;
        // Same visibility the game applied before: private JSON races and
        // code-only non-playable races never appeared in public lists.
        $hidden = $race['private'] || (!$playable && $json === null);

        $fields = [
            'code' => strtoupper($name),
            'name' => $name,
            'label' => $json->name ?? ucfirst($name),
            'description' => $json->text ?? '',
            'playable' => (int) $playable,
            'hidden' => (int) $hidden,
            'bgColor' => $this->normalizeBgColor($json->bgColor ?? self::FALLBACK_BG_COLORS[$name] ?? '#FFFFFF'),
            'color' => $json->color ?? 'black',
            'faction' => $json->faction ?? '',
            'plan' => $json->plan ?? '',
            'animateurId' => isset($json->animateur) ? (int) $json->animateur : null,
        ];
        foreach (self::CARAC_KEYS as $key) {
            $fields[$key] = (int) ($json->{$key} ?? 0);
        }

        // On existing rows keep identity, flags, lore and portrait counters
        // (possibly admin-tuned); refresh everything the JSON was the source
        // of truth for. description only fills in when currently empty.
        $updatable = array_diff(array_keys($fields), ['code', 'name', 'description', 'playable', 'hidden']);
        $updates = array_map(static fn (string $col): string => "`{$col}` = VALUES(`{$col}`)", $updatable);
        $updates[] = "description = IF(description IS NULL OR description = '', VALUES(description), description)";

        $columns = implode(', ', array_map(static fn (string $col): string => "`{$col}`", array_keys($fields)));
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));

        $this->addSql(
            "INSERT INTO races ({$columns}) VALUES ({$placeholders})
             ON DUPLICATE KEY UPDATE " . implode(', ', $updates),
            array_values($fields)
        );

        $this->seedNameList('race_starter_actions', $name, (array) ($json->actions ?? []));
        $this->seedNameList('race_spells', $name, (array) ($json->spells ?? []));
    }

    /**
     * bgColor feeds sscanf("#%02x%02x%02x") in the map-layer renderers, so
     * CSS color names from the JSON ('white') must become hex.
     */
    private function normalizeBgColor(string $color): string
    {
        return ['white' => '#FFFFFF', 'black' => '#000000'][strtolower($color)] ?? $color;
    }

    /**
     * Replace a race's name list with the JSON one (no-op when the JSON has
     * no list, so re-running never wipes admin-edited lists).
     *
     * @param string[] $names
     */
    private function seedNameList(string $table, string $raceName, array $names): void
    {
        if ($names === []) {
            return;
        }

        $this->addSql(
            "DELETE FROM {$table} WHERE race_id = (SELECT id FROM races WHERE name = ?)",
            [$raceName]
        );

        foreach (array_values($names) as $position => $actionName) {
            $this->addSql(
                "INSERT INTO {$table} (race_id, name, position)
                 SELECT id, ?, ? FROM races WHERE name = ?",
                [$actionName, $position, $raceName]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS race_starter_actions');
        $this->addSql('DROP TABLE IF EXISTS race_spells');

        $drops = ['label', 'bgColor', 'color', 'faction', 'plan', 'animateurId'];
        foreach (self::CARAC_KEYS as $key) {
            $drops[] = $key;
        }
        $this->addSql(
            'ALTER TABLE races ' . implode(', ', array_map(
                static fn (string $col): string => "DROP COLUMN IF EXISTS `{$col}`",
                $drops
            ))
        );
        $this->addSql('ALTER TABLE races DROP INDEX IF EXISTS UNIQ_RACES_NAME');
    }
}
