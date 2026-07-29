<?php

namespace App\Service;

/**
 * Catalogue des images du jeu pour l'éditeur Tiled : palette complète par
 * couche, structures multi-tuiles, fonds/ambiances candidats. Une seule
 * lecture des tailles par répertoire et par requête (mémoïsée).
 */
class TileCatalogService
{
    public const IMAGE_EXTENSIONS = ['png', 'webp', 'gif'];

    /** Règle unique des noms de tuiles/assets (tables map_*, fichiers img/) */
    public const ASSET_NAME_PATTERN = '/^[a-zA-Z0-9_.-]+$/';

    /** Tolérance au-delà de laquelle une image n'est plus une tuile posable */
    private const TILE_MAX_SIZE = TiledMapService::TILE_SIZE * 1.2;

    /** Taille maximale d'une image uploadée (stocks d'images de l'admin) */
    public const IMAGE_MAX_BYTES = 4 * 1024 * 1024;

    /**
     * From how many siblings an undeclared family reads as one object cut up
     * rather than as that many decor variants.
     */
    private const MIN_LOOSE_PIECES = 3;

    /** @var array<string, array<string, array{file: string, width: int, height: int}>> */
    private array $scans = [];

    /**
     * Palette et images : toutes les tuiles posables (taille ~50x50) de
     * chaque couche. Les grandes images (fonds de plan, météo) en sont
     * écartées — voir backgroundChoices().
     *
     * @param string[] $layers
     * @return array{catalog: array<string, string[]>, images: array<string, string>}
     */
    public function buildCatalog(array $layers): array
    {
        $catalog = [];
        $images = [];

        foreach ($layers as $layer) {
            $names = [];
            // La couche resources garde img/walls — voir layerImageDir()
            $dir = TiledMapService::layerImageDir($layer);

            foreach ($this->scanImages('img/' . $dir) as $name => $image) {
                if (!$this->isTileSized($image)) {
                    continue;
                }
                $names[] = $name;
                $images[$layer . '/' . $name] = 'img/' . $dir . '/' . $image['file'];
            }

            sort($names);
            $catalog[$layer] = $names;
        }

        return ['catalog' => $catalog, 'images' => $images];
    }

    /**
     * Structures multi-tuiles : grande image découpée en morceaux
     * « base-NN » (convention des convert.sh historiques : row-major depuis
     * le coin haut-gauche). Détection : img/<couche>/<base>/<base>.png
     * (l'originale entière) + tous les morceaux à la racine. Les offsets
     * publiés sont relatifs à la case d'ancrage (bas-gauche), en
     * coordonnées jeu (y vers le haut) — l'extension n'a aucune convention
     * à connaître.
     *
     * @param string[] $layers
     * @return array<string, array<int, array{name: string, image: string, width: int, height: int, pieces: array<int, array{name: string, dx: int, dy: int}>}>>
     */
    public function buildComposites(array $layers): array
    {
        $composites = [];
        $sprites = new \App\Service\Map\CompositeSpriteService();
        $catalogue = null;

        foreach ($layers as $layer) {
            $imageDir = TiledMapService::layerImageDir($layer);
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $imageDir;

            if (!is_dir($dir)) {
                continue;
            }

            /* The cut-out catalogue, not a lucky asset. Scanning for a
             * whole-object image only ever found 25 families of ~130, knew
             * one of the three naming conventions, and refused holed figures
             * — so an anvil reached the palette as nine loose pieces. */
            $catalogue ??= (new \App\Service\Map\EntityTypeFootprintService())->catalogue();
            $onDisk = $this->piecesByFamily($dir);

            foreach ($catalogue as $family => $footprint) {
                if ($footprint->isSingleCell() || !isset($onDisk[$family])) {
                    continue;
                }

                $image = $sprites->spriteFor($imageDir, (string) $family, $footprint, $onDisk[$family]);

                if ($image === null) {
                    continue;
                }

                $composites[$layer][] = [
                    'name'   => $family,
                    'image'  => $image,
                    'width'  => $footprint->width(),
                    'height' => $footprint->height(),
                    'pieces' => $this->pieceOffsets($footprint, $onDisk[$family]),
                ];
            }
        }

        return $composites;
    }

