<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use App\Service\TiledMapService;
use Doctrine\DBAL\Connection;

/**
 * La découpe des décors multi-cases, dérivée de la carte elle-même.
 *
 * Un fort, une pyramide, un géant occupent plusieurs cases. Rien ne le dit :
 * on pose autant de morceaux indépendants, nommés `base-00`, `base-01`… et
 * seule leur adjacence les relie. Ce service reconstruit l'objet.
 *
 * # Pourquoi pas les fichiers d'images
 *
 * `TileCatalogService::buildComposites()` le fait déjà, depuis le DISQUE :
 * l'image entière divisée par 50 donne w×h, et tous les morceaux doivent
 * exister sous `base-NN.png`. Deux raisons de ne pas s'en servir ici.
 *
 * D'abord il n'en couvre qu'une minorité : **442 fichiers de morceaux sont
 * nommés `_NN` contre 120 en `-NN`**, et il ne connaît que la seconde forme.
 * `unique_fort_turok` porte `_02`…`_16` — il n'a donc aucune tuile composite.
 *
 * Ensuite il refuse les figures TROUÉES : un morceau manquant et la famille
 * entière est écartée. Or elles existent — le géant pétrifié occupe 4 cases
 * dans une boîte de 3×3.
 *
 * La carte, elle, dit ce qui est réellement posé.
 *
 * # Le piège du regroupement
 *
 * Ce n'est PAS la connexité. Deux exemplaires collés d'un même décor sont
 * adjacents et fusionneraient : sur la production, trois groupes avalent
 * 28 géants à elles trois, dont une de 29 cases pour 13 géants.
 *
 * Le critère est **« connexes ET indices de morceaux tous distincts »** : un
 * groupe qui contient deux fois `-00` contient deux objets.
 *
 * # L'ancre
 *
 * C'est le PREMIER MORCEAU, pas le coin de la boîte englobante. Une figure
 * trouée n'a pas forcément de case à son coin bas-gauche — c'est ce qui
 * rendait l'ancre fausse pour cinq familles, et le problème disparaît avec ce
 * choix, sans cas particulier.
 */
final class SceneryFootprintDeriver
{
    /**
     * La connexion n'est ouverte QUE si on interroge la carte.
     *
     * `imageFootprints()` ne lit que le disque, et la palette de l'éditeur
     * s'en contente sur un déploiement sans base — comme un test unitaire.
     * Exiger une connexion pour lire des images était une dépendance de trop.
     */
    private ?Connection $conn;

    /**
     * Le balayage de la carte coûte 68 ms en production, celui du disque une
     * poignée : les redemander à chaque appel se payait cher. Un clic sur
     * « Compléter » enchaîne inspect() puis complete(), qui rappelle
     * inspect() — trois catalogues pour un geste.
     *
     * Mémoïsation par INSTANCE et non statique : la carte change entre deux
     * tests, et un cache global les ferait mentir.
     *
     * @var array<string, array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>,instances:int,truncated:int}>|null
     */
    private ?array $mapCache = null;

    /** @var array<string, array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>}>|null */
    private ?array $imageCache = null;

