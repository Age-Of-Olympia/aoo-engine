<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Une surcharge de plan ne dit plus que ce qu'elle change.
 *
 * Depuis que le type porte son rendement, une ligne de `race_harvest` sert à
 * DÉVIER — « le même arbre donne moins dans le désert que dans la forêt ». Or
 * elle remplaçait l'entrée entière : régler la seule repousse obligeait à
 * redire l'objet et l'épuisement, et un taux laissé vide voulait dire JAMAIS,
 * pas « comme le type ».
 *
 * Pour que vide puisse vouloir dire « hérite », il faut d'abord que « jamais »
 * ait sa propre écriture. Elle existe déjà : les deux lecteurs font
 * `(($e['exhaust'] ?? 0) ?: 0) > 1dN`, donc 0 et NULL s'y valent — un taux nul
 * ne tire jamais. Passer les NULL à 0 ne change donc STRICTEMENT rien au jeu,
 * et libère NULL.
 *
 * Après quoi : 0 = jamais, vide = ce que dit le type.
 */
final class Version20260801160000_APlanOnlySaysWhatItChanges extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'race_harvest: NULL rates become 0 (never), so NULL can mean "inherit from the type"';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE race_harvest SET exhaust = 0 WHERE exhaust IS NULL');
        $this->addSql('UPDATE race_harvest SET regrow = 0 WHERE regrow IS NULL');
    }

    public function down(Schema $schema): void
    {
        /* On ne rend pas les 0 aux NULL : les deux disaient « jamais » avant
         * cette migration, et rien ne distingue plus ceux d'avant de ceux
         * qu'un animateur a saisis depuis. Les laisser à 0 conserve le sens ;
         * les repasser à NULL le conserverait aussi, mais effacerait une
         * intention explicite. Le down est donc volontairement vide. */
    }
}
