<?php
/**
 * Mutations des découpes de décor (admin → Cartes · Découpes).
 *
 * Trois gestes, tous en PRG derrière un jeton CSRF :
 *
 * - `adopt`  — fige la découpe actuellement devinée. Elle cesse alors de
 *   dépendre de la carte : reprendre un décor mal posé ne la changera plus.
 * - `declare` — la saisit à la main, pour les figures qu'aucune source ne
 *   décrit correctement.
 * - `forget` — la retire ; le type retombe sur ce que la carte ou l'image
 *   disent.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;

$service = new EntityTypeFootprintService();

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $type = trim((string) ($_POST['type'] ?? ''));
    $action = (string) ($_POST['action'] ?? '');

    if ($type === '') {
        throw new RuntimeException('Aucun type indiqué.');
    }

    switch ($action) {
        case 'adopt':
            /* Ce que l'éditeur montre aujourd'hui devient ce qu'il montrera
             * demain, quoi qu'il advienne de la carte. */
            $footprint = $service->catalogue()[$type] ?? null;

            if ($footprint === null) {
                throw new RuntimeException('Aucune découpe connue pour « ' . $type . ' » : à saisir à la main.');
            }

            $service->declare($type, (int) $footprint['w'], (int) $footprint['h'], $footprint['offsets']);
            setFlash('success', 'Découpe de « ' . $type . ' » déclarée.');
            break;

        case 'declare':
            $offsets = json_decode((string) ($_POST['offsets'] ?? ''), true);
            $roles = json_decode((string) ($_POST['roles'] ?? ''), true);

            if (!is_array($offsets)) {
                throw new RuntimeException('Les décalages doivent être un objet JSON, par exemple {"0":[0,0],"1":[0,-1]}.');
            }

            $service->declare(
                $type,
                (int) ($_POST['w'] ?? 1),
                (int) ($_POST['h'] ?? 1),
                $offsets,
                is_array($roles) ? $roles : []
            );
            setFlash('success', 'Découpe de « ' . $type . ' » enregistrée.');
            break;

        case 'forget':
            $service->forget($type);
            setFlash('success', 'Découpe de « ' . $type . ' » oubliée : elle sera de nouveau devinée.');
            break;

        default:
            throw new RuntimeException('Action inconnue : ' . $action);
    }
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/footprints.php');
