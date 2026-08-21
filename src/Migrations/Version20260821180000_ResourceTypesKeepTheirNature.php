<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Un type récoltable garde sa nature.
 *
 * La sauvegarde partagée de l'éditeur de types (admin/races-save.php)
 * rabattait `structure_nature` sur édifice/obstacle pour tout visage autre
 * que le décor : un type créé depuis Types récoltables naissait avec la
 * bonne classe (`type_kind` = resource, posée par le visage) puis se
 * faisait écraser sa nature une ligne plus loin — et disparaissait de la
 * palette des ressources des deux éditeurs, qui filtre sur la nature.
 * Toute édition ultérieure d'un type récoltable, d'une plante ou d'un
 * décor produisait la même bascule silencieuse.
 *
 * Le code est corrigé (chaque visage impose sa nature) ; ici on répare les
 * lignes déjà touchées, en réalignant la nature sur la classe — la classe
 * est fiable, elle se fixe à la création et ne s'édite pas.
 */
final class Version20260821180000_ResourceTypesKeepTheirNature extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'races : structure_nature réaligné sur type_kind pour les types récoltables, plantes et décors écrasés par l\'éditeur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE races SET structure_nature = 'ressource' WHERE type_kind = 'resource' AND structure_nature <> 'ressource'");
        $this->addSql("UPDATE races SET structure_nature = 'plante' WHERE type_kind = 'plant' AND structure_nature <> 'plante'");
        $this->addSql("UPDATE races SET structure_nature = 'decor' WHERE type_kind = 'scenery' AND structure_nature <> 'decor'");
    }

    public function down(Schema $schema): void
    {
        // Les lignes corrigées étaient corrompues : rien à restaurer.
    }
}
