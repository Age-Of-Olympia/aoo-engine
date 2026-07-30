<?php

namespace App\Service\ImportExport;

use App\Service\PlanAdminService;
use App\Service\PlanConfigService;
use App\Service\Map\ResourceReconciler;
use App\Service\Map\StructureTypeService;
use App\Service\TiledMapService;
use Classes\Db;
use RuntimeException;
use Throwable;

/**
 * Importe des bundles de plans ({@see PlanExporter}) en create-or-replace :
 * un plan absent est créé, un plan existant est remplacé — mais seulement
 * son contenu authoré. Les lignes construites par des joueurs (player_id)
 * et map_items (loot runtime) ne sont jamais touchées, les coords
 * existantes sont conservées (les FK qui les visent — joueurs, logs —
 * restent valides), les manquantes sont créées.
 *
 * Implémente ObjectImporter directement plutôt que d'étendre
 * AbstractObjectImporter : le squelette abstrait transactionne l'EntityManager
 * Doctrine alors que les écritures map_* passent par Classes\Db (mysqli) —
 * la transaction doit vivre sur la même connexion que les écritures.
 *
 * Le fichier JSON du plan est remplacé APRÈS le commit : une base importée
 * sans JSON se répare en réimportant, l'inverse non.
 */
final class PlanImporter implements ObjectImporter
{
    /** Taille des lots d'INSERT multi-lignes (précédent : mapcmd.php). */
    private const INSERT_BATCH = 500;

    /**
     * La couche dont les lignes sont devenues des entités.
     *
     * Elle ne suit plus le chemin générique purge + INSERT : ses objets ont
     * une identité et un état qu'un remplacement jetterait.
     */
    private const ENTITY_LAYER = 'resources';

    private ?Db $db;
    private ?PlanConfigService $planConfig;
    private ?PlanAdminService $planAdmin;

    public function __construct(?Db $db = null, ?PlanConfigService $planConfig = null, ?PlanAdminService $planAdmin = null)
    {
        // Lazy : l'instanciation ne doit pas ouvrir de connexion DB
        $this->db = $db;
        $this->planConfig = $planConfig;
        $this->planAdmin = $planAdmin;
    }

    public function objectType(): string
    {
        return 'plan';
    }

    public function preview(array $objects): ImportReport
    {
        $report = new ImportReport();
        $this->collect($objects, $report);

        return $report;
    }

    public function import(array $objects): ImportReport
    {
        $report = new ImportReport();
        $payloads = $this->collect($objects, $report);

        // Tout-ou-rien : un seul rejet et le lot entier reste non écrit
        if ($report->hasRejections()) {
            return $report;
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            foreach ($payloads as $payload) {
                $this->applyPayload($payload, $report);
            }
            $db->commit();
        } catch (Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }

        // JSON après commit (les fichiers ne se rollbackent pas)
        foreach ($payloads as $payload) {
            if (is_array($payload['config'])) {
                ($this->planConfig ??= new PlanConfigService())->replace($payload['plan'], $payload['config']);
            }
        }

        return $report;
    }

    /**
     * Valide et classe chaque payload (create/update/reject/warn) sans rien
     * écrire — même squelette que AbstractObjectImporter::collect().
     *
     * @param array<int, mixed> $objects
     * @return list<array{plan: string, config: ?array, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>}>
     */
    private function collect(array $objects, ImportReport $report): array
    {
        $payloads = [];
        $seen = [];

        foreach ($objects as $index => $object) {
            $label = is_array($object) && is_string($object['plan'] ?? null)
                ? $object['plan']
                : 'objet #' . $index;

            try {
                $payload = $this->validate($object);
            } catch (RuntimeException $e) {
                $report->reject($label, $e->getMessage());
                continue;
            }

            if (isset($seen[$payload['plan']])) {
                $report->reject($payload['plan'], 'Doublon : « ' . $payload['plan'] . ' » apparaît plusieurs fois dans le lot.');
                continue;
            }
            $seen[$payload['plan']] = true;

            $this->classify($payload, $report);
            $payloads[] = $payload;
        }

        return $payloads;
    }

    /**
     * @return array{plan: string, config: ?array, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>}
     * @throws RuntimeException message utilisateur (français)
     */
    private function validate(mixed $object): array
    {
        if (!is_array($object)) {
            throw new RuntimeException('Le payload doit être un objet.');
        }

        $plan = $object['plan'] ?? null;
        if (!is_string($plan) || !preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan)) {
            throw new RuntimeException('Nom de plan invalide (attendu : minuscules, chiffres, _ ou -, 64 max).');
        }

        $config = $object['config'] ?? null;
        if ($config !== null && !is_array($config)) {
            throw new RuntimeException('« config » doit être le JSON du plan (objet) ou null.');
        }

