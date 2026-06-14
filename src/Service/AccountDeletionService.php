<?php

namespace App\Service;

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

    public function __construct(?MailerService $mailer = null)
    {
        $this->mailer = $mailer ?? new MailerService();
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

        $this->notifyAdmin($playerId, $playerName, $playerMail);
    }

    /**
     * Clear a pending deletion request (player unticked the option).
     */
    public function cancelDeletion(int $playerId): void
    {
        $db = new Db();

        $db->exe(
            'UPDATE players SET deletion_asked = NULL WHERE id = ?',
            [$playerId]
        );
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
