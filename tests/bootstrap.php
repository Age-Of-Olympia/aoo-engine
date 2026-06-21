<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Canonical game constants for the whole suite. Defining CARACS here, once and
// complete, removes the global-constant pollution where the first test to define
// a partial CARACS won the define-race and corrupted later tests — a CARACS
// without 'mvt' made the tutorial DB tests' getRemaining('mvt') return null.
// Mirrors config/constants.php; the per-test `if (!defined('CARACS'))` guards
// become no-ops.
if (!defined('CARACS')) {
    define('CARACS', [
        'a' => 'A', 'mvt' => 'Mvt', 'p' => 'P', 'pv' => 'PV', 'cc' => 'CC',
        'ct' => 'CT', 'f' => 'F', 'e' => 'E', 'agi' => 'Agi', 'pm' => 'PM',
        'fm' => 'FM', 'm' => 'M', 'r' => 'R', 'rm' => 'RM', 'spd' => 'Spd', 'ae' => 'Ae',
    ]);
}
if (!defined('ONE_DAY')) {
    define('ONE_DAY', 86400);
}
