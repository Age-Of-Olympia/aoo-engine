<?php
/**
 * Bridges the shared option whitelist into the constants the admin-access pages
 * use. The canonical list now lives on App\Service\PlayerOptionsService
 * (MANAGEABLE_OPTIONS / PRIVILEGED_OPTIONS) so the dashboard and the `option`
 * console command share one source of truth; these defines keep the existing
 * ADMIN_ACCESS_* references working without duplicating the list.
 */

use App\Service\PlayerOptionsService;

if (!defined('ADMIN_ACCESS_VALID_OPTIONS')) {
    define('ADMIN_ACCESS_VALID_OPTIONS', PlayerOptionsService::MANAGEABLE_OPTIONS);
    define('ADMIN_ACCESS_PRIVILEGED_OPTIONS', PlayerOptionsService::PRIVILEGED_OPTIONS);
}
