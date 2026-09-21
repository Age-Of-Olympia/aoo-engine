<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921100000_UpdateSkillCosts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mise à jour des coûts et conditions pour quelques actions';
    }

    public function up(Schema $schema): void
    {
        // 1. Mise à jour de la table action_conditions
        $this->addSql("UPDATE action_conditions SET parameters = '{\"imposture\": [4,1]}' WHERE action = 86 AND conditionType = 'RequiresTraitValue'");
        $this->addSql("UPDATE action_conditions SET parameters = '{\"imposture\": [4,1]}' WHERE action = 70 AND conditionType = 'RequiresTraitValue'");
        $this->addSql("UPDATE action_conditions SET parameters = '{"a":1, "pm":4}' WHERE action_id = 50 AND conditionType = 'RequiresTraitValue'");

        // 2. Mise à jour de la table actions
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>' WHERE name = 'discretion'");
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #2980b9;\">4x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>' WHERE name = 'pas-leger'");
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">4 PM</span>' WHERE name = 'manchette'");
    }

    public function down(Schema $schema): void
    {
        // Rollback table action_conditions
        $this->addSql("UPDATE action_conditions SET parameters = '{ \"a\": 1, \"imposture\": [2,1] }' WHERE action = 86 AND conditionType = 'RequiresTraitValue'");
        $this->addSql("UPDATE action_conditions SET parameters = '{ \"a\": 1, \"imposture\": [2,0.5] }' WHERE action = 70 AND conditionType = 'RequiresTraitValue'");
        $this->addSql("UPDATE action_conditions SET parameters = '{"a":1, "pm":2}' WHERE action_id = 50 AND conditionType = 'RequiresTraitValue'");

        // Rollback table actions
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1/2x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>' WHERE name = 'discretion'");
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2x(<i class=\"ra ra-player-teleport\"></i>+1) PM</span>, <span style=\"color: #27ae60;\">1x(<i class=\"ra ra-player-teleport\"></i>+1) Mvt</span>' WHERE name = 'pas-leger'");
        $this->addSql("UPDATE actions SET cost = '<span style=\"color: #8e44ad;\">1 A</span>, <span style=\"color: #2980b9;\">2 PM</span>' WHERE name = 'manchette'");
    }
}