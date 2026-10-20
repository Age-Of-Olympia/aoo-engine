<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * Opening a game session, by password (login.php) or by a remembered
 * browser. Both go through open(), so a remembered login counts like a typed
 * one: last login time, connection log, ban check.
 */
final class LoginService
{
    public const COOKIE = 'aoo_remember';
    private const LIFETIME = 30 * 86400;

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** @param string $footprintHash md5 of the browser footprint sent by the login form */
    public function open(int $playerId, string $footprintHash): void
    {
        (new AccountService($this->conn))->touchLastLogin($playerId);

        // New session id on privilege change (anonymous -> authenticated): no fixation.
        // One session cookie per response: the id session_start() just issued is
        // deleted here, and a proxy keeping the first of two cookies would hold it.
        if (session_status() === PHP_SESSION_ACTIVE) {
            header_remove('Set-Cookie');
            session_regenerate_id(true);
        }

        $_SESSION['mainPlayerId'] = $playerId;
        $_SESSION['playerId'] = $playerId;
        $_SESSION['originalPlayerId'] = $playerId;
        unset($_SESSION['nonewturn'], $_SESSION['deathScreenSeen'], $_SESSION['banned']);

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        $this->conn->insert('players_connections', [
            'player_id' => $playerId,
            'ip' => md5($ip),
            'footprint' => $footprintHash,
            'time' => time(),
        ]);

        $banned = $this->conn->fetchAssociative('SELECT text, ips FROM players_banned WHERE player_id = ?', [$playerId]);
        if ($banned === false) {
            return;
        }

        $_SESSION['banned'] = $banned['text'];

        $ips = array_filter(explode(',', (string) $banned['ips']), static fn (string $v): bool => $v !== '');
        if ($ip !== '' && !in_array($ip, $ips, true)) {
            $ips[] = $ip;
            $this->conn->executeStatement('UPDATE players_banned SET ips = ? WHERE player_id = ?', [implode(',', $ips), $playerId]);
        }
    }

    /** Remembers this browser for LIFETIME; returns the cookie value. */
    public function remember(int $playerId, string $footprintHash): string
    {
        $this->conn->executeStatement('DELETE FROM remember_tokens WHERE expires < ?', [time()]);

        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));

        $this->conn->insert('remember_tokens', [
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'player_id' => $playerId,
            'footprint' => $footprintHash,
            'expires' => time() + self::LIFETIME,
        ]);

        $value = $selector . ':' . $validator;
        $this->sendCookie($value, time() + self::LIFETIME);

        return $value;
    }

    /**
     * Logs back in from the cookie once the PHP session has expired. The
     * expiry slides: a browser used within 30 days stays remembered.
     */
    public function resume(): bool
    {
        $value = (string) ($_COOKIE[self::COOKIE] ?? '');
        [$selector, $validator] = explode(':', $value, 2) + ['', ''];
        if ($selector === '' || $validator === '') {
            return false;
        }

        $row = $this->conn->fetchAssociative(
            'SELECT player_id, validator_hash, footprint FROM remember_tokens WHERE selector = ? AND expires > ?',
            [$selector, time()]
        );
        if ($row === false || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            $this->forget();
            return false;
        }

        $this->open((int) $row['player_id'], (string) $row['footprint']);

        $expires = time() + self::LIFETIME;
        $this->conn->executeStatement('UPDATE remember_tokens SET expires = ? WHERE selector = ?', [$expires, $selector]);
        $this->sendCookie($value, $expires);

        return true;
    }

    /** Forgets this browser (logout). */
    public function forget(): void
    {
        $selector = explode(':', (string) ($_COOKIE[self::COOKIE] ?? ''), 2)[0];
        if ($selector !== '') {
            $this->conn->executeStatement('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
        }
        $this->sendCookie('', time() - 3600);
    }

    /** Forgets every browser of the character (password changed). */
    public function forgetAll(int $playerId): void
    {
        $this->conn->executeStatement('DELETE FROM remember_tokens WHERE player_id = ?', [$playerId]);
    }

    private function sendCookie(string $value, int $expires): void
    {
        $_COOKIE[self::COOKIE] = $value;
        if (headers_sent()) {
            return;
        }

        // Same flags as the session cookie (config.php): host-only, HTTPS behind the proxy too
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
