<?php

namespace Tests\Various;

use App\Service\AccountService;
use App\Service\AdminAuthorizationService;
use App\Service\PlayerOptionsService;
use Classes\Player;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Admin rights are the login's, disabled until sudo; they follow the session
 * onto PNJs, and onto a real player's character only when forced for it.
 */
#[Group('action')]
class AdminAuthorizationTest extends LegacyPlayerFixtureTestCase
{
    private const KEYS = ['playerId', 'originalPlayerId', 'sudo', 'sudoForced'];

    /** @var array<string, mixed> */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::KEYS as $key) {
            $this->saved[$key] = $_SESSION[$key] ?? null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $key => $value) {
            if ($value === null) {
                unset($_SESSION[$key]);
            } else {
                $_SESSION[$key] = $value;
            }
        }
        PlayerOptionsService::resetCache();
        parent::tearDown();
    }

    public function testRightsDisabledUntilSudoThenFollowTheSession(): void
    {
        $admin = (int) $this->createRealPlayer('GmGardien')->id;
        $plain = (int) $this->createRealPlayer('GmPassant')->id;
        $pnj = -999998;
        foreach (['isAdmin', 'isSuperAdmin'] as $option) {
            $this->link->executeStatement('INSERT INTO players_options (player_id, name) VALUES (?, ?)', [$admin, $option]);
        }
        PlayerOptionsService::resetCache();

        $_SESSION['originalPlayerId'] = $admin;
        $_SESSION['playerId'] = $pnj;
        unset($_SESSION['sudo'], $_SESSION['sudoForced']);
        $this->assertTrue(AdminAuthorizationService::canElevate());
        $this->assertFalse(AdminAuthorizationService::isAdmin(), 'disabled without sudo');

        AdminAuthorizationService::sudo(AdminAuthorizationService::SUDO_ADMIN);
        $this->assertTrue(AdminAuthorizationService::isAdmin());
        $this->assertFalse(AdminAuthorizationService::isSuperAdmin(), 'admin level only');

        AdminAuthorizationService::sudo(AdminAuthorizationService::SUDO_SUPERADMIN);
        $this->assertTrue(AdminAuthorizationService::isSuperAdmin(), 'on a PNJ');

        $_SESSION['playerId'] = $admin;
        $this->assertFalse(AdminAuthorizationService::isAdmin(), 'off on the admin\'s own PJ');

        AdminAuthorizationService::sudo(AdminAuthorizationService::SUDO_ADMIN, true);
        $this->assertTrue(AdminAuthorizationService::isAdmin(), 'forced for this PJ');
        $_SESSION['playerId'] = $plain;
        $this->assertFalse(AdminAuthorizationService::isAdmin(), 'the force does not follow to another PJ');

        AdminAuthorizationService::sudo(null);
        $_SESSION['playerId'] = $pnj;
        $this->assertFalse(AdminAuthorizationService::isAdmin());
    }

    public function testSudoRefusesALevelTheLoginDoesNotHold(): void
    {
        $id = (int) $this->createRealPlayer('GmPassant')->id;
        // Matricule 1 is made admin at creation: an empty test base hands it out.
        (new PlayerOptionsService())->endOption($id, 'isAdmin');
        $_SESSION['originalPlayerId'] = $_SESSION['playerId'] = $id;

        $this->assertFalse(AdminAuthorizationService::canElevate());
        $this->expectException(\RuntimeException::class);
        AdminAuthorizationService::sudo(AdminAuthorizationService::SUDO_ADMIN);
    }

    public function testOnlyAPnjWithItsOwnPasswordCanHoldAdminRights(): void
    {
        $pnj = (int) Player::put_player('GmMasque_' . bin2hex(random_bytes(4)), 'nain', true);
        $this->createdPlayerIds[] = $pnj;
        $options = new PlayerOptionsService();

        try {
            $options->addOption($pnj, 'isAdmin');
            $this->fail('a PNJ without a password must be refused');
        } catch (\InvalidArgumentException) {
        }

        (new AccountService($this->link))->setPassword($pnj, password_hash('x', PASSWORD_DEFAULT));
        $options->addOption($pnj, 'isAdmin');
        $_SESSION['originalPlayerId'] = $_SESSION['playerId'] = $pnj;
        AdminAuthorizationService::sudo(AdminAuthorizationService::SUDO_ADMIN);
        $this->assertTrue(AdminAuthorizationService::isAdmin(), 'a PNJ login plays itself with its rights');
    }
}