    /**
     * Names that are a piece of a figure rather than an object of their own.
     *
     * The palette is meant to offer things you place in one go. A family cut
     * into pieces is one object, so its pieces belong beside the palette, not
     * in it — whether or not the whole is composable today.
     *
     * A lone trailing digit also marks decor variants (`arbre1`, `arbre2`),
     * which are two objects, not one cut in two. A known cut-out settles it;
     * failing that, a family counts as cut up only from MIN_LOOSE_PIECES on,
     * where reading it as variants stops being plausible.
     *
     * @param array<string, \App\Service\Map\Footprint> $catalogue known cut-outs
     * @return list<string>
     */
    public function loosePieces(string $layer, array $catalogue): array
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . TiledMapService::layerImageDir($layer);

        if (!is_dir($dir)) {
            return [];
        }

        $pieces = [];

        foreach ($this->piecesByFamily($dir) as $family => $images) {
            $known = isset($catalogue[$family]) && !$catalogue[$family]->isSingleCell();

            if (!$known && count($images) < self::MIN_LOOSE_PIECES) {
                continue;
            }

            foreach ($images as $image) {
                $pieces[] = pathinfo($image, PATHINFO_FILENAME);
            }
        }

        sort($pieces);

        return $pieces;
    }

    /**
     * Piece images present in a layer's folder, by family then piece index.
     *
     * @return array<string, array<int, string>>
     */
    private function piecesByFamily(string $dir): array
    {
        $families = [];
        $webDir = 'img/' . basename($dir);

        foreach (glob($dir . '/*.png') ?: [] as $file) {
            $base = pathinfo($file, PATHINFO_FILENAME);
            [$family, $index] = \App\Service\Map\SceneryFootprintDeriver::splitPiece($base);
            $families[$family][$index] = $webDir . '/' . $base . '.png';
        }

        return $families;
    }

    /**
     * The pieces Tiled pushes back, with their offsets from the anchor.
     *
     * Only the pieces that exist: a figure whose art is incomplete still
     * places what it has rather than being dropped from the palette.
     *
     * @param array<int, string> $pieceImages
     * @return list<array{name: string, dx: int, dy: int}>
     */
    private function pieceOffsets(\App\Service\Map\Footprint $footprint, array $pieceImages): array
    {
        $pieces = [];

        foreach ($footprint->offsets() as $index => [$dx, $dy]) {
            if (!isset($pieceImages[$index])) {
                continue;
            }

            $pieces[] = [
                'name' => pathinfo($pieceImages[$index], PATHINFO_FILENAME),
                'dx'   => $dx,
                'dy'   => $dy,
            ];
        }

        return $pieces;
    }

    /**
     * Fonds/ambiances candidats pour bg et mask : les grandes images de
     * img/tiles (fonds de plan, brume, tempêtes), justement celles écartées
     * du catalogue de tuiles.
     *
     * @return string[]
     */
    public function backgroundChoices(): array
    {
        $choices = [];

        foreach ($this->scanImages('img/tiles') as $image) {
            if (!$this->isTileSized($image)) {
                $choices[] = 'img/tiles/' . $image['file'];
            }
        }

        sort($choices);

        return $choices;
    }

    /**
     * Scan mémoïsé d'un répertoire d'images : nom → fichier + dimensions.
     * Une seule passe getimagesize par répertoire et par requête.
     *
     * @return array<string, array{file: string, width: int, height: int}>
     */
    private function scanImages(string $relativeDir): array
    {
        if (isset($this->scans[$relativeDir])) {
            return $this->scans[$relativeDir];
        }

        $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . $relativeDir;
        $result = [];

        foreach (is_dir($dir) ? scandir($dir) : [] as $fileName) {
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $name = pathinfo($fileName, PATHINFO_FILENAME);

            if (!in_array($ext, self::IMAGE_EXTENSIONS, true)
                || !preg_match(self::ASSET_NAME_PATTERN, $name)
                || isset($result[$name])
            ) {
                continue;
            }

            $size = @getimagesize($dir . '/' . $fileName);
            if (!$size) {
                continue;
            }

            $result[$name] = ['file' => $fileName, 'width' => $size[0], 'height' => $size[1]];
        }

        return $this->scans[$relativeDir] = $result;
    }

    /** @param array{width: int, height: int} $image */
    private function isTileSized(array $image): bool
    {
        return $image['width'] <= self::TILE_MAX_SIZE && $image['height'] <= self::TILE_MAX_SIZE;
    }
}
