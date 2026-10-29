<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Keeps the monthly faith reward allocations available for later inspection. */
final class Version20261029100000_FaithRewardDistributions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le détail des attributions mensuelles des récompenses de foi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS faith_reward_distributions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                distribution_month CHAR(7) NOT NULL,
                god_id INT NOT NULL,
                player_id INT NOT NULL,
                player_name VARCHAR(255) NOT NULL,
                item_id INT NOT NULL,
                item_name VARCHAR(255) NOT NULL,
                quantity INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_faith_reward_distributions_month (distribution_month)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS faith_reward_distributions');
    }
}
