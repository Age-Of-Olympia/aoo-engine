<?php
use Classes\AdminCommand;
use Classes\Argument;
use Classes\File;
use Classes\Player;
use App\Service\ImportExport\BundleEnvelope;
use App\Service\ImportExport\ImportReport;
use App\Service\ImportExport\PlanExporter;
use App\Service\ImportExport\PlanImporter;

/**
 * Saves and restores the plan the admin stands on, in the bundle format the
 * admin import/export screens use: a file written here imports from the
 * admin, and a bundle exported there loads here.
 *
 * Loading advances step by step and stops when its budget runs out: running
 * the same command again resumes where it stopped
 * ({@see \App\Service\ImportExport\PlanImportRun}).
 */
class MapCmd extends AdminCommand
{
    private const PATH = 'datas/private/maps/';

    /** Seconds of work per call: the console hands back before PHP's time limit. */
    private const BUDGET = 20;

    public function __construct() {
        parent::__construct("map", [new Argument('action', true), new Argument('name', true)]);
        parent::setDescription(<<<EOT
sauvegarde et restaure le plan courant (format bundle, comme l'import/export de l'admin)
Exemple:
> map (liste les sauvegardes)
> map save [nom] (sauvegarde le plan courant)
> map load [nom] (restaure le plan courant ; relancer la commande reprend un chargement interrompu)
EOT);
    }

    public function execute(array $argumentValues): string
    {
        if (!is_dir(self::PATH)) {
            mkdir(self::PATH, 0755, true);
        }

        $action = $argumentValues[0] ?? null;
        $name = $argumentValues[1] ?? null;

        if ($action === 'save') {
            return $this->save($name);
        }

        if ($action === 'load') {
            return $this->load($name);
        }

        return $this->listBundles();
    }

    private function listBundles(): string
    {
        $out = 'Sauvegardes disponibles :<br />';
        $found = 0;

        foreach (File::scan_dir(self::PATH) as $file) {
            if (!str_ends_with((string) $file, '.json')) {
                continue;
            }
            $out .= '- ' . htmlspecialchars(basename((string) $file, '.json')) . '<br />';
            $found++;
        }

        return $found === 0 ? 'Aucune sauvegarde dans ' . self::PATH : $out;
    }

    private function save(?string $name): string
    {
        if ($name === null) {
            return '<font color="orange">erreur : nom manquant, ex. "map save eryn_dolen"</font>';
        }

        $plan = $this->currentPlan();
        $bundle = BundleEnvelope::build('plan', [(new PlanExporter())->exportOne($plan)]);
        $file = self::PATH . $this->fileName($name);

        file_put_contents($file, BundleEnvelope::encode($bundle));

        return 'Plan « ' . htmlspecialchars($plan) . ' » sauvegardé dans ' . htmlspecialchars($file);
    }

    private function load(?string $name): string
    {
        if ($name === null) {
            return '<font color="orange">erreur : nom manquant, ex. "map load eryn_dolen"</font>';
        }

        $file = self::PATH . $this->fileName($name);
        if (!file_exists($file)) {
            return '<font color="orange">erreur : ' . htmlspecialchars($file) . ' introuvable</font>';
        }

        try {
            $parsed = BundleEnvelope::parse((string) file_get_contents($file));
        } catch (\Throwable $e) {
            return '<font color="orange">erreur : ' . htmlspecialchars($e->getMessage()) . '</font>';
        }

        if ($parsed->objectType !== 'plan') {
            return '<font color="orange">erreur : ce bundle contient des « ' . htmlspecialchars($parsed->objectType) . ' », pas des plans</font>';
        }

        $importer = new PlanImporter();
        $report = new ImportReport();
        $out = '';
        $deadline = microtime(true) + self::BUDGET;

        foreach ($parsed->objects as $object) {
            try {
                $payload = $importer->payloadFor($object);
            } catch (\Throwable $e) {
                $out .= '<font color="orange">' . htmlspecialchars($e->getMessage()) . '</font><br />';
                continue;
            }

            $run = $importer->runFor($payload, $report);
            $out .= ($run->resumed() ? 'Reprise' : 'Chargement') . ' de « ' . htmlspecialchars($run->plan())
                . ' » : étape ' . $run->step() . '/' . $run->total() . '<br />';

            while (!$run->isDone() && microtime(true) < $deadline) {
                $label = $run->label();
                $run->next();
                $out .= '- ' . htmlspecialchars($label) . ' (' . $run->step() . '/' . $run->total() . ')<br />';
            }

            $out .= $run->isDone()
                ? '<b>' . htmlspecialchars($run->plan()) . ' : terminé.</b><br />'
                : '<font color="orange">Interrompu à l\'étape ' . $run->step() . '/' . $run->total()
                    . ' — relancez « map load ' . htmlspecialchars((string) $name) . ' » pour continuer.</font><br />';

            if (!$run->isDone()) {
                break;
            }
        }

        foreach ($report->warnings() as $warning) {
            $out .= '<font color="orange">' . htmlspecialchars($warning['name'] . ' — ' . $warning['message']) . '</font><br />';
        }

        return $out;
    }

    /** The plan the admin stands on. */
    private function currentPlan(): string
    {
        $player = new Player($_SESSION['playerId']);
        $player->getCoords();

        return (string) $player->coords->plan;
    }

    private function fileName(string $name): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '', $name) . '.json';
    }
}
