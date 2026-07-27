<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le catalogue d'actions peut enfin se joindre à lui-même.
 *
 * Suite de Version20260727110000_JoinKeyCollations, qui avait aligné les clés
 * de jointure du chantier ressources. Le catalogue d'actions souffrait du même
 * désordre, sur une surface plus large : ses clés de TYPE se répartissaient
 * entre trois collations selon la date de création de chaque table.
 *
 * Douze colonnes portent le MÊME vocabulaire — les clés de type (« search »,
 * « heal », « attack ») et les noms de conditions (« RequiresDistance ») — dans
 * trois collations différentes, selon la date de création de chaque table.
 * Toute comparaison entre deux d'entre elles échoue en « Illegal mix of
 * collations ».
 *
 * PORTÉE : aucun impact à l'exécution. Le moteur ne fait pas ces jointures —
 * il résout par ANCESTRALITÉ DE CLASSE côté PHP (ActionTypeRegistry::
 * typeKeysForAction, puis findBy(['typeKey' => $keys])), si bien qu'un
 * `attack` sans action de ce type reste la configuration parente légitime de
 * `melee` et `distance`.
 *
 * Ce que la divergence coûte, c'est la possibilité de VÉRIFIER ce catalogue :
 * la moindre requête croisée réclame un CONVERT des deux côtés. C'est
 * probablement pour ça qu'aucun contrôle d'intégrité n'a jamais été écrit
 * dessus, alors qu'il pilote tout le moteur d'actions.
 *
 * On aligne sur `utf8mb4_general_ci`, collation des DONNÉES (`players_actions`,
 * `players_logs`, `items`) et cible déjà retenue pour les clés de jointure.
 *
 * Colonne par colonne, jamais la table entière : les libellés et les gabarits
 * de message (`action_type_logs.actor_template`, `dialogs`…) gardent leur
 * insensibilité aux accents. Seules changent des clés ASCII — « search »,
 * « RequiresDistance », « heal » —, ce qui ne modifie aucune comparaison
 * existante.
 *
 * Les index portés par ces colonnes (dont deux UNIQUE sur `type_key`) sont
 * reconstruits par le MODIFY ; aucune clé étrangère ne les référence.
 */
final class Version20260727130000_ActionCatalogCollations extends AbstractMigration
{
    /**
     * table, colonne, type, nullable, collation D'ORIGINE — celle-ci est
     * conservée pour que le down restitue l'état exact, divergences comprises,
     * et non un état « propre » qui n'a jamais existé.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: bool, 4: string}>
     */
    private const COLUMNS = [
        ['actions', 'type', 'VARCHAR(255)', false, 'utf8mb4_uca1400_ai_ci'],
        ['action_conditions', 'conditionType', 'VARCHAR(100)', false, 'utf8mb4_uca1400_ai_ci'],
        ['outcome_instructions', 'type', 'VARCHAR(50)', false, 'utf8mb4_uca1400_ai_ci'],
        ['action_condition_preconditions', 'parent_condition_type', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_condition_preconditions', 'precondition_type', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_type_instructions', 'type_key', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_type_instructions', 'instruction_type', 'VARCHAR(50)', false, 'utf8mb4_unicode_ci'],
        ['action_type_logs', 'type_key', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_type_preconditions', 'type_key', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_type_preconditions', 'condition_type', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_type_xp', 'type_key', 'VARCHAR(100)', false, 'utf8mb4_unicode_ci'],
        ['action_passives', 'type', 'VARCHAR(255)', true, 'utf8mb4_unicode_ci'],
    ];

    public function getDescription(): string
    {
        return 'le catalogue d\'actions se joint à lui-même : clés de type alignées sur utf8mb4_general_ci';
    }

    public function up(Schema $schema): void
    {
        foreach (self::COLUMNS as [$table, $column, $type, $nullable]) {
            $this->addSql($this->modify($table, $column, $type, $nullable, 'utf8mb4_general_ci'));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::COLUMNS as [$table, $column, $type, $nullable, $original]) {
            $this->addSql($this->modify($table, $column, $type, $nullable, $original));
        }
    }

    private function modify(string $table, string $column, string $type, bool $nullable, string $collation): string
    {
        return sprintf(
            'ALTER TABLE %s MODIFY COLUMN `%s` %s CHARACTER SET utf8mb4 COLLATE %s %s',
            $table,
            $column,
            $type,
            $collation,
            $nullable ? 'DEFAULT NULL' : 'NOT NULL'
        );
    }
}
