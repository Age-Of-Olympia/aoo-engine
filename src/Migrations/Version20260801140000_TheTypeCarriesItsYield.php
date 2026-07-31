<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qu'une ressource rend appartient à son TYPE, pas au couple (plan, type).
 *
 * `race_harvest` était la source unique : un type absent de la table pour un
 * plan ne rendait RIEN. Poser un nouveau type de ressource ne suffisait donc
 * pas à le faire marcher — il fallait aller le déclarer plan par plan, et le
 * tableau de bord admin comptait les plans « sans rendement » parce que
 * l'incomplétude était l'état de départ.
 *
 * Arbitrage du lead : ajouter un type doit suffire. Le type porte son
 * rendement, `race_harvest` devient une SURCHARGE — une ligne n'existe plus
 * que là où un plan dévie volontairement.
 *
 * Le défaut est semé depuis ce que les plans disent déjà, et seulement quand
 * ils sont UNANIMES sur un type. Là où ils divergent, on ne tranche pas à leur
 * place : les lignes par plan restent, le type reste sans défaut, et la
 * divergence se voit à l'écran au lieu d'être moyennée en silence.
 *
 * Les lignes existantes ne sont pas supprimées, même devenues redondantes :
 * une surcharge identique au défaut est inerte, et les retirer serait jeter
 * l'intention de l'animateur sans qu'il l'ait demandé.
 */
final class Version20260801140000_TheTypeCarriesItsYield extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races.harvest_*: a resource type carries its own yield, race_harvest becomes the per-plan override';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS harvest_item VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS harvest_exhaust SMALLINT NULL');
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS harvest_regrow SMALLINT NULL');

        /* Unanimité seulement : COUNT(DISTINCT …) = 1 sur les trois colonnes,
         * NULL compris (COALESCE, car DISTINCT ignore les NULL et deux plans
         * « sans taux » passeraient pour d'accord avec un plan qui en a un). */
        $this->addSql(
            "UPDATE races r
               JOIN (
                    SELECT race_id,
                           MIN(item) AS item,
                           MIN(exhaust) AS exhaust,
                           MIN(regrow) AS regrow
                      FROM race_harvest
                     WHERE TRIM(item) <> ''
                     GROUP BY race_id
                    HAVING COUNT(DISTINCT item) = 1
                       AND COUNT(DISTINCT COALESCE(exhaust, -1)) = 1
                       AND COUNT(DISTINCT COALESCE(regrow, -1)) = 1
               ) h ON h.race_id = r.id
                SET r.harvest_item = h.item,
                    r.harvest_exhaust = h.exhaust,
                    r.harvest_regrow = h.regrow
              WHERE r.harvest_item IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS harvest_item');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS harvest_exhaust');
        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS harvest_regrow');
    }
}
