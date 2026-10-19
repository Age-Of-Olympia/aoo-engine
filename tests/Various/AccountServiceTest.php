<?php

namespace Tests\Various;

use App\Service\AccountService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Credentials live in `accounts`, read and written through AccountService.
 */
#[Group('entities-baseline')]
class AccountServiceTest extends LegacyPlayerFixtureTestCase
{
    public function testAWriteLandsInTheAccount(): void
    {
        $player = $this->createRealPlayer('GmCompte');
        $id = (int) $player->id;

        (new AccountService($this->link))->setPassword($id, 'un-condensat');

        $this->assertSame(
            'un-condensat',
            $this->link->fetchOne('SELECT psw FROM accounts WHERE player_id = ?', [$id])
        );
    }

    public function testACharacterWithoutAccountRowHasNoPassword(): void
    {
        $player = $this->createRealPlayer('GmSansCompte');
        $id = (int) $player->id;

        $this->link->executeStatement('DELETE FROM accounts WHERE player_id = ?', [$id]);

        $this->assertNull((new AccountService($this->link))->passwordHashOf($id));
    }

    public function testAnEmptyPasswordReadsAsNoneRatherThanEmptyString(): void
    {
        $player = $this->createRealPlayer('GmMuet');
        $id = (int) $player->id;

        $this->link->executeStatement(
            "INSERT INTO accounts (player_id, psw) VALUES (?, '')
             ON DUPLICATE KEY UPDATE psw = ''",
            [$id]
        );

        $this->assertNull(
            (new AccountService($this->link))->passwordHashOf($id),
            'null refuses a login; an empty string would be compared and could match'
        );
    }

    public function testMailAndItsBonusAreReadBackThroughTheService(): void
    {
        $player = $this->createRealPlayer('GmCourriel');
        $id = (int) $player->id;
        $accounts = new AccountService($this->link);

        $this->assertFalse($accounts->hasEmailBonus($id));

        $accounts->setMail($id, 'condensat', 'joueur@example.org');
        $accounts->grantEmailBonus($id);

        $this->assertSame('joueur@example.org', $accounts->plainMailOf($id));
        $this->assertTrue($accounts->hasEmailBonus($id));
    }

    /** The legacy row keeps carrying the account fields, through the join. */
    public function testTheLegacyRowStillCarriesTheAccountFields(): void
    {
        $player = $this->createRealPlayer('GmLegacy');
        $id = (int) $player->id;

        $accounts = new AccountService($this->link);
        $accounts->setMail($id, 'condensat', 'legacy@example.org');
        $accounts->touchLastLogin($id, 1_800_000_000);

        $player->get_row();

        $this->assertSame('legacy@example.org', $player->row->plain_mail);
        $this->assertSame(1_800_000_000, (int) $player->row->lastLoginTime);
    }

    /** Wiping an account leaves the character standing. */
    public function testForgettingClearsTheMailButKeepsTheCharacter(): void
    {
        $player = $this->createRealPlayer('GmOublie');
        $id = (int) $player->id;
        $accounts = new AccountService($this->link);

        $accounts->setMail($id, 'condensat', 'oubli@example.org');
        $accounts->forget($id, 'condensat-mort');

        $this->assertSame('', $accounts->plainMailOf($id));
        $this->assertSame('condensat-mort', $accounts->passwordHashOf($id));
        $this->assertNotFalse(
            $this->link->fetchOne('SELECT id FROM players WHERE id = ?', [$id]),
            'the character stays on the board'
        );
    }
}
