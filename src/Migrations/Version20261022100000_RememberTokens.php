<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * "Se souvenir de moi": one row per remembered browser. The cookie carries
 * selector:validator, the table only the validator's hash.
 */
final class Version20261022100000_RememberTokens extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'table remember_tokens (connexion mémorisée par navigateur)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE IF NOT EXISTS remember_tokens (
                selector CHAR(24) NOT NULL,
                validator_hash CHAR(64) NOT NULL,
                player_id INT NOT NULL,
                footprint CHAR(32) NOT NULL DEFAULT \'\',
                expires INT NOT NULL,
                PRIMARY KEY (selector),
                CONSTRAINT fk_remember_tokens_player FOREIGN KEY (player_id)
                    REFERENCES players (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS remember_tokens');
    }
}
