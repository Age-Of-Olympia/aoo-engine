<?php

namespace App\Service;

use App\Entity\BuildingType;
use App\Entity\Race;
use App\Entity\SceneryType;
use App\Factory\EntityManagerFactory;
use App\Service\Map\BoardChanges;
use App\Service\Map\EntitySpriteService;
use App\Service\Map\StructureTypeService;
use Classes\Player;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Renames the technical name of a building or decor type (races.name) and
 * every place that spells it out: placed instances, the item that builds it,
 * legacy map layers, plan biomes, footprints, and the image files named
 * after it.
 *
 * The rows change in one transaction, then the files move; a file that
 * cannot move puts the others back and the rows roll back.
 */
final class TypeRenameService
{
    /**
     * Type names PHP code still spells out (god altars, the bank lifecycle):
     * renaming them would silently break that code. Debt: these lookups
     * should read a flag or a role on the type, and this list disappear.
     */
    public const HARD_CODED_NAMES = [...BuildingService::GOD_TYPES, FactionChestService::BANK];

    private const NAME_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /** Folders under img/ that hold files named after a type. */
    private const IMAGE_DIRS = ['walls', 'foregrounds', 'plants', 'routes', 'avatars', 'portraits', 'items'];

    /** Legacy map layers whose rows name a type or one of its pieces. */
    private const LAYERS = ['resources', 'plants', 'routes', 'foregrounds'];

    /** Columns holding the type name as their whole value. */
    private const EXACT_COLUMNS = [
        ['players', 'race'],
        ['tutorial_npcs', 'race'],
        ['items', 'name'],
        ['items', 'requires_building'],
        ['craft_recipes', 'name'],
        ['entity_type_footprints', 'type_name'],
    ];

    /** Columns holding an image path that may sit under a renamed file or folder. */
    private const PATH_COLUMNS = [
        ['players', 'avatar'],
        ['players', 'portrait'],
        ['tutorial_npcs', 'avatar'],
        ['tutorial_npcs', 'portrait'],
    ];

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    private ?EntityManagerInterface $entityManager;
    private string $root;

    public function __construct(?EntityManagerInterface $entityManager = null, ?string $root = null)
    {
        $this->entityManager = $entityManager;
        $this->root = $root ?? (($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2));
    }

    /** The kinds of type this service renames: buildings and decor. */
    public static function handles(Race $race): bool
    {
        return $race instanceof BuildingType || $race instanceof SceneryType;
    }

    /** A handled type, unless PHP code spells its name. */
    public static function isRenamable(Race $race): bool
    {
        return self::handles($race) && !in_array($race->getName(), self::HARD_CODED_NAMES, true);
    }

    /**
     * @return array{name: string, references: array<string, int>, files: list<string>, warnings: list<string>}
     * @throws RuntimeException code 400, 404 (unknown type) or 409 (name taken)
     */
    public function rename(string $from, string $to): array
    {
        $race = $this->em()->getRepository(Race::class)->findOneBy(['name' => $from]);
        if ($race === null) {
            throw new RuntimeException("Type introuvable : {$from}.", 404);
        }
        $from = $race->getName();
        $to = strtolower(trim($to));
        $this->assertRenamable($race, $to);

        $conn = $this->em()->getConnection();
        $moves = $this->fileMoves($from, $to);
        $this->assertFree($conn, $to, $moves);

        $placedIds = $conn->fetchFirstColumn('SELECT id FROM players WHERE race = ?', [$from]);
        $conn->beginTransaction();
        try {
            $conn->executeStatement('UPDATE races SET name = ?, code = ? WHERE id = ?', [$to, strtoupper($to), $race->getId()]);
            $references = array_filter(
                $this->renameRows($conn, $from, $to) + $this->renameStoredPaths($conn, $moves)
            );
            $this->moveFiles($moves);
            $conn->commit();
        } catch (\Throwable $exception) {
            $conn->rollBack();
            throw $exception;
        }

        $this->em()->refresh($race);
        $this->forgetCaches($placedIds);

        return [
            'name'       => $to,
            'references' => $references,
            'files'      => array_map(fn (string $target): string => $this->relative($target), array_values($moves)),
            'warnings'   => $this->warnings($conn, $from),
        ];
    }

