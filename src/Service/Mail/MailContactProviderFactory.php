<?php

namespace App\Service\Mail;

/**
 * Construit le fournisseur configuré (MAIL_CONTACT_PROVIDER) — seul point de
 * choix du vendeur. Se rabat sur NullMailContactProvider si non configuré.
 */
class MailContactProviderFactory
{
    public const PROVIDER_ONESIGNAL = 'onesignal';

    public static function create(): MailContactProviderInterface
    {
        $provider = defined('MAIL_CONTACT_PROVIDER') ? MAIL_CONTACT_PROVIDER : self::PROVIDER_ONESIGNAL;

        switch ($provider) {
            case self::PROVIDER_ONESIGNAL:
                if (self::oneSignalConfigured()) {
                    return new OneSignalProvider(
                        (string) constant('ONESIGNAL_APP_ID'),
                        (string) constant('ONESIGNAL_REST_API_KEY')
                    );
                }
                break;
        }

        return new NullMailContactProvider();
    }

    private static function oneSignalConfigured(): bool
    {
        return defined('ONESIGNAL_APP_ID') && ONESIGNAL_APP_ID !== ''
            && defined('ONESIGNAL_REST_API_KEY') && ONESIGNAL_REST_API_KEY !== '';
    }
}
