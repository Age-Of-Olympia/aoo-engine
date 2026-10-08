<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * construire and consommer leave the buff type for their own (build,
 * consume): the event names the item instead of "a lancé Consommer". The
 * XP rule is the one they had as buff, now a row of their own the admin
 * can retune. Idempotent.
 */
final class Version20261008130000_BuildAndConsumeTypes extends AbstractMigration
{
    private const TYPES = [
        'construire' => ['build', '{actor} a construit {item}.'],
        'consommer' => ['consume', '{actor} a consommé {item}.'],
    ];

    public function getDescription(): string
    {
        return 'construire et consommer ont leur propre type (build, consume) et leur événement';
    }

    public function up(Schema $schema): void
    {
        foreach (self::TYPES as $name => [$typeKey, $template]) {
            $this->addSql("UPDATE actions SET type = ? WHERE name = ? AND type = 'buff'", [$typeKey, $name]);
            $this->addSql(
                "INSERT INTO action_type_xp (type_key, mode, params)
                 SELECT ?, 'fixed', '{\"actorSuccess\":2,\"actorFail\":0,\"targetSuccess\":0,\"targetFail\":0}'
                 WHERE NOT EXISTS (SELECT 1 FROM action_type_xp WHERE type_key = ?)",
                [$typeKey, $typeKey]
            );
            $this->addSql(
                'INSERT INTO action_type_logs (type_key, actor_template, target_template)
                 SELECT ?, ?, NULL
                 WHERE NOT EXISTS (SELECT 1 FROM action_type_logs WHERE type_key = ?)',
                [$typeKey, $template, $typeKey]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::TYPES as $name => [$typeKey]) {
            $this->addSql("UPDATE actions SET type = 'buff' WHERE name = ? AND type = ?", [$name, $typeKey]);
            $this->addSql('DELETE FROM action_type_logs WHERE type_key = ?', [$typeKey]);
            $this->addSql('DELETE FROM action_type_xp WHERE type_key = ?', [$typeKey]);
        }
    }
}
