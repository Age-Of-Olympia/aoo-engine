<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\PlanZLevel;
use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

/**
 * Configuration de plan (tables plans / plan_z_levels, ex
 * datas/private/plans/<plan>.json) éditée depuis Tiled et l'admin.
 * Écriture inerte en simulation (SimulationGuard — un flush() Doctrine
 * passerait sous ses chokepoints, on vérifie donc ici).
 *
 * La validation d'un payload (parse) est séparée de la persistance
 * (write) : l'orchestration du push valide TOUT avant d'écrire quoi que
 * ce soit — un 400 ne peut pas survenir après le commit des couches.
 */
class PlanConfigService
{
    /**
     * Clés éditables depuis Tiled. Toutes voyagent en chaîne côté éditeur
     * ('' = clé absente/retirée) ; le type pilote la validation/cast à
     * l'écriture (parse) et la mise en chaîne à la lecture (read).
     * `json` = structure éditée en texte JSON (biomes).
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
        /* Fractionnaire dans les données réelles (0.2 s) : float, pas int. */
        'scrollingMask'     => 'float',
        'verticalScrolling' => 'bool',
        /* Plus éditable depuis l'admin : les rendements vivent dans
         * `race_harvest`. La clé reste la graine que le seed des rendements
         * relit (plans.biomes). */
        'biomes'            => 'json',

