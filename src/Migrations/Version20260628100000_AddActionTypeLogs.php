<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create action_type_logs and seed it with the templates that were hardcoded in
 * each Action subclass's getLogMessages(). An action inherits the closest type
 * in its ancestry that has a row, so only the types that actually overrode the
 * method are seeded (a spell falls back to "technique", a melee to "attack").
 *
 * Placeholders: {actor}, {target}, {action}, {weapon}. The seeded text is the
 * verbatim French of the old methods.
 *
 * Idempotent: drops/clears before (re)creating.
 */
final class Version20260628100000_AddActionTypeLogs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create action_type_logs and seed per-type log templates (was getLogMessages)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS action_type_logs (
            id INT AUTO_INCREMENT NOT NULL,
            type_key VARCHAR(100) NOT NULL,
            actor_template TEXT DEFAULT NULL,
            target_template TEXT DEFAULT NULL,
            UNIQUE INDEX uniq_action_type_logs_type_key (type_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // verbatim from the old per-subclass getLogMessages()
        $rows = [
            ['attack',    '{actor} a attaqué {target}{weapon}.',           '{target} a été attaqué par {actor}{weapon}.'],
            ['technique', '{actor} a lancé {action} sur {target}.',        '{target} a été attaqué par {actor} avec {action}.'],
            ['buff',      '{actor} a lancé {action}.',                     null],
            ['heal',      '{actor} a lancé {action} sur {target}.',        '{target} a été soigné par {actor} avec {action}.'],
            ['pray',      '{actor} a prié.',                               null],
            ['rest',      'Vous vous êtes reposé.',                        null],
            ['run',       'Vous avez couru.',                              null],
            ['search',    'Vous avez fouillé les alentours.',             null],
            ['steal',     '{actor} a volé {target}.',                      '{target} a été volé par {actor}.'],
            ['train',     "{actor} s'est entraîné avec {target}.",         '{target} a été entraîné par {actor}.'],
        ];

        foreach ($rows as [$typeKey, $actorTemplate, $targetTemplate]) {
            $this->addSql('DELETE FROM action_type_logs WHERE type_key = :k', ['k' => $typeKey]);
            $this->addSql(
                'INSERT INTO action_type_logs (type_key, actor_template, target_template) VALUES (:k, :a, :t)',
                ['k' => $typeKey, 'a' => $actorTemplate, 't' => $targetTemplate]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS action_type_logs');
    }
}
