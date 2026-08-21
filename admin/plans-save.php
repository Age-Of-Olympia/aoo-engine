<?php
/**
 * Gestion des plans — mutations (POST only). Pendant de admin/plans.php.
 *
 * Routé sur ?action : create (vierge ou clonage) | update (config +
 * niveaux Z) | delete (bilan préalable et garde-fous côté
 * PlanAdminService : un joueur réel sur le plan bloque toujours ; PNJ et
 * logs exigent le forçage explicite) | rename | clear | delete-z.
 *
 * Les trois opérations destructives (delete, rename, clear) exigent une
 * DOUBLE validation : confirm() côté client ET le code du plan retapé
 * dans confirm_code, vérifié ici — un clic malheureux ne suffit pas.
 *
 * CSRF validé ; même niveau d'accès que le menu plans.php pour qu'un POST
 * direct ne contourne rien. Redirige (PRG) avec un flash.
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/helpers.php');

use App\Service\AdminMenuAccessService;
use App\Service\CsrfProtectionService;
use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\TiledMapService;
use Classes\Db;

(new AdminMenuAccessService())->enforce('plans.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('/admin/plans.php');
}

try {
    (new CsrfProtectionService())->validateTokenOrFail($_POST['csrf_token'] ?? null);
} catch (\Throwable $e) {
    setFlash('warning', 'Jeton de sécurité invalide ou expiré. Rechargez la page et réessayez.');
    redirectTo('/admin/plans.php');
}

$action = $_GET['action'] ?? '';
$plan = strtolower(trim((string) ($_POST['plan'] ?? '')));

if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan)) {
    setFlash('warning', 'Code de plan invalide (minuscules, chiffres, _ ou -, 64 max).');
    redirectTo('/admin/plans.php' . ($action === 'create' ? '?action=new' : ''));
}

$service = new PlanAdminService();

if ($action === 'create') {
    // name/shortName : seules les clés non vides sont écrites (convention
    // PlanConfigService::parse — '' retirerait la clé)
    $config = [];
    foreach (['name', 'shortName'] as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        if ($value !== '') {
            $config[$key] = $value;
        }
    }

    $mode = (string) ($_POST['mode'] ?? 'blank');

    try {
        if ($mode === 'clone') {
            $template = strtolower(trim((string) ($_POST['template'] ?? '')));
            if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $template)) {
                throw new RuntimeException('Choisissez un plan modèle.', 400);
            }
            $report = $service->clonePlan($template, $plan, $config);
            $layerTotal = array_sum($report['layers']);
            setFlash('success', "Plan « {$plan} » cloné depuis « {$template} » : {$report['coords']} case(s), "
                . "{$layerTotal} ligne(s) de couches, fichier JSON copié.");
        } else {
            $service->createBlankPlan($plan, $config);
            setFlash('success', "Plan « {$plan} » créé : fichier JSON et case d'amorce (0,0,0)."
                . ' Le contenu s\'authore via l\'extension Tiled.');
        }
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/plans.php?action=new');
    }

    redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
}

if ($action === 'update') {
    $posted = (array) ($_POST['config'] ?? []);
    $config = [];
    foreach (array_keys(PlanConfigService::PLAN_CONFIG_KEYS) as $key) {
        $config[$key] = (string) ($posted[$key] ?? '');
    }

    $configService = new PlanConfigService();

    try {
        // Tout valider avant d'écrire quoi que ce soit (parse ≠ write), y
        // compris les bornes des niveaux Z — un 400 ne laisse rien à moitié
        $parsed = $configService->parse($config);

        $zLevels = [];
        foreach ((array) ($_POST['z'] ?? []) as $z => $row) {
            $bounds = trim((string) ($row['bounds'] ?? 'auto'));
            // Pré-validation des bornes (writeZLevel les parse À l'écriture :
            // une erreur après write() laisserait la config à moitié posée)
            if ($bounds !== '' && strtolower($bounds) !== 'auto') {
                $parts = array_map('trim', explode(',', $bounds));
                if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
                    throw new RuntimeException(
                        'Bornes du niveau z=' . (int) $z . ' invalides (attendu « minX,maxX,minY,maxY » ou « auto ») : ' . $bounds,
                        400
                    );
                }
            }

            $zLevels[(int) $z] = [
                'name'           => (string) ($row['name'] ?? ''),
                'mapUnavailable' => isset($row['mapUnavailable']) ? 'true' : 'false',
                'chestsAllowed'  => isset($row['chestsAllowed']) ? 'true' : 'false',
                'bounds'         => $bounds,
            ];
        }

        $configService->write($plan, $parsed);
        foreach ($zLevels as $z => $zConfig) {
            // bounds explicites honorées ; « auto » = recalcul au prochain
            // push Tiled (null : pas d'étendue calculée ici)
            $configService->writeZLevel($plan, $z, $zConfig, null);
        }
    } catch (\RuntimeException $e) {
        setFlash('warning', $e->getMessage());
        redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
    }

    $health = $configService->validate($plan, new Db());
    $notice = '';
    if ($health['errors'] !== [] || $health['warnings'] !== []) {
        $notice = ' ⚠ Validation : ' . count($health['errors']) . ' erreur(s), '
            . count($health['warnings']) . ' avertissement(s) — détail en bas de page.';
    }

    setFlash('success', "Configuration du plan « {$plan} » enregistrée." . $notice);
    redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
}

/* Double validation des opérations destructives : le code du plan doit
 * être RETAPÉ (confirm_code) — la boîte confirm() du navigateur ne
 * suffit pas (c'est le garde-fou demandé après l'épisode « tout péter »). */
