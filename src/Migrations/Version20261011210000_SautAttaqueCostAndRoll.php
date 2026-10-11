<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * saut_attaque: its Mvt cost was keyed "mouv", a carac that does not exist,
 * so checking the cost crashed; and its attack roll was blocking, so a miss
 * stopped the action before the cost was paid. Every other technique pays
 * for a miss. No range limit and the jump only on a hit are intended.
 */
final class Version20261011210000_SautAttaqueCostAndRoll extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'saut_attaque : coût Mvt sous la clé mvt (et non mouv), jet d\'attaque non bloquant comme les autres techniques';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = JSON_INSERT(JSON_REMOVE(c.parameters, '$.mouv'), '$.mvt', JSON_EXTRACT(c.parameters, '$.mouv'))
              WHERE a.name = 'saut_attaque'
                AND c.conditionType = 'RequiresTraitValue'
                AND JSON_VALID(c.parameters)
                AND JSON_CONTAINS_PATH(c.parameters, 'one', '$.mouv')"
        );
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 0
              WHERE a.name = 'saut_attaque' AND c.conditionType = 'MeleeCompute'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.blocking = 1
              WHERE a.name = 'saut_attaque' AND c.conditionType = 'MeleeCompute'"
        );
        $this->addSql(
            "UPDATE action_conditions c
               JOIN actions a ON a.id = c.action_id
                SET c.parameters = JSON_INSERT(JSON_REMOVE(c.parameters, '$.mvt'), '$.mouv', JSON_EXTRACT(c.parameters, '$.mvt'))
              WHERE a.name = 'saut_attaque'
                AND c.conditionType = 'RequiresTraitValue'
                AND JSON_VALID(c.parameters)
                AND JSON_CONTAINS_PATH(c.parameters, 'one', '$.mvt')"
        );
    }
}
