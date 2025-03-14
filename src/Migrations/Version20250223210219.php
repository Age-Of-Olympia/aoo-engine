<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250223210219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_conditions (id INT AUTO_INCREMENT NOT NULL, conditionType VARCHAR(100) NOT NULL, parameters JSON DEFAULT NULL, action_id INT NOT NULL, execution_order INT NULL, blocking BOOLEAN NOT NULL DEFAULT FALSE , INDEX IDX_97C463639D32F035 (action_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE action_effects (id INT AUTO_INCREMENT NOT NULL, apply_to_self TINYINT(1) DEFAULT 0 NOT NULL, name VARCHAR(100) DEFAULT NULL, action_id INT NOT NULL, INDEX IDX_92A9B44B9D32F035 (action_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE actions (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE effect_instructions (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, onSuccess TINYINT(1) NOT NULL, onFailure TINYINT(1) NOT NULL, parameters JSON DEFAULT NULL, orderIndex INT DEFAULT 0 NOT NULL, effect_id INT NOT NULL, INDEX IDX_9DA2AC6FF5E9B83B (effect_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE race_actions (race_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_1AF8249F6E59D40D (race_id), INDEX IDX_1AF8249F9D32F035 (action_id), PRIMARY KEY(race_id, action_id))');
        $this->addSql('ALTER TABLE action_conditions ADD CONSTRAINT FK_97C463639D32F035 FOREIGN KEY (action_id) REFERENCES actions (id)');
        $this->addSql('ALTER TABLE action_effects ADD CONSTRAINT FK_92A9B44B9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id)');
        $this->addSql('ALTER TABLE effect_instructions ADD CONSTRAINT FK_9DA2AC6FF5E9B83B FOREIGN KEY (effect_id) REFERENCES action_effects (id)');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F6E59D40D FOREIGN KEY (race_id) REFERENCES races (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE audit CHANGE audit_key audit_key INT DEFAULT NULL, CHANGE ip_address ip_address VARCHAR(255) DEFAULT NULL, CHANGE details details LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE players_pnjs CHANGE displayed displayed TINYINT(1) NOT NULL, ADD PRIMARY KEY (player_id, pnj_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action_conditions DROP FOREIGN KEY FK_97C463639D32F035');
        $this->addSql('ALTER TABLE action_effects DROP FOREIGN KEY FK_92A9B44B9D32F035');
        $this->addSql('ALTER TABLE effect_instructions DROP FOREIGN KEY FK_9DA2AC6FF5E9B83B');
        $this->addSql('ALTER TABLE race_actions DROP FOREIGN KEY FK_1AF8249F6E59D40D');
        $this->addSql('ALTER TABLE race_actions DROP FOREIGN KEY FK_1AF8249F9D32F035');
        $this->addSql('DROP TABLE action_conditions');
        $this->addSql('DROP TABLE action_effects');
        $this->addSql('DROP TABLE actions');
        $this->addSql('DROP TABLE effect_instructions');
        $this->addSql('DROP TABLE race_actions');
    }
}
