<?php

namespace App\Service;

use App\Service\Mail\MailContactSyncService;
use Classes\Db;

/**
 * Handles account deletion requests issued from the profile options screen.
 *
 * The "Demander la suppression du compte" toggle used to only write a
 * `deleteAccount` row in `players_options`, which nothing ever read — the
 * request was silently dropped. This service stamps `players.deletion_asked`
 * so the admin team has a queryable backlog, and notifies them by email
 * (transport delegated to MailerService).
 */
class AccountDeletionService
{
    /** Recipient of deletion notifications. */
    public const ADMIN_EMAIL = 'admin@age-of-olympia.net';

    private MailerService $mailer;
    private MailContactSyncService $contactSync;

    public function __construct(?MailerService $mailer = null, ?MailContactSyncService $contactSync = null)
    {
        $this->mailer = $mailer ?? new MailerService();
        $this->contactSync = $contactSync ?? new MailContactSyncService();
    }

    /**
     * Record a deletion request and alert the admin team.
     *
     * The timestamp is only set when none exists yet, so re-toggling the
     * option does not reset the 7-day countdown.
     */
    public function requestDeletion(int $playerId, string $playerName, ?string $playerMail = null): void
    {
        $db = new Db();

        $db->exe(
            'UPDATE players SET deletion_asked = NOW() WHERE id = ? AND deletion_asked IS NULL',
            [$playerId]
        );

        // Source de vérité = la colonne DB. Le mail passé par l'appelant vient
        // du cache JSON ($player->data), périmé depuis l'inscription (register.php
        // écrit plain_mail APRÈS get_data() sans rafraîchir le cache) : on
        // résout donc l'email en base pour ne pas no-op au toggle profil.
        $mail = $this->resolvePlayerMail($playerId, $playerMail);

        $this->notifyAdmin($playerId, $playerName, $mail);

        // Coupe les mails de campagne pour un joueur en partance (non bloquant).
        $this->contactSync->onDeletionRequested($playerId, $mail);
    }

    /**
     * Clear a pending deletion request (player unticked the option).
     */
    public function cancelDeletion(int $playerId, ?string $playerMail = null): void
    {
        $db = new Db();

        $db->exe(
            'UPDATE players SET deletion_asked = NULL WHERE id = ?',
            [$playerId]
        );

        // Réabonne le joueur qui change d'avis (non bloquant). Email résolu en
        // base pour les mêmes raisons que requestDeletion (cache JSON périmé).
        $this->contactSync->onDeletionCancelled(
            $playerId,
            $this->resolvePlayerMail($playerId, $playerMail)
        );
    }

    /**
     * Résout l'email du joueur de façon fiable.
     *
     * Le mail fourni par l'appelant provient du cache JSON ($player->data), qui
     * peut être vide (cf. requestDeletion). On le garde s'il est renseigné,
     * sinon on lit `plain_mail` directement en base (source de vérité).
     */
    private function resolvePlayerMail(int $playerId, ?string $providedMail): string
    {
        if ($providedMail !== null && $providedMail !== '') {
            return $providedMail;
        }

        $res = (new Db())->exe('SELECT plain_mail FROM players WHERE id = ?', [$playerId]);

        if ($res && $row = $res->fetch_object()) {
            return $row->plain_mail ?? '';
        }

        return '';
    }

    private function notifyAdmin(int $playerId, string $playerName, ?string $playerMail): void
    {
        $subject = 'Demande de suppression de compte';
        $message = "Un joueur a demandé la suppression de son compte.\n\n"
            . 'ID: ' . $playerId . "\n"
            . 'Nom: ' . $playerName . "\n"
            . 'Mail: ' . ($playerMail !== null && $playerMail !== '' ? $playerMail : 'inconnu') . "\n\n"
            . "Le compte doit être supprimé sous 7 jours.";

        $this->mailer->send(self::ADMIN_EMAIL, $subject, $message);
    }
}
