<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'étape de combat du tutoriel vise l'action qui existe.
 *
 * Le catalogue des actions ne connaît plus `attaquer` : le bouton du panneau
 * s'appelle `melee` (« Corps à corps ») et action.php refuse désormais tout
 * nom inconnu. L'étape attack_enemy pointait donc un bouton absent, exigeait
 * une action morte, et la fin du tutoriel devenait inatteignable.
 *
 * Les UPDATE sont bornés sur l'ancienne valeur exacte : rejouables, et muets
 * sur un contenu déjà corrigé ou personnalisé.
 */
final class Version20260820151000_TutorialAttackIsMelee extends AbstractMigration
{
    public function getDescription(): string
    {
        return "tutoriel: l'étape de combat cible melee, attaquer n'existe plus";
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui
               SET target_selector = '.action[data-action=\"melee\"]'
             WHERE target_selector = '.action[data-action=\"attaquer\"]'
        ");

        $this->addSql("
            UPDATE tutorial_step_interactions
               SET selector = '.action[data-action=\"melee\"]'
             WHERE selector = '.action[data-action=\"attaquer\"]'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation
               SET action_name = 'melee'
             WHERE action_name = 'attaquer'
        ");

        // L'étape « cibler l'ennemi » attend que le bouton d'attaque
        // devienne visible : même sélecteur, même renommage.
        $this->addSql("
            UPDATE tutorial_step_validation
               SET element_selector = '.action[data-action=\"melee\"]'
             WHERE element_selector = '.action[data-action=\"attaquer\"]'
        ");

        // Le texte nomme le bouton tel que le joueur le lit.
        $this->addSql("
            UPDATE tutorial_steps
               SET text = REPLACE(text, '<strong>Attaquer</strong>', '<strong>Corps à corps</strong>')
             WHERE text LIKE '%<strong>Attaquer</strong>%'
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("
            UPDATE tutorial_step_ui
               SET target_selector = '.action[data-action=\"attaquer\"]'
             WHERE target_selector = '.action[data-action=\"melee\"]'
        ");

        $this->addSql("
            UPDATE tutorial_step_interactions
               SET selector = '.action[data-action=\"attaquer\"]'
             WHERE selector = '.action[data-action=\"melee\"]'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation
               SET action_name = 'attaquer'
             WHERE action_name = 'melee'
        ");

        $this->addSql("
            UPDATE tutorial_step_validation
               SET element_selector = '.action[data-action=\"attaquer\"]'
             WHERE element_selector = '.action[data-action=\"melee\"]'
        ");

        $this->addSql("
            UPDATE tutorial_steps
               SET text = REPLACE(text, '<strong>Corps à corps</strong>', '<strong>Attaquer</strong>')
             WHERE text LIKE '%<strong>Corps à corps</strong>%'
        ");
    }
}
