<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * La bordure de race dit d'un coup d'œil À QUI on a affaire. Elle a du
 * sens sur un personnage ; sur un mur, un coffre ou une statue, elle
 * encombre le décor sans rien apprendre — décision 2026-07-26 : elle
 * est désormais réservée aux personnages par défaut.
 *
 * L'option `hideStructureBorders` étant une PRÉSENCE de ligne (le
 * modèle d'options du jeu n'a pas de valeur, seulement l'existence
 * d'une entrée), la poser par défaut se traduit par une ligne pour
 * chacun. Les nouveaux joueurs la reçoivent à la création
 * (Player::put_player) ; celle-ci s'occupe de ceux qui existent déjà.
 *
 * Qui la refuse la retire du popover « Affichage » — et ne la verra
 * pas revenir : la migration ne s'exécute qu'une fois.
 */
final class Version20260726140000_HideStructureBordersByDefault extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'hideStructureBorders posée par défaut : la bordure de race reste aux personnages';
    }

    public function up(Schema $schema): void
    {
        /* INSERT ... SELECT plutôt qu'une boucle : une seule requête, et
         * le NOT EXISTS la rend rejouable sans créer de doublon. */
        $this->addSql(
            "INSERT INTO players_options (player_id, name)
             SELECT p.id, 'hideStructureBorders'
             FROM players AS p
             WHERE NOT EXISTS (
                 SELECT 1 FROM players_options AS o
                 WHERE o.player_id = p.id AND o.name = 'hideStructureBorders'
             )"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM players_options WHERE name = 'hideStructureBorders'");
    }
}
