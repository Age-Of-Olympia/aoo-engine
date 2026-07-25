<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * « attaquer » était un nom fantôme : une seule ligne accordée, sans
 * entrée au catalogue `actions`, qui se transformait à l'exécution en
 * `melee` ou `distance` selon la distance à la cible (repli sur null
 * dans action.php). Une action qui en fabrique deux, donc, avec les
 * contournements que ça traîne partout : un alias dans les
 * statistiques, un habillage de bouton dédié, une exception dans le
 * tutoriel, un court-circuit à l'octroi.
 *
 * On lui substitue les DEUX actions réelles, qui existent déjà au
 * catalogue et portent déjà la bonne condition de portée :
 * `melee` a RequiresDistance {"max":1}, `distance` a {"min":2}.
 *
 * Ces deux conditions passent en `display_context` : c'est ce drapeau
 * qui les fait évaluer AU RENDU du panneau, si bien qu'une seule des
 * deux actions apparaît selon l'endroit où se trouve la cible. Le
 * joueur retrouve exactement ce qu'il avait — un bouton d'attaque
 * adapté au contexte — mais il vient cette fois du chemin ordinaire des
 * actions, sans cas particulier.
 *
 * Idempotent : les insertions sont gardées par NOT EXISTS, les
 * suppressions sont naturellement rejouables.
 *
 * Rétro-compatible : le code déployé AVANT cette migration continue de
 * fonctionner après. Il lit les actions du joueur et les passe au même
 * filtre `matchesDisplayContext`, qui existe déjà ; sa branche
 * « attaquer » devient simplement morte, faute de ligne à traiter.
 */
final class Version20260725110000_SplitAttaquerIntoMeleeAndDistance extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace the phantom "attaquer" grant with the real melee + distance actions, shown by context';
    }

    public function up(Schema $schema): void
    {
        /* 1. Les conditions de portée deviennent contextuelles : un seul
         *    des deux boutons s'affiche à la fois. À faire AVANT l'octroi,
         *    sinon les joueurs migrés verraient brièvement les deux. */
        $this->addSql(
            "UPDATE action_conditions ac
             JOIN actions a ON a.id = ac.action_id
             SET ac.display_context = 1
             WHERE a.name IN ('melee', 'distance')
               AND ac.conditionType = 'RequiresDistance'"
        );

        /* 2. Tout détenteur d'« attaquer » possède désormais les deux
         *    actions nommément. Le type reste vide : ce ne sont pas des
         *    compétences apprises, elles ne comptent pas dans le plafond
         *    NUMBER_MAX_COMP (même statut qu'« attaquer »). */
        foreach (['melee', 'distance'] as $name) {
            $this->addSql(
                "INSERT INTO players_actions (player_id, name, type)
                 SELECT pa.player_id, '{$name}', ''
                 FROM players_actions pa
                 WHERE pa.name = 'attaquer'
                   AND NOT EXISTS (
                       SELECT 1 FROM (SELECT * FROM players_actions) x
                       WHERE x.player_id = pa.player_id AND x.name = '{$name}'
                   )"
            );
        }

        $this->addSql("DELETE FROM players_actions WHERE name = 'attaquer'");

        /* 3. La source : sans ça, chaque nouveau personnage naîtrait de
         *    nouveau avec le nom fantôme. « melee » reprend la position
         *    d'« attaquer » — donc la tête de liste — et « distance » se
         *    pose juste derrière.
         *
         *    Cette position n'est pas décorative : EntityCardView trie
         *    les actions de base du panneau sur l'ordre de la liste de
         *    départ de la race. Deux entrées au même rang laisseraient
         *    l'ordre des deux attaques indéterminé, d'où le décalage des
         *    suivantes pour libérer un rang propre. */
        $this->addSql(
            "UPDATE race_starter_actions SET name = 'melee' WHERE name = 'attaquer'"
        );
        $this->addSql(
            "UPDATE race_starter_actions s
             JOIN (SELECT race_id, position FROM race_starter_actions WHERE name = 'melee') m
               ON m.race_id = s.race_id
             SET s.position = s.position + 1
             WHERE s.position > m.position
               AND NOT EXISTS (
                   SELECT 1 FROM (SELECT * FROM race_starter_actions) d
                   WHERE d.race_id = s.race_id AND d.name = 'distance'
               )"
        );
        $this->addSql(
            "INSERT INTO race_starter_actions (race_id, name, position)
             SELECT s.race_id, 'distance', s.position + 1
             FROM race_starter_actions s
             WHERE s.name = 'melee'
               AND NOT EXISTS (
                   SELECT 1 FROM (SELECT * FROM race_starter_actions) x
                   WHERE x.race_id = s.race_id AND x.name = 'distance'
               )"
        );
    }

    public function down(Schema $schema): void
    {
        /* Retour au nom fantôme : une seule ligne, et les conditions de
         * portée redeviennent de simples refus à l'exécution. */
        $this->addSql(
            "INSERT INTO players_actions (player_id, name, type)
             SELECT pa.player_id, 'attaquer', ''
             FROM players_actions pa
             WHERE pa.name = 'melee'
               AND NOT EXISTS (
                   SELECT 1 FROM (SELECT * FROM players_actions) x
                   WHERE x.player_id = pa.player_id AND x.name = 'attaquer'
               )"
        );
        $this->addSql("DELETE FROM players_actions WHERE name IN ('melee', 'distance')");
        /* Referme le rang libéré à l'aller, puis rend son nom d'origine. */
        $this->addSql(
            "UPDATE race_starter_actions s
             JOIN (SELECT race_id, position FROM race_starter_actions WHERE name = 'distance') d
               ON d.race_id = s.race_id
             SET s.position = s.position - 1
             WHERE s.position > d.position"
        );
        $this->addSql("DELETE FROM race_starter_actions WHERE name = 'distance'");
        $this->addSql("UPDATE race_starter_actions SET name = 'attaquer' WHERE name = 'melee'");
        $this->addSql(
            "UPDATE action_conditions ac
             JOIN actions a ON a.id = ac.action_id
             SET ac.display_context = 0
             WHERE a.name IN ('melee', 'distance')
               AND ac.conditionType = 'RequiresDistance'"
        );
    }
}
