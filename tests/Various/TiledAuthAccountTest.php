<?php

namespace Tests\Various;

use App\Service\TiledAuthService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * The Tiled login checks the same password as the game: the one in `accounts`,
 * read through AccountService, not the `players.psw` column kept as a mirror.
 */
#[Group('entities-baseline')]
class TiledAuthAccountTest extends LegacyPlayerFixtureTestCase
{
    public function testTheTiledLoginReadsThePasswordFromTheAccount(): void
    {
        $id = (int) $this->createRealPlayer('GmCartographe')->id;

        $this->link->executeStatement('INSERT INTO players_options (player_id, name) VALUES (?, ?)', [$id, 'isAdmin']);
        $this->link->executeStatement(
            'INSERT INTO accounts (player_id, psw) VALUES (?, ?) ON DUPLICATE KEY UPDATE psw = VALUES(psw)',
            [$id, password_hash('du-compte', PASSWORD_DEFAULT)]
        );
        $this->link->executeStatement(
            'UPDATE players SET psw = ? WHERE id = ?',
            [password_hash('de-la-colonne', PASSWORD_DEFAULT), $id]
        );

        $this->assertSame($id, TiledAuthService::authenticate((string) $id, 'du-compte'));
        $this->assertNull(TiledAuthService::authenticate((string) $id, 'de-la-colonne'));
    }
}
