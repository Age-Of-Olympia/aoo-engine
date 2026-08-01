<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * The type says whether a thing can be shut at all.
 *
 * A chest and a door can; a wall cannot. Nothing in the catalogues answered
 * that: `structure_nature` sorts a real building (`edifice`, which offers
 * services behind a door) from a built object (`obstacle`), and every chest in
 * the game is an `obstacle` — alongside the walls. The distinction it draws is
 * a different one, so it cannot be borrowed for this.
 *
 * One column per catalogue, because a thing that shuts may be a `races` type or
 * an `items` type — a stone doorway in a rampart and a gate carried in a bag
 * are the same rule read from two tables. Both answer through
 * {@see \App\Interface\LockableInterface}, so the caller never learns which
 * table replied.
 *
 * Seeded from what already behaves that way: the four chests, and the single
 * `edifice`, whose door the game already opens and closes. Everything else
 * stays shut out of the mechanism rather than being guessed into it.
 */
final class Version20260803180000_TheTypeSaysWhatCanBeShut extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.lockable / items.lockable: a type states whether it can be shut';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE races ADD COLUMN IF NOT EXISTS lockable TINYINT(1) NOT NULL DEFAULT 0'
        );
        $this->addSql(
            'ALTER TABLE items ADD COLUMN IF NOT EXISTS lockable TINYINT(1) NOT NULL DEFAULT 0'
        );

        /* Ce que fermer FAIT ne se déduit pas : un édifice fermé cesse de
         * servir, un coffre fermé retient son contenu, une porte fermée barre
         * le chemin. Seule la dernière touche au passage, et aucun type ne le
         * revendique encore — marquer un type de mur en fera une porte. */
        $this->addSql(
            'ALTER TABLE races ADD COLUMN IF NOT EXISTS opens_the_way TINYINT(1) NOT NULL DEFAULT 0'
        );

        /* Un édifice s'ouvre et se ferme déjà : le jeu tait son dialogue
         * quand il est clos. Sa fermeture décide de ses SERVICES, jamais du
         * passage — on n'a jamais marché à travers une taverne. */
        $this->addSql(
            "UPDATE races SET lockable = 1 WHERE kind = 'structure' AND structure_nature = 'edifice'"
        );

        /* Les coffres, des deux côtés : ils sont un type de bâtiment ET un objet
         * constructible tant que les deux existent. */
        $this->addSql("UPDATE races SET lockable = 1 WHERE name LIKE 'coffre%'");
        $this->addSql("UPDATE items SET lockable = 1 WHERE name LIKE 'coffre%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS opens_the_way');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS lockable');
        $this->addSql('ALTER TABLE items DROP COLUMN IF EXISTS lockable');
    }
}
