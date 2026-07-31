<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * On ne répare que ce qui se répare, et c'est le TYPE qui le dit.
 *
 * `reparer` filtrait par CATÉGORIE — `TargetType: structure`. C'était juste
 * tant que les seules structures étaient bâties. Ressources, décors et plantes
 * sont devenus des entités et sont tombés du même côté de l'arbre : on pouvait
 * réparer une fleur, et y laisser une action.
 *
 * La catégorie ne peut pas trancher, elle n'a que deux valeurs et les deux sont
 * justes. La réponse appartient au type, comme le rendement ou la repousse.
 *
 * La colonne est NULLABLE à dessein, et n'est PAS semée : `null` veut dire
 * « ce que dit ma famille », et la famille le dit en code
 * ({@see \App\Entity\StructureType::repairableByDefault()}). Semer aurait figé
 * la règle du jour de la migration dans 26 000 lignes ; ici, changer la règle
 * suit tous ceux qui s'en remettent encore à elle, et cocher la case sur un
 * type précis l'emporte.
 *
 * Ce que ce lot CHANGE en jeu : reste réparable ce qui a été DRESSÉ par
 * quelqu'un — bâtiments et décors. Ce qui pousse ou gît là ne l'est plus :
 * ressources et plantes suivent l'épuisement et la repousse, pas l'entretien.
 * Elles l'étaient par ricochet, sans que personne l'ait décidé ; la case de
 * leur type suffit à le rétablir au cas par cas.
 *
 * La condition est posée en `display_context` : le bouton disparaît sur ce qui
 * ne se répare pas, au lieu de s'afficher et d'échouer au clic. Et en
 * `execution_order` 1, donc AVANT le coût (RequiresTraitValue, ordre 3) — on ne
 * facture pas l'action à qui vise une cible qu'on va refuser.
 */
final class Version20260802100000_OnlyWhatIsBuiltGetsRepaired extends AbstractMigration
{
    private const REPARER_ACTION_ID = 92;

    public function getDescription(): string
    {
        return 'races.repairable + condition RequiresRepairableTarget sur reparer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE races ADD COLUMN IF NOT EXISTS repairable TINYINT(1) NULL');

        $this->addSql(
            "INSERT INTO action_conditions (conditionType, parameters, action_id, execution_order, blocking, display_context)
             SELECT 'RequiresRepairableTarget', '{}', :action, 1, 1, 1
             FROM DUAL
             WHERE NOT EXISTS (
                SELECT 1 FROM (SELECT * FROM action_conditions) AS existing
                WHERE existing.action_id = :action
                  AND existing.conditionType = 'RequiresRepairableTarget'
             )",
            ['action' => self::REPARER_ACTION_ID]
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "DELETE FROM action_conditions
              WHERE action_id = :action AND conditionType = 'RequiresRepairableTarget'",
            ['action' => self::REPARER_ACTION_ID]
        );

        $this->addSql('ALTER TABLE races DROP COLUMN IF EXISTS repairable');
    }
}
