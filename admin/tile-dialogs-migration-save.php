<?php
/**
 * Application de la reprise des déclencheurs de case (PRG).
 *
 * Le plan se lit sur tile-dialogs-migration.php ; ici on l'exécute.
 * Chaque case est traitée pour elle-même : une case qui échoue ne doit
 * pas emporter les autres, et la reprise doit pouvoir être relancée sur
 * ce qui reste.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\TileDialogMigrationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['apply'])) {
    redirectTo('/admin/tile-dialogs-migration.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('danger', 'Jeton invalide, rien n\'a été fait.');
    redirectTo('/admin/tile-dialogs-migration.php');
}

$done = (new TileDialogMigrationService())->apply();

$ok = 0;
$errors = [];
foreach ($done as $line) {
    if ($line['error'] === '') {
        $ok++;
        continue;
    }
    $errors[] = 'case #' . $line['coords_id'] . ' : ' . $line['error'];
}

if ($errors === []) {
    setFlash('success', $ok . ' case(s) reprise(s). Les déclencheurs transférés ont été supprimés.');
} else {
    /* On dit ce qui a marché ET ce qui a échoué : une reprise partielle
     * qui ne s'annonce pas se relance à l'aveugle. */
    setFlash('warning', $ok . ' case(s) reprise(s), ' . count($errors) . ' en échec — '
        . e(implode(' ; ', array_slice($errors, 0, 3)))
        . (count($errors) > 3 ? ' …' : ''));
}

redirectTo('/admin/tile-dialogs-migration.php');