    private function assertRenamable(Race $race, string $to): void
    {
        if (!self::handles($race)) {
            throw new RuntimeException('Seuls les types de bâtiment et de décor se renomment ici.', 400);
        }
        if (!self::isRenamable($race)) {
            throw new RuntimeException("« {$race->getName()} » est écrit en dur dans le code : renommage refusé.", 400);
        }
        if (!preg_match(self::NAME_PATTERN, $to)) {
            throw new RuntimeException('Nom invalide (minuscules, chiffres, _ ; commence par une lettre).', 400);
        }
        if (in_array($to, self::HARD_CODED_NAMES, true)) {
            throw new RuntimeException("« {$to} » est réservé : écrit en dur dans le code.", 400);
        }
        if ($to === $race->getName()) {
            throw new RuntimeException('Le nouveau nom est identique.', 400);
        }
    }

    /** @param array<string, string> $moves */
    private function assertFree(Connection $conn, string $to, array $moves): void
    {
        $taken = [
            'SELECT 1 FROM races WHERE name = ?'                         => "Le type ou la race « {$to} » existe déjà.",
            'SELECT 1 FROM items WHERE name = ?'                         => "Un objet s'appelle déjà « {$to} » : il deviendrait l'objet de construction du type.",
            // Footprints may exist for a name that is no type yet.
            'SELECT 1 FROM entity_type_footprints WHERE type_name = ?'   => "Une découpe est déjà déclarée pour « {$to} ».",
        ];
        foreach ($taken as $sql => $message) {
            if ($conn->fetchOne($sql, [$to]) !== false) {
                throw new RuntimeException($message, 409);
            }
        }
        foreach ($moves as $target) {
            if (file_exists($target)) {
                throw new RuntimeException('Le fichier ' . $this->relative($target) . ' existe déjà.', 409);
            }
        }
    }

    /**
     * Every row spelling the type name, keyed "table.column" (or layer).
     *
     * @return array<string, int>
     */
    private function renameRows(Connection $conn, string $from, string $to): array
    {
        $counts = [];
        foreach (self::EXACT_COLUMNS as [$table, $column]) {
            $counts[$table . '.' . $column] = (int) $conn->executeStatement(
                "UPDATE {$table} SET {$column} = ? WHERE {$column} = ?",
                [$to, $from]
            );
        }
        foreach (self::LAYERS as $layer) {
            $counts['map_' . $layer] = $this->renameLayerRows($conn, $layer, $from, $to);
        }
        $counts['plans.biomes'] = $this->renameInBiomes($conn, $from, $to);
        $counts['outcome_instructions'] = $this->renameInPlaceStructure($conn, $from, $to);

        return $counts;
    }

    /** Rows naming the type or one of its pieces/variants, as the file names do. */
    private function renameLayerRows(Connection $conn, string $layer, string $from, string $to): int
    {
        $pattern = self::piecePattern($from, '', 'i');
        $names = $conn->fetchFirstColumn(
            "SELECT DISTINCT name FROM map_{$layer} WHERE LEFT(name, ?) = ?",
            [strlen($from), $from]
        );

        $n = 0;
        foreach ($names as $name) {
            if (preg_match($pattern, (string) $name, $m)) {
                $n += (int) $conn->executeStatement("UPDATE map_{$layer} SET name = ? WHERE name = ?", [$to . $m[1], $name]);
            }
        }

        return $n;
    }

    /** Plan biomes list type names at any depth of their JSON. */
    private function renameInBiomes(Connection $conn, string $from, string $to): int
    {
        $n = 0;
        foreach ($conn->fetchAllAssociative('SELECT slug, biomes FROM plans WHERE LOCATE(?, biomes) > 0', ['"' . $from . '"']) as $row) {
            $biomes = json_decode((string) $row['biomes'], true);
            if (!is_array($biomes)) {
                continue;
            }
            $edited = $biomes;
            array_walk_recursive($edited, static function (mixed &$value) use ($from, $to): void {
                $value = $value === $from ? $to : $value;
            });
            if ($edited !== $biomes) {
                $n += (int) $conn->executeStatement('UPDATE plans SET biomes = ? WHERE slug = ?', [json_encode($edited, self::JSON_FLAGS), $row['slug']]);
            }
        }

        return $n;
    }

    /** "Construire" actions name the type they place in their parameters. */
    private function renameInPlaceStructure(Connection $conn, string $from, string $to): int
    {
        $n = 0;
        foreach ($conn->fetchAllAssociative("SELECT id, parameters FROM outcome_instructions WHERE type = 'placestructure' AND LOCATE(?, parameters) > 0", [$from]) as $row) {
            $params = json_decode((string) $row['parameters'], true);
            if (is_array($params) && ($params['type'] ?? null) === $from) {
                $params['type'] = $to;
                $n += (int) $conn->executeStatement('UPDATE outcome_instructions SET parameters = ? WHERE id = ?', [json_encode($params, self::JSON_FLAGS), $row['id']]);
            }
        }

        return $n;
    }