        /* Ombres du plan — une grotte se veut plus sombre qu'une plaine, et
         * un plan de glace plus bleu. Absentes, les valeurs du tableau de
         * bord admin s'appliquent (CellShadeService). L'INTENSITÉ, elle,
         * reste sur la case : `coords.shade`. */
        'shade_step'        => 'float',
        'shade_max'         => 'int',
        'shade_color'       => 'string',
    ];

    /** Sentinelle interne de parse() : remettre la clé à son défaut */
    private const REMOVE = "\0remove";

    private ?EntityManagerInterface $em;

    public function __construct(?EntityManagerInterface $em = null)
    {
        $this->em = $em;
    }

    private function em(): EntityManagerInterface
    {
        return $this->em ??= EntityManagerFactory::getEntityManager();
    }

    private function find(string $plan): ?Plan
    {
        return $this->em()->getRepository(Plan::class)->findOneBy(['slug' => $plan]);
    }

    /** Ligne existante, ou nouvelle ligne minimale (name = slug), non flushée. */
    private function findOrCreate(string $plan): Plan
    {
        $entity = $this->find($plan);
        if ($entity === null) {
            $entity = new Plan($plan, $plan);
            $this->em()->persist($entity);
        }

        return $entity;
    }

    /** Persiste et invalide le cache de lecture. */
    private function flush(string $plan): void
    {
        $this->em()->flush();
        PlanService::forget($plan);
    }

    /**
     * Position (x, y) du territoire sur la carte du monde olympia, ou null
     * si le plan n'y figure pas (donjon hors grille).
     *
     * @return array{x: ?int, y: ?int}
     */
    public function readPosition(string $plan): array
    {
        $entity = $this->find($plan);

        return [
            'x' => $entity?->getX(),
            'y' => $entity?->getY(),
        ];
    }

    /** @return array<string, string> valeurs courantes, en chaînes */
    public function read(string $plan): array
    {
        $entity = $this->find($plan);

        $values = [];
        foreach (self::PLAN_CONFIG_KEYS as $key => $type) {
            $values[$key] = $this->valueToString($type, $entity === null ? null : $this->getKey($entity, $key));
        }
        if ($entity === null) {
            // Un plan sans ligne se présente par son slug, comme l'ancien repli fichier
            $values['name'] = $plan;
        }

        return $values;
    }

    /**
     * Valide et caste un payload d'édition, sans rien écrire.
     *
     * @param array<string, mixed> $config clé => valeur chaîne
     * @return array<string, mixed> valeurs prêtes à écrire (REMOVE = défaut)
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

    /** Écrit un payload préalablement validé par parse(). Inerte en simulation. */
    public function write(string $plan, array $parsed): void
    {
        if (SimulationGuard::isActive()) {
            return;
        }

        $entity = $this->findOrCreate($plan);

        foreach ($parsed as $key => $value) {
            $this->setKey($entity, $key, $value === self::REMOVE ? null : $value);
        }

        $this->flush($plan);
    }

    /**
     * Configuration complète du plan dans la forme de l'ancien fichier JSON
     * (y compris z_levels — hors clés au défaut), ou null s'il n'existe pas.
     * Pendant de replace() pour l'export de bundle.
     *
     * @return array<string, mixed>|null
     */
    public function readFull(string $plan): ?array
    {
        $data = plans()->read($plan);

        return is_object($data) ? json_decode(json_encode($data), true) : null;
    }

    /**
     * Copie la configuration d'un plan vers un autre (niveaux z compris),
     * avec surcharges (name, shortName…). Une source sans ligne retombe sur
     * le minimum — le clone d'un plan « coords seulement » produit une
     * config minimale. Utilisé par le clonage admin
     * ({@see PlanAdminService::clonePlan}) et les instances de tutoriel.
     *
     * @param array<string, mixed> $overrides clé => valeur écrite telle quelle
     */
    public function copy(string $sourcePlan, string $targetPlan, array $overrides = []): void
    {
        $source = $this->readFull($sourcePlan) ?? ['name' => $sourcePlan];

        $this->applyFull($targetPlan, array_merge($source, $overrides));
    }

    /**
     * Remplace la configuration entière d'un plan (import de bundle : le
     * payload porte tout l'ancien fichier, contrairement au diff par clés de
     * write()).
     *
     * @param array<string, mixed> $json
     */
    public function replace(string $plan, array $json): void
    {
        $this->applyFull($plan, $json);
    }

    /** Écrase toute la configuration d'un plan depuis la forme JSON legacy. */
    private function applyFull(string $plan, array $json): void
    {
        if (SimulationGuard::isActive()) {
            return;
        }

        $entity = $this->findOrCreate($plan);

        $entity->setName(trim((string) ($json['name'] ?? '')) !== '' ? (string) $json['name'] : $plan);
        foreach (self::PLAN_CONFIG_KEYS as $key => $type) {
            if ($key === 'name') {
                continue;
            }
            $value = $json[$key] ?? null;
            if ($type === 'bool') {
                /* La forme legacy est déjà booléenne ; player_visibility
                 * absent vaut « visible ». */
                $value = $key === 'player_visibility'
                    ? (!array_key_exists($key, $json) || $json[$key] !== false)
                    : !empty($value);
            }
            $this->setKey($entity, $key, $value);
        }

        $entity->setVisibleByDefault(!empty($json['visibleByDefault']));

        if (isset($json['visibleBoundsMinX'], $json['visibleBoundsMaxX'], $json['visibleBoundsMinY'], $json['visibleBoundsMaxY'])) {
            $entity->setVisibleBounds(
                (int) $json['visibleBoundsMinX'],
                (int) $json['visibleBoundsMaxX'],
                (int) $json['visibleBoundsMinY'],
                (int) $json['visibleBoundsMaxY']
            );
        } else {
            $entity->setVisibleBounds(null, null, null, null);
        }

        /* Niveaux : mise à jour en place (jamais delete + re-insert du même
         * (plan_id, z) — Doctrine ordonne les INSERT avant les DELETE dans un
         * flush, la contrainte unique claquerait). */
        $desired = [];
        foreach ($json['z_levels'] ?? [] as $level) {
            $level = (array) $level;
            if (isset($level['z']) && is_numeric($level['z'])) {
                $desired[(int) $level['z']] ??= $level;
            }
        }

        foreach ($entity->getZLevels()->toArray() as $existing) {
            if (!isset($desired[$existing->getZ()])) {
                $entity->removeZLevel($existing);
            }
        }
        foreach ($desired as $z => $level) {
            $zLevel = $entity->getZLevel($z);
            if ($zLevel === null) {
                $zLevel = new PlanZLevel($entity, $z);
                $entity->addZLevel($zLevel);
            }
            $zLevel->setName((string) ($level['z-name'] ?? 'Niveau ' . $z));
            $zLevel->setMapUnavailable(!empty($level['MapUnavailable']));
            if (!$zLevel->isMapUnavailable()
                && isset($level['visibleBoundsMinX'], $level['visibleBoundsMaxX'], $level['visibleBoundsMinY'], $level['visibleBoundsMaxY'])
            ) {
                $zLevel->setVisibleBounds(
                    (int) $level['visibleBoundsMinX'],
                    (int) $level['visibleBoundsMaxX'],
                    (int) $level['visibleBoundsMinY'],
                    (int) $level['visibleBoundsMaxY']
                );
            } else {
                $zLevel->setVisibleBounds(null, null, null, null);
            }
        }

        $this->flush($plan);
    }

    /**
     * Configuration d'un niveau z pour l'éditeur : nom affiché, drapeau
     * « pas de carte » et bornes visibles (« auto » quand recalculées au push).
     *
     * @return array{name: string, mapUnavailable: string, bounds: string}
     */
    public function readZLevel(string $plan, int $z): array
    {
        $level = $this->find($plan)?->getZLevel($z);

        // Bornes « auto » par défaut (recalculées au push) ; si la base en
        // porte déjà d'explicites, on les montre « minX,maxX,minY,maxY »
        // pour que l'admin les voie et puisse les ajuster
        $bounds = 'auto';
        if ($level !== null && $level->hasVisibleBounds()) {
            $bounds = $level->getVisibleBoundsMinX() . ',' . $level->getVisibleBoundsMaxX() . ',' .
                $level->getVisibleBoundsMinY() . ',' . $level->getVisibleBoundsMaxY();
        }

        return [
            'name'           => $level?->getName() ?? '',
            'mapUnavailable' => ($level !== null && $level->isMapUnavailable()) ? 'true' : 'false',
            'bounds'         => $bounds,
        ];
    }

    /**
     * Écrit la configuration éditable d'un niveau z (nom, MapUnavailable) et
     * recale ses bornes visibles sur l'étendue réelle — sauf niveau marqué
     * MapUnavailable, qui n'a pas de carte. Crée l'entrée au besoin.
     *
     * @param array<string, mixed>                             $zConfig clé => valeur chaîne (issue des propriétés du groupe)
     * @param array{minX: int, maxX: int, minY: int, maxY: int}|null $bounds étendue du niveau, null si vide
     */
    public function writeZLevel(string $plan, int $z, array $zConfig, ?array $bounds): void
    {
        if (SimulationGuard::isActive()) {
            return;
        }

        $entity = $this->findOrCreate($plan);

        $level = $entity->getZLevel($z);
        if ($level === null) {
            $level = new PlanZLevel($entity, $z);
            $entity->addZLevel($level);
        }

        if (isset($zConfig['name'])) {
            $name = trim((string) $zConfig['name']);
            $level->setName($name !== '' ? $name : 'Niveau ' . $z);
        } elseif ($level->getName() === '') {
            $level->setName('Niveau ' . $z);
        }

        $mapUnavailable = isset($zConfig['mapUnavailable'])
            ? in_array(strtolower((string) $zConfig['mapUnavailable']), ['true', '1'], true)
            : $level->isMapUnavailable();

        $level->setMapUnavailable($mapUnavailable);
        if ($mapUnavailable) {
            $level->setVisibleBounds(null, null, null, null);
        } else {
            // Bornes explicites « minX,maxX,minY,maxY » saisies par l'admin,
            // sinon « auto » → étendue réelle du contenu ($bounds)
            $chosen = $this->parseBounds((string) ($zConfig['bounds'] ?? 'auto')) ?? $bounds;
            if ($chosen !== null) {
                $level->setVisibleBounds($chosen['minX'], $chosen['maxX'], $chosen['minY'], $chosen['maxY']);
            }
        }

        $this->flush($plan);
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
     * Retire l'entrée d'un niveau — le pendant config de la suppression
     * d'une ligne de niveau (sinon la ligne réapparaît dans l'éditeur via
     * l'union coords ∪ config). No-op si absente.
     */
    public function removeZLevel(string $plan, int $z): void
    {
        if (SimulationGuard::isActive()) {
            return;
        }

        $level = $this->find($plan)?->getZLevel($z);
        if ($level === null) {
            return;
        }

        $level->getPlan()->removeZLevel($level);
        $this->flush($plan);
    }

    /**
     * Bilan de santé de la config du plan (PlanJsonValidator), remonté dans
     * le rapport de push. Vide si tout va bien.
     *
     * @param list<string>|null $knownItemNames précharge les items (évite une requête par biome)
     * @return array{errors: string[], warnings: string[]}
     */
    public function validate(string $plan, ?object $db = null, ?array $knownItemNames = null): array
    {
        $data = plans()->read($plan);
        if (!is_object($data)) {
            return ['errors' => [], 'warnings' => []];
        }

        $result = PlanJsonValidator::validate($data, $plan, $db, $knownItemNames);

        return ['errors' => $result['errors'], 'warnings' => $result['warnings']];
    }

    /** Valeur d'une clé éditable sur l'entité, null quand au défaut. */
    private function getKey(Plan $entity, string $key): mixed
    {
        return match ($key) {
            'name'              => $entity->getName(),
            'shortName'         => $entity->getShortName(),
            'x'                 => $entity->getX(),
            'y'                 => $entity->getY(),
            'player_visibility' => $entity->isPlayerVisibility(),
            'pnj'               => $entity->getPnj(),
            'size'              => $entity->getSize(),
            'bg'                => $entity->getBg(),
            'mask'              => $entity->getMask(),
            'scrollingMask'     => $entity->getScrollingMask(),
            'verticalScrolling' => $entity->isVerticalScrolling(),
            'biomes'            => $entity->getBiomes(),
            'shade_step'        => $entity->getShadeStep(),
            'shade_max'         => $entity->getShadeMax(),
            'shade_color'       => $entity->getShadeColor(),
            default             => throw new RuntimeException('Propriété de plan inconnue : ' . $key, 400),
        };
    }

    /** Écrit une clé éditable sur l'entité ; null = retour au défaut. */
    private function setKey(Plan $entity, string $key, mixed $value): void
    {
        switch ($key) {
            case 'name':
                $entity->setName($value !== null && trim((string) $value) !== '' ? (string) $value : $entity->getSlug());
                break;
            case 'shortName':
                $entity->setShortName($value === null ? null : (string) $value);
                break;
            case 'x':
                $entity->setX($value === null ? null : (int) $value);
                break;
            case 'y':
                $entity->setY($value === null ? null : (int) $value);
                break;
            case 'player_visibility':
                // Clé retirée = « visible », le défaut historique
                $entity->setPlayerVisibility($value === null ? true : (bool) $value);
                break;
            case 'pnj':
                $entity->setPnj($value === null ? null : (int) $value);
                break;
            case 'size':
                $entity->setSize($value === null ? null : (int) $value);
                break;
            case 'bg':
                $entity->setBg($value === null ? null : (string) $value);
                break;
            case 'mask':
                $entity->setMask($value === null ? null : (string) $value);
                break;
            case 'scrollingMask':
                $entity->setScrollingMask($value === null ? null : (float) $value);
                break;
            case 'verticalScrolling':
                $entity->setVerticalScrolling((bool) $value);
                break;
            case 'biomes':
                $entity->setBiomes($value === null ? null : (array) $value);
                break;
            case 'shade_step':
                $entity->setShadeStep($value === null ? null : (float) $value);
                break;
            case 'shade_max':
                $entity->setShadeMax($value === null ? null : (int) $value);
                break;
            case 'shade_color':
                $entity->setShadeColor($value === null ? null : (string) $value);
                break;
            default:
                throw new RuntimeException('Propriété de plan inconnue : ' . $key, 400);
        }
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

    /** Pendant de castValue : valeur de la base → chaîne pour l'éditeur */
    private function valueToString(string $type, mixed $value): string
    {
        return match (true) {
            $value === null  => '',
            $type === 'bool' => $value ? 'true' : 'false',
            $type === 'json' => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            default          => (string) $value,
        };
    }
}
