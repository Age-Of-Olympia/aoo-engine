<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Align the action-resource copy in the tutorial with the rest of
 * the in-game vocabulary: the UI labels actions as "A" (cf. the
 * "1 A, 2 PM" tooltips rendered from races/*.json), but three
 * tutorial steps still spoke of "Points d'Action (PA)".
 *
 * Idempotent + scoped — each row is only rewritten when it still
 * carries the exact pre-change text. Admin edits via
 * tutorial-step-editor (and DBs that have already been migrated
 * via an earlier hand-applied SQL) are preserved as no-ops.
 *
 * Also flips the masculine pronoun "Ils" → "Elles" in
 * `actions_intro` to agree with the new feminine subject "Actions".
 */
final class Version20260515230000_RenameTutorialPaToActions extends AbstractMigration
{
    private const ROWS = [
        'actions_intro' => [
            'old' => 'En plus des mouvements, vous avez des <strong>Points d\'Action (PA)</strong>. Ils permettent de fouiller, attaquer, récolter...',
            'new' => 'En plus des mouvements, vous avez des <strong>Actions (A)</strong>. Elles permettent de fouiller, attaquer, récolter...',
        ],
        'actions_panel_info' => [
            'old' => 'Voici vos <strong>actions disponibles</strong> ! Chaque action consomme des PA. Nous allons en tester une : la récolte de ressources.',
            'new' => 'Voici vos <strong>actions disponibles</strong> ! Chaque action consomme une ou plusieurs A. Nous allons en tester une : la récolte de ressources.',
        ],
        'action_consumed' => [
            'old' => 'Vous avez récolté du <strong>bois</strong> ! Remarquez que l\'action a consommé <strong>1 PA</strong>. Vos PA se régénèrent aussi à chaque tour.',
            'new' => 'Vous avez récolté du <strong>bois</strong> ! Remarquez que l\'action a consommé <strong>1 A</strong>. Vos A se régénèrent aussi à chaque tour.',
        ],
    ];

    public function getDescription(): string
    {
        return "Tutorial copy: rename 'Points d'Action (PA)' → 'Actions (A)' across three steps";
    }

    public function up(Schema $schema): void
    {
        foreach (self::ROWS as $stepId => $texts) {
            $this->addSql(
                "UPDATE tutorial_steps SET text = ?
                 WHERE step_id = ?
                   AND version = '1.0.0'
                   AND text = ?",
                [$texts['new'], $stepId, $texts['old']]
            );
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::ROWS as $stepId => $texts) {
            $this->addSql(
                "UPDATE tutorial_steps SET text = ?
                 WHERE step_id = ?
                   AND version = '1.0.0'
                   AND text = ?",
                [$texts['old'], $stepId, $texts['new']]
            );
        }
    }
}
