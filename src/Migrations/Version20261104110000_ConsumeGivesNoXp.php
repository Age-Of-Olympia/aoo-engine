<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The consume action type (consommer) no longer rewards XP: its fixed rule
 * drops from 2 actor XP on success to 0. Guarded on the current params so a
 * rule retuned from the admin is left untouched. Idempotent.
 */
final class Version20261104110000_ConsumeGivesNoXp extends AbstractMigration
{
    private const OLD_ACTOR_SUCCESS = 2;
    private const NEW_ACTOR_SUCCESS = 0;

    public function getDescription(): string
    {
        return 'Le type consume (consommer) donne 0 XP au lieu de 2';
    }

    public function up(Schema $schema): void
    {
        $this->setActorSuccess(self::OLD_ACTOR_SUCCESS, self::NEW_ACTOR_SUCCESS);
    }

    public function down(Schema $schema): void
    {
        $this->setActorSuccess(self::NEW_ACTOR_SUCCESS, self::OLD_ACTOR_SUCCESS);
    }

    /** Only touches the rule when it still holds the expected value (admin retunes are kept). */
    private function setActorSuccess(int $from, int $to): void
    {
        $this->addSql(
            "UPDATE action_type_xp SET params = JSON_SET(params, '$.actorSuccess', ?)
             WHERE type_key = 'consume' AND mode = 'fixed'
               AND JSON_EXTRACT(params, '$.actorSuccess') = ?",
            [$to, $from]
        );
    }
}
