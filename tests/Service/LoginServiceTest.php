<?php

namespace Tests\Service;

use App\Service\AccountService;
use App\Service\LoginService;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * A remembered browser logs back in like a typed password: last login,
 * connection log; a wrong cookie or a new password ends it.
 */
class LoginServiceTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_COOKIE[LoginService::COOKIE]);
        parent::tearDown();
    }

    private function sessionPlayer(): mixed
    {
        return $_SESSION['playerId'] ?? null;
    }

    public function testARememberedBrowserLogsBackInLikeALogin(): void
    {
        $id = (int) $this->createRealPlayer('GmRemember')->id;
        $login = new LoginService($this->link);
        $login->remember($id, md5('fp'));
        (new AccountService($this->link))->touchLastLogin($id, 1);
        $_SESSION = [];

        $this->assertTrue($login->resume());

        $this->assertSame($id, $this->sessionPlayer());
        $this->assertGreaterThan(1, (new AccountService($this->link))->lastLoginOf($id), 'the last login moves');
        $this->assertSame(
            md5('fp'),
            $this->link->fetchOne('SELECT footprint FROM players_connections WHERE player_id = ? ORDER BY time DESC LIMIT 1', [$id]),
            'the connection is logged with the footprint of the remembered login'
        );
    }

    public function testAForgedValidatorIsRefusedAndForgotten(): void
    {
        $id = (int) $this->createRealPlayer('GmForged')->id;
        $login = new LoginService($this->link);
        [$selector] = explode(':', $login->remember($id, ''));
        $_COOKIE[LoginService::COOKIE] = $selector . ':' . str_repeat('0', 64);
        $_SESSION = [];

        $this->assertFalse($login->resume());
        $this->assertNull($this->sessionPlayer());
        $this->assertFalse($this->link->fetchOne('SELECT 1 FROM remember_tokens WHERE selector = ?', [$selector]));
    }

    public function testANewPasswordForgetsEveryBrowser(): void
    {
        $id = (int) $this->createRealPlayer('GmNewPsw')->id;
        $login = new LoginService($this->link);
        $login->remember($id, '');
        (new AccountService($this->link))->setPassword($id, password_hash('x', PASSWORD_DEFAULT));
        $_SESSION = [];

        $this->assertFalse($login->resume());
    }

    public function testEndingSessionsDeletesOnlyThatAccountsFiles(): void
    {
        $dir = sys_get_temp_dir() . '/aoo-sessions-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/sess_mine', 'playerId|i:-5;mainPlayerId|i:2;');
        file_put_contents($dir . '/sess_other', 'mainPlayerId|i:23;');
        file_put_contents($dir . '/sess_lookalike', 'otherMainPlayerId|i:2;');

        (new LoginService($this->link))->endSessionsOf(2, $dir);
        $this->assertSame(['sess_lookalike', 'sess_other'], array_values(array_diff(scandir($dir), ['.', '..'])));

        array_map('unlink', glob($dir . '/*'));
        rmdir($dir);
    }
}
