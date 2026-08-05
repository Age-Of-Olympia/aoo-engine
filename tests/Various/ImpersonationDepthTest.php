<?php

namespace Tests\Various;

use App\Service\ImpersonationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A mask is worn from one's own face only — depth ONE. Chaining masks
 * would let a borrowed identity open doors its wearer never owned.
 * Going home is always allowed, from any mask.
 */
#[Group('action')]
class ImpersonationDepthTest extends LegacyPlayerFixtureTestCase
{
    /** @var array<string, mixed> */
    private array $previousSession = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['playerId', 'mainPlayerId'] as $key) {
            $this->previousSession[$key] = $_SESSION[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousSession as $key => $value) {
            if ($value === null) {
                unset($_SESSION[$key]);
            } else {
                $_SESSION[$key] = $value;
            }
        }
        parent::tearDown();
    }

    public function testOneMaskThenHomeThenAnother(): void
    {
        $main = (int) $this->createRealPlayer('GmVisage')->id;
        $maskA = (int) $this->createRealPlayer('GmMasqueA')->id;
        $maskB = (int) $this->createRealPlayer('GmMasqueB')->id;

        $_SESSION['mainPlayerId'] = $main;
        $_SESSION['playerId'] = $main;

        $service = new ImpersonationService();

        $service->driveAs($maskA);
        $this->assertSame($maskA, (int) $_SESSION['playerId']);

        try {
            $service->driveAs($maskB);
            $this->fail('a second mask over the first must refuse');
        } catch (\RuntimeException $e) {
            $this->assertSame('On ne porte pas un masque par-dessus un autre.', $e->getMessage());
            $this->assertSame($maskA, (int) $_SESSION['playerId'], 'the session did not move');
        }

        // Going home is always allowed — then the other mask fits.
        $service->driveAs($main);
        $this->assertSame($main, (int) $_SESSION['playerId']);
        $service->driveAs($maskB);
        $this->assertSame($maskB, (int) $_SESSION['playerId']);

        $this->assertSame($main, $service->release(), 'release answers the face');
        $this->assertSame($main, (int) $_SESSION['playerId']);
    }
}
