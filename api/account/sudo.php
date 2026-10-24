<?php

use App\Service\AdminAuthorizationService;

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

/* The HUD admin button: enables the highest level the login holds, on the
 * character played right now (deliberate gesture = -force), then the page
 * opens the panel. JSON only, as aooFetch sends it: a cross-site form
 * cannot post that without a preflight. */
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
) {
    ExitError('Invalid request');
}

if (!AdminAuthorizationService::canElevate()) {
    ExitError('Action réservée aux admin');
}

AdminAuthorizationService::sudoHighest();
ExitSuccess(['message' => 'Droits activés.']);