        $coords = [];
        foreach ((array) ($object['coords'] ?? []) as $triple) {
            if (!is_array($triple) || count($triple) !== 3
                || !is_numeric($triple[0]) || !is_numeric($triple[1]) || !is_numeric($triple[2])
            ) {
                throw new RuntimeException('« coords » doit être une liste de triplets [x, y, z].');
            }
            $coords[] = [(int) $triple[0], (int) $triple[1], (int) $triple[2]];
        }

        $layers = $object['layers'] ?? [];
        if (!is_array($layers)) {
            throw new RuntimeException('« layers » doit être un objet couche => lignes.');
        }
        // Bundles exportés avant le renommage map_walls → map_resources
        $layers = TiledMapService::normalizeLegacyLayerKeys($layers);
        foreach ($layers as $layer => $rows) {
            if (!isset(TiledMapService::AUTHORABLE_LAYERS[$layer])) {
                throw new RuntimeException('Couche inconnue : ' . $layer);
            }
            if (!is_array($rows)) {
                throw new RuntimeException('Les lignes de la couche ' . $layer . ' doivent être une liste.');
            }
            foreach ($rows as $row) {
                TiledMapService::validateIncomingRow($layer, $row);
                if (!isset($row['z']) || !is_numeric($row['z'])) {
                    throw new RuntimeException('Ligne sans z dans la couche ' . $layer . ' en ' . $row['x'] . ',' . $row['y']);
                }
            }
        }
        // Couche absente du bundle = couche vide : le bundle porte l'état complet
        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            $layers[$layer] ??= [];
        }

        return ['plan' => $plan, 'config' => $config, 'coords' => $coords, 'layers' => $layers];
    }

    /** @param array{plan: string, layers: array<string, array>} $payload */
    private function classify(array $payload, ImportReport $report): void
    {
        $plan = $payload['plan'];
        $planAdmin = $this->planAdmin ??= new PlanAdminService();

        if (!$planAdmin->planExists($plan)) {
            $report->addCreated($plan);
            return;
        }

        $report->addUpdated($plan);

        $characters = $planAdmin->countCharactersOnPlan($plan);
        if ($characters['players'] > 0) {
            $report->warn($plan, $characters['players'] . ' joueur(s) se trouvent sur ce plan — son contenu va changer sous leurs pieds.');
        }

        $playerBuilt = $this->countPlayerBuiltRows($plan);
        if ($playerBuilt > 0) {
            $report->warn($plan, $playerBuilt . ' construction(s) de joueurs sur ce plan (préservées, hors import).');
        }
    }

    /**
     * Remplace le contenu authoré d'un plan par celui du payload, dans la
     * transaction ouverte par import().
     *
     * @param array{plan: string, coords: list<array{0:int,1:int,2:int}>, layers: array<string, list<array<string, mixed>>>} $payload
     */
    private function applyPayload(array $payload, ImportReport $report): void
    {
        $plan = $payload['plan'];
        $db = $this->db();

        // 1. Purge du contenu authoré (les lignes joueur restent)
        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            if ($layer === self::ENTITY_LAYER) {
                continue;
            }

            $playerFilter = in_array('player_id', TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'], true)
                ? ' AND (m.player_id IS NULL OR m.player_id = 0)'
                : '';
            $db->exe(
                'DELETE m FROM map_' . $layer . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?' . $playerFilter,
                array($plan)
            );
        }

        // 2. Coords : celles du payload + celles des lignes, création en lots
        $needed = [];
        foreach ($payload['coords'] as [$x, $y, $z]) {
            $needed[$x . '|' . $y . '|' . $z] = [$x, $y, $z];
        }
        foreach ($payload['layers'] as $rows) {
            foreach ($rows as $row) {
                $key = (int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z'];
                $needed[$key] ??= [(int) $row['x'], (int) $row['y'], (int) $row['z']];
            }
        }

        $coordsIds = $this->loadCoordsIds($plan);
        $missing = array_diff_key($needed, $coordsIds);
        foreach (array_chunk(array_values($missing), self::INSERT_BATCH) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '(?, ?, ?, ?)'));
            $params = [];
            foreach ($chunk as [$x, $y, $z]) {
                array_push($params, $x, $y, $z, $plan);
            }
            $db->exe('INSERT INTO coords (x, y, z, plan) VALUES ' . $placeholders, $params);
        }
        if ($missing !== []) {
            $coordsIds = $this->loadCoordsIds($plan);
        }

        // 3. Insertion des couches en lots
        foreach ($payload['layers'] as $layer => $rows) {
            if ($layer === self::ENTITY_LAYER) {
                continue;
            }

            $this->insertLayerRows($layer, $rows, $coordsIds);
        }

        /* 4. Les ressources sont des entités : on COMPARE au lieu de remplacer.
         * Une ressource que le bundle redessine à l'identique garde son id et
         * son état — épuisée, elle le reste et repousse à son heure. Le
         * réconciliateur écrit sur la connexion Doctrine, qui est celle que
         * Classes\Db enveloppe : même transaction, même rollback. */
        $resources = (new ResourceReconciler())->reconcile($plan, $payload['layers'][self::ENTITY_LAYER] ?? []);

        if ($resources['unknown'] !== []) {
            $report->warn(
                $plan,
                'Types absents du catalogue, non posés : ' . implode(', ', $resources['unknown']) . '.'
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, int>         $coordsIds "x|y|z" => id
     */
    private function insertLayerRows(string $layer, array $rows, array $coordsIds): void
    {
        if ($rows === []) {
            return;
        }

        // Colonnes uniformes par couche (INSERT multi-lignes) : les extras
        // portables de AUTHORABLE_LAYERS, avec les défauts du schéma
        $extras = array_values(array_filter(
            TiledMapService::AUTHORABLE_LAYERS[$layer]['columns'],
            fn(string $column) => $column !== 'player_id' && $column !== 'endTime'
        ));

        $columnSql = '(coords_id, `name`' . ($extras !== [] ? ', `' . implode('`, `', $extras) . '`' : '') . ')';
        $rowPlaceholder = '(' . implode(', ', array_fill(0, 2 + count($extras), '?')) . ')';

        foreach (array_chunk($rows, self::INSERT_BATCH) as $chunk) {
            $params = [];
            foreach ($chunk as $row) {
                $params[] = $coordsIds[(int) $row['x'] . '|' . (int) $row['y'] . '|' . (int) $row['z']];
                $params[] = (string) $row['name'];
                foreach ($extras as $column) {
                    $params[] = $this->extraValue($layer, $column, $row);
                }
            }
            $this->db()->exe(
                'INSERT INTO map_' . $layer . ' ' . $columnSql . ' VALUES '
                    . implode(', ', array_fill(0, count($chunk), $rowPlaceholder)),
                $params
            );
        }
    }

    /** Valeur portable d'une colonne extra, défauts alignés sur le schéma / insertRow(). */
    private function extraValue(string $layer, string $column, array $row): int|string
    {
        if ($column === 'damages') {
            // Même défaut authoré que TiledMapService::insertRow() : -1
            // (récoltable) pour les ressources du catalogue, sinon 0
            return isset($row['damages']) && is_numeric($row['damages'])
                ? (int) $row['damages']
                : (StructureTypeService::isHarvestable((string) $row['name']) ? -1 : 0);
        }
        if ($column === 'foreground') {
            return isset($row['foreground']) && is_numeric($row['foreground']) ? (int) $row['foreground'] : 0;
        }

        // params (plants, triggers, dialogs)
        return (string) ($row[$column] ?? '');
    }

    /** @return array<string, int> "x|y|z" => coords_id du plan entier */
    private function loadCoordsIds(string $plan): array
    {
        $res = $this->db()->exe('SELECT id, x, y, z FROM coords WHERE plan = ?', array($plan));

        $coordsIds = [];
        while ($row = $res->fetch_assoc()) {
            $coordsIds[$row['x'] . '|' . $row['y'] . '|' . $row['z']] = (int) $row['id'];
        }

        return $coordsIds;
    }

    private function countPlayerBuiltRows(string $plan): int
    {
        /* Ce qu'un joueur a construit n'est plus une ligne de couche mais une
         * entité bâtiment : sans ce compte, l'avertissement « préservées, hors
         * import » se tairait sur les seules constructions qui restent. */
        $built = $this->db()->exe(
            'SELECT COUNT(*) n FROM buildings b
               JOIN players p ON p.id = b.player_id
               JOIN coords c ON c.id = p.coords_id
              WHERE c.plan = ? AND b.owner_id IS NOT NULL AND b.owner_id <> 0',
            array($plan)
        );

        $total = (int) ($built->fetch_assoc()['n'] ?? 0);

        foreach (TiledMapService::AUTHORABLE_LAYERS as $layer => $spec) {
            if ($layer === self::ENTITY_LAYER || !in_array('player_id', $spec['columns'], true)) {
                continue;
            }
            $res = $this->db()->exe(
                'SELECT COUNT(*) n FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ? AND m.player_id IS NOT NULL AND m.player_id <> 0',
                array($plan)
            );
            $total += (int) ($res->fetch_assoc()['n'] ?? 0);
        }

        return $total;
    }

    private function db(): Db
    {
        return $this->db ??= new Db();
    }
}
