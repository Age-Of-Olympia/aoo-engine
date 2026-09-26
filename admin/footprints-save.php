<?php
/**
 * Save or drop a scenery family's shape (admin → Cartes). CSRF + PRG.
 *
 * The figure arrives serialised as the editor built it:
 * `{family, w, h, offsets: {piece: [dx, dy]}, roles: {piece: role}}`, each
 * role one of the four the editor paints (cover, fence, wall, screen).
 *
 * Both gestures re-spread the instances already on the map, otherwise a
 * correction would only apply to future placements.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityCellService;
use App\Service\Map\EntityTypeFootprintService;
use App\Service\Map\SceneryObjectService;

/** How many placed instances were taken up, said back to the game master. */
function reapplied(int $count): string
{
    return $count === 0
        ? ' Aucun exemplaire posé sur la carte.'
        : ' ' . $count . ' exemplaire' . ($count > 1 ? 's' : '') . ' mis à jour sur la carte.';
}

$service = new EntityTypeFootprintService();

/* Back where the form came from: a type's own page, a kind's, or the whole list. */
$back = (string) ($_POST['back'] ?? '');
$back = preg_match('/^\?(type=[^&]*|kind=[a-z]+)$/', $back) ? $back : '';

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    if (($_POST['action'] ?? '') === 'remove') {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $removed = (new SceneryObjectService())->removeEntities($ids);

        setFlash($removed === 0 ? 'warning' : 'success', $removed === 0
            ? 'Aucun décor coché.'
            : $removed . ' décor' . ($removed > 1 ? 's retirés' : ' retiré') . ' de la carte.');

        redirectTo('/admin/footprints.php' . $back);
    }

    $type = trim((string) ($_POST['type'] ?? ''));

    if ($type === '') {
        throw new RuntimeException('Aucun type indiqué.');
    }

    $cells = new EntityCellService();

    if (($_POST['action'] ?? '') === 'forget') {
        $service->forget($type);

        setFlash('success', 'La forme de « ' . $type . ' » sera de nouveau calculée.'
            . reapplied($cells->reapplyForType($type)));

        redirectTo('/admin/footprints.php' . $back);
    }

    $figure = json_decode((string) ($_POST['figure'] ?? ''), true);

    if (!is_array($figure) || !isset($figure['offsets']) || !is_array($figure['offsets'])) {
        throw new RuntimeException('Figure illisible.');
    }

    $offsets = [];

    foreach ($figure['offsets'] as $piece => $offset) {
        if (is_array($offset) && count($offset) === 2) {
            $offsets[(int) $piece] = [(int) $offset[0], (int) $offset[1]];
        }
    }

    // Every piece gets the state the editor showed: no cell is left to a family default
    $painted = [EntityCellService::ROLE_COVER, EntityCellService::ROLE_FENCE, EntityCellService::ROLE_WALL, EntityCellService::ROLE_SCREEN];
    $roles = [];

    foreach (array_keys($offsets) as $piece) {
        $role = (string) ($figure['roles'][$piece] ?? '');
        $roles[$piece] = in_array($role, $painted, true) ? $role : EntityCellService::ROLE_COVER;
    }

    $service->declare($type, (int) ($figure['w'] ?? 1), (int) ($figure['h'] ?? 1), $offsets, $roles);

    // A cut-out saved without a type would have no effect
    $created = (new SceneryObjectService())->ensureType($type);

    setFlash('success', 'La forme de « ' . $type . ' » est enregistrée.'
        . ($created ? ' Type créé au catalogue.' : '')
        . reapplied($cells->reapplyForType($type)));
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/footprints.php' . $back);
