<?php

namespace App\Factory;

use App\Service\Mail\NullMailContactProvider;
use App\Service\Mail\OneSignalProvider;
use App\Interface\MailContactProviderInterface;
/** Le fournisseur configuré (OneSignal), ou un no-op quand les clés manquent. */
class MailContactProviderFactory
{
    public static function create(): MailContactProviderInterface
    {
        if (self::oneSignalConfigured()) {
            return new OneSignalProvider(
                (string) constant('ONESIGNAL_APP_ID'),
                (string) constant('ONESIGNAL_REST_API_KEY')
            );
        }

        return new NullMailContactProvider();
    }

    /** Modèle OneSignal du mail de bienvenue, '' quand non configuré. */
    public static function welcomeTemplateId(): string
    {
        return defined('ONESIGNAL_WELCOME_TEMPLATE_ID') ? (string) constant('ONESIGNAL_WELCOME_TEMPLATE_ID') : '';
    }

    private static function oneSignalConfigured(): bool
    {
        return defined('ONESIGNAL_APP_ID') && ONESIGNAL_APP_ID !== ''
            && defined('ONESIGNAL_REST_API_KEY') && ONESIGNAL_REST_API_KEY !== '';
    }
}
