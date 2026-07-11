<?php

namespace App\Service;

use Classes\Db;
use Classes\View;
use RuntimeException;

/**
 * Export / import des plans du jeu pour l'extension Tiled (tools/tiled/aoo).
 *
 * Un « plan » exporté = les couches authorables d'un (plan, z) donné.
 * map_items n'apparaît jamais : c'est de l'état runtime (objets au sol).
 *
 * L'import est un diff par couche, clé d'identité (x, y, name[, params]) :
 *  - les lignes identiques sont conservées telles quelles (leurs colonnes
 *    runtime — damages des murs, endTime des éléments — survivent) ;
 *  - les lignes construites par des joueurs (player_id non nul) sont
 *    intouchables : jamais supprimées, ignorées par le diff ;
 *  - le tout est transactionnel, avec contrôle de version optimiste :
 *    la version calculée au pull doit correspondre à l'état courant,
 *    sinon 409 (un autre admin — ou le jeu — a modifié le plan).
 */
class TiledMapService
{
    /** Couche => colonnes exportées en plus de name/x/y */
    public const AUTHORABLE_LAYERS = [
        'tiles'       => ['foreground', 'player_id'],
        'routes'      => ['player_id'],
        'plants'      => ['params'],
        'walls'       => ['damages', 'player_id'],
        'elements'    => ['endTime'],
        'foregrounds' => [],
        'triggers'    => ['params'],
        'dialogs'     => ['params'],
    ];

    /**
     * Couches dont params est du contenu authoré : il fait partie de la clé
     * d'identité (modifier le params d'un trigger = suppression + insertion).
     * Pour les autres couches, les colonnes hors clé sont de l'état runtime
     * préservé sur les lignes conservées.
     */
    private const PARAMS_AUTHORED_LAYERS = ['plants', 'triggers', 'dialogs'];

    private const IMAGE_EXTENSIONS = ['png', 'webp', 'gif'];

    public const TILE_SIZE = 50;

    private Db $db;

    public function __construct()
    {
        $this->db = new Db();
    }

    /** @return array|null null si le plan/z ne contient rien */
    public function exportPlan(string $plan, int $z): ?array
    {
        $layers = $this->fetchLayers($plan, $z);

        if (array_sum(array_map('count', $layers)) === 0) {
            return null;
        }

        return [
            'plan'     => $plan,
            'z'        => $z,
            'tileSize' => self::TILE_SIZE,
            'version'  => $this->computeVersion($layers),
            'layers'   => $layers,
            'images'   => $this->resolveImages($layers),
        ];
    }

