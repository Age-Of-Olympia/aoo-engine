<?php
/** Biome admin mutations (POST only). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\Map\BiomeAdminService;
use App\Service\Map\BiomeBrushService;

(new AdminMenuAccessService())->enforce('maps.biomes');

$filter = (string) ($_POST['plan_filter'] ?? '');
$back = array_filter(['plan' => $filter, 'edit' => (string) ($_POST['edit'] ?? ''), 'palette' => (string) ($_POST['palette'] ?? '')]);
$anchor = isset($back['palette']) ? '#palette' : (isset($back['edit']) ? '#populations' : '');
$redirect = '/admin/biomes.php' . ($back !== [] ? '?' . http_build_query($back) : '') . $anchor;

handleAdminPost($redirect, static function (): string {
    $service = new BiomeAdminService();
    $id = (int) ($_POST['id'] ?? 0);

    switch ((string) ($_POST['action'] ?? '')) {
        case 'save_type':
            $service->saveType(
                $id,
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['label'] ?? ''),
                (string) ($_POST['color'] ?? ''),
                (int) ($_POST['patch_scale'] ?? BiomeAdminService::DEFAULT_PATCH_SCALE)
            );
            return 'Type enregistré.';
        case 'save_palette':
            $shares = [];
            foreach ((array) ($_POST['terrain'] ?? []) as $line => $terrain) {
                $terrain = (string) $terrain;
                if ($terrain === '') {
                    continue;
                }
                if (isset($shares[$terrain])) {
                    throw new InvalidArgumentException('Le terrain ' . $terrain . ' figure deux fois dans la palette.');
                }
                $shares[$terrain] = (int) ($_POST['share'][$line] ?? 0);
            }
            $service->saveGrounds($id, $shares);
            return 'Palette enregistrée.';
        case 'delete_type':
            $service->deleteType($id);
            return 'Type supprimé.';
        case 'save_biome':
            $saved = $service->saveBiome(
                $id,
                (string) ($_POST['plan'] ?? ''),
                (int) ($_POST['type_id'] ?? 0),
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['zone'] ?? ''),
                (int) ($_POST['stack_order'] ?? 0),
                ($_POST['seed'] ?? '') === '' ? null : (int) $_POST['seed']
            );
            return 'Biome enregistré : ' . $saved['cells'] . ' case(s).';
        case 'save_population':
            $service->savePopulation(
                $id,
                (int) ($_POST['race_id'] ?? 0),
                (int) ($_POST['chance'] ?? 0),
                (float) str_replace(',', '.', (string) ($_POST['density'] ?? 0)),
                (int) ($_POST['z_min'] ?? 0),
                (int) ($_POST['z_max'] ?? 0)
            );
            return 'Pousse enregistrée.';
        case 'delete_population':
            $service->deletePopulation((int) ($_POST['population_id'] ?? 0));
            return 'Pousse retirée.';
        case 'reseed_biome':
            $seed = (new BiomeBrushService())->reseed($id);
            return 'Nouvelle graine : ' . $seed . '. Repeignez le biome avec le pinceau de biome du Tiled de l\'admin pour voir le nouveau dessin.';
        case 'delete_biome':
            $service->deleteBiome($id);
            return 'Biome supprimé.';
    }

    throw new InvalidArgumentException('Action inconnue.');
});
