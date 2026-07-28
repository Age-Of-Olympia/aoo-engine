<?php
use Classes\Command;
use Classes\Argument;
use App\Service\Map\EntityCellService;

/**
 * L'emprise des entités — état et réparation.
 *
 * `entity_cells` double `players.coords_id` en attendant de la remplacer. Tant
 * que rien ne la lit (L3 la pose, L4 la branchera), une dérive ne casse rien
 * à l'écran — mais elle ferait démarrer la suite d'une carte fausse, sans
 * que personne s'en aperçoive.
 *
 * Cette commande est le filet : elle dit combien d'ancres divergent, et les
 * remet d'aplomb.
 */
class EntityCellsCmd extends Command
{
    public function __construct()
    {
        parent::__construct('entity-cells', [new Argument('action', true)]);
        parent::setDescription(<<<EOT
État et réparation de l'emprise des entités (table entity_cells).
Exemple:
> entity-cells            (état : emprises manquantes ou divergentes)
> entity-cells repair     (remet les cases d'aplomb)
> entity-cells convert    (donne une entité au décor posé sans elle)
EOT);
    }

    public function execute(array $argumentValues): string
    {
        $service = new EntityCellService();
        $action = strtolower(trim((string) ($argumentValues[0] ?? 'status')));

        if ($action === 'convert') {
            $converted = (new \App\Service\Map\SceneryObjectService())->convertOrphans();

            return $converted === 0
                ? 'Aucun décor sans entité.'
                : $converted . ' décor(s) devenu(s) entité(s) — leurs cases portent enfin leur rôle.';
        }

        $drift = $service->drift();

        if ($action !== 'repair') {
            if ($drift === []) {
                return 'Emprises : toutes les ancres correspondent à players.coords_id.';
            }

            $lines = ['Emprises : ' . count($drift) . ' ancre(s) divergente(s).'];

            foreach (array_slice($drift, 0, 20) as $row) {
                $lines[] = sprintf(
                    '  entité %-8s attendue case %-8s trouvée %s',
                    $row['player_id'],
                    $row['expected'],
                    $row['actual'] === null ? 'AUCUNE' : $row['actual']
                );
            }

            if (count($drift) > 20) {
                $lines[] = '  … et ' . (count($drift) - 20) . ' autre(s).';
            }

            $lines[] = 'Réparer : entity-cells repair';

            return implode('<br />', $lines);
        }

        if ($drift === []) {
            return 'Rien à réparer.';
        }

        $repaired = $service->reconcile();

        return $repaired . ' ancre(s) remise(s) d\'aplomb sur ' . count($drift) . ' divergente(s).';
    }
}
