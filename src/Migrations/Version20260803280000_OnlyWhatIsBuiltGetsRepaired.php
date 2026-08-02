<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qui se répare, le TYPE le dit — et la visée cesse de le dire à sa place.
 *
 * Deux réponses à la même question s'étaient croisées. La première, arrivée par
 * `Version20260803270000`, nommait les familles réparables dans la visée de
 * `reparer` : juste, mais gravé dans la donnée d'une action, donc
 * incontestable par un type. La seconde — celle-ci — pose la question au type,
 * avec un défaut par famille et une case pour le contredire.
 *
 * Elles ne se contredisent pas, elles se recouvrent, et c'est la mauvaise qui
 * restait : tant que la visée filtre les familles, cocher « réparable » sur un
 * type de ressource ne produirait rien et la case mentirait. La visée revient
 * donc à l'enveloppe large — `structure`, ce que l'action peut atteindre — et
 * la nouvelle condition tranche, type par type.
 *
 * La colonne est NULLABLE à dessein : `null` = ce que dit la famille (bâti et
 * décor s'entretiennent, ce qui pousse ou gît non), si bien que changer la
 * règle d'une famille suit tous ceux qui s'en remettent à elle.
 */
final class Version20260803280000_OnlyWhatIsBuiltGetsRepaired extends AbstractMigration
{
    private const ENVELOPE = '{"allowed":["structure"]}';

    public function getDescription(): string
    {
        return 'races.repairable + condition RequiresRepairableTarget sur reparer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS repairable TINYINT(1) NULL');

        /* Par le NOM : l'id de `reparer` dépend de l'ordre dans lequel les
         * catalogues ont été semés, et il diffère déjà d'une base à l'autre. */
        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresRepairableTarget', '{}', a.id, 1, 1, 1
               FROM actions a
              WHERE a.name = 'reparer'
                AND NOT EXISTS (
                    SELECT 1 FROM (SELECT action_id, conditionType FROM action_conditions) AS existing
                     WHERE existing.action_id = a.id
                       AND existing.conditionType = 'RequiresRepairableTarget'
                )"
        );

        /* L'enveloppe redevient large : c'est le type qui tranche désormais,
         * et une liste de familles ici l'empêcherait d'être contredit. */
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = ?
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'",
            [self::ENVELOPE]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE c FROM action_conditions c
               JOIN actions a ON a.id = c.action_id
              WHERE a.name = 'reparer' AND c.conditionType = 'RequiresRepairableTarget'"
        );

        /* Retour à la garde par familles, qui redevient la seule en place. */
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = '{\"allowed\":[\"building\",\"scenery\",\"item\"]}'
              WHERE a.name = 'reparer' AND c.conditionType = 'TargetType'"
        );

        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS repairable');
    }
}
