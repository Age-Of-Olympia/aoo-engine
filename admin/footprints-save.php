<?php
/**
 * Save or drop a scenery family's shape (admin → Cartes). CSRF + PRG.
 *
 * The figure arrives serialised as the editor built it:
 * `{family, w, h, offsets: {piece: [dx, dy]}, blocked: [pieces]}`. Blocking
 * cells become `block` roles; the rest keep the type's default.
 *
 * Both gestures re-spread the instances already on the map, otherwise a
 * correction would only apply to future placements.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityCellService;
use App\Service\Map\EntityTypeFootprintService;

/** How many placed instances were taken up, said back to the game master. */
function reapplied(int $count): string
{
    return $count === 0
        ? ' Aucun exemplaire posé sur la carte.'
        : ' ' . $count . ' exemplaire' . ($count > 1 ? 's' : '') . ' repris sur la carte.';
}

$service = new EntityTypeFootprintService();

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $type = trim((string) ($_POST['type'] ?? ''));

    if ($type === '') {
        throw new RuntimeException('Aucun décor indiqué.');
    }

    $cells = new EntityCellService();

    if (($_POST['action'] ?? '') === 'forget') {
        $service->forget($type);

        setFlash('success', 'La forme de « ' . $type . ' » sera de nouveau devinée.'
            . reapplied($cells->reapplyForType($type)));

        redirectTo('/admin/footprints.php');
    }

    $figure = json_decode((string) ($_POST['figure'] ?? ''), true);

    if (!is_array($figure) || !isset($figure['offsets']) || !is_array($figure['offsets'])) {
        throw new RuntimeException('La figure envoyée est illisible.');
    }

    $offsets = [];

    foreach ($figure['offsets'] as $piece => $offset) {
        if (is_array($offset) && count($offset) === 2) {
            $offsets[(int) $piece] = [(int) $offset[0], (int) $offset[1]];
        }
    }

    $roles = [];

    foreach ($figure['blocked'] ?? [] as $piece) {
        if (isset($offsets[(int) $piece])) {
            $roles[(int) $piece] = 'block';
        }
    }

    $service->declare($type, (int) ($figure['w'] ?? 1), (int) ($figure['h'] ?? 1), $offsets, $roles);

    setFlash('success', 'La forme de « ' . $type . ' » est enregistrée.'
        . reapplied($cells->reapplyForType($type)));
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/footprints.php');
