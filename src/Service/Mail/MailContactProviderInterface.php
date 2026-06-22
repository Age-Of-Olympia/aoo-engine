<?php

namespace App\Service\Mail;

/**
 * Liste de contacts des campagnes mail (OneSignal aujourd'hui). Couture qui
 * permet de changer de fournisseur sans toucher au code du jeu.
 *
 * Contact = matricule (external_id = players.id) + abonnement email + tags.
 * Implémentations tolérantes aux pannes : ne jamais propager une erreur réseau.
 */
interface MailContactProviderInterface
{
    /**
     * Upsert clé par email : pose external_id, abonnement et son état en un
     * appel. $subscribed=false => désabonné. $tags vide => tags inchangés.
     *
     * @param array<string,string> $tags
     */
    public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void;

    /**
     * Rafraîchit les tags (via external_id), sans toucher à l'abonnement.
     *
     * @param array<string,string> $tags
     */
    public function updateTags(int $playerId, array $tags): void;
}
