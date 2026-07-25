<?php

namespace Tests\Tutorial;

use App\Service\ActionService;
use App\Tutorial\TutorialConstants;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Les actions accordées aux personnages de tutoriel doivent EXISTER au
 * catalogue.
 *
 * Elles n'y étaient pas toutes : « attaquer » y figurait sans avoir de
 * ligne dans `actions`. Ça passait inaperçu parce qu'action.php se
 * rabattait sur melee ou distance selon la portée dès qu'un nom était
 * introuvable — un repli qui masquait l'écart au lieu de le signaler.
 * Le repli retiré, une action inconnue est refusée, et un tutoriel qui
 * en accorde une devient un tutoriel où l'on ne peut pas attaquer.
 *
 * Ce test est le garde-fou qui manquait : il relie la liste au
 * catalogue, sans quoi rien ne les tient ensemble.
 */
#[Group('tutorial')]
class BasicActionsExistTest extends LegacyPlayerFixtureTestCase
{
    public function testEveryBasicTutorialActionIsInTheCatalog(): void
    {
        $actionService = new ActionService();
        $missing = [];

        foreach (TutorialConstants::BASIC_TUTORIAL_ACTIONS as $name) {
            if ($actionService->getActionByName($name) === null) {
                $missing[] = $name;
            }
        }

        $this->assertSame(
            [],
            $missing,
            'actions accordées au tutoriel sans ligne au catalogue : ' . implode(', ', $missing)
        );
    }

    public function testTheTutorialGrantsBothWaysToAttack(): void
    {
        // Le contact et le tir sont deux actions distinctes depuis la
        // scission : n'en accorder qu'une laisserait le personnage
        // désarmé à l'autre portée.
        $this->assertContains('melee', TutorialConstants::BASIC_TUTORIAL_ACTIONS);
        $this->assertContains('distance', TutorialConstants::BASIC_TUTORIAL_ACTIONS);
    }
}
