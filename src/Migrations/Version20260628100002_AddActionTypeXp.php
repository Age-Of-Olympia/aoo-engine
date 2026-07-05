<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create action_type_xp and seed it from the calculate*Xp() methods. `mode`
 * picks the algorithm family, `params` its knobs. The non-combat types are
 * fixed rewards; "attack" (inherited by the whole attack family), "steal" and
 * "train" keep their algorithms, now reading the seeded constants
 * (ACTION_XP -> base, MAX_XP_FOR_STEALING -> cap, etc.).
 *
 * Idempotent: clears each type row before inserting.
 */
final class Version20260628100002_AddActionTypeXp extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_type_xp and seed per-type XP rules (was calculateXp)';
    }

    public function isTransactional(): bool
    {
        // CREATE TABLE auto-commits on MySQL, so wrapping it in a transaction
        // leaves nothing to commit at the end ("no active transaction").
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS action_type_xp (
            id INT AUTO_INCREMENT NOT NULL,
            type_key VARCHAR(100) NOT NULL,
            mode VARCHAR(30) NOT NULL,
            params JSON DEFAULT NULL,
            UNIQUE INDEX uniq_action_type_xp_type_key (type_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $fixed = static fn (int $as, int $af, int $ts, int $tf): string => json_encode(
            ['actorSuccess' => $as, 'actorFail' => $af, 'targetSuccess' => $ts, 'targetFail' => $tf]
        );

        $rows = [
            ['attack', 'attack', json_encode(['base' => 5, 'min' => 2, 'reducedXp' => 1, 'diffCap' => 3, 'targetFail' => 2])],
            ['buff',   'fixed',  $fixed(2, 0, 0, 0)],
            ['heal',   'fixed',  $fixed(3, 0, 0, 0)],
            ['pray',   'fixed',  $fixed(1, 0, 0, 0)],
            ['rest',   'fixed',  $fixed(0, 0, 0, 0)],
            ['run',    'fixed',  $fixed(1, 1, 0, 0)],
            ['search', 'fixed',  $fixed(1, 1, 0, 0)],
            ['steal',  'steal',  json_encode(['cap' => 3, 'targetFail' => 2])],
            ['train',  'train',  json_encode(['base' => 1, 'energieHighBonus' => 1, 'energieAnyBonus' => 1, 'rankBonus' => 1])],
        ];

        foreach ($rows as [$typeKey, $mode, $params]) {
            $this->addSql('DELETE FROM action_type_xp WHERE type_key = :k', ['k' => $typeKey]);
            $this->addSql(
                'INSERT INTO action_type_xp (type_key, mode, params) VALUES (:k, :m, :p)',
                ['k' => $typeKey, 'm' => $mode, 'p' => $params]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS action_type_xp');
    }
}
