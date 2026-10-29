<?php
/** Reputation settings mutations (POST only). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\ReputationSettingsService;

(new AdminMenuAccessService())->enforce('reputation.settings');

/** A posted number, or a refusal naming the field. */
function reputation_number(string $key, string $label): float
{
    $raw = $_POST[$key] ?? '';
    if (!is_scalar($raw) || !is_numeric(str_replace(',', '.', (string) $raw))) {
        throw new InvalidArgumentException($label . ' doit être un nombre.');
    }

    return (float) str_replace(',', '.', (string) $raw);
}

/** A posted whole number, or a refusal naming the field. */
function reputation_int(string $key, string $label): int
{
    $raw = $_POST[$key] ?? '';
    if (!is_scalar($raw) || filter_var($raw, FILTER_VALIDATE_INT) === false) {
        throw new InvalidArgumentException($label . ' doit être un nombre entier.');
    }

    return (int) $raw;
}

$action = (string) ($_POST['action'] ?? '');
handleAdminPost('/admin/reputation.php', static function () use ($action): string {
    $settings = new ReputationSettingsService();

    switch ($action) {
        case 'gains':
            $settings->saveGains(
                reputation_number('per_post', 'Le gain par post'),
                reputation_number('per_cookie', 'Le gain par cookie'),
                reputation_number('per_cookie_same_faction', 'Le gain par cookie de même faction'),
                reputation_int('reward_every', 'Le palier de récompense')
            );
            return 'Gains de réputation enregistrés.';

        case 'cookies':
            $settings->saveCookies(reputation_int('cookie_max_days', 'Le délai des cookies'), booleanCheckbox('missive_cookies'));
            return 'Réglages des cookies enregistrés.';

        case 'tier':
            $tier = reputation_int('tier', 'Le palier');
            $settings->saveRewardTier($tier, reputation_int('pr', 'Le PR versé'), reputation_number('weight', 'Le poids'));
            return 'Palier ' . $tier . ' enregistré.';

        case 'title-add':
            $settings->addTitle(reputation_int('threshold', 'Le seuil'), (string) ($_POST['title'] ?? ''));
            return 'Titre ajouté.';

        case 'title-update':
            $settings->updateTitle(reputation_int('id', 'Le titre'), reputation_int('threshold', 'Le seuil'), (string) ($_POST['title'] ?? ''));
            return 'Titre enregistré.';

        case 'title-remove':
            $settings->removeTitle(reputation_int('id', 'Le titre'));
            return 'Titre retiré.';
    }

    throw new InvalidArgumentException('Action inconnue.');
});
