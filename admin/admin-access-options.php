<?php
/**
 * Shared option whitelist for the Admin Access feature.
 *
 * Included by both admin-access.php (renders the toggles) and
 * admin-access-toggle.php (validates the POST), so the two can never drift.
 * Kept in sync with console-commands/optioncmd.php's $valid_options.
 */

if (!defined('ADMIN_ACCESS_VALID_OPTIONS')) {
    /**
     * Canonical whitelist of toggleable options. Any name outside this list is
     * rejected server-side, so a tampered POST can never write an arbitrary
     * option row.
     */
    define('ADMIN_ACCESS_VALID_OPTIONS', [
        'isSuperAdmin', 'isAdmin', 'isMerchant', 'isTrainer', 'showActionDetails',
        'alreadyFished', 'incognitoMode', 'invisibleMode', 'showBlockedTiles',
        'doubleUpload', 'alreadyChanged', 'dlag',
    ]);

    /** Options that carry admin authority — highlighted in the UI. */
    define('ADMIN_ACCESS_PRIVILEGED_OPTIONS', ['isAdmin', 'isSuperAdmin']);
}
