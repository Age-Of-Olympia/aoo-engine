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
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/layout.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\CsrfProtectionService;
use App\Service\Map\EntityTypeFootprintService;

$service = new EntityTypeFootprintService();

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);

    $type = trim((string) ($_POST['type'] ?? ''));

    if ($type === '') {
        throw new RuntimeException('Aucun décor indiqué.');
    }

    if (($_POST['action'] ?? '') === 'forget') {
        $service->forget($type);
        setFlash('success', 'La forme de « ' . $type . ' » sera de nouveau devinée.');
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

    setFlash('success', 'La forme de « ' . $type . ' » est enregistrée.');
} catch (\Throwable $e) {
    setFlash('danger', $e->getMessage());
}

redirectTo('/admin/footprints.php');
