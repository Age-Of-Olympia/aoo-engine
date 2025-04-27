<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250427223731 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE race_actions (race_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_1AF8249F6E59D40D (race_id), INDEX IDX_1AF8249F9D32F035 (action_id), PRIMARY KEY(race_id, action_id))');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F6E59D40D FOREIGN KEY (race_id) REFERENCES races (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE race_actions ADD CONSTRAINT FK_1AF8249F9D32F035 FOREIGN KEY (action_id) REFERENCES actions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE actions CHANGE name name VARCHAR(50) NOT NULL, CHANGE icon icon VARCHAR(50) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE display_name display_name VARCHAR(50) NOT NULL, CHANGE text text VARCHAR(150) NOT NULL');
        $this->addSql('ALTER TABLE audit CHANGE action action VARCHAR(255) NOT NULL, CHANGE ip_address ip_address VARCHAR(255) DEFAULT NULL, CHANGE details details LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE outcome_instructions CHANGE type type VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE outcome_instructions RENAME INDEX idx_9da2ac6ff5e9b83b TO IDX_3502E1EFE6EE6D63');
        $this->addSql('ALTER TABLE races CHANGE description description LONGTEXT DEFAULT NULL, CHANGE playable playable TINYINT(1) NOT NULL, CHANGE hidden hidden TINYINT(1) NOT NULL, CHANGE portraitNextNumber portraitNextNumber INT DEFAULT 1 NOT NULL, CHANGE avatarNextNumber avatarNextNumber INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE races RENAME INDEX code TO UNIQ_5DBD1EC977153098');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        
    }
}
