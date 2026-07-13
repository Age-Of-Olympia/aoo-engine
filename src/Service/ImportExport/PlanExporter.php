<?php

namespace App\Service\ImportExport;

use App\Service\PlanConfigService;
use App\Service\TiledMapService;
use Classes\Db;
use InvalidArgumentException;

/**
 * Exporte un plan (carte locale) en payload à clés naturelles : l'identité
 * est le nom du plan, chaque case est portée par (x, y, z) — aucun id de
 * base, le bundle est portable entre environnements.
 *
 * Un payload = le fichier JSON du plan (verbatim), toutes ses coords (pour
 * préserver les niveaux z vides) et ses couches authorables. map_items
 * (loot runtime), les lignes construites par des joueurs et endTime sont
 * exclus — mêmes règles que l'export Tiled.
 *
 * Taille : un bundle est un fichier unique (pas de découpage façon mapcmd).
 * Un plan 200x200 pèse quelques Mo de JSON — dimensionné pour l'admin ;
 * si ça devient un problème, gzip côté endpoint avant de multi-partir.
 *
 * Contrairement aux autres familles, « exporter tout » est lourd : l'admin
 * propose surtout l'export unitaire via exportOne().
 */
final class PlanExporter implements ObjectExporter
{
    private ?Db $db;
    private ?TiledMapService $tiledMap;
    private ?PlanConfigService $planConfig;

    public function __construct(?Db $db = null, ?TiledMapService $tiledMap = null, ?PlanConfigService $planConfig = null)
    {
        // Lazy : l'instanciation ne doit pas ouvrir de connexion DB
        $this->db = $db;
        $this->tiledMap = $tiledMap;
        $this->planConfig = $planConfig;
    }

    public function objectType(): string
    {
        return 'plan';
    }

    public function exportAll(): array
    {
        $plans = array_keys(($this->tiledMap ??= new TiledMapService())->listPlans());
        sort($plans);

        return array_map(fn(string $plan): array => $this->exportOne($plan), $plans);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportOne(string $plan): array
    {
        $this->tiledMap ??= new TiledMapService();
        $this->planConfig ??= new PlanConfigService();

        return [
            'plan'   => $plan,
            'config' => $this->planConfig->readFull($plan),
            'coords' => $this->allCoords($plan),
            'layers' => $this->tiledMap->exportAllLayers($plan),
        ];
    }

    /**
     * Les plans n'ont pas d'entité Doctrine : l'export unitaire passe par
     * exportOne() (clé naturelle chaîne), pas par toArray().
     */
    public function toArray(object $entity): array
    {
        throw new InvalidArgumentException('PlanExporter : utiliser exportOne(string $plan).');
    }

    /**
     * Triplets compacts [x, y, z] — ~4x plus légers que des objets, et
     * porteurs des cases sans contenu (niveaux z vides mais existants).
     *
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function allCoords(string $plan): array
    {
        $res = ($this->db ??= new Db())->exe(
            'SELECT x, y, z FROM coords WHERE plan = ? ORDER BY z, y, x',
            array($plan)
        );

        $coords = [];
        while ($row = $res->fetch_assoc()) {
            $coords[] = [(int) $row['x'], (int) $row['y'], (int) $row['z']];
        }

        return $coords;
    }
}
