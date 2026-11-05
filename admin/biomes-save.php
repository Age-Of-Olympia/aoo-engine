<?php
/** Biome admin mutations (POST only). */
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\Map\BiomeAdminService;

(new AdminMenuAccessService())->enforce('maps.biomes');

$filter = (string) ($_POST['plan_filter'] ?? '');
$redirect = '/admin/biomes.php' . ($filter !== '' ? '?plan=' . urlencode($filter) : '');

handleAdminPost($redirect, static function (): string {
    $service = new BiomeAdminService();
    $id = (int) ($_POST['id'] ?? 0);

    switch ((string) ($_POST['action'] ?? '')) {
        case 'save_type':
            $service->saveType($id, (string) ($_POST['name'] ?? ''), (string) ($_POST['label'] ?? ''), (string) ($_POST['color'] ?? ''));
            return 'Type enregistré.';
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
                (int) ($_POST['stack_order'] ?? 0)
            );
            return 'Biome enregistré : ' . $saved['cells'] . ' case(s).';
        case 'delete_biome':
            $service->deleteBiome($id);
            return 'Biome supprimé.';
    }

    throw new InvalidArgumentException('Action inconnue.');
});
