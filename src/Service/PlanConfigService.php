<?php

namespace App\Service;

use Classes\Json;
use RuntimeException;

/**
 * Configuration de plan (datas/private/plans/<plan>.json) éditée depuis
 * Tiled. Lecture via Classes\Json (commentaires tolérés, repli
 * public/privé) ; écriture via Json::write_json (inerte en simulation).
 *
 * La validation d'un payload (parse) est séparée de la persistance
 * (write) : l'orchestration du push valide TOUT avant d'écrire quoi que
 * ce soit — un 400 ne peut pas survenir après le commit des couches.
 */
class PlanConfigService
{
    /**
     * Clés du JSON de plan éditables depuis Tiled. Toutes voyagent en
     * chaîne côté éditeur ('' = clé absente/retirée) ; le type pilote la
     * validation/cast à l'écriture (parse) et la mise en chaîne à la
     * lecture (read). `json` = structure éditée en texte JSON (biomes).
     */
    public const PLAN_CONFIG_KEYS = [
        'name'              => 'string',
        'shortName'         => 'string',
        'x'                 => 'int',
        'y'                 => 'int',
        'player_visibility' => 'bool',
        'pnj'               => 'int',
        'size'              => 'int',
        'bg'                => 'image',
        'mask'              => 'image',
        'scrollingMask'     => 'int',
        'verticalScrolling' => 'bool',
        'biomes'            => 'json',

        /* Ombres du plan — une grotte se veut plus sombre qu'une plaine, et
         * un plan de glace plus bleu. Absentes, les valeurs du tableau de
         * bord admin s'appliquent (CellShadeService). L'INTENSITÉ, elle,
         * reste sur la case : `coords.shade`. */
        'shade_step'        => 'float',
        'shade_max'         => 'int',
        'shade_color'       => 'string',
    ];

    /** Sentinelle interne de parse() : retirer la clé du JSON */
    private const REMOVE = "\0remove";

    /**
     * Position (x, y) du territoire sur la carte du monde olympia, ou null
     * si le plan n'y figure pas (donjon hors grille).
     *
     * @return array{x: ?int, y: ?int}
     */
    public function readPosition(string $plan): array
    {
        $json = $this->load($plan);

        return [
            'x' => isset($json['x']) && is_numeric($json['x']) ? (int) $json['x'] : null,
            'y' => isset($json['y']) && is_numeric($json['y']) ? (int) $json['y'] : null,
        ];
    }

    /** @return array<string, string> valeurs courantes, en chaînes */
    public function read(string $plan): array
    {
        $json = $this->load($plan);

        $values = [];
        foreach (self::PLAN_CONFIG_KEYS as $key => $type) {
            $values[$key] = $this->valueToString($type, $json[$key] ?? null);
        }

        return $values;
    }

    /**
     * Valide et caste un payload d'édition, sans rien écrire.
     *
     * @param array<string, mixed> $config clé => valeur chaîne
     * @return array<string, mixed> valeurs prêtes à écrire (REMOVE = retirer)
     * @throws RuntimeException code 400 sur clé inconnue ou valeur invalide
     */
    public function parse(array $config): array
    {
        $parsed = [];

        foreach ($config as $key => $raw) {
            $type = self::PLAN_CONFIG_KEYS[$key] ?? null;
            if ($type === null || !is_scalar($raw)) {
                throw new RuntimeException('Propriété de plan inconnue : ' . $key, 400);
            }

            $raw = trim((string) $raw);
            $parsed[$key] = $raw === '' ? self::REMOVE : $this->castValue($key, $type, $raw);
        }

        return $parsed;
    }

    /** Écrit un payload préalablement validé par parse(). */
    public function write(string $plan, array $parsed): void
    {
        $json = $this->load($plan);

        foreach ($parsed as $key => $value) {
            if ($value === self::REMOVE) {
                unset($json[$key]);
            } else {
                $json[$key] = $value;
            }
        }

        $this->save($plan, $json);
    }

    /**
     * Fichier JSON complet du plan (y compris z_levels, exits… hors clés
     * éditables), ou null s'il n'existe pas / est invalide. Pendant de
     * replace() pour l'export de bundle.
     *
     * @return array<string, mixed>|null
     */
    public function readFull(string $plan): ?array
    {
        $data = json()->decode('plans', $plan);

        return is_object($data) ? json_decode(json_encode($data), true) : null;
    }

