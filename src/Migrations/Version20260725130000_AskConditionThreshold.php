<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une demande d'achat porte un objet de CATALOGUE : « je cherche une
 * épée ». Depuis que les exemplaires individualisés circulent, y
 * répondre peut vouloir dire livrer une épée à 3/20 — et le demandeur,
 * qui a bloqué son or à l'avance, n'aurait aucun moyen de s'y opposer.
 *
 * Il déclare donc l'état le PIRE qu'il accepte, en pourcentage de
 * durabilité. Trois niveaux à l'écran, mais un pourcentage en base : le
 * jour où un quatrième palier sera voulu, il ne coûtera rien.
 *
 *   100 → neuf : uniquement un exemplaire intact (ou une pile, qui
 *         l'est par construction)
 *    50 → bon état : la bande verte de l'affichage d'usure
 *     1 → mauvais état : tout sauf un objet brisé, qui ne contribue
 *         plus ses caractéristiques et ne peut donc satisfaire personne
 *     0 → aucune contrainte : la valeur des lignes existantes, dont le
 *         sens actuel est « rien n'était vérifié »
 *
 * Idempotent, et rétro-compatible : le défaut 0 laisse les demandes
 * déjà ouvertes se comporter comme avant.
 */
final class Version20260725130000_AskConditionThreshold extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'items_asks.min_durability_pct — the worst condition a buyer accepts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE items_asks
             ADD COLUMN IF NOT EXISTS min_durability_pct TINYINT UNSIGNED NOT NULL DEFAULT 0
             COMMENT '0 = aucune contrainte, 1 = pas de brisé, 50 = bon état, 100 = neuf'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE items_asks DROP COLUMN IF EXISTS min_durability_pct');
    }
}
