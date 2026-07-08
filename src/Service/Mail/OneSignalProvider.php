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
 * Implémentation OneSignal via le SDK officiel (onesignal/onesignal-php-api).
 * Contact = user OneSignal (external_id = players.id) + abonnement email + tags.
 * Tolérant aux pannes : toute exception SDK est loggée, jamais propagée.
 */
class OneSignalProvider implements MailContactProviderInterface
{
    private const ALIAS_EXTERNAL_ID = 'external_id';
    private const SUBSCRIPTION_EMAIL = 'Email';

    private string $appId;
    private DefaultApi $api;

    /** @param DefaultApi|null $api Injectable pour les tests. */
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
            // createUser réconcilie par email : attache l'external_id aux contacts
            // legacy et fixe l'abonnement, dans le même appel.
            $subscription = (new Subscription())
                ->setType(self::SUBSCRIPTION_EMAIL)
                ->setToken($email)
                ->setEnabled($subscribed);

            $user = (new User())
                ->setIdentity([self::ALIAS_EXTERNAL_ID => (string) $playerId])
                ->setSubscriptions([$subscription]);

            // Tableau vide => on ne touche pas aux tags existants.
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
     * Les tags OneSignal sont des chaînes.
     *
     * @param array<string,mixed> $tags
     * @return array<string,string>
     */
    private function stringifyTags(array $tags): array
    {
        return array_map(static fn ($value) => (string) $value, $tags);
    }
}
