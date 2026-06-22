<?php

namespace App\Service\Mail;

/**
 * Port indépendant du fournisseur pour la liste de contacts des campagnes mail.
 *
 * Le jeu maintient une liste de contacts externe (actuellement OneSignal) qui
 * sert à envoyer des campagnes marketing / de rétention. Cette interface est la
 * couture qui permet de changer de fournisseur sans toucher au code du jeu :
 * l'inscription, la suppression de compte et le cron de synchro quotidien
 * dépendent tous de ce contrat, jamais d'un vendeur concret.
 *
 * Un contact est identifié par le matricule du joueur (`external_id = players.id`)
 * et porte un abonnement email ainsi qu'un ensemble de « tags » (chaînes) servant
 * à construire les segments de ciblage.
 *
 * Les implémentations DOIVENT être tolérantes aux pannes : une indisponibilité
 * du fournisseur ne doit jamais casser une inscription ou une demande de
 * suppression. Les méthodes avalent donc les erreurs de transport (journalisées
 * via error_log) au lieu de les propager.
 */
interface MailContactProviderInterface
{
    /**
     * Crée ou met à jour un contact, en posant en un seul appel atomique :
     * l'identité (external_id), l'abonnement email ET son état d'abonnement.
     *
     * L'email porté par l'appel est la clé de réconciliation : un contact
     * existant (y compris legacy, importé sans external_id) est retrouvé par
     * email et se voit attacher son external_id, sans doublon. C'est aussi ce
     * qui permet de poser l'état d'abonnement sur les contacts legacy.
     *
     * `$subscribed = false` désabonne l'email (statut Unsubscribed), pas un
     * simple tag : le fournisseur cesse d'envoyer à cette adresse.
     *
     * @param array<string,string> $tags Tags de segmentation. Tableau vide =>
     *                                    les tags existants sont laissés tels
     *                                    quels (on ne touche qu'à l'abonnement).
     */
    public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void;

    /**
     * Rafraîchit uniquement les tags d'un contact existant (sans toucher à
     * l'état de son abonnement), via son external_id.
     *
     * @param array<string,string> $tags Tags de segmentation (valeurs en chaîne).
     */
    public function updateTags(int $playerId, array $tags): void;
}
