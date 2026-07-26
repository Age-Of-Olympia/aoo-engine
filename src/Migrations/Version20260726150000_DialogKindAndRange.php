<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un dialogue n'a pas toujours la même nature ni la même portée
 * (décision 2026-07-26).
 *
 * `kind` — ce qu'on FAIT du dialogue :
 *   - 'interactive' : on s'adresse à quelqu'un. Un marchand, un
 *     tenancier ; l'arbre de réponses a un sens, la conversation est
 *     un échange. C'est le comportement historique, donc le défaut.
 *   - 'informative' : on LIT. Une pancarte, une inscription, une
 *     plaque. Il n'y a personne à qui parler — le texte se donne, sans
 *     qu'on ait à l'aborder.
 *
 * `readable_from_afar` — la PORTÉE :
 *   Un panneau se lit depuis la case voisine comme depuis trois cases
 *   plus loin ; une échoppe demande qu'on s'y présente. La règle
 *   d'adjacence existante (Chebyshev <= 1) reste le défaut, et ce
 *   drapeau la lève.
 *
 * Les deux sont distincts à dessein : on peut vouloir une inscription
 * qu'il faut approcher pour déchiffrer (informative, non lisible de
 * loin), ou un crieur public qu'on entend de loin (interactif, audible
 * de loin).
 */
final class Version20260726150000_DialogKindAndRange extends AbstractMigration
{
    public function getDescription(): string
    {
        return "dialogs : nature (lire / s'adresser) et portée (lisible de loin)";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE dialogs
             ADD COLUMN IF NOT EXISTS kind VARCHAR(20) NOT NULL DEFAULT 'interactive'"
        );
        $this->addSql(
            'ALTER TABLE dialogs
             ADD COLUMN IF NOT EXISTS readable_from_afar TINYINT(1) NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dialogs DROP COLUMN IF EXISTS kind');
        $this->addSql('ALTER TABLE dialogs DROP COLUMN IF EXISTS readable_from_afar');
    }
}
