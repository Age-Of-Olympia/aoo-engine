<?php

namespace App\Service\Mail;

use GuzzleHttp\Client;
use onesignal\client\api\DefaultApi;
use onesignal\client\Configuration;
use onesignal\client\model\PropertiesObject;
use onesignal\client\model\Subscription;
use onesignal\client\model\UpdateUserRequest;
use onesignal\client\model\User;

/**
 * Implémentation OneSignal de {@see MailContactProviderInterface}.
 *
 * S'appuie sur le SDK PHP officiel (`onesignal/onesignal-php-api`, lui-même
 * basé sur Guzzle) plutôt que sur du cURL maison : les contrats d'API et les
 * modèles typés sont maintenus par l'éditeur. Les contacts sont des « users »
 * OneSignal identifiés par `external_id = players.id`, avec un abonnement email
 * et des Data Tags qui pilotent les segments du dashboard.
 *
 * Chaque méthode publique est tolérante aux pannes : toute exception du SDK
 * (transport, HTTP non-2xx) est attrapée et journalisée, jamais propagée, afin
 * qu'une panne de OneSignal ne casse ni une inscription ni une demande de
 * suppression.
 *
 * @see https://github.com/OneSignal/onesignal-php-api
 */
class OneSignalProvider implements MailContactProviderInterface
{
    /** Label d'alias OneSignal portant le matricule joueur. */
    private const ALIAS_EXTERNAL_ID = 'external_id';

    /** Type d'abonnement email tel que nommé par l'API OneSignal. */
    private const SUBSCRIPTION_EMAIL = 'Email';

    private string $appId;
    private DefaultApi $api;

    /**
     * @param DefaultApi|null $api Injectable pour les tests ; construit à partir
     *                             des identifiants sinon.
     */
    public function __construct(string $appId, string $apiKey, ?DefaultApi $api = null)
    {
        $this->appId = $appId;
        $this->api = $api ?? new DefaultApi(
            new Client(),
            (new Configuration())->setRestApiKeyToken($apiKey)
        );
    }

    public function upsertContact(int $playerId, string $email, array $tags, bool $subscribed = true): void
    {
        try {
            // createUser est idempotent et réconcilie par email : il attache
            // l'external_id aux contacts legacy (clés email seul) et fixe l'état
            // de l'abonnement (enabled) dans le même appel — pas de fenêtre où un
            // contact « en suppression » resterait abonné.
            $subscription = (new Subscription())
                ->setType(self::SUBSCRIPTION_EMAIL)
                ->setToken($email)
                ->setEnabled($subscribed);

            $user = (new User())
                ->setIdentity([self::ALIAS_EXTERNAL_ID => (string) $playerId])
                ->setSubscriptions([$subscription]);

            // Tableau vide => on ne touche pas aux tags existants (cas
            // (dés)abonnement où l'on ne veut pas les recalculer).
            if ($tags !== []) {
                $user->setProperties((new PropertiesObject())->setTags($this->stringifyTags($tags)));
            }

            $this->api->createUser($this->appId, $user);
        } catch (\Throwable $e) {
            error_log("[OneSignal] upsertContact échoué pour #{$playerId} : " . $e->getMessage());
        }
    }

    public function updateTags(int $playerId, array $tags): void
    {
        try {
            $request = (new UpdateUserRequest())
                ->setProperties((new PropertiesObject())->setTags($this->stringifyTags($tags)));

            $this->api->updateUser($this->appId, self::ALIAS_EXTERNAL_ID, (string) $playerId, $request);
        } catch (\Throwable $e) {
            error_log("[OneSignal] updateTags échoué pour #{$playerId} : " . $e->getMessage());
        }
    }

    /**
     * Les tags OneSignal sont des chaînes : on convertit chaque valeur pour
     * éviter qu'elles soient silencieusement ignorées.
     *
     * @param array<string,mixed> $tags
     * @return array<string,string>
     */
    private function stringifyTags(array $tags): array
    {
        return array_map(static fn ($value) => (string) $value, $tags);
    }
}
