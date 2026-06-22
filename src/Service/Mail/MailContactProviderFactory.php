<?php

namespace App\Service\Mail;

/**
 * Construit le fournisseur de contacts de campagnes mail configuré.
 *
 * C'est le seul endroit qui décide quel fournisseur concret implémente le port
 * {@see MailContactProviderInterface}. Pour changer de vendeur, ajouter un cas
 * ici et faire pointer `MAIL_CONTACT_PROVIDER` (config/onesignal_constants.php)
 * dessus — aucune modification côté appelants.
 *
 * Quand le fournisseur sélectionné n'a pas ses identifiants, la factory se
 * rabat sur {@see NullMailContactProvider} pour que le jeu ne casse jamais par
 * défaut de configuration.
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
                    return new OneSignalProvider(ONESIGNAL_APP_ID, ONESIGNAL_REST_API_KEY);
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