$needsTypedCode = in_array($action, ['delete', 'rename', 'clear'], true);
if ($needsTypedCode && strtolower(trim((string) ($_POST['confirm_code'] ?? ''))) !== $plan) {
    setFlash('warning', "Confirmation refusée : retapez le code du plan (« {$plan} ») dans le champ de confirmation.");
    redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
}

if ($action === 'rename') {
    $to = strtolower(trim((string) ($_POST['new_plan'] ?? '')));
    try {
        $report = $service->renamePlan($plan, $to);
        $refs = [];
        foreach ($report['references'] as $table => $n) {
            $refs[] = "{$table} ×{$n}";
        }
        if ($report['teleports'] > 0) {
            $refs[] = 'téléporteurs ×' . $report['teleports'];
        }
        if ($report['files'] !== []) {
            $refs[] = count($report['files']) . ' fichier(s)';
        }
        setFlash('success', "Plan « {$plan} » renommé en « {$to} » : {$report['coords']} case(s)"
            . ($refs !== [] ? ' — ' . implode(', ', $refs) : '') . '.');
        redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($to));
    } catch (\RuntimeException $e) {
        setFlash('warning', str_replace("\n", ' ', $e->getMessage()));
        redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
    }
}

if ($action === 'clear') {
    try {
        $report = $service->clearPlanCoords($plan, isset($_POST['force']));
        $layerTotal = array_sum($report['layers']) + $report['map_items'];
        setFlash('success', "Plan « {$plan} » vidé : {$report['coords']} case(s), {$layerTotal} ligne(s) de couches"
            . ($report['npcs'] > 0 ? ' — ' . $report['npcs'] . ' PNJ supprimé(s)' : '')
            . '. La configuration JSON est conservée.');
    } catch (\RuntimeException $e) {
        setFlash('warning', str_replace("\n", ' ', $e->getMessage()));
    }
    redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
}

if ($action === 'delete-z') {
    $z = (int) ($_POST['z'] ?? 0);
    try {
        $report = $service->deleteZLevel($plan, $z);
        $layerTotal = array_sum($report['layers']) + $report['map_items'];
        setFlash('success', "Niveau z{$z} du plan « {$plan} » supprimé : {$report['coords']} case(s), {$layerTotal} ligne(s) de couches.");
    } catch (\RuntimeException $e) {
        setFlash('warning', str_replace("\n", ' ', $e->getMessage()));
    }
    redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
}

if ($action === 'delete') {
    try {
        $report = $service->deletePlan($plan, isset($_POST['force']));
        $layerTotal = array_sum($report['layers']) + $report['map_items'];
        $extras = [];
        if ($report['npcs'] > 0) {
            $extras[] = $report['npcs'] . ' PNJ supprimé(s)';
        }
        if ($report['logs_detached'] > 0) {
            $extras[] = $report['logs_detached'] . ' log(s) détaché(s)';
        }
        if ($report['files'] !== []) {
            $extras[] = count($report['files']) . ' fichier(s) supprimé(s)';
        }
        setFlash('success', "Plan « {$plan} » supprimé : {$report['coords']} case(s), {$layerTotal} ligne(s) de couches"
            . ($extras !== [] ? ' — ' . implode(', ', $extras) : '') . '.');
    } catch (\RuntimeException $e) {
        setFlash('warning', str_replace("\n", ' ', $e->getMessage()));
        redirectTo('/admin/plans.php?action=edit&plan=' . urlencode($plan));
    }

    redirectTo('/admin/plans.php');
}

setFlash('warning', 'Action inconnue.');
redirectTo('/admin/plans.php');
