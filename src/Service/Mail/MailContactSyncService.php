<?php

namespace App\Service\Mail;

/**
 * Traduit l'état du jeu en appels au fournisseur (inscription, suppression,
 * annulation, cron). L'abonnement passe toujours par un upsert clé email
 * (atomique, couvre les contacts legacy sans external_id).
 *
 * Tags (3 max, plan Free) : full_name (perso, matricule inclus pour que le
 * dormant se reconnecte), race (scénarios), is_inactive (win-back).
 * L'onboarding est une automation événementielle hors tag.
 */
class MailContactSyncService
{
    private MailContactProviderInterface $provider;

    public function __construct(?MailContactProviderInterface $provider = null)
    {
        $this->provider = $provider ?? MailContactProviderFactory::create();
    }

    /** Nouveau joueur => contact abonné (is_inactive=0 par définition). */
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

    /** Désabonne (upsert clé email, tags inchangés ; couvre les contacts legacy). */
    public function onDeletionRequested(int $playerId, string $email): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [], false);
    }

    /** Réabonne le joueur qui annule sa demande de suppression. */
    public function onDeletionCancelled(int $playerId, string $email): void
    {
        if ($email === '') {
            return;
        }

        $this->provider->upsertContact($playerId, $email, [], true);
    }

    /**
     * Backfill / maintien : un upsert pose external_id, tags et abonnement.
     * Row attendue : id, plain_mail, name, race, lastLoginTime, deletion_asked,
     * et (optionnel) delete_account.
     */
    public function syncPlayer(object $row): void
    {
        $email = $row->plain_mail ?? '';
        if ($email === '' || $email === null) {
            return;
        }

        $playerId = (int) $row->id;

        // Désabonné si suppression demandée : deletion_asked (système actuel) ou
        // option legacy deleteAccount (exposée par le cron via delete_account).
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

    /** Même règle que PlayerService::isInactive, inline pour éviter une connexion DB. */
    private function isInactive(int $lastLoginTime): bool
    {
        return $lastLoginTime < (time() - INACTIVE_TIME);
    }
}