    /**
     * Stored image paths follow their file or folder, in move order.
     *
     * @param array<string, string> $moves
     * @return array<string, int>
     */
    private function renameStoredPaths(Connection $conn, array $moves): array
    {
        $counts = [];
        foreach ($moves as $source => $target) {
            $slash = is_dir($source) ? '/' : '';
            $old = $this->relative($source) . $slash;
            $new = $this->relative($target) . $slash;
            foreach (self::PATH_COLUMNS as [$table, $column]) {
                $key = $table . '.' . $column;
                $counts[$key] = ($counts[$key] ?? 0) + (int) $conn->executeStatement(
                    "UPDATE {$table} SET {$column} = CONCAT(?, SUBSTRING({$column}, ?)) WHERE LEFT({$column}, ?) = ?",
                    [$new, strlen($old) + 1, strlen($old), $old]
                );
            }
        }

        return $counts;
    }

    /**
     * All or nothing: a move that fails puts the earlier ones back.
     *
     * @param array<string, string> $moves
     */
    private function moveFiles(array $moves): void
    {
        $done = [];
        foreach ($moves as $source => $target) {
            if (!@rename($source, $target)) {
                foreach (array_reverse($done, true) as $movedFrom => $movedTo) {
                    @rename($movedTo, $movedFrom);
                }
                throw new RuntimeException('Renommage du fichier impossible : ' . $this->relative($source));
            }
            $done[$source] = $target;
        }
    }

    /**
     * Files and folders named after the type, source => target, in the order
     * they must move: a type's own folder first, then the files inside it.
     *
     * @return array<string, string> absolute paths
     */
    private function fileMoves(string $from, string $to): array
    {
        $pattern = self::piecePattern($from, '\.[a-z0-9]+');
        $moves = [];
        foreach (self::IMAGE_DIRS as $dir) {
            $base = $this->root . '/img/' . $dir;
            $moves += $this->namedFiles($base, $base, $pattern, $to);
            $moves += $this->namedFiles($base . '/_composed', $base . '/_composed', $pattern, $to);

            if (is_dir($base . '/' . $from)) {
                $moves[$base . '/' . $from] = $base . '/' . $to;
                // Listed from the source, renamed once the folder has moved.
                $moves += $this->namedFiles($base . '/' . $from, $base . '/' . $to, $pattern, $to);
            }
        }

        return $moves;
    }

    /**
     * Files of $folder matching the type, keyed by their path in $targetFolder.
     *
     * @return array<string, string>
     */
    private function namedFiles(string $folder, string $targetFolder, string $pattern, string $to): array
    {
        $moves = [];
        foreach (is_dir($folder) ? scandir($folder) : [] as $file) {
            if (preg_match($pattern, $file, $m) && is_file($folder . '/' . $file)) {
                $moves[$targetFolder . '/' . $file] = $targetFolder . '/' . $to . $m[1];
            }
        }

        return $moves;
    }

    /**
     * The type, a piece (_NN or -NN), then a variant (wound sprite, open door,
     * item miniature), then $tail — captured after the name. Never a longer
     * name sharing the prefix (mur_bois_petrifie is not mur_bois).
     */
    private static function piecePattern(string $from, string $tail, string $flags = ''): string
    {
        return '/^' . preg_quote($from, '/') . '((?:[-_]\d{1,2})?(?:_(?:broken|open|mini))?' . $tail . ')$/' . $flags;
    }

    /** @param list<int|string> $placedIds */
    private function forgetCaches(array $placedIds): void
    {
        RaceService::clearCache();
        StructureTypeService::forget();
        EntitySpriteService::forget();
        BoardChanges::world();
        foreach ($placedIds as $id) {
            @unlink(Player::cachePath((int) $id, '.json'));
            json()->forget('players', (string) $id);
        }
    }

    /**
     * What the rename leaves alone on purpose, to check by hand.
     *
     * @return list<string>
     */
    private function warnings(Connection $conn, string $from): array
    {
        $warnings = [];
        if ($conn->fetchOne('SELECT 1 FROM dialogs WHERE name = ?', [$from]) !== false) {
            $warnings[] = "Le dialogue « {$from} » garde son nom (les dialogues ont leur propre code).";
        }
        $warnings[] = "Les cartes Tiled (.tmx) et l'extension Tiled écrivent encore « {$from} ».";

        return $warnings;
    }

    private function relative(string $path): string
    {
        return ltrim(substr($path, strlen($this->root)), '/');
    }

    private function em(): EntityManagerInterface
    {
        return $this->entityManager ??= EntityManagerFactory::getEntityManager();
    }
}
