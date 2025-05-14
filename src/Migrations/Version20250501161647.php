<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250501161647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
     
        $this->addSql(<<<'SQL'
            CREATE TABLE craft_recipes (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, race_id INT DEFAULT NULL, INDEX IDX_26D39CCE6E59D40D (race_id), PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE craft_recipes_ingredients (id INT AUTO_INCREMENT NOT NULL, count INT DEFAULT 1 NOT NULL, item_id INT NOT NULL, recipe_id INT NOT NULL, INDEX IDX_3A88044F59D8A214 (recipe_id), PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE craft_recipes_results (id INT AUTO_INCREMENT NOT NULL, count INT DEFAULT 1 NOT NULL, item_id INT NOT NULL, recipe_id INT NOT NULL, INDEX IDX_7684F80159D8A214 (recipe_id), PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes ADD CONSTRAINT FK_26D39CCE6E59D40D FOREIGN KEY (race_id) REFERENCES races (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes_ingredients ADD CONSTRAINT FK_3A88044F59D8A214 FOREIGN KEY (recipe_id) REFERENCES craft_recipes (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes_results ADD CONSTRAINT FK_7684F80159D8A214 FOREIGN KEY (recipe_id) REFERENCES craft_recipes (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes_ingredients ADD CONSTRAINT FK_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT ON UPDATE CASCADE
         SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes DROP FOREIGN KEY FK_26D39CCE6E59D40D
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes_ingredients DROP FOREIGN KEY FK_3A88044F59D8A214
        SQL);

        $this->addSql(<<<'SQL'
        ALTER TABLE craft_recipes_ingredients DROP FOREIGN KEY FK_item
    SQL);
    
        $this->addSql(<<<'SQL'
            ALTER TABLE craft_recipes_results DROP FOREIGN KEY FK_7684F80159D8A214
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE craft_recipes
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE craft_recipes_ingredients
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE craft_recipes_results
        SQL);
    }
}
