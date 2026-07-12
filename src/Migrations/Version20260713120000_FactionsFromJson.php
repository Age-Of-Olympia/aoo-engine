<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Factions become first-class DB entities (factions, faction_roles) instead
 * of datas/[public|private]/factions/*.json — same move as
 * Version20260710120000_RacesFromJson for races.
 *
 * players.faction / players.secretFaction keep holding the faction *code*
 * (JSON file basename, now factions.code) and players.factionRole /
 * secretFactionRole keep being 0-based indexes into the ordered role list,
 * now faction_roles.position — no player data changes.
 *
 * Seeding reads whatever faction JSON exists in THIS environment; in prod the
 * deployment runs migrations from the git checkout where datas/ (gitignored)
 * is absent, so only minimal fallback rows are created there (KNOWN_FACTIONS
 * snapshot + every code already referenced by players). The real JSON seed is
 * re-run from the web root via admin/faction-seed.php (FactionSeedService),
 * exactly like races.
 *
 * Idempotent (IF NOT EXISTS + ON DUPLICATE KEY) and re-runnable: existing
 * rows keep their hidden/secret flags and non-empty lore, and role lists are
 * only replaced when the JSON provides one.
 */
final class Version20260713120000_FactionsFromJson extends AbstractMigration
{
    /** Snapshot of the faction JSON files shipped at migration time. */
    private const KNOWN_FACTIONS = ['eryn_dolen', 'forge_sacree', 'saruta_et_freres'];

    /** Role permission flags, in the order the JSON files used them. */
    private const ROLE_FLAGS = [
        'defaultRole', 'showPosition', 'showForum', 'addMember',
        'editRole', 'kickMember', 'initRole',
    ];

    public function getDescription(): string
    {
        return 'Create factions + faction_roles tables and seed from datas/*/factions/*.json';
    }

    public function up(Schema $schema): void
    {
        $this->createTables();
        $this->seed();
    }

