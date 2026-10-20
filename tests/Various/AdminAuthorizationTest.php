<?php

namespace Tests\Various;

use App\Service\AdminAuthorizationService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Admin rights follow the character being played: switching from an admin
 * character to a plain one in the same session drops them (#253).
 */
#[Group('action')]
class AdminAuthorizationTest extends LegacyPlayerFixtureTestCase
{
    private mixed $previousPlayerId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlayerId = $_SESSION['playerId'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->previousPlayerId === null) {
            unset($_SESSION['playerId']);
        } else {
            $_SESSION['playerId'] = $this->previousPlayerId;
        }
        parent::tearDown();
    }

    public function testRightsDoNotSurviveACharacterSwitch(): void
    {
        $admin = (int) $this->createRealPlayer('GmGardien')->id;
        $plain = (int) $this->createRealPlayer('GmPassant')->id;
        foreach (['isAdmin', 'isSuperAdmin'] as $option) {
            $this->link->executeStatement('INSERT INTO players_options (player_id, name) VALUES (?, ?)', [$admin, $option]);
        }

        $_SESSION['playerId'] = $admin;
        $this->assertTrue(AdminAuthorizationService::isAdmin());
        $this->assertTrue(AdminAuthorizationService::isSuperAdmin());

        $_SESSION['playerId'] = $plain;
        $this->assertFalse(AdminAuthorizationService::isAdmin());
        $this->assertFalse(AdminAuthorizationService::isSuperAdmin());
    }
}
