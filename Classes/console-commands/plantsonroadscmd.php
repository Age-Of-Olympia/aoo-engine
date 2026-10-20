<?php
use Classes\Command;
use Classes\Argument;
use App\Service\PlantsService;

/**
 * Plants that grew on a road, turned back into their seed on the same cell.
 */
class PlantsOnRoadsCmd extends Command
{
    public function __construct()
    {
        parent::__construct('plants-on-roads', [new Argument('action', true)]);
        parent::setDescription(<<<EOT
Remplace les plantes poussées sur une route par leur graine, posée au sol.
Exemple:
> plants-on-roads        (état)
> plants-on-roads fix    (applique le remplacement)
EOT);
    }

    public function accessKey(array $argumentValues): ?string
    {
        return 'console:plants-on-roads';
    }

    public function execute(array $argumentValues): string
    {
        $apply = strtolower(trim((string) ($argumentValues[0] ?? ''))) === 'fix';
        $r = PlantsService::seedBackFromRoads($apply);

        if ($r['plants'] === 0) {
            return 'Aucune plante sur une route.';
        }

        $summary = $r['plants'] . ' plante(s) sur une route : ' . $r['seeded'] . ' avec graine, '
            . $r['removed'] . ' sans graine connue (simplement retirée(s)).';

        return $apply ? 'Fait. ' . $summary : $summary . '<br />Appliquer : plants-on-roads fix';
    }
}
