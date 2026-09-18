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
     * Éléments posables : une image dans img/elements ET un effet du
     * catalogue (exigence d'Element::put et de l'application au pas —
     * un élément sans effet ne ferait rien).
     *
     * @return list<string>
     */
    public function placeableNames(): array
    {
        $effectService = new EffectService();

        $names = [];
        $pattern = '/img/elements/*.{' . implode(',', TileCatalogService::IMAGE_EXTENSIONS) . '}';
        foreach (glob($this->root() . $pattern, GLOB_BRACE) ?: [] as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if ($effectService->exists($name)) {
                $names[] = $name;
            }
        }
        sort($names);

        return array_values(array_unique($names));
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
                "Élément « {$name} » inconnu — il faut une image img/elements et un effet du même nom au catalogue."
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