    /**
     * Copie le JSON d'un plan vers un autre, avec surcharges (name,
     * shortName…). Une source sans fichier retombe sur le minimum de load()
     * — le clone d'un plan « coords seulement » produit un JSON minimal.
     * Utilisé par le clonage admin ({@see PlanAdminService::clonePlan}).
     *
     * @param array<string, mixed> $overrides clé => valeur écrite telle quelle
     */
    public function copy(string $sourcePlan, string $targetPlan, array $overrides = []): void
    {
        $this->save($targetPlan, array_merge($this->load($sourcePlan), $overrides));
    }

    /**
     * Remplace le fichier JSON entier d'un plan (import de bundle : le
     * payload porte tout le fichier, contrairement au diff par clés de
     * write()).
     *
     * @param array<string, mixed> $json
     */
    public function replace(string $plan, array $json): void
    {
        $this->save($plan, $json);
    }

    /**
     * Recale les bornes visibles d'un niveau z (sauf niveau marqué
     * MapUnavailable). Crée l'entrée z_levels manquante — plus de bornes à
     * maintenir à la main.
     *
     * @param array{minX: int, maxX: int, minY: int, maxY: int} $bounds
     */
    /**
     * Configuration d'un niveau z pour l'éditeur : nom affiché, drapeau
     * « pas de carte » et bornes visibles (« auto » quand recalculées au push).
     *
     * @return array{name: string, mapUnavailable: string, bounds: string}
     */
    public function readZLevel(string $plan, int $z): array
    {
        $level = $this->findZLevel($this->load($plan), $z);

        // Bornes « auto » par défaut (recalculées au push) ; si le JSON en
        // porte déjà d'explicites, on les montre « minX,maxX,minY,maxY »
        // pour que l'admin les voie et puisse les ajuster
        $bounds = 'auto';
        if (isset($level['visibleBoundsMinX'], $level['visibleBoundsMaxX'], $level['visibleBoundsMinY'], $level['visibleBoundsMaxY'])) {
            $bounds = $level['visibleBoundsMinX'] . ',' . $level['visibleBoundsMaxX'] . ',' .
                $level['visibleBoundsMinY'] . ',' . $level['visibleBoundsMaxY'];
        }

        return [
            'name'           => (string) ($level['z-name'] ?? ''),
            'mapUnavailable' => !empty($level['MapUnavailable']) ? 'true' : 'false',
            'bounds'         => $bounds,
        ];
    }

    /**
     * Écrit la configuration éditable d'un niveau z (nom, MapUnavailable) et
     * recale ses bornes visibles sur l'étendue réelle — sauf niveau marqué
     * MapUnavailable, qui n'a pas de carte. Crée l'entrée z_levels au besoin.
     *
     * @param array<string, mixed>                             $zConfig clé => valeur chaîne (issue des propriétés du groupe)
     * @param array{minX: int, maxX: int, minY: int, maxY: int}|null $bounds étendue du niveau, null si vide
     */
    public function writeZLevel(string $plan, int $z, array $zConfig, ?array $bounds): void
    {
        $json = $this->load($plan);
        $json['z_levels'] ??= [];

        $index = null;
        foreach ($json['z_levels'] as $i => $level) {
            if (isset($level['z']) && (int) $level['z'] === $z) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            $json['z_levels'][] = ['z' => $z];
            $index = array_key_last($json['z_levels']);
        }

        $level = &$json['z_levels'][$index];
        $level['z'] = $z;

        if (isset($zConfig['name'])) {
            $name = trim((string) $zConfig['name']);
            $level['z-name'] = $name !== '' ? $name : 'Niveau ' . $z;
        } elseif (!isset($level['z-name'])) {
            $level['z-name'] = 'Niveau ' . $z;
        }

        $mapUnavailable = isset($zConfig['mapUnavailable'])
            ? in_array(strtolower((string) $zConfig['mapUnavailable']), ['true', '1'], true)
            : !empty($level['MapUnavailable']);

        if ($mapUnavailable) {
            $level['MapUnavailable'] = true;
            unset($level['visibleBoundsMinX'], $level['visibleBoundsMaxX'], $level['visibleBoundsMinY'], $level['visibleBoundsMaxY']);
        } else {
            unset($level['MapUnavailable']);

            // Bornes explicites « minX,maxX,minY,maxY » saisies par l'admin,
            // sinon « auto » → étendue réelle du contenu ($bounds)
            $chosen = $this->parseBounds($zConfig['bounds'] ?? 'auto') ?? $bounds;
            if ($chosen !== null) {
                $level['visibleBoundsMinX'] = $chosen['minX'];
                $level['visibleBoundsMaxX'] = $chosen['maxX'];
                $level['visibleBoundsMinY'] = $chosen['minY'];
                $level['visibleBoundsMaxY'] = $chosen['maxY'];
            }
        }

        $this->save($plan, $json);
    }

