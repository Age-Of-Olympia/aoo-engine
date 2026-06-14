<?php

namespace App\Service;

/**
 * Single entry point for transactional emails sent by the game.
 *
 * Wraps PHP's native mail() with the shared sender identity and headers
 * so callers only provide recipient, subject and body. The header block
 * was previously duplicated inline (ResetPasswordView, then
 * AccountDeletionService) — this centralises it.
 */
class MailerService
{
    /** Sender / Reply-To identity for all outgoing mail. */
    public const FROM_EMAIL = 'admin@age-of-olympia.net';

    /**
     * Send a plain-text email.
     *
     * Returns mail()'s result (false on a hard handoff failure). Note an
     * MTA is required, so this is effectively a no-op in the devcontainer.
     */
    public function send(string $to, string $subject, string $message): bool
    {
        $headers = 'From: ' . self::FROM_EMAIL . "\r\n"
            . 'Reply-To: ' . self::FROM_EMAIL . "\r\n"
            . 'X-Mailer: PHP/' . phpversion();

        return mail($to, $subject, $message, $headers);
    }
}
