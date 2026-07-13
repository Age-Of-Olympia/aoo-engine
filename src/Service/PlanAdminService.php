<?php

namespace App\Service;

use Classes\Db;
use RuntimeException;

/**
 * Cycle de vie des plans côté admin : création (vierge ou par clonage),
 * bilan préalable et suppression. Compose les points de passage existants —
 * TiledMapService (coord d'amorce, spec des couches) et PlanConfigService
 * (fichier JSON) — sans dupliquer leurs règles.
 *
 * Les clones sont de l'authoring pur : les lignes construites par des
 * joueurs (player_id) ne sont jamais copiées, ni les joueurs/PNJ, ni
 * map_items (loot runtime) — mêmes règles que l'export Tiled.
 *
 * @see \App\Tutorial\TutorialMapInstance clone jetable par session de
 *      tutoriel (Doctrine, spawn de PNJ entrelacé) — volontairement laissé
 *      intact ; une consolidation future pourra le rebrancher ici.
 */
class PlanAdminService
{
    /** Tables sujettes à la cascade PNJ, dans l'ordre de suppression (FK). */
    private const NPC_CASCADE_BY_PLAYER = ['players_actions', 'players_items', 'players_effects'];
    private const NPC_CASCADE_BY_PLAYER_OR_TARGET = ['players_logs', 'players_kills', 'players_assists'];

    private Db $db;
    private PlanConfigService $planConfig;
    private TiledMapService $tiledMap;

    public function __construct(?Db $db = null, ?PlanConfigService $planConfig = null, ?TiledMapService $tiledMap = null)
    {
        $this->db = $db ?? new Db();
        $this->planConfig = $planConfig ?? new PlanConfigService();
        $this->tiledMap = $tiledMap ?? new TiledMapService();
    }

    /** Le plan existe-t-il, côté coords OU côté fichier JSON ? */
    public function planExists(string $plan): bool
    {
        $res = $this->db->exe('SELECT 1 FROM coords WHERE plan = ? LIMIT 1', array($plan));
        if ($res->fetch_row() !== null) {
            return true;
        }

        return file_exists($this->jsonPath($plan));
    }

    /**
     * Crée un plan vierge : coord d'amorce (0,0,0) puis JSON minimal.
     *
     * @param array<string, string> $config sous-ensemble de PLAN_CONFIG_KEYS,
     *                                      valeurs chaînes (convention parse())
     * @throws RuntimeException code 400 (nom/config invalide) ou 409 (existe déjà,
     *                          y compris fichier JSON orphelin — comble le trou de
     *                          createPlan() qui ne regarde que coords)
     */
    public function createBlankPlan(string $plan, array $config = []): void
    {
        $this->assertValidPlanName($plan);

        if ($this->planExists($plan)) {
            throw new RuntimeException('Le plan existe déjà : ' . $plan, 409);
        }

        if (trim($config['name'] ?? '') === '') {
            $config['name'] = $plan;
        }
        // Valider AVANT d'écrire quoi que ce soit : ni coord ni fichier sur un 400
        $parsed = $this->planConfig->parse($config);

        $this->tiledMap->createPlan($plan);

        try {
            // write() sur fichier absent le crée (load() retombe sur ['name' => plan])
            $this->planConfig->write($plan, $parsed);
        } catch (\Throwable $e) {
            // Compensation best-effort : ne pas laisser un plan « coords sans JSON »
            $this->db->exe('DELETE FROM coords WHERE plan = ?', array($plan));
            throw $e;
        }
    }

