<?php

namespace Tests\Action;

use App\Service\ActionService;
use App\Service\Action\ActionTargeting;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Contexte d'affichage des boutons d'action
 * (action_conditions.display_context) : une condition marquée
 * contextuelle est évaluée AU RENDU par
 * ActionTargeting::matchesDisplayContext — le bouton n'apparaît que si
 * elle passe. Démontré sur la RequiresDistance de « melee » : visible
 * adjacent, masqué à distance ; et sans drapeau, jamais masqué
 * (comportement historique).
 *
 * Le test pose LUI-MÊME les deux états et restaure la valeur d'origine :
 * depuis la scission d'« attaquer » en melee + distance, cette condition
 * est livrée contextuelle, et une prémisse implicite sur la donnée
 * seedée rendrait le test faux sans que le mécanisme ait bougé.
 */
#[Group('entities-baseline')]
class ActionDisplayContextBaselineTest extends LegacyPlayerFixtureTestCase
{
    private ?int $flaggedConditionId = null;

    /** Valeur seedée, restaurée au démontage. */
    private int $originalDisplayContext = 0;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->link->executeQuery('SELECT display_context FROM action_conditions LIMIT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped('display_context unavailable (run migrations): ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->flaggedConditionId !== null && $this->link !== null) {
            $this->link->executeStatement(
                'UPDATE action_conditions SET display_context = ? WHERE id = ?',
                [$this->originalDisplayContext, $this->flaggedConditionId]
            );
        }

        parent::tearDown();
    }

    public function testAContextualDistanceConditionGatesTheButtonByRange(): void
    {
        $conditionId = $this->link->fetchOne(
            "SELECT ac.id FROM action_conditions ac
             JOIN actions a ON a.id = ac.action_id
             WHERE a.name = 'melee' AND ac.conditionType = 'RequiresDistance'"
        );
        if ($conditionId === false) {
            $this->markTestSkipped("melee/RequiresDistance not seeded.");
        }

        /* On part d'un état CHOISI, pas de celui que la base porte. */
        $this->flaggedConditionId = (int) $conditionId;
        $this->originalDisplayContext = (int) $this->link->fetchOne(
            'SELECT display_context FROM action_conditions WHERE id = ?',
            [(int) $conditionId]
        );
        $this->link->executeStatement(
            'UPDATE action_conditions SET display_context = 0 WHERE id = ?',
            [(int) $conditionId]
        );
        \App\Factory\EntityManagerFactory::getEntityManager()->clear();

        $actor = $this->createRealPlayer('GmCtx');
        $target = $this->createRealPlayer('GmCtx');
        $this->movePlayerTo((int) $actor->id, 0, 0);
        $this->movePlayerTo((int) $target->id, 0, 1);
        $actor->get_data();
        $target->get_data();

        $targeting = new ActionTargeting();
        $em = \App\Factory\EntityManagerFactory::getEntityManager();

        // SANS drapeau : jamais masqué, quelle que soit la distance
        // (comportement historique — la distance refuse à l'exécution).
        $action = (new ActionService())->getActionByName('melee');
        $this->assertNotNull($action);
        $this->assertTrue($targeting->matchesDisplayContext($action, $actor, $target));

        $this->movePlayerTo((int) $target->id, 0, 5);
        $target->getCoords(refresh: true);
        $this->assertTrue(
            $targeting->matchesDisplayContext($action, $actor, $target),
            'sans display_context, la distance ne masque pas le bouton'
        );

        // AVEC drapeau : masqué hors portée, visible adjacent.
        $this->link->executeStatement('UPDATE action_conditions SET display_context = 1 WHERE id = ?', [(int) $conditionId]);
        $em->clear();
        $action = (new ActionService())->getActionByName('melee');

        $this->assertFalse(
            $targeting->matchesDisplayContext($action, $actor, $target),
            'condition contextuelle non remplie (distance 5 > max 1) => bouton masqué'
        );

        $this->movePlayerTo((int) $target->id, 1, 1);
        $target->getCoords(refresh: true);
        $this->assertTrue(
            $targeting->matchesDisplayContext($action, $actor, $target),
            'adjacent (distance 1 <= max 1) => bouton visible'
        );
    }
}
