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

    /** Taille maximale d'une image poussée depuis l'éditeur (fonds compris) */
    public const IMAGE_MAX_BYTES = 4 * 1024 * 1024;

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

            foreach ($this->scanImages('img/' . $layer) as $name => $image) {
                if (!$this->isTileSized($image)) {
                    continue;
                }
                $names[] = $name;
                $images[$layer . '/' . $name] = 'img/' . $layer . '/' . $image['file'];
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

        foreach ($layers as $layer) {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $layer;

            foreach (is_dir($dir) ? scandir($dir) : [] as $base) {
                if ($base === '.' || $base === '..'
                    || !preg_match(self::ASSET_NAME_PATTERN, $base)
                    || !is_file($dir . '/' . $base . '/' . $base . '.png')
                ) {
                    continue;
                }

                $size = @getimagesize($dir . '/' . $base . '/' . $base . '.png');
                $tileSize = TiledMapService::TILE_SIZE;
                if (!$size || $size[0] % $tileSize !== 0 || $size[1] % $tileSize !== 0) {
                    continue;
                }

                $width = (int) ($size[0] / $tileSize);
                $height = (int) ($size[1] / $tileSize);
                if ($width * $height < 2) {
                    continue;
                }

                // Tous les morceaux doivent exister à la racine (rendu du jeu)
                $pieces = [];
                for ($i = 0; $i < $width * $height; $i++) {
                    $piece = sprintf('%s-%02d', $base, $i);
                    if (!file_exists($dir . '/' . $piece . '.png')) {
                        continue 2;
                    }
                    // index row-major haut-gauche → offset jeu depuis l'ancre bas-gauche
                    $row = intdiv($i, $width);
                    $pieces[] = [
                        'name' => $piece,
                        'dx'   => $i % $width,
                        'dy'   => $height - 1 - $row,
                    ];
                }

                $composites[$layer][] = [
                    'name'   => $base,
                    'image'  => 'img/' . $layer . '/' . $base . '/' . $base . '.png',
                    'width'  => $width,
                    'height' => $height,
                    'pieces' => $pieces,
                ];
            }
        }

        return $composites;
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
     * Toutes les images que l'éditeur peut synchroniser : fichiers à la
     * racine de chaque img/<couche authorable> (tuiles ET grandes images —
     * fonds/masques de plan) plus l'originale des structures composites
     * (img/<couche>/<base>/<base>.<ext>). Chemins relatifs triés.
     *
     * @return string[]
     */
    public function listImagePaths(): array
    {
        $paths = [];

        foreach (array_keys(TiledMapService::AUTHORABLE_LAYERS) as $layer) {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/img/' . $layer;

            foreach (is_dir($dir) ? scandir($dir) : [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                if (is_dir($dir . '/' . $entry)) {
                    if (!preg_match(self::ASSET_NAME_PATTERN, $entry)) {
                        continue;
                    }
                    foreach (self::IMAGE_EXTENSIONS as $ext) {
                        if (is_file($dir . '/' . $entry . '/' . $entry . '.' . $ext)) {
                            $paths[] = 'img/' . $layer . '/' . $entry . '/' . $entry . '.' . $ext;
                        }
                    }
                    continue;
                }

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, self::IMAGE_EXTENSIONS, true)
                    && preg_match(self::ASSET_NAME_PATTERN, pathinfo($entry, PATHINFO_FILENAME))
                ) {
                    $paths[] = 'img/' . $layer . '/' . $entry;
                }
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Chemin absolu d'une image de l'éditeur, ou null si le chemin sort du
     * cadre autorisé : img/<couche authorable>/<nom>.<ext> ou, pour
     * l'originale d'une structure composite, img/<couche>/<base>/<base>.<ext>
     * (le sous-dossier doit porter le nom de l'image — ce qui interdit au
     * passage tout segment de traversée). Le fichier peut ne pas exister :
     * la même validation sert au téléchargement et à l'upload.
     */
    public function resolveImagePath(string $path): ?string
    {
        $segments = explode('/', $path);
        $count = count($segments);

        if ($count < 3 || $count > 4 || $segments[0] !== 'img'
            || !array_key_exists($segments[1], TiledMapService::AUTHORABLE_LAYERS)
        ) {
            return null;
        }

        $file = $segments[$count - 1];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $name = pathinfo($file, PATHINFO_FILENAME);

        if (!in_array($ext, self::IMAGE_EXTENSIONS, true)
            || !preg_match(self::ASSET_NAME_PATTERN, $name)
            || ($count === 4 && $segments[2] !== $name)
        ) {
            return null;
        }

        return $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
    }

    /** Le contenu est-il une image d'un format supporté (magic bytes) ? */
    public static function looksLikeImage(string $bytes): bool
    {
        return str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP')
            || str_starts_with($bytes, 'GIF87a')
            || str_starts_with($bytes, 'GIF89a');
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
