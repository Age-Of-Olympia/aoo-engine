<?php

namespace Tests\Various;

use App\Service\ActionExecutorService;
use App\Service\InscriptionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Player\Mock\LegacyPlayerFixtureTestCase;

/**
 * Writing on a thing: its people only, through the `ecrire` gesture,
 * which costs 1 A and needs the text sent with it.
 */
#[Group('action')]
class InscriptionGestureTest extends LegacyPlayerFixtureTestCase
{
    protected function tearDown(): void
    {
        unset($_POST['text']);
        parent::tearDown();
    }

    /** @return array{0: int, 1: int} chest id, owner standing beside it */
    private function ownedChestWithOwnerBeside(): array
    {
        [$x, $y] = $this->farTile();
        $owner = (int) $this->createRealPlayer('GmScribe')->id;
        $chest = $this->installExemplar('coffre_bois', $x, $y, $owner);
        $this->link->executeStatement("UPDATE players SET owner_id = ?, faction = '' WHERE id = ?", [$owner, $chest]);
        $this->link->executeStatement(
            'UPDATE players SET coords_id = ? WHERE id = ?',
            [$this->coordsIdOn('gaia', $x + 1, $y), $owner]
        );

        return [$chest, $owner];
    }

    private function ecrire(): \App\Entity\Action
    {
        $action = $this->actionOrSkip('ecrire');
        $this->assertInstanceOf(\App\Entity\Action::class, $action);

        return $action;
    }

    public function testOnlyItsPeopleWriteOnAThing(): void
    {
        [$chest, $owner] = $this->ownedChestWithOwnerBeside();
        $stranger = (int) $this->createRealPlayer('GmGraffiti')->id;
        $this->link->executeStatement("UPDATE players SET faction = '' WHERE id = ?", [$stranger]);
        $service = new InscriptionService();

        $service->inscribe($chest, $owner, '  Réserve du forgeron  ');
        $this->assertSame('Réserve du forgeron', $this->link->fetchOne('SELECT text FROM players WHERE id = ?', [$chest]));

        $service->inscribe($chest, $owner, '');
        $this->assertSame('', $this->link->fetchOne('SELECT text FROM players WHERE id = ?', [$chest]), 'empty erases');

        $this->assertFalse($service->mayInscribe($owner, $owner), 'a character is never written on');

        $this->expectExceptionMessage('Vous ne pouvez pas écrire ici.');
        $service->inscribe($chest, $stranger, 'Tag');
    }

    public function testTheGestureWritesAndCostsOneAction(): void
    {
        $action = $this->ecrire();
        [$chest, $owner] = $this->ownedChestWithOwnerBeside();
        $actor = $this->loadedCharacter($owner);
        $before = $actor->getRemaining('a');

        $_POST['text'] = 'Réserve';
        $results = (new ActionExecutorService($action, $actor, $this->loadedCharacter($chest)))->executeAction();

        $this->assertFalse($results->isBlocked());
        $this->assertSame('Réserve', $this->link->fetchOne('SELECT text FROM players WHERE id = ?', [$chest]));
        $this->assertSame($before - 1, $this->loadedCharacter($owner)->getRemaining('a'), 'writing costs 1 A');
    }

    public function testWithoutTextTheGestureDoesNothing(): void
    {
        $action = $this->ecrire();
        [$chest, $owner] = $this->ownedChestWithOwnerBeside();

        unset($_POST['text']);
        $results = (new ActionExecutorService($action, $this->loadedCharacter($owner), $this->loadedCharacter($chest)))->executeAction();

        $this->assertTrue($results->isBlocked(), 'no text sent (prompt cancelled): nothing written, nothing paid');
    }
}
