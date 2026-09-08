<?php

namespace Tests\Various;

use App\Factory\PlayerFactory;
use Classes\Player;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyBootstrapTrait;

/**
 * Smoke tests for PlayerFactory.
 *
 * Tests that don't hit the database. The factory's `legacy()`, `active()`,
 * `entity()` paths construct `Classes\Player` or call Doctrine (both DB-bound)
 * and are exercised by integration / e2e tests, not here.
 */
#[Group('player-factory')]
class PlayerFactoryTest extends TestCase
{
    use LegacyBootstrapTrait;

    /** Fixture ids, out of reach of real ones. */
    private const REAL_ID = 990201;
    private const NPC_ID = -990201;
    private const TUTORIAL_ID = 990202;

    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if ($this->link !== null) {
            $this->link->executeStatement(
                'DELETE FROM players WHERE id IN (?, ?, ?)',
                [self::REAL_ID, self::NPC_ID, self::TUTORIAL_ID]
            );
        }
    }

    /**
     * Seed one character of the given kind and return its name.
     *
     * The lookup cases used to borrow whichever row came first, which made them
     * skip on a database holding no character — and silently test a structure's
     * row on one where a decor had the lowest id.
     */
    private function seedCharacter(Connection $link, int $id, string $type, string $name): string
    {
        $link->executeStatement('DELETE FROM players WHERE id = ?', [$id]);
        $link->executeStatement(
            'INSERT INTO players (id, player_type, name, race) VALUES (?, ?, ?, ?)',
            [$id, $type, $name, 'nain']
        );

        return $name;
    }

    public function testActiveIdReturnsZeroWhenNoSession(): void
    {
        $this->assertSame(0, PlayerFactory::activeId());
    }

    public function testActiveIdReturnsSessionPlayerIdWhenNotInTutorial(): void
    {
        $_SESSION['playerId'] = 42;

        $this->assertSame(42, PlayerFactory::activeId());
    }

    public function testActiveIdIgnoresTutorialFlagWithoutTutorialPlayerId(): void
    {
        // `in_tutorial` alone is not enough — both flags must be set for the
        // tutorial branch to engage. Otherwise we fall back to main playerId.
        $_SESSION['playerId'] = 7;
        $_SESSION['in_tutorial'] = true;

        $this->assertSame(7, PlayerFactory::activeId());
    }

    public function testLegacyByNameReturnsNullWhenNameNotFound(): void
    {
        $this->bootstrapLegacyOrSkip();

        // Opaque name that cannot collide with any seeded player — the
        // factory must normalise the legacy `false` miss to `null`.
        $miss = 'phaseLBNMiss_' . bin2hex(random_bytes(6));

        $this->assertNull(PlayerFactory::legacyByName($miss));
    }

    public function testLegacyByNameReturnsPlayerWithMatchingIdWhenFound(): void
    {
        $link = $this->bootstrapLegacyOrSkip();

        $name = $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueLegacy');

        $player = PlayerFactory::legacyByName($name);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertSame(self::REAL_ID, $player->id);
    }

    public function testEntityByNameReturnsNullWhenNameNotFound(): void
    {
        $this->bootstrapLegacyOrSkip();

        $miss = 'phaseEBNMiss_' . bin2hex(random_bytes(6));

        $this->assertNull(PlayerFactory::entityByName($miss));
    }

    public function testEntityByNameReturnsRealPlayerWithMatchingIdWhenFound(): void
    {
        $link = $this->bootstrapLegacyOrSkip();

        $name = $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueEntite');

        $entity = PlayerFactory::entityByName($name);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame(self::REAL_ID, $entity->getId());
    }

    public function testRealPlayerByIdReturnsNullWhenIdDoesNotExist(): void
    {
        $this->bootstrapLegacyOrSkip();

        // An id high enough to never collide with seeded rows. The
        // STI-narrow lookup must produce null, just like find() on the
        // parent Character would, not throw.
        $this->assertNull(PlayerFactory::realPlayerById(999999999));
    }

    public function testRealPlayerByIdReturnsRealPlayerForRealPlayerId(): void
    {
        $link = $this->bootstrapLegacyOrSkip();

        $this->seedCharacter($link, self::REAL_ID, 'real', 'GmFabriqueReel');

        $entity = PlayerFactory::realPlayerById(self::REAL_ID);

        $this->assertInstanceOf(\App\Entity\RealPlayer::class, $entity);
        $this->assertSame(self::REAL_ID, $entity->getId());
    }

    public function testRealPlayerByIdRejectsNpcId(): void
    {
        // STI narrowing: passing an NPC id (player_type='npc',
        // negative id) must return null rather than hydrating the
        // NonPlayerCharacter subclass. This is the guard that keeps
        // ResetPasswordView from password-resetting an NPC "account".
        $link = $this->bootstrapLegacyOrSkip();

        $this->seedCharacter($link, self::NPC_ID, 'npc', 'GmFabriquePnj');

        $this->assertNull(
            PlayerFactory::realPlayerById(self::NPC_ID),
            'realPlayerById must not return NonPlayerCharacter rows'
        );
    }

    public function testRealPlayerByIdRejectsTutorialPlayerId(): void
    {
        $link = $this->bootstrapLegacyOrSkip();

        $this->seedCharacter($link, self::TUTORIAL_ID, 'tutorial', 'GmFabriqueTuto');

        $this->assertNull(
            PlayerFactory::realPlayerById(self::TUTORIAL_ID),
            'realPlayerById must not return TutorialPlayer rows'
        );
    }
}
