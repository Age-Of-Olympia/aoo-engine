<?php

namespace Tests\Various;

use App\Service\Mail\MailContactProviderInterface;
use App\Service\Mail\MailContactSyncService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Verrouille les règles métier de MailContactSyncService — la couche qui
 * traduit l'état du jeu en appels au fournisseur de contacts de campagnes.
 *
 * Tout passe par un provider espion (aucune DB, aucun réseau) : on n'observe
 * que ce que le service DEMANDE au fournisseur. Les invariants protégés ici
 * sont ceux qui ont motivé la revue :
 *  - état d'abonnement TOUJOURS posé via un upsert unique clé par email
 *    (jamais le pattern upsert-puis-unsubscribe), donc atomique ;
 *  - (dés)abonnement porté par l'email => couvre les contacts legacy ;
 *  - graphie figée des tags (full_name / is_new / is_inactive / race) ;
 *  - email vide => aucun appel.
 */
class MailContactSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        // Le service lit INACTIVE_TIME (défini par config/constants.php en prod).
        // On le pose ici de façon idempotente pour rester indépendant de l'ordre
        // des tests et ne pas dépendre du bootstrap applicatif.
        if (!defined('INACTIVE_TIME')) {
            define('INACTIVE_TIME', 604800); // ONE_WEEK
        }
    }

    public function testOnRegisterUpsertsSubscribedContactWithFullTags(): void
    {
        $spy = $this->spyProvider();
        (new MailContactSyncService($spy))->onRegister(42, 'a@b.c', 'Yorgos', 'nain');

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame('upsert', $call['method']);
        $this->assertSame(42, $call['id']);
        $this->assertSame('a@b.c', $call['email']);
        $this->assertTrue($call['subscribed']);
        $this->assertSame([
            'full_name' => 'Yorgos (mat:42)',
            'is_new' => '1',
            'is_inactive' => '0',
            'race' => 'nain',
        ], $call['tags']);
    }

    public function testOnRegisterIgnoresEmptyEmail(): void
    {
        $spy = $this->spyProvider();
        (new MailContactSyncService($spy))->onRegister(42, '', 'Yorgos', 'nain');

        $this->assertSame([], $spy->calls);
    }

    public function testOnDeletionRequestedUnsubscribesViaEmailKeyedUpsertWithoutTouchingTags(): void
    {
        $spy = $this->spyProvider();
        (new MailContactSyncService($spy))->onDeletionRequested(42, 'a@b.c');

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame('upsert', $call['method']);
        $this->assertSame('a@b.c', $call['email']);
        $this->assertFalse($call['subscribed']);
        // Tableau vide => le provider ne réécrit pas les tags existants.
        $this->assertSame([], $call['tags']);
    }

    public function testOnDeletionRequestedIgnoresEmptyEmail(): void
    {
        $spy = $this->spyProvider();
        (new MailContactSyncService($spy))->onDeletionRequested(42, '');

        $this->assertSame([], $spy->calls);
    }

    public function testOnDeletionCancelledResubscribes(): void
    {
        $spy = $this->spyProvider();
        (new MailContactSyncService($spy))->onDeletionCancelled(42, 'a@b.c');

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame('upsert', $call['method']);
        $this->assertTrue($call['subscribed']);
        $this->assertSame([], $call['tags']);
    }

    public function testSyncPlayerActivePlayerStaysSubscribedAndFresh(): void
    {
        $spy = $this->spyProvider();
        $row = (object) [
            'id' => 7,
            'plain_mail' => 'x@y.z',
            'name' => 'Dorna',
            'race' => 'nain',
            'registerTime' => time(),       // tout juste inscrit => is_new=1
            'lastLoginTime' => time(),       // connecté => is_inactive=0
            'deletion_asked' => null,         // pas de suppression => abonné
        ];

        (new MailContactSyncService($spy))->syncPlayer($row);

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame(7, $call['id']);
        $this->assertTrue($call['subscribed']);
        $this->assertSame([
            'full_name' => 'Dorna (mat:7)',
            'is_new' => '1',
            'is_inactive' => '0',
            'race' => 'nain',
        ], $call['tags']);
    }

    public function testSyncPlayerOldInactiveDeletingPlayerIsUnsubscribedInOneCall(): void
    {
        $spy = $this->spyProvider();
        $row = (object) [
            'id' => 8,
            'plain_mail' => 'o@y.z',
            'name' => 'Vieux',
            'race' => 'elfe',
            'registerTime' => time() - (99 * 86400),   // > 30j => is_new=0
            'lastLoginTime' => time() - (30 * 86400),   // > ONE_WEEK => is_inactive=1
            'deletion_asked' => '2026-06-01 00:00:00',   // suppression => désabonné
        ];

        (new MailContactSyncService($spy))->syncPlayer($row);

        // Un SEUL appel pose tags + désabonnement : pas de fenêtre upsert-puis-unsubscribe.
        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertFalse($call['subscribed']);
        $this->assertSame([
            'full_name' => 'Vieux (mat:8)',
            'is_new' => '0',
            'is_inactive' => '1',
            'race' => 'elfe',
        ], $call['tags']);
    }

    public function testSyncPlayerLegacyDeleteAccountOptionUnsubscribes(): void
    {
        $spy = $this->spyProvider();
        // Demande de suppression à l'ancienne : option deleteAccount présente
        // (delete_account non-null), mais deletion_asked encore vide.
        $row = (object) [
            'id' => 11,
            'plain_mail' => 'legacy@y.z',
            'name' => 'Ancien',
            'race' => 'nain',
            'registerTime' => time(),
            'lastLoginTime' => time(),
            'deletion_asked' => null,
            'delete_account' => 11, // o.player_id renvoyé par le LEFT JOIN du cron
        ];

        (new MailContactSyncService($spy))->syncPlayer($row);

        $this->assertCount(1, $spy->calls);
        $this->assertFalse($spy->calls[0]['subscribed']);
    }

    public function testSyncPlayerWithoutDeleteAccountFieldStaysSubscribed(): void
    {
        // Robustesse : une row sans la propriété delete_account (ex. appel hors
        // cron) ne doit pas casser ni désabonner.
        $spy = $this->spyProvider();
        $row = (object) [
            'id' => 12,
            'plain_mail' => 'x@y.z',
            'name' => 'Sans',
            'race' => 'elfe',
            'registerTime' => time(),
            'lastLoginTime' => time(),
            'deletion_asked' => null,
        ];

        (new MailContactSyncService($spy))->syncPlayer($row);

        $this->assertCount(1, $spy->calls);
        $this->assertTrue($spy->calls[0]['subscribed']);
    }

    public function testSyncPlayerIgnoresEmptyEmail(): void
    {
        $spy = $this->spyProvider();
        $row = (object) [
            'id' => 9,
            'plain_mail' => '',
            'name' => 'NoMail',
            'race' => 'hs',
            'registerTime' => time(),
            'lastLoginTime' => time(),
            'deletion_asked' => null,
        ];

        (new MailContactSyncService($spy))->syncPlayer($row);

        $this->assertSame([], $spy->calls);
    }

    /**
     * Provider espion : enregistre chaque appel sans rien envoyer.
     *
     * @return MailContactProviderInterface&object{calls: array<int, array<string, mixed>>}
     */
    private function spyProvider(): MailContactProviderInterface
    {
        return new class implements MailContactProviderInterface {
            /** @var array<int, array<string, mixed>> */
            public array $calls = [];

            public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void
            {
                $this->calls[] = [
                    'method' => 'upsert',
                    'id' => $playerId,
                    'email' => $email,
                    'tags' => $tags,
                    'subscribed' => $subscribed,
                ];
            }

            public function updateTags(int $playerId, array $tags): void
            {
                $this->calls[] = [
                    'method' => 'updateTags',
                    'id' => $playerId,
                    'tags' => $tags,
                ];
            }
        };
    }
}
