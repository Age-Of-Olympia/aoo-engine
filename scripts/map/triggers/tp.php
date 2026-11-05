<?php
use App\Service\Map\TriggerRequirements;
use App\Service\PlanAdminService;
use Classes\View;

/* params: "x,y,z,plan[,condition]". The explode limit keeps the condition
   whole, commas included. */
$coordsTbl = explode(',', $params, 5);

$need = trim((string) ($coordsTbl[4] ?? ''));

if ($need !== '' && !TriggerRequirements::met($player, $need)) {

    echo '<script>alert("'. TriggerRequirements::REFUSAL .'");</script>';

    exit();
}

$coords = (object) array();

$coords->x = (is_numeric($coordsTbl[0])) ? $coordsTbl[0] : $goCoords->x;
$coords->y = (is_numeric($coordsTbl[1])) ? $coordsTbl[1] : $goCoords->y;
$coords->z = (is_numeric($coordsTbl[2])) ? $coordsTbl[2] : $goCoords->z;
$coords->plan = ($coordsTbl[3] != 'plan') ? $coordsTbl[3] : $goCoords->plan;

/* A destination plan that does not exist would be created on the fly by the
   first coords lookup below: refuse the step and leave a trace instead. */
if (!(new PlanAdminService())->planExists((string) $coords->plan)) {

    error_log('[tp] déclencheur '. $triggerId .' vers un plan inconnu : '. $coords->plan);

    refuseStep('Ce téléporteur mène vers un plan inexistant (« '. $coords->plan .' »). Signalez ce bug à l\'équipe.');
}

$goCoords = $coords;

$coordsId = View::get_free_coords_id_arround($goCoords, 1, avoidElements: true);


\App\Service\Map\BoardChanges::cell($player->coords);
