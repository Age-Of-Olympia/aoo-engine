<?php
/**
 * A type's pictures, from its Formes page (admin → Cartes). CSRF + PRG.
 *
 * Two gestures on the folder the type's kind keeps its images in:
 *   cut    — a whole picture (uploaded, or the one the board draws) sliced
 *            into `<type>_<n>.png` pieces along the declared shape;
 *   rename — a file of that folder given another name (`banque_naine.png`
 *            → `banque.png`), never moved out of it.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\CompositeSpriteService;
use App\Service\Map\EntitySpriteService;
use App\Service\Map\EntityTypeFootprintService;

$type = trim((string) ($_POST['type'] ?? ''));
$back = '/admin/footprints.php?type=' . urlencode($type);

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $sprites = new EntitySpriteService();
    $dirs = $type === '' ? [] : $sprites->dirsOf($type);

    if ($dirs === []) {
        throw new RuntimeException('Type inconnu au catalogue : « ' . $type . ' ».');
    }

    /* Pieces are written in the kind's folder; a rename works in whichever
     * folder of the type's the file is listed from. */
    $dir = $dirs[0];

    if (($_POST['action'] ?? '') === 'rename') {
        $dir = in_array((string) ($_POST['dir'] ?? ''), $dirs, true) ? (string) $_POST['dir'] : $dir;
        $folder = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $dir;
        $from = basename((string) ($_POST['from'] ?? ''));
        $to = basename((string) ($_POST['to'] ?? ''));

        if (!preg_match('/^[a-z0-9_-]+\.png$/', $to)) {
            throw new RuntimeException('Nom attendu : lettres minuscules, chiffres, _ ou -, en .png.');
        }

        if (!is_file($folder . '/' . $from)) {
            throw new RuntimeException('Fichier introuvable : ' . $from);
        }

        if (is_file($folder . '/' . $to)) {
            throw new RuntimeException('Un fichier ' . $to . ' existe déjà.');
        }

        if (!rename($folder . '/' . $from, $folder . '/' . $to)) {
            throw new RuntimeException('Renommage impossible.');
        }

        EntitySpriteService::forget();
        setFlash('success', $from . ' renommé en ' . $to . '.');
        redirectTo($back);
    }

    $footprint = (new EntityTypeFootprintService())->catalogue()[$type] ?? null;

    if ($footprint === null || $footprint->isSingleCell()) {
        throw new RuntimeException('Déclarer d\'abord une emprise de plus d\'une case.');
    }

    /* The picture to cut: the one sent, else the type's whole picture in
     * its folder — never the stitched one, which IS the pieces. */
    $upload = $_FILES['sheet'] ?? null;
    $source = is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ? (string) $upload['tmp_name']
        : $_SERVER['DOCUMENT_ROOT'] . '/img/' . $dir . '/' . $type . '.png';

    if (!is_file($source)) {
        throw new RuntimeException('Aucune image à découper : en envoyer une, ou déposer img/' . $dir . '/' . $type . '.png.');
    }

    $pieces = (new CompositeSpriteService())->cutPieces($dir, $type, $footprint, $source);

    if ($pieces === []) {
        throw new RuntimeException('Image illisible (png, webp, gif ou jpeg attendu).');
    }

    EntitySpriteService::forget();
    setFlash('success', count($pieces) . ' morceaux écrits dans img/' . $dir . '/.');
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo($back);