    private function createTables(): void
    {
        $this->addSql(
            "CREATE TABLE IF NOT EXISTS factions (
                id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                code VARCHAR(100) NOT NULL,
                name VARCHAR(100) NOT NULL DEFAULT '',
                text LONGTEXT DEFAULT NULL,
                raFont VARCHAR(50) NOT NULL DEFAULT '',
                respawnPlan VARCHAR(50) NOT NULL DEFAULT 'olympia',
                hidden TINYINT(1) NOT NULL DEFAULT 0,
                secret TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY UNIQ_FACTIONS_CODE (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addSql(
            'CREATE TABLE IF NOT EXISTS faction_roles (
                id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
                faction_id INT NOT NULL,
                position INT NOT NULL DEFAULT 0,
                name VARCHAR(100) NOT NULL,
                defaultRole TINYINT(1) NOT NULL DEFAULT 0,
                showPosition TINYINT(1) NOT NULL DEFAULT 0,
                showForum TINYINT(1) NOT NULL DEFAULT 0,
                addMember TINYINT(1) NOT NULL DEFAULT 0,
                editRole TINYINT(1) NOT NULL DEFAULT 0,
                kickMember TINYINT(1) NOT NULL DEFAULT 0,
                initRole TINYINT(1) NOT NULL DEFAULT 0,
                UNIQUE KEY UNIQ_faction_roles_position (faction_id, position),
                CONSTRAINT FK_faction_roles_faction FOREIGN KEY (faction_id)
                    REFERENCES factions (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function seed(): void
    {
        // 1. JSON files present in this environment (dev; absent in prod).
        foreach ($this->collectFactions() as $code => $faction) {
            $this->upsertFaction($code, $faction['json'], $faction['private']);
        }

        // 2. Snapshot fallback so prod (no datas/) still gets a row per known
        //    faction; a JSON-seeded row is left untouched (no-op update).
        foreach (self::KNOWN_FACTIONS as $code) {
            $this->addSql(
                'INSERT INTO factions (code, name) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE code = code',
                [$code, ucwords(str_replace('_', ' ', $code))]
            );
        }

        // 3. Safety net: unlike races there is no code-side list of faction
        //    codes, so guarantee a row for every code players already
        //    reference — a dangling players.faction would break the faction
        //    page. secret=1 only applies when the row is created here.
        $this->addSql(
            "INSERT INTO factions (code, name)
             SELECT DISTINCT faction, faction FROM players WHERE faction <> ''
             ON DUPLICATE KEY UPDATE code = code"
        );
        $this->addSql(
            "INSERT INTO factions (code, name, secret)
             SELECT DISTINCT secretFaction, secretFaction, 1 FROM players WHERE secretFaction <> ''
             ON DUPLICATE KEY UPDATE code = code"
        );
    }

    /**
     * @return array<string, array{json: object, private: bool}>
     */
    private function collectFactions(): array
    {
        $factions = [];

        foreach (['public', 'private'] as $visibility) {
            $dir = __DIR__ . '/../../datas/' . $visibility . '/factions';
            foreach (glob($dir . '/*.json') ?: [] as $file) {
                $json = json_decode((string) file_get_contents($file));
                if (!is_object($json)) {
                    $this->warnIf(true, "factions seed: skipping unreadable {$file}");
                    continue;
                }
                $factions[basename($file, '.json')] = [
                    'json' => $json,
                    'private' => $visibility === 'private',
                ];
            }
        }

        return $factions;
    }

    private function upsertFaction(string $code, object $json, bool $private): void
    {
        $fields = [
            'code' => $code,
            'name' => (string) ($json->name ?? ucwords(str_replace('_', ' ', $code))),
            'text' => (string) ($json->text ?? ''),
            'raFont' => (string) ($json->raFont ?? ''),
            'respawnPlan' => (string) ($json->respawnPlan ?? 'olympia'),
            'hidden' => (int) (!empty($json->hidden) || $private),
            'secret' => (int) !empty($json->secret),
        ];

        // Existing rows keep flags (possibly admin-tuned) and non-empty lore;
        // name/raFont/respawnPlan follow the JSON, their source of truth.
        $this->addSql(
            'INSERT INTO factions (code, name, text, raFont, respawnPlan, hidden, secret)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                raFont = VALUES(raFont),
                respawnPlan = VALUES(respawnPlan),
                text = IF(text IS NULL OR text = \'\', VALUES(text), text)',
            array_values($fields)
        );

        $this->seedRoles($code, is_array($json->role ?? null) ? $json->role : []);
    }

    /**
     * Replace a faction's role list with the JSON one (no-op when the JSON
     * has none, so re-running never wipes admin-edited roles). Positions are
     * the array order — players.factionRole indexes into it.
     *
     * @param array<int, mixed> $roles Raw JSON entries, unvalidated.
     */
    private function seedRoles(string $code, array $roles): void
    {
        if ($roles === []) {
            return;
        }

        $this->addSql(
            'DELETE FROM faction_roles WHERE faction_id = (SELECT id FROM factions WHERE code = ?)',
            [$code]
        );

        foreach (array_values($roles) as $position => $role) {
            if (!is_object($role) || trim((string) ($role->name ?? '')) === '') {
                $this->warnIf(true, "factions seed: skipping unnamed role #{$position} of {$code}");
                continue;
            }

            $params = [(string) $role->name, $position];
            foreach (self::ROLE_FLAGS as $flag) {
                $params[] = (int) !empty($role->{$flag});
            }
            $params[] = $code;

            $this->addSql(
                'INSERT INTO faction_roles
                    (name, position, ' . implode(', ', self::ROLE_FLAGS) . ', faction_id)
                 SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, id FROM factions WHERE code = ?',
                $params
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS faction_roles');
        $this->addSql('DROP TABLE IF EXISTS factions');
    }
}
