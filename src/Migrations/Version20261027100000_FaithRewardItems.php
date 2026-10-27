<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Persistent item catalogue for rewards offered by the faith system. */
final class Version20261027100000_FaithRewardItems extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le catalogue des objets pouvant être proposés en récompense de foi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS faith_reward_items (
                item_id INT NOT NULL PRIMARY KEY,
                quantity INT NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_faith_reward_items_item FOREIGN KEY (item_id)
                    REFERENCES items (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS faith_reward_items');
    }
}
