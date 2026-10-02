<?php

namespace App\Service;

use Classes\Db;
use Classes\Element;
use RuntimeException;

/**
 * Éléments posés sur les cases (map_elements) — LE lien case ↔ effet :
 * marcher sur une case applique l'effet du même nom (Player::go →
 * add_effect ; l'élément et l'effet partagent leur nom, même convention
 * que le Saignement des races). Socle du panneau admin
 * « Cartes → Éléments » : inventaire par plan, pose (durée ou
 * permanent — endTime = 0, que le cron horaire delete_elements ne purge
 * jamais) et retrait. La pose passe par Element::put, qui tient les
 * règles de la case : un seul élément, et un sol dessous.
 */
class MapElementService
{
    /**
     * Placeable elements: every image in img/elements. The effect of the
     * same name, when one exists, is applied on step; otherwise the
     * element is decor.
     *
     * @return list<string>
     */
    public function placeableNames(): array
    {
        $names = [];
        $pattern = '/img/elements/*.{' . implode(',', TileCatalogService::IMAGE_EXTENSIONS) . '}';
        foreach (glob($this->root() . $pattern, GLOB_BRACE) ?: [] as $file) {
            $names[] = pathinfo($file, PATHINFO_FILENAME);
        }
        sort($names);

        return array_values(array_unique($names));
    }

    /** @var array<string, array{effect: ?string, fluid: bool, label: string, duration: int, value: int}>|null element_types, read once per request */
    private static ?array $types = null;

    /** Placeable elements that do apply an effect on step. @return list<string> */
    public function namesWithEffect(): array
    {
        return array_values(array_filter($this->placeableNames(), fn(string $name) => $this->effectOf($name) !== null));
    }

    /**
     * The effect stepping on this element applies: the one its type names
     * (element_types), else the effect of the same name. Null = decor.
     */
    public function effectOf(string $element): ?string
    {
        $types = self::types();
        $effect = isset($types[$element]) ? $types[$element]['effect'] : $element;

        return $effect !== null && (new EffectService())->exists($effect) ? $effect : null;
    }

    /** Turns the effect lasts once stepped on; a type without a row: one. */
    public function effectDurationOf(string $element): int
    {
        return self::types()[$element]['duration'] ?? 1;
    }

    /** Intensity the effect lands with; a type without a row: one. */
    public function effectValueOf(string $element): int
    {
        return self::types()[$element]['value'] ?? 1;
    }

    /** Duration in turns (0 = until next turn, -1 = endless) and intensity (≥ 1). */
    public function setEffectStrength(string $element, int $duration, int $value): void
    {
        (new Db())->exe(
            'INSERT INTO element_types (name, effect_name, effect_duration, effect_value)
             SELECT ?, (SELECT name FROM effects WHERE name = ?), ?, ?
             ON DUPLICATE KEY UPDATE effect_duration = VALUES(effect_duration), effect_value = VALUES(effect_value)',
            [$element, $element, max(-1, $duration), max(1, $value)]
        );
        self::clearCache();
    }

    /** Whether the element blends with its neighbours (edge fades, elbows); a type without a row does. */
    public function isFluid(string $element): bool
    {
        return self::types()[$element]['fluid'] ?? true;
    }

    /** Name shown to players: the type's label, else its code. */
    public function labelOf(string $element): string
    {
        $label = self::types()[$element]['label'] ?? '';

        return $label !== '' ? $label : $element;
    }

    public function setLabel(string $element, string $label): void
    {
        (new Db())->exe(
            'INSERT INTO element_types (name, effect_name, label)
             SELECT ?, (SELECT name FROM effects WHERE name = ?), ?
             ON DUPLICATE KEY UPDATE label = VALUES(label)',
            [$element, $element, $label]
        );
        self::clearCache();
    }

    /** @return array<string, array{effect: ?string, fluid: bool, label: string, duration: int, value: int}> */
    private static function types(): array
    {
        if (self::$types === null) {
            self::$types = [];
            $res = (new Db())->exe('SELECT name, effect_name, fluid, label, effect_duration, effect_value FROM element_types');
            while ($row = $res->fetch_object()) {
                self::$types[(string) $row->name] = ['effect' => $row->effect_name, 'fluid' => (bool) $row->fluid,
                    'label' => (string) $row->label, 'duration' => (int) $row->effect_duration,
                    'value' => (int) $row->effect_value];
            }
        }

        return self::$types;
    }

    public static function clearCache(): void
    {
        self::$types = null;
    }

    /** Names the effect an element type applies; null makes it decor. */
    public function setEffect(string $element, ?string $effect): void
    {
        if ($effect !== null && !(new EffectService())->exists($effect)) {
            throw new RuntimeException("Effet « {$effect} » inconnu.");
        }

        (new Db())->exe(
            'INSERT INTO element_types (name, effect_name) VALUE (?, ?)
             ON DUPLICATE KEY UPDATE effect_name = VALUES(effect_name)',
            [$element, $effect]
        );
        self::clearCache();
    }

