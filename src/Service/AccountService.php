<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The one gateway to a character's credentials: password, mail, mail bonus and
 * last login. They live in `accounts`, keyed by player_id.
 */
final class AccountService
{
    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /** The hash to check a login against, null when the character has none. */
    public function passwordHashOf(int $playerId): ?string
    {
        $hash = $this->readOne($playerId, 'psw');

        return ($hash === null || $hash === '') ? null : $hash;
    }

    public function setPassword(int $playerId, string $hash): void
    {
        $this->write($playerId, ['psw' => $hash]);
    }

    /** The hashed copy of the address, checked by the password reset. */
    public function mailHashOf(int $playerId): string
    {
        return (string) $this->readOne($playerId, 'mail');
    }

    /** The address as typed, for contacting the player; '' when unknown. */
    public function plainMailOf(int $playerId): string
    {
        return (string) $this->readOne($playerId, 'plain_mail');
    }

    /** @param string $hash the historical hashed copy kept beside the plain one */
    public function setMail(int $playerId, string $hash, string $plain): void
    {
        $this->write($playerId, ['mail' => $hash, 'plain_mail' => $plain]);
    }

    public function hasEmailBonus(int $playerId): bool
    {
        return (bool) $this->readOne($playerId, 'email_bonus');
    }

    public function grantEmailBonus(int $playerId): void
    {
        $this->write($playerId, ['email_bonus' => 1]);
    }

    public function lastLoginOf(int $playerId): int
    {
        return (int) $this->readOne($playerId, 'last_login_time');
    }

    public function touchLastLogin(int $playerId, ?int $time = null): void
    {
        $this->write($playerId, ['last_login_time' => $time ?? time()]);
    }

    /**
     * Wipe what identifies the account, leaving the character in place — the
     * anonymisation half of a deletion request.
     */
    public function forget(int $playerId, string $deadPasswordHash): void
    {
        $this->write($playerId, [
            'psw' => $deadPasswordHash,
            'mail' => '',
            'plain_mail' => '',
        ]);
    }

    private function readOne(int $playerId, string $column): ?string
    {
        $value = $this->conn->fetchOne(
            "SELECT {$column} FROM accounts WHERE player_id = ?",
            [$playerId]
        );

        return ($value === false || $value === null) ? null : (string) $value;
    }

    /** @param array<string, string|int> $values accounts columns */
    private function write(int $playerId, array $values): void
    {
        $columns = array_keys($values);

        $this->conn->executeStatement(
            'INSERT INTO accounts (player_id, ' . implode(', ', $columns) . ')
             VALUES (?' . str_repeat(', ?', count($columns)) . ')
             ON DUPLICATE KEY UPDATE '
                . implode(', ', array_map(
                    static fn (string $c): string => "{$c} = VALUES({$c})",
                    $columns
                )),
            array_merge([$playerId], array_values($values))
        );
    }
}
