<?php

namespace Tests\View;

use App\View\DeathView;
use PHPUnit\Framework\TestCase;

/**
 * The "Vous êtes mort" page: when it shows, and which exit button it
 * offers depending on who is looking.
 */
class DeathViewTest extends TestCase
{
    public function testDisplaysForAKilledPlayerStillInTheUnderworld(): void
    {
        $this->assertTrue(DeathView::shouldDisplay(true, 'enfers', 'enfers', false));
    }

    public function testStaysQuietForALivingVisitorOfTheUnderworld(): void
    {
        $this->assertFalse(DeathView::shouldDisplay(false, 'enfers', 'enfers', false));
    }

    public function testStaysQuietOutsideTheUnderworld(): void
    {
        $this->assertFalse(DeathView::shouldDisplay(true, 'olympia', 'enfers', false));
    }

    public function testShowsOnlyOncePerSession(): void
    {
        $this->assertFalse(DeathView::shouldDisplay(true, 'enfers', 'enfers', true));
    }

    public function testStaysQuietWithoutCoords(): void
    {
        $this->assertFalse(DeathView::shouldDisplay(true, null, 'enfers', false));
    }

    public function testThePlayerIsWelcomedToHell(): void
    {
        $this->assertSame('Bienvenue aux Enfers', DeathView::dismissLabel(false, true));
    }

    public function testAReactiveOpenedSessionMayResurrect(): void
    {
        $this->assertSame('Ressusciter', DeathView::dismissLabel(true, true));
    }

    public function testANonReactiveOpenedSessionHasNoWayThrough(): void
    {
        $this->assertNull(DeathView::dismissLabel(true, false));
    }

    /** @param array<string, int> $session */
    private function isOpenedSession(array $session): bool
    {
        $saved = $_SESSION ?? [];
        $_SESSION = $session;
        try {
            return (new \ReflectionMethod(DeathView::class, 'isOpenedSession'))->invoke(null);
        } finally {
            $_SESSION = $saved;
        }
    }

    public function testDrivingOwnPnjIsNotAnOpenedSession(): void
    {
        $this->assertFalse($this->isOpenedSession(['originalPlayerId' => 2, 'mainPlayerId' => 2, 'playerId' => -5]));
    }

    public function testAdminConsoleSessionIsAnOpenedSession(): void
    {
        $this->assertTrue($this->isOpenedSession(['originalPlayerId' => 1, 'mainPlayerId' => 2, 'playerId' => 2]));
    }

    public function testKeepsOnlyEventsWherePlayerIsActorOrTarget(): void
    {
        $logs = [
            (object) ['player_id' => 2, 'target_id' => 5],
            (object) ['player_id' => 5, 'target_id' => 2],
            (object) ['player_id' => 5, 'target_id' => 7],
        ];

        $kept = DeathView::aboutPlayer($logs, 2);

        $this->assertCount(2, $kept);
        $this->assertSame([2, 5], [(int) $kept[0]->player_id, (int) $kept[1]->player_id]);
    }
}
