<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ce qu'une entité a à dire vit déjà quelque part : `players.text`, le
 * message du jour. Un bâtiment ÉTANT une ligne de `players`, il a la
 * colonne — et elle est vierge : au recensement de l'expérimental, les
 * 13 549 bâtiments portaient tous le même texte, le défaut posé à leur
 * création. Une inscription n'est donc pas un champ de plus, c'est le
 * MDJ d'un objet : le personnage y met son message du jour, la pancarte
 * ce qui est gravé dessus.
 *
 * Reste la PORTÉE, et elle appartient au bâtiment, pas au texte : c'est
 * la taille de l'objet qui décide, pas ce qu'il raconte. Une grande
 * pancarte se lit de trois cases ; la même phrase gravée sur une plaque
 * demande qu'on s'approche. Le même texte sur deux objets n'a pas la
 * même portée — d'où un drapeau ici, et non sur le catalogue de
 * dialogues.
 *
 * Le défaut ne bouge pas : il faut être à côté, comme aujourd'hui.
 */
final class Version20260726150000_BuildingReadableFromAfar extends AbstractMigration
{
    public function getDescription(): string
    {
        return "buildings.readable_from_afar : ce qui se lit sans s'approcher";
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE buildings
             ADD COLUMN IF NOT EXISTS readable_from_afar TINYINT(1) NOT NULL DEFAULT 0'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buildings DROP COLUMN IF EXISTS readable_from_afar');
    }
}
