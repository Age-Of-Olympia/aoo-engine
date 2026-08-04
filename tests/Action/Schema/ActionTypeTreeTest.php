<?php

namespace Tests\Action\Schema;

use App\Service\Action\ActionTypeNode;
use App\Service\Action\ActionTypeRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('action-schema')]
class ActionTypeTreeTest extends TestCase
{
    public function testRootsAreTheTypesWhoseParentIsTheActionRoot(): void
    {
        $roots = $this->keys((new ActionTypeRegistry())->tree());

        // Sorted, and every concrete/abstract type directly under App\Entity\Action.
        $this->assertSame(
            ['attack', 'buff', 'craft', 'equip', 'gesture', 'pray', 'rest', 'run', 'search', 'steal', 'train', 'work'],
            $roots
        );
    }

    public function testAttackGroupsMeleeDistanceAndTechnique(): void
    {
        $attack = $this->find((new ActionTypeRegistry())->tree(), 'attack');

        $this->assertNotNull($attack);
        $this->assertTrue($attack->abstract, 'attack is a grouping (abstract) type');
        $this->assertSame('Attack', $attack->label);
        $this->assertSame(['distance', 'melee', 'technique'], $this->keys($attack->children));
    }

    public function testTechniqueNestsItsSpellAndTechniqueChildren(): void
    {
        $attack = $this->find((new ActionTypeRegistry())->tree(), 'attack');
        $technique = $this->find($attack->children, 'technique');

        $this->assertNotNull($technique);
        $this->assertSame(
            ['cursespell', 'distancetechnique', 'meleetechnique', 'spell'],
            $this->keys($technique->children)
        );

        $distanceTechnique = $this->find($technique->children, 'distancetechnique');
        $this->assertNotNull($distanceTechnique);
        $this->assertSame(['jettechnique', 'munitiontechnique'], $this->keys($distanceTechnique->children));
    }

    public function testConcreteLeafTypesHaveNoChildren(): void
    {
        $melee = $this->find($this->find((new ActionTypeRegistry())->tree(), 'attack')->children, 'melee');

        $this->assertNotNull($melee);
        $this->assertFalse($melee->abstract);
        $this->assertSame([], $melee->children);
    }

    public function testBuffOwnsHeal(): void
    {
        $buff = $this->find((new ActionTypeRegistry())->tree(), 'buff');

        $this->assertNotNull($buff);
        $this->assertSame(['heal'], $this->keys($buff->children));
    }

    public function testEveryAssignableTypeAppearsExactlyOnceInTheTree(): void
    {
        $registry = new ActionTypeRegistry();
        $flattened = [];
        $this->collect($registry->tree(), $flattened);

        sort($flattened);
        $assignable = array_keys($registry->assignableTypes());
        sort($assignable);

        $this->assertSame($assignable, $flattened);
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @return array<int, string>
     */
    private function keys(array $nodes): array
    {
        return array_map(static fn (ActionTypeNode $node): string => $node->key, $nodes);
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     */
    private function find(array $nodes, string $key): ?ActionTypeNode
    {
        foreach ($nodes as $node) {
            if ($node->key === $key) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param array<int, ActionTypeNode> $nodes
     * @param array<int, string>         $out
     */
    private function collect(array $nodes, array &$out): void
    {
        foreach ($nodes as $node) {
            $out[] = $node->key;
            $this->collect($node->children, $out);
        }
    }
}
