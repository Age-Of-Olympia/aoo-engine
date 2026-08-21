<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use App\Service\Map\StructureTypeService;

/**
 * Inventaire et gestion des images de tuiles (img/<couche>) pour le panneau
 * admin « Tuiles & images » : lister chaque image avec ses diagnostics
 * (PNG à palette — cause des fondus noirs —, taille hors palette d'éditeur,
 * nom invalide, formats multiples, image référencée en base mais absente,
 * image inutilisée), ajouter (normalisée en PNG vraies couleurs), supprimer
 * et renommer avec garde-fous (une image encore posée sur une carte ne se
 * supprime pas ; un renommage met à jour les cartes et terrains.json).
 *
 * Les fondus générés (trans_*) ne sont pas inventoriés un à un : ils se
 * gèrent depuis la page Transitions de terrain.
 */
class TileAssetService
{
    private ?EntityManagerInterface $entityManager;
    private string $root;

    public function __construct(?EntityManagerInterface $entityManager = null, ?string $root = null)
    {
        $this->entityManager = $entityManager;
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /** Couches gérées : celles qui ont un dossier d'images et une table map_*. */
    public function layers(): array
    {
        return array_keys(TiledMapService::AUTHORABLE_LAYERS);
    }

    /**
     * Inventaire d'une couche : une entrée par nom (image présente OU nom
     * référencé en base sans image), avec diagnostics et nombre d'usages.
     *
     * @return array{
     *   entries: list<array{name: string, files: list<string>, width: int, height: int,
     *                       usage: int, isTerrain: bool, problems: list<string>, missing: bool}>,
     *   transitions: int
     * }
     */
    public function inventory(string $layer): array
    {
        $this->assertLayer($layer);
        $dir = $this->root . '/img/' . TiledMapService::layerImageDir($layer);

        // Fichiers présents, groupés par nom (toutes extensions)
        $files = [];
        foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, TileCatalogService::IMAGE_EXTENSIONS, true)) {
                continue;
            }
            $files[pathinfo($fileName, PATHINFO_FILENAME)][] = $fileName;
        }
        ksort($files);

        $transitions = 0;
        $usage = $this->usageByName($layer);
        $terrainTiles = $layer === TerrainTransitionService::GROUND_LAYER
            ? $this->declaredTerrainTiles($layer) : [];

        $entries = [];
        foreach ($files as $name => $nameFiles) {
            if (str_starts_with((string) $name, 'trans_')) {
                $transitions++;
                continue;
            }
            $entries[] = $this->buildEntry($layer, (string) $name, $nameFiles, $usage, $terrainTiles);
        }

        // Noms posés sur les cartes dont l'image a disparu : tuiles cassées
        foreach ($usage as $name => $count) {
            if (!isset($files[$name]) && !str_starts_with((string) $name, 'trans_')) {
                $entries[] = [
                    'name' => (string) $name, 'files' => [], 'width' => 0, 'height' => 0,
                    'usage' => $count, 'isTerrain' => isset($terrainTiles[$name]),
                    'problems' => ["référencée sur les cartes ({$count} case(s)) mais image absente — tuile cassée en jeu"],
                    'missing' => true,
                ];
            }
        }

        return ['entries' => $entries, 'transitions' => $transitions];
    }

    /**
     * Ajoute une image : validée, convertie en PNG vraies couleurs (les PNG
     * à palette ont produit des fondus noirs — on normalise à l'entrée).
     */
    public function add(string $layer, string $name, string $tmpPath): void
    {
        $this->assertLayer($layer);
        $this->assertName($name);
        if ($this->existingFiles($layer, $name) !== []) {
            throw new RuntimeException("L'image « {$name} » existe déjà dans cette couche.");
        }
        if (!is_file($tmpPath) || filesize($tmpPath) > TileCatalogService::IMAGE_MAX_BYTES) {
            throw new RuntimeException('Fichier absent ou trop volumineux (max 4 Mo).');
        }

        $info = @getimagesize($tmpPath);
        $image = match ($info['mime'] ?? '') {
            'image/png'  => imagecreatefrompng($tmpPath),
            'image/webp' => imagecreatefromwebp($tmpPath),
            'image/gif'  => imagecreatefromgif($tmpPath),
            default      => false,
        };
        if (!$image) {
            throw new RuntimeException('Image illisible (png, webp ou gif attendu).');
        }
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);

        $dir = $this->root . '/img/' . TiledMapService::layerImageDir($layer);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new RuntimeException('Dossier non inscriptible : ' . $dir);
        }
        if (!imagepng($image, $dir . '/' . $name . '.png')) {
            throw new RuntimeException('Écriture impossible : ' . $dir . '/' . $name . '.png');
        }
    }

    /**
     * Supprime une image inutilisée. Refus si elle est encore posée sur une
     * carte, ou déclarée terrain (la déclasser d'abord sur la page
     * Transitions de terrain).
     */
    public function delete(string $layer, string $name): void
    {
        $this->assertLayer($layer);
        $files = $this->existingFiles($layer, $name);
        if ($files === []) {
            throw new RuntimeException("Image inconnue : {$name}.");
        }

        $usage = $this->usageByName($layer)[$name] ?? 0;
        if ($usage > 0) {
            throw new RuntimeException("« {$name} » est encore posée sur les cartes ({$usage} case(s)) — remplacez-la d'abord.");
        }
        if (isset($this->declaredTerrainTiles($layer)[$name])) {
            throw new RuntimeException("« {$name} » est déclarée terrain — déclassez-la d'abord (page Transitions de terrain).");
        }

        foreach ($files as $file) {
            if (!unlink($this->root . '/img/' . TiledMapService::layerImageDir($layer) . '/' . $file)) {
                throw new RuntimeException('Suppression impossible : ' . $file);
            }
        }
    }

    /**
     * Déplace une image vers une autre couche (transformer une tuile de sol
     * en décor de premier plan, un élément en mur…). Le fichier change de
     * dossier, rien d'autre : refusé tant que l'image est posée sur une
     * carte dans sa couche d'origine (les cases la référenceraient dans le
     * vide) ou déclarée terrain. Si la couche cible référence déjà ce nom
     * en base sans image, le déplacement RÉPARE ces cases.
     */
    public function move(string $fromLayer, string $name, string $toLayer): void
    {
        $this->assertLayer($fromLayer);
        $this->assertLayer($toLayer);
        if ($fromLayer === $toLayer) {
            throw new RuntimeException('Couche cible identique à la couche actuelle.');
        }

        $files = $this->existingFiles($fromLayer, $name);
        if ($files === []) {
            throw new RuntimeException("Image inconnue : {$name}.");
        }
        if ($this->existingFiles($toLayer, $name) !== []) {
            throw new RuntimeException("« {$name} » existe déjà dans la couche {$toLayer}.");
        }

        $usage = $this->usageByName($fromLayer)[$name] ?? 0;
        if ($usage > 0) {
            throw new RuntimeException("« {$name} » est encore posée sur les cartes en couche {$fromLayer} "
                . "({$usage} case(s)) — remplacez-la d'abord.");
        }
        if ($fromLayer === TerrainTransitionService::GROUND_LAYER
            && isset($this->declaredTerrainTiles($fromLayer)[$name])) {
            throw new RuntimeException("« {$name} » est déclarée terrain — déclassez-la d'abord (page Transitions de terrain).");
        }

        $targetDir = $this->root . '/img/' . $toLayer;
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            throw new RuntimeException('Dossier non inscriptible : ' . $targetDir);
        }

        foreach ($files as $file) {
            if (!rename($this->root . '/img/' . $fromLayer . '/' . $file, $targetDir . '/' . $file)) {
                throw new RuntimeException('Déplacement impossible : ' . $file);
            }
        }
    }

    /**
     * Renomme une image ET toutes ses références : cases posées sur les
     * cartes (tous plans), et pour la couche sol l'entrée de terrain de
     * terrains.json (mapping + libellé de couleur, au même index — les
     * wangId référencent les couleurs par index, jamais déplacées).
     *
     * Refusé quand des fondus générés embarquent le nom (trans_<nom>_…) :
     * supprimer/régénérer les transitions d'abord.
     *
     * @return array{rowsUpdated: int, warnings: list<string>}
     */
    public function rename(string $layer, string $old, string $new): array
    {
        $this->assertLayer($layer);
        $this->assertName($new);
        $files = $this->existingFiles($layer, $old);
        if ($files === []) {
            throw new RuntimeException("Image inconnue : {$old}.");
        }
        if ($this->existingFiles($layer, $new) !== []) {
            throw new RuntimeException("« {$new} » existe déjà.");
        }

        $terrains = null;
        $cfg = null;
        if ($layer === TerrainTransitionService::GROUND_LAYER) {
            $service = new TerrainTransitionService(null, $this->root);
            $terrains = $service->loadTerrains();
            $cfg = &$service->layerConfig($terrains, $layer);

            $embedded = 0;
            foreach (array_keys($cfg['tiles']) as $tileName) {
                if (is_array($cfg['tiles'][$tileName])
                    && preg_match('/(^trans_|_)' . preg_quote($old, '/') . '_/', (string) $tileName)) {
                    $embedded++;
                }
            }
            if ($embedded > 0) {
                throw new RuntimeException("« {$old} » apparaît dans le nom de {$embedded} fondu(s) générés — "
                    . 'supprimez ou régénérez ses transitions avant de renommer.');
            }
        }

        $connection = $this->connection();
        $connection->beginTransaction();
        try {
            $rowsUpdated = (int) $connection->executeStatement(
                'UPDATE map_' . $layer . ' SET name = ? WHERE name = ?',
                [$new, $old]
            );

            foreach ($files as $file) {
                $ext = pathinfo($file, PATHINFO_EXTENSION);
                if (!rename($this->root . '/img/' . TiledMapService::layerImageDir($layer) . '/' . $file,
                    $this->root . '/img/' . TiledMapService::layerImageDir($layer) . '/' . $new . '.' . $ext)) {
                    throw new RuntimeException('Renommage du fichier impossible : ' . $file);
                }
            }

            if ($cfg !== null && is_string($cfg['tiles'][$old] ?? null)) {
                $color = $cfg['tiles'][$old];
                unset($cfg['tiles'][$old]);
                // même index de couleur : les wangId existants restent valides
                $index = array_search($color, $cfg['colors'], true);
                if ($index !== false && $color === $old) {
                    $cfg['colors'][$index] = $new;
                    $color = $new;
                }
                $cfg['tiles'][$new] = $color;
                (new TerrainTransitionService(null, $this->root))->saveTerrains($terrains);
            }

            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return ['rowsUpdated' => $rowsUpdated, 'warnings' => $this->renameWarnings($layer, $old)];
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param list<string> $nameFiles
     * @param array<string, int> $usage
     * @param array<string, true> $terrainTiles
     * @return array{name: string, files: list<string>, width: int, height: int,
     *               usage: int, isTerrain: bool, problems: list<string>, missing: bool}
     */
    private function buildEntry(string $layer, string $name, array $nameFiles, array $usage, array $terrainTiles): array
    {
        $dir = $this->root . '/img/' . TiledMapService::layerImageDir($layer);
        $primary = $dir . '/' . $nameFiles[0];
        $size = @getimagesize($primary);
        $problems = [];

        if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name)) {
            $problems[] = 'nom invalide — ignorée par les éditeurs et la palette';
        }
        if (count($nameFiles) > 1) {
            $problems[] = 'plusieurs formats (' . implode(', ', $nameFiles) . ') — le png prime, les autres sont morts';
        }
        if (!$size) {
            $problems[] = 'image illisible';
        } elseif ($this->isPaletteImage($primary)) {
            $problems[] = 'image à palette — source des fondus noirs historiques ; convertie à la volée depuis le correctif, mais un ré-enregistrement en vraies couleurs est plus sûr';
        }

        $count = $usage[$name] ?? 0;
        // Les morceaux de structures composites (base-NN) se posent via la
        // structure entière : « inutilisée » serait un faux positif
        if ($count === 0 && !isset($terrainTiles[$name]) && $size && !preg_match('/-\d{2}$/', $name)) {
            $problems[] = 'posée sur aucune carte';
        }

        return [
            'name'      => $name,
            'files'     => $nameFiles,
            'width'     => $size[0] ?? 0,
            'height'    => $size[1] ?? 0,
            'usage'     => $count,
            'isTerrain' => isset($terrainTiles[$name]),
            'problems'  => $problems,
            'missing'   => false,
        ];
    }

    /**
     * PNG couleur indexée (type 3 dans l'en-tête IHDR) ou GIF : imagecolorat
     * y renvoie l'index de palette, pas la couleur. Sniff d'en-tête, pas de
     * décodage complet (des centaines de fichiers par couche).
     */
    private function isPaletteImage(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'gif') {
            return true;
        }
        if ($ext !== 'png') {
            return false;
        }
        $header = (string) @file_get_contents($path, false, null, 0, 26);
        return strlen($header) === 26 && ord($header[25]) === 3;
    }

    /** @return array<string, int> nom => nombre de cases sur toutes les cartes */
    private function usageByName(string $layer): array
    {
        $rows = $this->connection()->fetchAllKeyValue(
            'SELECT name, COUNT(*) FROM map_' . $layer . ' GROUP BY name'
        );
        return array_map('intval', $rows);
    }

    /** @return array<string, true> tuiles pleines déclarées dans terrains.json */
    private function declaredTerrainTiles(string $layer): array
    {
        try {
            $service = new TerrainTransitionService(null, $this->root);
            $terrains = $service->loadTerrains();
            $cfg = &$service->layerConfig($terrains, $layer);
            return array_fill_keys(array_keys(array_filter($cfg['tiles'], 'is_string')), true);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    /**
     * Références de configuration qu'un renommage NE met PAS à jour : à
     * vérifier à la main. Les biomes des plans (plans.biomes) nomment des
     * murs/ressources ; le catalogue des types aussi.
     *
     * @return list<string>
     */
    private function renameWarnings(string $layer, string $old): array
    {
        $warnings = [];

        if (StructureTypeService::isKnown($old)) {
            $warnings[] = "« {$old} » figure au catalogue des types — à renommer à la main";
        }

        foreach (glob($this->root . '/datas/*/plans/*.json') ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), '"' . $old . '"')) {
                $warnings[] = 'référencée dans ' . basename($file) . ' (biomes du plan) — à renommer à la main';
            }
        }

        return $warnings;
    }

    /** @return list<string> fichiers existants (toutes extensions) pour ce nom */
    private function existingFiles(string $layer, string $name): array
    {
        $files = [];
        foreach (TileCatalogService::IMAGE_EXTENSIONS as $ext) {
            if (is_file($this->root . '/img/' . TiledMapService::layerImageDir($layer) . '/' . $name . '.' . $ext)) {
                $files[] = $name . '.' . $ext;
            }
        }
        return $files;
    }

    private function assertLayer(string $layer): void
    {
        if (!isset(TiledMapService::AUTHORABLE_LAYERS[$layer])) {
            throw new RuntimeException('Couche inconnue : ' . $layer);
        }
    }

    private function assertName(string $name): void
    {
        if (!preg_match(TileCatalogService::ASSET_NAME_PATTERN, $name) || str_starts_with($name, 'trans_')) {
            throw new RuntimeException('Nom invalide (lettres, chiffres, _ . - ; préfixe trans_ réservé).');
        }
    }

    /**
     * Positions d'une tuile sur les cartes — pour le popover « Positions »
     * de la page d'admin (le compte seul ne dit pas OÙ aller la remplacer).
     *
     * @return list<string> « x, y (z) plan », bornées à $limit
     */
    public function positionsOfTile(string $layer, string $name, int $limit = 200): array
    {
        $this->assertLayer($layer);

        $rows = $this->connection()->fetchAllAssociative(
            'SELECT c.x, c.y, c.z, c.plan FROM map_' . $layer . ' m
             INNER JOIN coords c ON m.coords_id = c.id
             WHERE m.name = ? ORDER BY c.plan, c.z, c.x, c.y LIMIT ' . $limit,
            [$name]
        );

        return array_map(
            static fn(array $row): string => $row['x'] . ', ' . $row['y'] . ' (z' . $row['z'] . ') ' . $row['plan'],
            $rows
        );
    }

    private function connection(): \Doctrine\DBAL\Connection
    {
        $this->entityManager ??= EntityManagerFactory::getEntityManager();
        return $this->entityManager->getConnection();
    }
}
