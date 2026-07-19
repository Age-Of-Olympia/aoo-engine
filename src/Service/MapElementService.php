<?php

namespace App\Service;

use Classes\Db;
use RuntimeException;

/**
 * Éléments posés sur les cases (map_elements) — LE lien case ↔ effet :
 * marcher sur une case applique l'effet du même nom (Player::go →
 * add_effect ; l'élément et l'effet partagent leur nom, même convention
 * que le Saignement des races). Socle du panneau admin
 * « Cartes → Éléments » : inventaire par plan, pose (durée ou
 * permanent — endTime = 0, que le cron horaire delete_elements ne purge
 * jamais) et retrait. Complète Element::put, réservé aux durées finies.
 */
class MapElementService
{
    /**
     * Éléments posables : une image dans img/elements ET un effet du
     * catalogue (exigence d'Element::put et de l'application au pas —
     * un élément sans effet ne ferait rien). Les traces de pas, sans
     * effet, en sont naturellement exclues.
     *
     * @return list<string>
     */
    public function placeableNames(): array
    {
        $effectService = new EffectService();

        $names = [];
        foreach (glob($this->root() . '/img/elements/*.{png,webp,gif}', GLOB_BRACE) ?: [] as $file) {
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
        foreach (['png', 'webp', 'gif'] as $extension) {
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
     * @return list<array{id: int, name: string, x: int, y: int, z: int, endTime: int}>
     */
    public function listByPlan(string $plan, bool $withFootprints = false): array
    {
        $sql = "SELECT me.id, me.name, me.endTime, c.x, c.y, c.z
                FROM map_elements me
                JOIN coords c ON c.id = me.coords_id
                WHERE c.plan = ?"
            . ($withFootprints ? '' : " AND me.name NOT LIKE 'trace_pas_%'")
            . ' ORDER BY me.name, c.x, c.y, c.z';

        $rows = [];
        $res = (new Db())->exe($sql, [$plan]);
        while ($row = $res->fetch_object()) {
            $rows[] = [
                'id' => (int) $row->id, 'name' => (string) $row->name,
                'x' => (int) $row->x, 'y' => (int) $row->y, 'z' => (int) $row->z,
                'endTime' => (int) $row->endTime,
            ];
        }

        return $rows;
    }

    /**
     * Pose un élément sur une case (upsert : reposer prolonge).
     * $durationSeconds null = permanent (endTime 0, jamais purgé).
     */
    public function place(string $name, int $x, int $y, int $z, string $plan, ?int $durationSeconds): void
    {
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

        $db->exe(
            'INSERT INTO map_elements (`name`, `coords_id`, `endTime`) VALUE (?, ?, ?)
             ON DUPLICATE KEY UPDATE endTime = VALUES(endTime)',
            [$name, (int) $coordsId, $durationSeconds === null ? 0 : time() + $durationSeconds]
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
     * Purge des éléments expirés — le travail du cron horaire
     * delete_elements, déclenchable à la main depuis le panneau (tous
     * plans confondus, comme le cron). endTime 0 (permanent) survit.
     */
    public function purgeExpired(): int
    {
        return (int) (new Db())->exe(
            'DELETE FROM map_elements WHERE endTime != 0 AND endTime <= ?',
            [time()],
            false,
            true
        );
    }

    private function root(): string
    {
        return ($_SERVER['DOCUMENT_ROOT'] ?? '') ?: dirname(__DIR__, 2);
    }
}