    /**
     * @param array<string, array> $incomingLayers couches envoyées par l'extension
     * @return array{layers: array, newVersion: string}
     * @throws RuntimeException code 400 (payload invalide) ou 409 (conflit de version)
     */
    public function importPlan(string $plan, int $z, array $incomingLayers, string $expectedVersion): array
    {
        foreach (array_keys($incomingLayers) as $layer) {
            if (!isset(self::AUTHORABLE_LAYERS[$layer])) {
                throw new RuntimeException('Couche inconnue : ' . $layer, 400);
            }
        }

        $currentLayers = $this->fetchLayers($plan, $z);
        $currentVersion = $this->computeVersion($currentLayers);

        if (!hash_equals($currentVersion, $expectedVersion)) {
            throw new RuntimeException(
                'Le plan a changé depuis le pull — refaire un pull avant de pousser.',
                409
            );
        }

        $report = [];

        $this->db->beginTransaction();
        try {
            foreach ($incomingLayers as $layer => $rows) {
                $report[$layer] = $this->importLayer($plan, $z, $layer, $rows, $currentLayers[$layer]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'layers'     => $report,
            'newVersion' => $this->computeVersion($this->fetchLayers($plan, $z)),
        ];
    }

    /** @return array<string, array> toutes les couches authorables du (plan, z) */
    private function fetchLayers(string $plan, int $z): array
    {
        $layers = [];

        foreach (self::AUTHORABLE_LAYERS as $layer => $extraColumns) {

            $columns = 'm.id, m.name, c.x, c.y';
            foreach ($extraColumns as $column) {
                $columns .= ', m.`' . $column . '`';
            }

            $res = $this->db->exe(
                'SELECT ' . $columns . '
                 FROM map_' . $layer . ' m
                 JOIN coords c ON c.id = m.coords_id
                 WHERE c.plan = ? AND c.z = ?
                 ORDER BY c.y, c.x, m.id',
                array($plan, $z)
            );

            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $row['id'] = (int) $row['id'];
                $row['x'] = (int) $row['x'];
                $row['y'] = (int) $row['y'];
                $rows[] = $row;
            }

            $layers[$layer] = $rows;
        }

        return $layers;
    }

    /**
     * Empreinte du contenu authoré. Exclut les lignes protégées (player_id)
     * et les colonnes runtime (damages, endTime), qui évoluent pendant le jeu
     * sans que ce soit un conflit d'édition.
     */
    private function computeVersion(array $layers): string
    {
        $parts = [];

        foreach ($layers as $layer => $rows) {
            $paramsAuthored = in_array($layer, self::PARAMS_AUTHORED_LAYERS, true);

            foreach ($rows as $row) {
                if (!empty($row['player_id'])) {
                    continue;
                }
                $parts[] = $layer . '|' . $this->rowKey($row, $paramsAuthored);
            }
        }

        sort($parts);

        return sha1(implode("\n", $parts));
    }

    private function rowKey(array $row, bool $paramsAuthored): string
    {
        $key = $row['x'] . '|' . $row['y'] . '|' . $row['name'];

        if ($paramsAuthored) {
            $key .= '|' . (string) ($row['params'] ?? '');
        }

        return $key;
    }

    /** @return array{inserted: int, deleted: int, kept: int, protected: int} */
    private function importLayer(string $plan, int $z, string $layer, array $incomingRows, array $currentRows): array
    {
        $paramsAuthored = in_array($layer, self::PARAMS_AUTHORED_LAYERS, true);

        // Lignes existantes disponibles pour le rapprochement, par clé
        $available = [];
        $protected = 0;

        foreach ($currentRows as $row) {
            if (!empty($row['player_id'])) {
                $protected++;
                continue;
            }
            $available[$this->rowKey($row, $paramsAuthored)][] = $row['id'];
        }

        $kept = 0;
        $toInsert = [];

        foreach ($incomingRows as $row) {
            $this->validateIncomingRow($layer, $row);

            $key = $this->rowKey($row, $paramsAuthored);

            if (!empty($available[$key])) {
                array_pop($available[$key]);
                $kept++;
            } else {
                $toInsert[] = $row;
            }
        }

        foreach ($toInsert as $row) {
            $this->insertRow($plan, $z, $layer, $row, $paramsAuthored);
        }

        $toDelete = [];
        foreach ($available as $ids) {
            foreach ($ids as $id) {
                $toDelete[] = $id;
            }
        }

        if ($toDelete !== []) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $this->db->exe('DELETE FROM map_' . $layer . ' WHERE id IN (' . $placeholders . ')', $toDelete);
        }

        return [
            'inserted'  => count($toInsert),
            'deleted'   => count($toDelete),
            'kept'      => $kept,
            'protected' => $protected,
        ];
    }

    private function validateIncomingRow(string $layer, mixed $row): void
    {
        if (!is_array($row)
            || !isset($row['x'], $row['y'], $row['name'])
            || !is_numeric($row['x']) || !is_numeric($row['y'])
            || !is_string($row['name'])
            || !preg_match('/^[a-zA-Z0-9_.-]+$/', $row['name'])
        ) {
            throw new RuntimeException('Ligne invalide dans la couche ' . $layer . ' : ' . json_encode($row), 400);
        }

        if (isset($row['params']) && (!is_scalar($row['params']) || strlen((string) $row['params']) > 255)) {
            throw new RuntimeException('Params invalide dans la couche ' . $layer . ' en ' . $row['x'] . ',' . $row['y'], 400);
        }
    }

    private function insertRow(string $plan, int $z, string $layer, array $row, bool $paramsAuthored): void
    {
        $coordsId = View::get_coords_id((object) [
            'x'    => (int) $row['x'],
            'y'    => (int) $row['y'],
            'z'    => $z,
            'plan' => $plan,
        ]);

        if (!$coordsId) {
            throw new RuntimeException('Création de coordonnées impossible en ' . $row['x'] . ',' . $row['y'], 500);
        }

        $values = [
            'name'      => $row['name'],
            'coords_id' => $coordsId,
        ];

        if ($layer === 'walls') {
            // Défaut authoré : -1 (récoltable) pour les ressources de
            // WALLS_PV, 0 (intact) pour les autres murs
            $values['damages'] = (defined('WALLS_PV') && (WALLS_PV[$row['name']] ?? 0) === -1) ? -1 : 0;
        }

        if ($paramsAuthored && isset($row['params']) && $row['params'] !== '') {
            $values['params'] = (string) $row['params'];
        }

        $this->db->insert('map_' . $layer, $values);
    }

    /** @return array<string, string|null> "couche/nom" => chemin image ou null */
    private function resolveImages(array $layers): array
    {
        $images = [];

        foreach ($layers as $layer => $rows) {
            foreach ($rows as $row) {
                $key = $layer . '/' . $row['name'];
                if (array_key_exists($key, $images)) {
                    continue;
                }

                $images[$key] = null;
                foreach (self::IMAGE_EXTENSIONS as $ext) {
                    $candidate = 'img/' . $layer . '/' . $row['name'] . '.' . $ext;
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $candidate)) {
                        $images[$key] = $candidate;
                        break;
                    }
                }
            }
        }

        return $images;
    }
}
