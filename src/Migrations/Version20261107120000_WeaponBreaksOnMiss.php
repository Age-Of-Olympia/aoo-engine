<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Every action whose success outcome may break equipment (damageobject)
 * gets a failure outcome that may break the attacker's weapon: a missed
 * blow still strikes something. The target's armour stays a hit-only risk,
 * since a miss never reached it. Actions are found by instruction type,
 * not by id, so actions present only on prod are covered too.
 */
final class Version20261107120000_WeaponBreaksOnMiss extends AbstractMigration
{
    private const OUTCOME = 'weapon_break_on_miss';

    public function getDescription(): string
    {
        return 'Casse de l\'arme de l\'attaquant aussi sur une attaque ratée';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO action_outcomes (apply_to, name, on_success, action_id)
             SELECT DISTINCT o.apply_to, ?, 0, o.action_id
               FROM action_outcomes o
               JOIN outcome_instructions oi ON oi.outcome_id = o.id AND oi.type = 'damageobject'
              WHERE o.on_success = 1
                AND NOT EXISTS (SELECT 1 FROM action_outcomes f WHERE f.action_id = o.action_id AND f.name = ?)",
            [self::OUTCOME, self::OUTCOME]
        );
        $this->addSql(
            "INSERT INTO outcome_instructions (type, parameters, orderIndex, outcome_id)
             SELECT 'damageobject', ?, 0, f.id
               FROM action_outcomes f
              WHERE f.name = ?
                AND NOT EXISTS (SELECT 1 FROM outcome_instructions oi WHERE oi.outcome_id = f.id)",
            [json_encode(['player' => 'ACTOR']), self::OUTCOME]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'DELETE oi FROM outcome_instructions oi JOIN action_outcomes f ON f.id = oi.outcome_id WHERE f.name = ?',
            [self::OUTCOME]
        );
        $this->addSql('DELETE FROM action_outcomes WHERE name = ?', [self::OUTCOME]);
    }
}
