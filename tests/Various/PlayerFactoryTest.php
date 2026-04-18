<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Smoke tests for PlayerFactory.
 *
 * Tests that don't hit the database. The factory's `legacy()`, `active()`,
 * `entity()` paths construct `Classes\Player` or call Doctrine (both DB-bound)
 * and are exercised by integration / e2e tests, not here.
 */
class PlayerFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    #[Group('player-factory')]
    public function testActiveIdReturnsZeroWhenNoSession(): void
    {
        $this->assertSame(0, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testActiveIdReturnsSessionPlayerIdWhenNotInTutorial(): void
    {
        $_SESSION['playerId'] = 42;

        $this->assertSame(42, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testActiveIdIgnoresTutorialFlagWithoutTutorialPlayerId(): void
    {
        // `in_tutorial` alone is not enough — both flags must be set for the
        // tutorial branch to engage. Otherwise we fall back to main playerId.
        $_SESSION['playerId'] = 7;
        $_SESSION['in_tutorial'] = true;

        $this->assertSame(7, PlayerFactory::activeId());
    }

    #[Group('player-factory')]
    public function testFactoryExposesExpectedStaticApi(): void
    {
        $class = new ReflectionClass(PlayerFactory::class);

        $this->assertTrue($class->isFinal(), 'PlayerFactory should be final');

        foreach (['legacy', 'active', 'activeId', 'entity', 'activeEntity'] as $method) {
            $this->assertTrue($class->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($class->getMethod($method)->isStatic(), "{$method} should be static");
            $this->assertTrue($class->getMethod($method)->isPublic(), "{$method} should be public");
        }
    }
}
