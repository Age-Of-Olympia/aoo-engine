<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * On ne répare que ce qui est abîmé.
 *
 * `reparer` est de type `heal`, et `action_type_xp` accorde 3 XP par succès en
 * mode fixe — le meilleur rapport XP par point d'action du jeu. Ses trois
 * conditions ne vérifiaient que la distance, le type de cible et le coût :
 * aucune ne regardait si la structure visée était endommagée. Le soin étant
 * plafonné au déficit, réparer un bâtiment intact soignait zéro point et
 * rapportait l'XP quand même, indéfiniment.
 *
 * La condition est posée en execution_order 1, donc avant le coût
 * (RequiresTraitValue, order 3) : on ne facture pas l'action à qui vise une
 * cible intacte.
 */
final class Version20260727120000_ReparerRequiresDamage extends AbstractMigration
{
    private const REPARER_ACTION_ID = 92;

    public function getDescription(): string
    {
        return 'on ne répare que ce qui est abîmé : condition RequiresDamagedTarget sur reparer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresDamagedTarget', '{}', :action, 1, 1, 0
             FROM DUAL
             WHERE NOT EXISTS (
                SELECT 1 FROM (SELECT * FROM action_conditions) AS existing
                WHERE existing.action_id = :action
                  AND existing.conditionType = 'RequiresDamagedTarget'
             )",
            ['action' => self::REPARER_ACTION_ID]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM action_conditions WHERE action_id = :action AND conditionType = 'RequiresDamagedTarget'",
            ['action' => self::REPARER_ACTION_ID]
        );
    }
}
