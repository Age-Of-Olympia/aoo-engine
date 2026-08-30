<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The element field leaves the items.
 *
 * The admin sheet promised "Élément porté par l'objet (feu, eau…) — marque le
 * nom et joue avec les règles élémentaires". Neither half was true: nothing
 * read `items.element`. The getter and setter on the entity were never called,
 * the name is coloured by `race`, and the elemental interactions live in the
 * effects catalogue as each effect's cancellation lists. Every row in every
 * environment holds the empty string.
 *
 * An admin field that reads as configuration and does nothing is how a ghost
 * becomes a rule, so it goes — with the promise it made.
 */
final class Version20260830150000_TheElementFieldLeavesTheItems extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'retire items.element : champ d\'administration sans aucun lecteur, les interactions élémentaires vivent dans le catalogue des effets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS element');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE items ADD COLUMN IF NOT EXISTS element VARCHAR(255) NOT NULL DEFAULT ''");
    }
}
