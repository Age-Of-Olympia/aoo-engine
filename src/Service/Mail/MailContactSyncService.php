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
 * DOIT PAS changer. Limités à 3 (plafond du plan OneSignal Free) :
 *  - full_name   : "{name} (mat:{id})" — personnalisation des mails (le matricule
 *                  permet au joueur dormant de se reconnecter sans son pseudo)
 *  - is_inactive : "1" quand lastLoginTime dépasse INACTIVE_TIME (même règle
 *                  que PlayerService::isInactive, recopiée inline pour rester
 *                  un calcul pur sans connexion DB par joueur) — ciblage win-back
 *  - race        : code players.race brut (nain/geant/olympien/hs/elfe) — ciblage
 *                  des mails de scénario par race
 *
 * L'onboarding (J+1/J+3/J+7) n'utilise PAS de tag : il est piloté par une
 * automation événementielle déclenchée à l'inscription, ce qui évite d'enrôler
 * les contacts existants lors du backfill et économise le 3e/4e slot de tag.
 */
class MailContactSyncService
{
    private MailContactProviderInterface $provider;

    public function __construct(?MailContactProviderInterface $provider = null)
    {
        $this->provider = $provider ?? MailContactProviderFactory::create();
    }

    /**
     * Enregistre un joueur fraîchement créé en tant que contact abonné.
     *
     * Appelé juste après le stockage de l'email. Un nouveau joueur est par
     * définition `is_inactive=0`, on évite donc le calcul de date.
     */
    public function onRegister(int $playerId, string $email, string $name, string $race): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [
            'full_name' => $this->fullName($name, $playerId),
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
     * La ligne doit exposer : id, plain_mail, name, race, lastLoginTime,
     * deletion_asked, et (optionnel) delete_account.
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
            'is_inactive' => $this->isInactive((int) $row->lastLoginTime) ? '1' : '0',
            'race' => (string) $row->race,
        ], $subscribed);
    }

    private function fullName(string $name, int $playerId): string
    {
        return "{$name} (mat:{$playerId})";
    }

    private function isInactive(int $lastLoginTime): bool
    {
        return $lastLoginTime < (time() - INACTIVE_TIME);
    }
}