    public function setFluid(string $element, bool $fluid): void
    {
        /* A new row keeps the implicit effect (the one of the same name),
         * as effectOf() gives it to a type without a row. */
        (new Db())->exe(
            'INSERT INTO element_types (name, effect_name, fluid)
             SELECT ?, (SELECT name FROM effects WHERE name = ?), ?
             ON DUPLICATE KEY UPDATE fluid = VALUES(fluid)',
            [$element, $element, (int) $fluid]
        );
        self::clearCache();
    }

    /** Construction over an element follows its effect; decor blocks. */
    public function isBuildableOver(string $element): bool
    {
        $effect = $this->effectOf($element);

        return $effect !== null && (new EffectService())->isBuildableOver($effect);
    }

    /** Chemin web de l'image d'un élément, ou '' si absente. */
    public function imagePath(string $name): string
    {
        foreach (TileCatalogService::IMAGE_EXTENSIONS as $extension) {
            if (is_file($this->root() . '/img/elements/' . $name . '.' . $extension)) {
                return 'img/elements/' . $name . '.' . $extension;
            }
        }

        return '';
    }

    /**
     * Inventaire d'un plan — les traces de pas (bruit du moteur, une par
     * déplacement) sont exclues sauf demande explicite.
     *
     * @return list<array{id: int, name: string, x: int, y: int, z: int, endTime: int, rotation: int}>
     */
    public function listByPlan(string $plan): array
    {
        $sql = 'SELECT me.id, me.name, me.endTime, me.rotation, c.x, c.y, c.z
                FROM map_elements me
                JOIN coords c ON c.id = me.coords_id
                WHERE c.plan = ?
                ORDER BY me.name, c.x, c.y, c.z';

        $rows = [];
        $res = (new Db())->exe($sql, [$plan]);
        while ($row = $res->fetch_object()) {
            $rows[] = [
                'id' => (int) $row->id, 'name' => (string) $row->name,
                'x' => (int) $row->x, 'y' => (int) $row->y, 'z' => (int) $row->z,
                'endTime' => (int) $row->endTime, 'rotation' => (int) $row->rotation,
            ];
        }

        return $rows;
    }

    /**
     * Pose un élément sur une case (upsert : reposer prolonge).
     *
     * $durationTurns s'écrit en TOURS, comme les durées d'effet, et se
     * vit en temps réel : un élément n'appartient à aucun joueur, donc
     * aucun tour ne le décrémente — c'est le cron horaire qui l'efface.
     * La conversion passe par le tour de référence (18 h). null =
     * permanent (endTime 0, jamais purgé).
     */
    public function place(string $name, int $x, int $y, int $z, string $plan, ?int $durationTurns, int $rotation = 0): void
    {
        if (!in_array($rotation, TiledMapService::ROTATIONS, true)) {
            throw new RuntimeException('Rotation invalide : 0, 90, 180 ou 270.');
        }
        if (!in_array($name, $this->placeableNames(), true)) {
            throw new RuntimeException(
                "Élément « {$name} » inconnu — il faut une image dans img/elements."
            );
        }

        $db = new Db();
        $coordsId = $db->exe(
            'SELECT id FROM coords WHERE x = ? AND y = ? AND z = ? AND plan = ?',
            [$x, $y, $z, $plan]
        )->fetch_object()->id ?? null;
        if ($coordsId === null) {
            throw new RuntimeException(
                "Case ({$x},{$y},{$z}) inexistante sur le plan « {$plan} » (aucune entrée coords)."
            );
        }

        if (!Element::put($name, (int) $coordsId, $durationTurns ?? Element::DURATION_INFINITE, $rotation)) {
            throw new RuntimeException(
                "Case ({$x},{$y},{$z}) : pas de sol, ou un autre élément l'occupe déjà — une case n'en porte qu'un."
            );
        }
    }

    /**
     * Lays a mark (map_marks): a footstep, the flag. No effect, so several
     * share a cell and one sits on water. Never purges the cached boards —
     * the step that leaves a footstep already purges its two cells.
     */
    public function putMark(string $name, int $coordsId, int $turns): void
    {
        (new Db())->exe(
            'INSERT INTO map_marks (`name`, `coords_id`, `endTime`) VALUE (?, ?, ?)
             ON DUPLICATE KEY UPDATE endTime = VALUES(endTime)',
            [$name, $coordsId, time() + ($turns * TurnScheduleService::referenceTurnSeconds())]
        );
    }

    public function remove(int $id): void
    {
        (new Db())->exe('DELETE FROM map_elements WHERE id = ?', [$id]);
    }

    /** @param list<int> $ids */
    public function removeMany(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }

        return (int) (new Db())->exe(
            'DELETE FROM map_elements WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids,
            false,
            true
        );
    }

    /**
     * Purge des éléments et des marques expirés — le travail du cron
     * horaire delete_elements, déclenchable à la main depuis le panneau
     * (tous plans confondus, comme le cron). endTime 0 (permanent) survit.
     */
    public function purgeExpired(): int
    {
        $db = new Db();
        $purged = 0;
        foreach (['map_elements', 'map_marks'] as $table) {
            $purged += (int) $db->exe(
                'DELETE FROM ' . $table . ' WHERE endTime != 0 AND endTime <= ?',
                [time()],
                false,
                true
            );
        }

        return $purged;
    }

    private function root(): string
    {
        return ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
    }
}
