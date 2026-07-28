<?php
/**
 * Enregistre la forme et le passage d'un décor (admin → Cartes).
 *
 * Deux gestes, en PRG derrière un jeton CSRF :
 *
 * - `save`   — enregistre la figure telle qu'elle est réglée à l'écran. Elle
 *   cesse alors d'être devinée : reprendre un décor mal posé sur la carte ne
 *   la changera plus.
 * - `forget` — la retire ; la forme redevient celle que la carte ou l'image
 *   d'ensemble racontent.
 *
 * La figure arrive sérialisée dans un seul champ, telle que l'éditeur l'a
 * construite : `{family, w, h, offsets: {morceau: [dx, dy]}, blocked: [morceaux]}`.
 * Les cases qui barrent le chemin deviennent des rôles `block` ; les autres
 * restent au rôle par défaut du type, ce qui évite d'écrire une évidence
 * autant que de figer un défaut qui pourrait changer.
 *
 * Les deux gestes REPOSENT ensuite l'emprise des exemplaires déjà sur la
 * carte. Sans cela, corriger une figure ne corrigerait que les poses à venir,
 * et l'animateur repartirait convaincu d'avoir agi.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityCellService;
use App\Service\Map\EntityTypeFootprintService;

/**
 * Ce que la reprise des exemplaires posés a changé, dit à l'animateur.
 *
 * Corriger une figure sans reposer ce qui est déjà sur la carte laisserait la
 * correction invisible ; le taire laisserait croire qu'elle ne fait rien.
 */
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