    /**
     * « minX,maxX,minY,maxY » → bornes, ou null pour « auto »/vide.
     *
     * @return array{minX: int, maxX: int, minY: int, maxY: int}|null
     * @throws RuntimeException code 400 si non vide et mal formé
     */
    private function parseBounds(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '' || strtolower($raw) === 'auto') {
            return null;
        }

        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) !== 4 || count(array_filter($parts, 'is_numeric')) !== 4) {
            throw new RuntimeException('Bornes du niveau invalides (attendu « minX,maxX,minY,maxY » ou « auto ») : ' . $raw, 400);
        }

        return [
            'minX' => (int) $parts[0], 'maxX' => (int) $parts[1],
            'minY' => (int) $parts[2], 'maxY' => (int) $parts[3],
        ];
    }

    /**
     * Retire l'entrée z_levels d'un niveau — le pendant config de la
     * suppression d'une ligne de niveau (sinon la ligne réapparaît dans
     * l'éditeur via l'union DB ∪ JSON). No-op si absente.
     */
    public function removeZLevel(string $plan, int $z): void
    {
        $json = $this->load($plan);
        if (empty($json['z_levels'])) {
            return;
        }

        $kept = array_values(array_filter(
            $json['z_levels'],
            static fn(array $level): bool => !isset($level['z']) || (int) $level['z'] !== $z
        ));

        if (count($kept) !== count($json['z_levels'])) {
            $json['z_levels'] = $kept;
            $this->save($plan, $json);
        }
    }

    /** @return array<string, mixed> entrée z_levels du niveau, ou [] */
    private function findZLevel(array $json, int $z): array
    {
        foreach ($json['z_levels'] ?? [] as $level) {
            if (isset($level['z']) && (int) $level['z'] === $z) {
                return $level;
            }
        }

        return [];
    }

    /**
     * Bilan de santé du JSON de plan (PlanJsonValidator), remonté dans le
     * rapport de push. Vide si tout va bien.
     *
     * @param list<string>|null $knownItemNames précharge les items (évite une requête par biome)
     * @return array{errors: string[], warnings: string[]}
     */
    public function validate(string $plan, ?object $db = null, ?array $knownItemNames = null): array
    {
        $data = json()->decode('plans', $plan);
        if (!is_object($data)) {
            return ['errors' => [], 'warnings' => []];
        }

        $result = PlanJsonValidator::validate($data, $plan, $db, $knownItemNames);

        return ['errors' => $result['errors'], 'warnings' => $result['warnings']];
    }

    /** @throws RuntimeException code 400 */
    private function castValue(string $key, string $type, string $raw): mixed
    {
        switch ($type) {
            case 'int':
                if (!is_numeric($raw)) {
                    throw new RuntimeException($key . ' doit être un entier : ' . $raw, 400);
                }
                return (int) $raw;

            case 'float':
                if (!is_numeric($raw)) {
                    throw new RuntimeException($key . ' doit être un nombre : ' . $raw, 400);
                }
                return (float) $raw;

            case 'bool':
                $lower = strtolower($raw);
                if (!in_array($lower, ['true', '1', 'false', '0'], true)) {
                    throw new RuntimeException($key . ' doit valoir true ou false : ' . $raw, 400);
                }
                return in_array($lower, ['true', '1'], true);

            case 'image':
                if (!preg_match('#^img/[a-z]+/[a-zA-Z0-9_.-]+$#', $raw)
                    || !file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $raw)
                ) {
                    throw new RuntimeException($key . ' : image introuvable : ' . $raw, 400);
                }
                return $raw;

            case 'json':
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException($key . ' : JSON invalide', 400);
                }
                return $decoded;

            default:
                return mb_substr($raw, 0, 255);
        }
    }

    /** Pendant de castValue : valeur du JSON → chaîne pour l'éditeur */
    private function valueToString(string $type, mixed $value): string
    {
        return match (true) {
            $value === null  => '',
            $type === 'bool' => $value ? 'true' : 'false',
            $type === 'json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default          => (string) $value,
        };
    }

    /** @return array<string, mixed> */
    private function load(string $plan): array
    {
        $data = json()->decode('plans', $plan);

        return is_object($data)
            ? json_decode(json_encode($data), true)
            : ['name' => $plan];
    }

    private function save(string $plan, array $json): void
    {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/datas/private/plans';
        if (!is_dir($dir)) {
            throw new RuntimeException('Répertoire des plans introuvable : ' . $dir, 500);
        }

        Json::write_json(
            'datas/private/plans/' . $plan . '.json',
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
        );

        // sans quoi les lectures suivantes de la même requête (validation,
        // bornes du niveau suivant) verraient l'ancien contenu
        json()->forget('plans', $plan);
    }
}
