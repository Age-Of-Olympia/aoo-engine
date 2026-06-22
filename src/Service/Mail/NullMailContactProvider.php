<?php

namespace App\Service\Mail;

/**
 * Fournisseur de contacts « no-op » utilisé quand aucun fournisseur de campagnes
 * mail n'est configuré.
 *
 * Permet au code d'inscription / suppression / cron d'appeler l'API de contacts
 * sans condition de configuration : dans le devcontainer (ou tout environnement
 * sans identifiants OneSignal), les appels ne font tout simplement rien.
 */
class NullMailContactProvider implements MailContactProviderInterface
{
    public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void
    {
        // volontairement no-op
    }

    public function updateTags(int $playerId, array $tags): void
    {
        // volontairement no-op
    }
}
