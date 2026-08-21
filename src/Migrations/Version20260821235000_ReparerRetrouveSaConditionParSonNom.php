<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige Version20260727120000_ReparerRequiresDamage, qui visait `reparer`
 * par un id en dur (92). Les ids d'actions ne sont pas stables d'une base à
 * l'autre (imports de bundles, re-seeds) : sur une base où 92 est une autre
 * action, la condition RequiresDamagedTarget s'est posée sur elle — en local,
 * sur l'attaque dmg2/uppercut, bloquée face à une cible intacte — pendant que
 * `reparer` restait sans garde, ce qui rouvrait la ferme à XP que la
 * condition devait fermer.
 *
 * Ici on retire les lignes RequiresDamagedTarget posées ailleurs que sur
 * `reparer` (ou orphelines), puis on pose la condition en visant l'action par
 * son NOM. Rejouable sans effet sur une base déjà correcte.
 */
final class Version20260821235000_ReparerRetrouveSaConditionParSonNom extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'la condition RequiresDamagedTarget vise reparer par son nom, plus par un id en dur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE ac FROM action_conditions ac
               LEFT JOIN actions a ON a.id = ac.action_id
              WHERE ac.conditionType = 'RequiresDamagedTarget'
                AND (a.id IS NULL OR a.name <> 'reparer')"
        );

        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresDamagedTarget', '{}', a.id, 1, 1, 0
               FROM actions a
              WHERE a.name = 'reparer'
                AND NOT EXISTS (
                    SELECT 1 FROM action_conditions ac
                     WHERE ac.action_id = a.id AND ac.conditionType = 'RequiresDamagedTarget'
                )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE ac FROM action_conditions ac
               JOIN actions a ON a.id = ac.action_id
              WHERE ac.conditionType = 'RequiresDamagedTarget' AND a.name = 'reparer'"
        );
    }
}
