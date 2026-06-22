<?php

namespace App\Service\Mail;

/**
 * Fait le pont entre l'état du jeu et le fournisseur de contacts de campagnes.
 *
 * Porte les règles *spécifiques au jeu* (quels tags existent, comment leurs
 * valeurs sont calculées) afin que le fournisseur reste un simple transport.
 * Utilisé à quatre endroits :
 *  - inscription        ({@see onRegister})          — crée le contact (abonné)
 *  - demande de suppression ({@see onDeletionRequested}) — désabonne
 *  - annulation de suppression ({@see onDeletionCancelled}) — réabonne
 *  - cron de synchro quotidien ({@see syncPlayer})   — backfill + maintien
 *
 * L'état d'abonnement passe TOUJOURS par un upsert clé par email : un seul
 * appel atomique pose l'external_id, l'email et le flag `enabled`. Cela évite la
 * fenêtre du pattern upsert-puis-unsubscribe (un 2e appel qui échoue laissait le
 * contact réabonné) et couvre les contacts legacy importés sans external_id.
 *
 * La graphie des tags est figée par les segments du dashboard OneSignal et NE
 * DOIT PAS changer :
 *  - full_name   : "{name} (mat:{id})"
 *  - is_new      : "1" tant que registerTime est dans NEW_PLAYER_WINDOW, sinon "0"
 *  - is_inactive : "1" quand lastLoginTime dépasse INACTIVE_TIME (même règle
 *                  que PlayerService::isInactive, recopiée inline pour rester
 *                  un calcul pur sans connexion DB par joueur)
 *  - race        : code players.race brut (nain/geant/olympien/hs/elfe)
 */
class MailContactSyncService
{
    /** Fenêtre pendant laquelle un joueur compte comme « nouveau » : 30 jours (1 mois). */
    public const NEW_PLAYER_WINDOW = 30 * 86400;

    private MailContactProviderInterface $provider;

    public function __construct(?MailContactProviderInterface $provider = null)
    {
        $this->provider = $provider ?? MailContactProviderFactory::create();
    }

    /**
     * Enregistre un joueur fraîchement créé en tant que contact abonné.
     *
     * Appelé juste après le stockage de l'email. Un nouveau joueur est par
     * définition `is_new=1` et `is_inactive=0`, on évite donc les calculs de date.
     */
    public function onRegister(int $playerId, string $email, string $name, string $race): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [
            'full_name' => $this->fullName($name, $playerId),
            'is_new' => '1',
            'is_inactive' => '0',
            'race' => $race,
        ], true);
    }

    /**
     * Désabonne un joueur qui a demandé la suppression de son compte.
     *
     * Upsert clé par email avec `enabled=false` (tags inchangés) : couvre aussi
     * les contacts legacy sans external_id, qui se voient en plus attacher leur
     * external_id au passage.
     */
    public function onDeletionRequested(int $playerId, string $email): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [], false);
    }

    /**
     * Réabonne un joueur qui annule sa demande de suppression.
     */
    public function onDeletionCancelled(int $playerId, string $email): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [], true);
    }

    /**
     * Réconcilie un joueur existant (backfill + maintien continu).
     *
     * Un seul upsert : (ré)attache l'external_id au contact clé par email,
     * rafraîchit tous les tags et aligne l'abonnement sur l'état de suppression.
     * La ligne doit exposer : id, plain_mail, name, race, registerTime,
     * lastLoginTime, deletion_asked, et (optionnel) delete_account.
     */
    public function syncPlayer(object $row): void
    {
        $email = $row->plain_mail ?? '';
        if ($email === '' || $email === null) {
            return;
        }

        $playerId = (int) $row->id;

        // Désabonné si suppression demandée par l'un ou l'autre système :
        //  - deletion_asked (système actuel, AccountDeletionService) ;
        //  - option legacy players_options.deleteAccount (antérieure, exposée par
        //    le cron via delete_account). Le backfill doit honorer les anciennes
        //    demandes pour ne pas réabonner d'anciens demandeurs de suppression.
        $subscribed = empty($row->deletion_asked) && empty($row->delete_account ?? null);

        $this->provider->upsertContact($playerId, $email, [
            'full_name' => $this->fullName((string) $row->name, $playerId),
            'is_new' => $this->isNew((int) $row->registerTime) ? '1' : '0',
            'is_inactive' => $this->isInactive((int) $row->lastLoginTime) ? '1' : '0',
            'race' => (string) $row->race,
        ], $subscribed);
    }

    private function fullName(string $name, int $playerId): string
    {
        return "{$name} (mat:{$playerId})";
    }

    private function isNew(int $registerTime): bool
    {
        return $registerTime > (time() - self::NEW_PLAYER_WINDOW);
    }

    private function isInactive(int $lastLoginTime): bool
    {
        return $lastLoginTime < (time() - INACTIVE_TIME);
    }
}
