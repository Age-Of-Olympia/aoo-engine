<?php

namespace Tests\Tutorial;

use App\Entity\TutorialPlayer;
use App\Tutorial\TutorialResourceManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Phase 4.3 — contract tests for the entity adapters on
 * TutorialResourceManager.
 *
 * The adapters (createTutorialPlayerAsEntity,
 * getTutorialPlayerAsEntity, deleteTutorialPlayerAsEntity) are the
 * seam between TutorialManager (now entity-only) and the service-
 * class creation workflow underneath (map instance, enemy spawn).
 * These tests pin the public contract so a future refactor that
 * drops an adapter or changes a return type breaks loudly.
 *
 * Full end-to-end behaviour of the adapters is exercised by the
 * Cypress `tutorial-production-ready` spec (all three adapters are
 * hit in the start → complete flow). A wet-path PHPUnit test would
 * require overriding the EntityManagerFactory singleton to point at
 * aoo4_test — out of scope for Phase 4.3.
 */
class TutorialResourceManagerEntityAdaptersTest extends TestCase
{
    #[Group('tutorial-resource-adapters')]
    #[Group('phase-4-3')]
    public function testCreateAsEntitySignature(): void
    {
        $method = new ReflectionMethod(
            TutorialResourceManager::class,
            'createTutorialPlayerAsEntity'
        );

        $params = $method->getParameters();
        $this->assertCount(4, $params);
        $this->assertSame('int',    $this->typeName($params[0]));
        $this->assertSame('string', $this->typeName($params[1]));
        $this->assertSame('?string', $this->typeName($params[2]));
        $this->assertSame('string', $this->typeName($params[3]));

        $this->assertSame(
            TutorialPlayer::class,
            $this->typeName($method->getReturnType()),
            'createTutorialPlayerAsEntity must return TutorialPlayer (non-nullable)'
        );
    }

    #[Group('tutorial-resource-adapters')]
    #[Group('phase-4-3')]
    public function testGetAsEntitySignature(): void
    {
        $method = new ReflectionMethod(
            TutorialResourceManager::class,
            'getTutorialPlayerAsEntity'
        );

        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('string', $this->typeName($params[0]));

        $this->assertSame(
            '?' . TutorialPlayer::class,
            $this->typeName($method->getReturnType()),
            'getTutorialPlayerAsEntity must return ?TutorialPlayer'
        );
    }

    #[Group('tutorial-resource-adapters')]
    #[Group('phase-4-3')]
    public function testDeleteAsEntitySignature(): void
    {
        $method = new ReflectionMethod(
            TutorialResourceManager::class,
            'deleteTutorialPlayerAsEntity'
        );

        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame(TutorialPlayer::class, $this->typeName($params[0]));
        $this->assertSame('string', $this->typeName($params[1]));

        $this->assertSame('void', $this->typeName($method->getReturnType()));
    }

    /**
     * Stringify a reflection type for the assertSame comparisons —
     * handles nullable (`?Foo`) and union types cleanly.
     */
    private function typeName(mixed $t): string
    {
        if ($t instanceof \ReflectionParameter) {
            $t = $t->getType();
        }
        if ($t instanceof ReflectionNamedType) {
            return ($t->allowsNull() && $t->getName() !== 'mixed' ? '?' : '') . $t->getName();
        }
        return (string) $t;
    }
}
