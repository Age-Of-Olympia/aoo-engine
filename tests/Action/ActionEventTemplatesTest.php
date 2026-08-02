<?php

namespace Tests\Action;

use App\Service\Action\ActionTypeRegistry;
use App\Service\Action\ActionTypeNode;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * TOUTE action laisse un événement (revue du 2026-07-19) : chaque type
 * RACINE de l'arbre doit avoir son gabarit action_type_logs — les
 * sous-types héritent du plus proche ancêtre. Un nouveau type sans
 * gabarit casserait ce test au lieu de produire des actions muettes.
 */
#[Group('items-baseline')]
class ActionEventTemplatesTest extends LegacyPlayerFixtureTestCase
{
    public function testEveryRootActionTypeHasAnEventTemplate(): void
    {
        $roots = array_map(
            static fn (ActionTypeNode $node): string => $node->key,
            (new ActionTypeRegistry())->tree()
        );
        $this->assertNotEmpty($roots);

        $templated = array_column(
            $this->link->fetchAllAssociative('SELECT type_key FROM action_type_logs'),
            'type_key'
        );

        $silent = array_diff($roots, $templated);
        $this->assertSame(
            [],
            array_values($silent),
            'Types racines sans gabarit action_type_logs (actions muettes) : ' . implode(', ', $silent)
        );
    }
}