    /**
     * Clone un plan complet : coords, couches authorables et fichier JSON.
     * SQL ensembliste (jointure ancien→nouveau par (x,y,z)) — pas de remap
     * PHP ligne à ligne. endTime (map_elements) n'est pas copié : c'est de
     * l'état runtime ; damages (map_walls) l'est : il encode l'intention
     * d'auteur (-1 = récoltable).
     *
     * @param array<string, mixed> $jsonOverrides clés du JSON cible surchargées (name…)
     * @return array{coords: int, layers: array<string, int>} lignes copiées
     * @throws RuntimeException code 400, 404 (source sans coords) ou 409 (cible existe)
     */
    public function clonePlan(string $sourcePlan, string $targetPlan, array $jsonOverrides = []): array
    {
        $this->assertValidPlanName($sourcePlan);
        $this->assertValidPlanName($targetPlan);

        if ($this->planExists($targetPlan)) {
            throw new RuntimeException('Le plan existe déjà : ' . $targetPlan, 409);
        }
        if (!isset($this->tiledMap->listPlans()[$sourcePlan])) {
            throw new RuntimeException('Plan modèle introuvable (aucune coordonnée) : ' . $sourcePlan, 404);
        }

        $report = ['coords' => 0, 'layers' => []];

        $this->db->beginTransaction();
        try {
            $report['coords'] = (int) $this->db->exe(
                'INSERT INTO coords (x, y, z, plan) SELECT x, y, z, ? FROM coords WHERE plan = ?',
                array($targetPlan, $sourcePlan),
                false,
                true
            );

            foreach (TiledMapService::AUTHORABLE_LAYERS as $layer => $spec) {
                $report['layers'][$layer] = $this->copyLayer($layer, $spec, $sourcePlan, $targetPlan);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // Après commit : un clone en base sans JSON se répare en relançant la
        // copie du fichier, l'inverse (fichier sans base) serait un orphelin
        if (trim((string) ($jsonOverrides['name'] ?? '')) === '') {
            $jsonOverrides['name'] = $targetPlan;
        }
        $this->planConfig->copy($sourcePlan, $targetPlan, $jsonOverrides);

        return $report;
    }

    /**
     * Joueurs et PNJ présents sur le plan (zone dangereuse de l'admin).
     *
     * @return array{players: int, npcs: int}
     */
    public function countCharactersOnPlan(string $plan): array
    {
        $res = $this->db->exe(
            'SELECT
                COALESCE(SUM(p.id > 0), 0) AS players,
                COALESCE(SUM(p.id < 0), 0) AS npcs
             FROM players p JOIN coords c ON c.id = p.coords_id
             WHERE c.plan = ?',
            array($plan)
        );
        $row = $res->fetch_assoc();

        return ['players' => (int) ($row['players'] ?? 0), 'npcs' => (int) ($row['npcs'] ?? 0)];
    }

    /**
     * Bilan préalable à la suppression : ce qui la bloque et ce qu'elle
     * emportera. Couvre toutes les FK vers coords (players, players_logs
     * (+archives), tutorial_enemies, map_*) plus les références par nom de
     * plan (factions.respawnPlan, tutoriel) et les téléporteurs entrants.
     *
     * @return array{
     *     blockers: list<array{check: string, count: int, detail: string, forceable: bool}>,
     *     warnings: list<array{check: string, count: int, detail: string}>
     * }
     */
    public function deletePreflight(string $plan): array
    {
        $blockers = [];
        $warnings = [];

        $characters = $this->countCharactersOnPlan($plan);
        if ($characters['players'] > 0) {
            $blockers[] = [
                'check' => 'players', 'count' => $characters['players'], 'forceable' => false,
                'detail' => $characters['players'] . ' joueur(s) se trouvent sur ce plan — déplacez-les d\'abord.',
            ];
        }
        if ($characters['npcs'] > 0) {
            $blockers[] = [
                'check' => 'npcs', 'count' => $characters['npcs'], 'forceable' => true,
                'detail' => $characters['npcs'] . ' PNJ sur ce plan (supprimés avec leurs données si suppression forcée).',
            ];
        }

        $logsCount = $this->countJoinCoords('players_logs', 'coords_id', $plan)
            + $this->countJoinCoords('players_logs_archives', 'coords_id', $plan);
        if ($logsCount > 0) {
            $blockers[] = [
                'check' => 'players_logs', 'count' => $logsCount, 'forceable' => true,
                'detail' => $logsCount . ' ligne(s) de logs pointent des coordonnées du plan (détachées si suppression forcée — l\'historique est conservé).',
            ];
        }

        $enemies = $this->countJoinCoords('tutorial_enemies', 'enemy_coords_id', $plan);
        if ($enemies > 0) {
            $blockers[] = [
                'check' => 'tutorial_enemies', 'count' => $enemies, 'forceable' => false,
                'detail' => $enemies . ' ennemi(s) de tutoriel référencent ce plan — terminez/annulez les sessions concernées.',
            ];
        }

        // Plan de résurrection des factions — table factions (source de
        // vérité depuis la migration FactionsFromJson), colonne NOT NULL
        // DEFAULT 'olympia' : une faction sans respawn explicite bloque
        // donc la suppression d'olympia
        $factionNames = [];
        $res = $this->db->exe('SELECT name FROM factions WHERE respawnPlan = ?', array($plan));
        while ($row = $res->fetch_assoc()) {
            $factionNames[] = $row['name'];
        }
        if ($factionNames !== []) {
            $blockers[] = [
                'check' => 'factions', 'count' => count($factionNames), 'forceable' => false,
                'detail' => 'Plan de résurrection des factions : ' . implode(', ', $factionNames)
                    . ' — changez leur respawnPlan (admin Factions) d\'abord.',
            ];
        }

        $incoming = $this->incomingTeleportSources($plan);
        if ($incoming !== []) {
            $warnings[] = [
                'check' => 'incoming_tp', 'count' => count($incoming),
                'detail' => 'Des téléporteurs pointent vers ce plan depuis : ' . implode(', ', $incoming) . ' (ils resteront cassés).',
            ];
        }

        $items = $this->countJoinCoords('map_items', 'coords_id', $plan);
        if ($items > 0) {
            $warnings[] = [
                'check' => 'map_items', 'count' => $items,
                'detail' => $items . ' objet(s) au sol seront supprimés avec le plan.',
            ];
        }

        foreach (['tutorial_catalog' => 'plan', 'tutorial_map_instances' => 'plan_name'] as $table => $column) {
            $res = $this->db->exe('SELECT COUNT(*) n FROM ' . $table . ' WHERE `' . $column . '` = ?', array($plan));
            $n = (int) ($res->fetch_assoc()['n'] ?? 0);
            if ($n > 0) {
                $warnings[] = [
                    'check' => $table, 'count' => $n,
                    'detail' => $n . ' référence(s) dans ' . $table . ' (non supprimées — à nettoyer côté tutoriel).',
                ];
            }
        }

        return ['blockers' => $blockers, 'warnings' => $warnings];
    }

    /**
     * Supprime un plan : couches map_*, coords, puis (hors transaction,
     * best-effort) fichier JSON et PNG générés. $force lève uniquement les
     * blocages marqués forceable (cascade PNJ, détachement des logs) —
     * jamais les joueurs réels ni les FK tutoriel/factions.
     *
     * @return array{coords: int, layers: array<string, int>, map_items: int,
     *               npcs: int, logs_detached: int, files: list<string>}
     * @throws RuntimeException code 400, 404 (plan inconnu) ou 409 (bilan bloquant)
     */
    public function deletePlan(string $plan, bool $force = false): array
    {
        $this->assertValidPlanName($plan);

        if (!$this->planExists($plan)) {
            throw new RuntimeException('Plan introuvable : ' . $plan, 404);
        }

        $preflight = $this->deletePreflight($plan);
        $blocking = array_filter(
            $preflight['blockers'],
            fn(array $b) => !$force || !$b['forceable']
        );
        if ($blocking !== []) {
            throw new RuntimeException(
                "Suppression impossible :\n- " . implode("\n- ", array_column($blocking, 'detail')),
                409
            );
        }

        $report = ['coords' => 0, 'layers' => [], 'map_items' => 0, 'npcs' => 0, 'logs_detached' => 0, 'files' => []];

        $this->db->beginTransaction();
        try {
            if ($force) {
                $report['npcs'] = $this->deleteNpcsOnPlan($plan);
                foreach (['players_logs', 'players_logs_archives'] as $table) {
                    $report['logs_detached'] += (int) $this->db->exe(
                        'UPDATE ' . $table . ' l JOIN coords c ON c.id = l.coords_id
                         SET l.coords_id = NULL WHERE c.plan = ?',
                        array($plan),
                        false,
                        true
                    );
                }
            }

            $report['map_items'] = $this->deleteLayerRows('map_items', $plan);
            foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
                $report['layers'][$layer] = $this->deleteLayerRows('map_' . $layer, $plan);
            }

            $report['coords'] = (int) $this->db->exe(
                'DELETE FROM coords WHERE plan = ?',
                array($plan),
                false,
                true
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // Fichiers en dernier et best-effort : la base fait foi, un fichier
        // survivant se renettoie, une base à moitié supprimée non
        $jsonPath = $this->jsonPath($plan);
        if (file_exists($jsonPath) && @unlink($jsonPath)) {
            $report['files'][] = 'datas/private/plans/' . $plan . '.json';
        }
        foreach (glob($_SERVER['DOCUMENT_ROOT'] . '/img/maps/local/local_' . $plan . '_*.png') ?: [] as $png) {
            if (@unlink($png)) {
                $report['files'][] = 'img/maps/local/' . basename($png);
            }
        }

        return $report;
    }

    /** @throws RuntimeException code 400 */
    private function assertValidPlanName(string $plan): void
    {
        if (!preg_match(TiledMapService::PLAN_NAME_PATTERN, $plan)) {
            throw new RuntimeException(
                'Nom de plan invalide (attendu : minuscules, chiffres, _ ou -, 64 max) : ' . $plan,
                400
            );
        }
    }

    private function jsonPath(string $plan): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans/' . $plan . '.json';
    }

    /**
     * Copie une couche map_* entre deux plans, jointure (x,y,z). Les lignes
     * joueur sont écartées, player_id jamais copié, endTime (runtime) non plus.
     *
     * @param array{columns: list<string>} $spec entrée de AUTHORABLE_LAYERS
     */
    private function copyLayer(string $layer, array $spec, string $sourcePlan, string $targetPlan): int
    {
        $columns = ['name'];
        foreach ($spec['columns'] as $column) {
            if ($column !== 'player_id' && $column !== 'endTime') {
                $columns[] = $column;
            }
        }
        $columnSql = '`' . implode('`, `', $columns) . '`';
        $selectSql = 'm.`' . implode('`, m.`', $columns) . '`';

        $playerFilter = in_array('player_id', $spec['columns'], true)
            ? ' AND (m.player_id IS NULL OR m.player_id = 0)'
            : '';

        return (int) $this->db->exe(
            'INSERT INTO map_' . $layer . ' (coords_id, ' . $columnSql . ')
             SELECT nc.id, ' . $selectSql . '
             FROM map_' . $layer . ' m
             JOIN coords oc ON oc.id = m.coords_id AND oc.plan = ?
             JOIN coords nc ON nc.plan = ? AND nc.x = oc.x AND nc.y = oc.y AND nc.z = oc.z' . $playerFilter,
            array($sourcePlan, $targetPlan),
            false,
            true
        );
    }

    /** Nombre de lignes d'une table à FK coords_id présentes sur le plan. */
    private function countJoinCoords(string $table, string $column, string $plan): int
    {
        $res = $this->db->exe(
            'SELECT COUNT(*) n FROM ' . $table . ' t JOIN coords c ON c.id = t.`' . $column . '` WHERE c.plan = ?',
            array($plan)
        );

        return (int) ($res->fetch_assoc()['n'] ?? 0);
    }

    private function deleteLayerRows(string $table, string $plan): int
    {
        return (int) $this->db->exe(
            'DELETE m FROM ' . $table . ' m JOIN coords c ON c.id = m.coords_id WHERE c.plan = ?',
            array($plan),
            false,
            true
        );
    }

    /**
     * Plans sources des triggers `tp` menant vers $plan (même décodage CSV
     * que le pass links de {@see TiledMapService::worldLayout()}).
     *
     * @return list<string>
     */
    private function incomingTeleportSources(string $plan): array
    {
        $res = $this->db->exe(
            'SELECT DISTINCT c.plan AS src, t.params FROM map_triggers t
             JOIN coords c ON c.id = t.coords_id WHERE t.name = "tp" AND c.plan <> ?',
            array($plan)
        );

        $sources = [];
        while ($row = $res->fetch_assoc()) {
            $dest = trim(explode(',', (string) $row['params'])[3] ?? '');
            if ($dest === $plan) {
                $sources[$row['src']] = true;
            }
        }

        return array_keys($sources);
    }

    /**
     * Cascade de suppression des PNJ du plan — même liste ordonnée de tables
     * que TutorialMapInstance::deleteInstance() (FK players).
     */
    private function deleteNpcsOnPlan(string $plan): int
    {
        $res = $this->db->exe(
            'SELECT p.id FROM players p JOIN coords c ON c.id = p.coords_id WHERE c.plan = ? AND p.id < 0',
            array($plan)
        );
        $npcIds = [];
        while ($row = $res->fetch_assoc()) {
            $npcIds[] = (int) $row['id'];
        }
        if ($npcIds === []) {
            return 0;
        }

        $in = implode(',', array_fill(0, count($npcIds), '?'));

        foreach (self::NPC_CASCADE_BY_PLAYER_OR_TARGET as $table) {
            $this->db->exe(
                'DELETE FROM ' . $table . ' WHERE player_id IN (' . $in . ') OR target_id IN (' . $in . ')',
                array_merge($npcIds, $npcIds)
            );
        }
        foreach (self::NPC_CASCADE_BY_PLAYER as $table) {
            $this->db->exe('DELETE FROM ' . $table . ' WHERE player_id IN (' . $in . ')', $npcIds);
        }

        $this->db->exe('DELETE FROM players WHERE id IN (' . $in . ')', $npcIds);

        return count($npcIds);
    }
}
