<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * The one gateway to a character's credentials: password, mail, mail bonus and
 * last login. They live in `accounts`, keyed by player_id.
 *
 * Every write also updates the matching `players` column, because code still
 * reads those during the transition. When the columns go, the dual write goes
 * with them and only the first statement of each method remains — that is the
 * whole point of routing writes through here first.
 */
final class AccountService extends BaseService
{
    /** players columns still mirrored, keyed by their accounts counterpart. */
    private const MIRRORED = [
        'psw' => 'psw',
        'mail' => 'mail',
        'plain_mail' => 'plain_mail',
        'email_bonus' => 'email_bonus',
        'last_login_time' => 'lastLoginTime',
    ];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
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

    /** A character born without credentials still gets its row. */
    public function ensureRow(int $playerId): void
    {
        $this->conn->executeStatement(
            'INSERT IGNORE INTO accounts (player_id) VALUES (?)',
            [$playerId]
        );
    }

    private function readOne(int $playerId, string $column): ?string
    {
        $value = $this->conn->fetchOne(
            "SELECT {$column} FROM accounts WHERE player_id = ?",
            [$playerId]
        );

        /* Empty counts as absent, matching the join in Player::get_row(): the
         * backfill gave every character a row, so '' means untouched, not
         * "deliberately blank". */
        if ($value !== false && $value !== null && $value !== '') {
            return (string) $value;
        }

        /* No account row yet: the character predates the table, or was created
         * by a path that has not been routed here. Fall back to the column the
         * migration copied FROM, so a read never comes back empty by accident. */
        $legacy = $this->conn->fetchOne(
            'SELECT ' . self::MIRRORED[$column] . ' FROM players WHERE id = ?',
            [$playerId]
        );

        return ($legacy === false || $legacy === null) ? null : (string) $legacy;
    }

    /** @param array<string, string|int> $values accounts columns */
    private function write(int $playerId, array $values): void
    {
        $this->conn->transactional(function (Connection $conn) use ($playerId, $values): void {
            $columns = array_keys($values);

            $conn->executeStatement(
                'INSERT INTO accounts (player_id, ' . implode(', ', $columns) . ')
                 VALUES (?' . str_repeat(', ?', count($columns)) . ')
                 ON DUPLICATE KEY UPDATE '
                    . implode(', ', array_map(
                        static fn (string $c): string => "{$c} = VALUES({$c})",
                        $columns
                    )),
                array_merge([$playerId], array_values($values))
            );

            $mirrored = array_map(
                static fn (string $c): string => self::MIRRORED[$c] . ' = ?',
                $columns
            );

            $conn->executeStatement(
                'UPDATE players SET ' . implode(', ', $mirrored) . ' WHERE id = ?',
                array_merge(array_values($values), [$playerId])
            );
        });
    }
}
