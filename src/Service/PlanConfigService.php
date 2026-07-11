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
    ];

    /** Sentinelle interne de parse() : retirer la clé du JSON */
    private const REMOVE = "\0remove";

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
     * Recale les bornes visibles d'un niveau z (sauf niveau marqué
     * MapUnavailable). Crée l'entrée z_levels manquante — plus de bornes à
     * maintenir à la main.
     *
     * @param array{minX: int, maxX: int, minY: int, maxY: int} $bounds
     */
    public function writeZLevelBounds(string $plan, int $z, array $bounds): void
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

        if ($index !== null && !empty($json['z_levels'][$index]['MapUnavailable'])) {
            return;
        }

        if ($index === null) {
            $json['z_levels'][] = ['z' => $z, 'z-name' => 'Niveau ' . $z];
            $index = array_key_last($json['z_levels']);
        }

        $level = &$json['z_levels'][$index];
        $level['visibleBoundsMinX'] = $bounds['minX'];
        $level['visibleBoundsMaxX'] = $bounds['maxX'];
        $level['visibleBoundsMinY'] = $bounds['minY'];
        $level['visibleBoundsMaxY'] = $bounds['maxY'];

        $this->save($plan, $json);
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