    /** @var array<string, array<int, string>>|null */
    private ?array $diskCache = null;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn;
    }

    /** La connexion, ouverte au premier besoin. */
    private function conn(): Connection
    {
        return $this->conn ??= EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Sépare un nom de décor en (famille, indice de morceau).
     *
     * Trois conventions coexistent sur le disque et dans la carte : `-NN`,
     * `_NN`, et le chiffre collé au nom. Un décor sans suffixe est une famille
     * à lui seul, morceau 0.
     *
     * @return array{0: string, 1: int}
     */
    public static function splitPiece(string $name): array
    {
        if (preg_match('/^(.*?)[-_](\d{1,2})$/', $name, $m)) {
            return [$m[1], (int) $m[2]];
        }

        if (preg_match('/^(.*?[a-z])(\d{1,2})$/', $name, $m)) {
            return [$m[1], (int) $m[2]];
        }

        return [$name, 0];
    }

    /**
     * Toutes les découpes dérivables, par famille.
     *
     * @return array<string, array{
     *     w: int, h: int, cells: int, holed: bool,
     *     offsets: array<int, array{0:int,1:int}>,
     *     instances: int, truncated: int
     * }>
     */
    public function derive(): array
    {
        if ($this->mapCache !== null) {
            return $this->mapCache;
        }

        $catalogue = [];

        foreach ($this->families() as $family => $cells) {
            $pieces = array_values(array_unique(array_column($cells, 'piece')));

            if (count($pieces) < 2) {
                continue; /* une seule pièce : rien à découper */
            }

            $groups = $this->groupsIn($cells);
            $model = $this->completeModel($groups, count($pieces));

            if ($model === null) {
                continue; /* aucun exemplaire complet : la découpe n'est pas dérivable */
            }

            [$instances, $truncated] = $this->countInstances($groups, count($pieces));
            $offsets = $this->offsetsFrom($model);

            $xs = array_column($offsets, 0);
            $ys = array_column($offsets, 1);
            $w = max($xs) - min($xs) + 1;
            $h = max($ys) - min($ys) + 1;

            $catalogue[$family] = [
                'w'         => $w,
                'h'         => $h,
                'cells'     => count($offsets),
                'holed'     => count($offsets) < $w * $h,
                'offsets'   => $offsets,
                'instances' => $instances,
                'truncated' => $truncated,
            ];
        }

        ksort($catalogue);

        return $this->mapCache = $catalogue;
    }

    /**
     * Le catalogue que les ÉDITEURS consultent : la carte, complétée par les
     * images d'ensemble.
     *
     * `derive()` ne connaît que ce qui est posé — c'est ce qu'il faut pour un
     * rapport ou une migration, qui parlent de l'existant. Un éditeur, lui,
     * doit savoir poser une figure qui ne figure encore nulle part.
     *
     * L'ordre de préséance n'est pas neutre : **la carte l'emporte**. Les deux
     * sources se contredisent — l'image d'ensemble de `geant_petrifie` annonce
     * 1×2 cases quand quatre morceaux existent et que la carte en montre une
     * figure de 3×3 trouée. L'asset est incomplet ; ce qui est posé ne ment
     * pas.
     *
     * @return array<string, array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>}>
     */
    public function catalogue(): array
    {
        $catalogue = $this->derive();

        foreach ($this->imageFootprints() as $family => $footprint) {
            if (!isset($catalogue[$family])) {
                $catalogue[$family] = $footprint;
            }
        }

        ksort($catalogue);

        return $catalogue;
    }

    /**
     * Les découpes lisibles sur les images d'ensemble, `base/base.png`.
     *
     * Taille divisée par 50, morceaux rangés en lignes depuis le haut-gauche,
     * décalages ramenés au premier morceau — la convention que Tiled utilise
     * déjà.
     *
     * Une image trop petite pour ses morceaux est ÉCARTÉE plutôt que crue :
     * c'est le cas du géant, dont l'ensemble ne montre que le corps.
     *
     * @return array<string, array{w:int,h:int,cells:int,holed:bool,offsets:array<int,array{0:int,1:int}>}>
     */
    public function imageFootprints(): array
    {
        if ($this->imageCache !== null) {
            return $this->imageCache;
        }

        $root = self::foregroundsDir();

        if ($root === null) {
            return [];
        }

        $footprints = [];

        foreach ($this->piecesOnDisk() as $family => $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            $size = @getimagesize($root . $family . '/' . $family . '.png');

            if (!$size || $size[0] % TiledMapService::TILE_SIZE !== 0 || $size[1] % TiledMapService::TILE_SIZE !== 0) {
                continue;
            }

            $w = (int) ($size[0] / TiledMapService::TILE_SIZE);
            $h = (int) ($size[1] / TiledMapService::TILE_SIZE);

            if ($w * $h < count($indexes)) {
                continue; /* image incomplète : elle ne décrit pas cette figure */
            }

            $offsets = [];

            foreach (array_keys($indexes) as $piece) {
                $offsets[$piece] = [$piece % $w, $h - 1 - intdiv($piece, $w)];
            }

            $anchor = $offsets[array_key_first($offsets)];

            foreach ($offsets as $piece => [$dx, $dy]) {
                $offsets[$piece] = [$dx - $anchor[0], $dy - $anchor[1]];
            }

            $footprints[$family] = [
                'w' => $w, 'h' => $h,
                'cells' => count($offsets),
                'holed' => count($offsets) < $w * $h,
                'offsets' => $offsets,
            ];
        }

        return $this->imageCache = $footprints;
    }

    /**
     * Les morceaux présents sur le disque, par famille et par indice.
     *
     * C'est ce qui EXISTE, par opposition à ce qui est posé : une famille dont
     * aucun exemplaire ne figure sur la carte est ici, et c'est justement
     * celle qu'un animateur veut pouvoir régler.
     *
     * @return array<string, array<int, string>> famille → morceau → chemin web
     */
    public function piecesOnDisk(): array
    {
        if ($this->diskCache !== null) {
            return $this->diskCache;
        }

        $root = self::foregroundsDir();

        if ($root === null) {
            return $this->diskCache = [];
        }

        $pieces = [];

        foreach (glob($root . '*.png') ?: [] as $file) {
            $base = basename($file, '.png');
            [$family, $index] = self::splitPiece($base);
            $pieces[$family][$index] = '/img/foregrounds/' . $base . '.png';
        }

        foreach ($pieces as &$indexes) {
            ksort($indexes);
        }

        unset($indexes);
        ksort($pieces);

        return $this->diskCache = $pieces;
    }

    /**
     * Le dossier des décors, ou `null` s'il est absent.
     *
     * `DOCUMENT_ROOT` n'existe pas hors du web — console, migration, test. Le
     * dépôt, lui, est toujours à trois niveaux au-dessus de ce fichier : le
     * repli garde la même réponse partout, ce qui compte pour un catalogue que
     * l'éditeur ET la ligne de commande consultent.
     */
    private static function foregroundsDir(): ?string
    {
        $root = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/img/foregrounds/';

        if (!is_dir($root)) {
            $root = dirname(__DIR__, 3) . '/img/foregrounds/';
        }

        return is_dir($root) ? $root : null;
    }

    /**
     * Les familles dont AUCUN exemplaire n'est complet — découpe indérivable.
     *
     * Deux cas sur la production, et ils ne demandent pas la même réponse :
     * `lac_thetis` dont les suffixes `-04`/`-05` sont deux VARIANTES de lac et
     * non les moitiés d'une figure, et `triton_statue` qui mélange deux
     * conventions de nommage dans la même famille. Ni l'un ni l'autre ne se
     * devine : ils se tranchent.
     *
     * @return array<string, array{pieces: int, groups: int}>
     */
    public function undecidable(): array
    {
        $result = [];

        foreach ($this->families() as $family => $cells) {
            $pieces = array_values(array_unique(array_column($cells, 'piece')));

            if (count($pieces) < 2) {
                continue;
            }

            $groups = $this->groupsIn($cells);

            if ($this->completeModel($groups, count($pieces)) === null) {
                $result[$family] = ['pieces' => count($pieces), 'groups' => count($groups)];
            }
        }

        ksort($result);

        return $result;
    }

    /**
     * Les cases de la carte, groupées par famille.
     *
     * @return array<string, list<array{name: string, x: int, y: int, z: int, plan: string, piece: int}>>
     */
    private function families(): array
    {
        $families = [];

        foreach ($this->conn()->fetchAllAssociative(
            'SELECT f.name, c.x, c.y, c.z, c.plan
               FROM map_foregrounds f JOIN coords c ON c.id = f.coords_id'
        ) as $row) {
            [$family, $piece] = self::splitPiece((string) $row['name']);

            $families[$family][] = [
                'name'  => (string) $row['name'],
                'x'     => (int) $row['x'],
                'y'     => (int) $row['y'],
                'z'     => (int) $row['z'],
                'plan'  => (string) $row['plan'],
                'piece' => $piece,
            ];
        }

        return $families;
    }

    /**
     * Les groupes de cases qui se touchent, dans un ensemble.
     *
     * Le parcours lui-même vit dans TouchingCells, partagé avec le service qui
     * retrouve l'objet d'une case : c'était deux fois le même code, à un
     * critère d'arrêt près.
     *
     * @param list<array{x: int, y: int, z: int, plan: string, piece: int}> $cells
     * @return list<list<array{x: int, y: int, z: int, plan: string, piece: int}>>
     */
    private function groupsIn(array $cells): array
    {
        $byKey = [];

        foreach ($cells as $cell) {
            $byKey[TouchingCells::key($cell)] = $cell;
        }

        /** @var list<list<array{x: int, y: int, z: int, plan: string, piece: int}>> */
        return TouchingCells::groups($byKey);
    }

    /**
     * Un groupe portant TOUS les morceaux, chacun une seule fois.
     *
     * C'est la figure complète : celle qui sert de modèle au catalogue. Les
     * exemplaires tronqués ne peuvent pas la donner, et les agrégats non plus.
     *
     * @param list<list<array{piece: int, x: int, y: int}>> $groups
     * @return list<array{piece: int, x: int, y: int}>|null
     */
    private function completeModel(array $groups, int $pieceCount): ?array
    {
        foreach ($groups as $group) {
            $counts = array_count_values(array_column($group, 'piece'));

            if (max($counts) === 1 && count($counts) === $pieceCount) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Combien d'exemplaires, et combien sont tronqués.
     *
     * Un exemplaire tronqué est un groupe isolé à qui il manque des morceaux.
     * Un AGRÉGAT — plusieurs exemplaires collés — compte pour autant
     * d'exemplaires que son morceau le plus répété.
     *
     * @param list<list<array{piece: int}>> $groups
     * @return array{0: int, 1: int}
     */
    private function countInstances(array $groups, int $pieceCount): array
    {
        $instances = 0;
        $truncated = 0;

        foreach ($groups as $group) {
            $counts = array_count_values(array_column($group, 'piece'));
            $copies = max($counts);
            $instances += $copies;

            if ($copies === 1 && count($counts) < $pieceCount) {
                $truncated++;
            }
        }

        return [$instances, $truncated];
    }

    /**
     * Décalages relatifs au premier morceau.
     *
     * @param list<array{piece: int, x: int, y: int}> $model
     * @return array<int, array{0:int,1:int}>
     */
    private function offsetsFrom(array $model): array
    {
        usort($model, static fn (array $a, array $b): int => $a['piece'] <=> $b['piece']);

        $ax = $model[0]['x'];
        $ay = $model[0]['y'];
        $offsets = [];

        foreach ($model as $cell) {
            $offsets[$cell['piece']] = [$cell['x'] - $ax, $cell['y'] - $ay];
        }

        ksort($offsets);

        return $offsets;
    }
}
