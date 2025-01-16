<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250116002503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE races CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE playable playable TINYINT(1) NOT NULL, CHANGE hidden hidden TINYINT(1) NOT NULL, CHANGE portraitNextNumber portraitNextNumber INT DEFAULT 1 NOT NULL, CHANGE avatarNextNumber avatarNextNumber INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE races RENAME INDEX code TO UNIQ_5DBD1EC977153098');
        $this->addSql('CREATE TABLE actions (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE action_conditions (id INT AUTO_INCREMENT NOT NULL, conditionType VARCHAR(100) NOT NULL, parameters JSON DEFAULT NULL, action_id INT NOT NULL, INDEX IDX_97C463639D32F035 (action_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE action_effects (id INT AUTO_INCREMENT NOT NULL, applyToSelf TINYINT(1) DEFAULT 0 NOT NULL, name VARCHAR(100) DEFAULT NULL, action_id INT NOT NULL, INDEX IDX_92A9B44B9D32F035 (action_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE effect_instructions (id INT AUTO_INCREMENT NOT NULL, operation VARCHAR(50) NOT NULL, parameters JSON DEFAULT NULL, orderIndex INT DEFAULT 0 NOT NULL, effect_id INT NOT NULL, INDEX IDX_9DA2AC6FF5E9B83B (effect_id), PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE race_actions (race_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_1AF8249F6E59D40D (race_id), INDEX IDX_1AF8249F9D32F035 (action_id), PRIMARY KEY(race_id, action_id))');
        $this->addSql('ALTER TABLE action_conditions ADD CONSTRAINT FK_97C463639D32F035 FOREIGN KEY (action_id) REFERENCES actions (id)');
        $this->addSql('ALTER TABLE action_effects ADD CONSTRAINT FK_92A9B44B9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id)');
        $this->addSql('ALTER TABLE effect_instructions ADD CONSTRAINT FK_9DA2AC6FF5E9B83B FOREIGN KEY (effect_id) REFERENCES action_effects (id)');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F6E59D40D FOREIGN KEY (race_id) REFERENCES races (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE action_conditions');
        $this->addSql('DROP TABLE action_effects');
        $this->addSql('DROP TABLE actions');
        $this->addSql('DROP TABLE effect_instructions');
        $this->addSql('DROP TABLE race_actions');
        $this->addSql('ALTER TABLE races CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE playable playable TINYINT(1) DEFAULT NULL, CHANGE hidden hidden TINYINT(1) DEFAULT NULL, CHANGE portraitNextNumber portraitNextNumber INT DEFAULT NULL, CHANGE avatarNextNumber avatarNextNumber INT DEFAULT NULL');
        $this->addSql('ALTER TABLE races RENAME INDEX uniq_5dbd1ec977153098 TO code');
    }
}
