<?php

namespace App\Service\Mail;

/** No-op quand aucun fournisseur n'est configuré (ex. devcontainer). */
class NullMailContactProvider implements MailContactProviderInterface
{
    public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void
    {
    }

    public function updateTags(int $playerId, array $tags): void
    {
    }
}
