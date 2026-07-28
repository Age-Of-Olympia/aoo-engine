<?php

namespace App\Service\Map;

use App\Entity\EntityManagerFactory;
use Doctrine\DBAL\Connection;

/**
 * L'emprise d'une entité — les cases qu'elle occupe.
 *
 * `players.coords_id` ne sait dire qu'une case. Un fort, une pyramide, un
 * géant en occupent plusieurs, et le contournement d'aujourd'hui consiste à
 * poser autant de morceaux indépendants que rien ne relie : impossible de
 * déplacer, détruire ou interroger l'objet d'un bloc.
 *
 * `entity_cells` porte l'occupation réelle. Ce service en est le seul
 * écrivain.
 *
 * # Où on en est
 *
 * L3 a posé la table et l'a remplie à l'identique : une case par entité,
 * celle de `players.coords_id`, en rôle d'ancre. L'occupation la lit
 * désormais — une entité barre le pas sur toutes les cases qu'elle tient.
 *
 * Ce lot-ci ferme la boucle par l'autre bout : `syncFootprint()` pose
 * l'emprise entière depuis la découpe déclarée du type. Sans lui, un
 * animateur pouvait dessiner une figure de 3×3 dans la page d'administration
 * sans que rien ne l'écrive — la découpe restait un dessin.
 *
 * Les deux méthodes se partagent le travail sans se marcher dessus :
 * `syncAnchor()` ne touche QUE l'ancre, `syncFootprint()` que le reste.
 *
 * # Pourquoi l'ancre reste dans `players`
 *
 * `players.coords_id` est lue par 337 sites du dépôt. La supprimer d'un coup
 * était exclu ; elle reste donc la référence, et `entity_cells` la double.
 * Le jour où les lecteurs auront migré, c'est elle qui s'en ira.
 */
final class EntityCellService
{
    /**
     * Le rôle d'une case d'emprise sur laquelle personne ne s'est prononcé.
     *
     * Elle appartient à l'entité, et ne dit rien de plus : c'est le type qui
     * tranche le passage, comme pour l'ancre. `block`, `cover`, `door` et
     * `open` sont les avis explicites d'un humain, posés depuis la page des
     * décors ; `part` est leur absence, et il fallait pouvoir l'écrire sans
     * figer une réponse.
     */
    public const ROLE_PART = 'part';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Pose ou déplace l'ancre d'une entité sur la case que `players` déclare.
     *
     * Idempotent : appelable après chaque écriture de `players.coords_id`
     * sans se demander si l'ancre existait déjà.
     *
     * Le refus est explicite plutôt que deviné : `players.coords_id` est NOT
     * NULL et porte une clé étrangère vers `coords`, donc une entité POSÉE a
     * toujours une case. Reste l'entité qui n'existe pas (ou plus) — on retire
     * alors son ancre au lieu de la laisser pointer dans le vide.
     *
     * @return bool vrai si l'ancre est en place, faux si l'entité est
     *              introuvable — le refus est silencieux et normal
     */
    public function syncAnchor(int $playerId): bool
    {
        $row = $this->conn->fetchAssociative(
            'SELECT p.coords_id, co.plan, co.z, co.x, co.y
               FROM players p
               JOIN coords co ON co.id = p.coords_id
              WHERE p.id = ? AND p.coords_id > 0',
            [$playerId]
        );

        if ($row === false) {
            $this->conn->executeStatement(
                "DELETE FROM entity_cells WHERE player_id = ? AND role = 'anchor'",
                [$playerId]
            );

            return false;
        }

        /* L'ancienne ancre s'en va d'abord : la clé primaire est
         * (player_id, coords_id), donc une entité qui a bougé en aurait DEUX
         * si on se contentait d'insérer. On ne touche pas aux autres cases —
         * ce sont les morceaux de l'emprise, que L4 posera. */
        $this->conn->executeStatement(
            "DELETE FROM entity_cells WHERE player_id = ? AND role = 'anchor' AND coords_id <> ?",
            [$playerId, (int) $row['coords_id']]
        );

        $this->conn->executeStatement(
            "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
             VALUES (:p, :c, :plan, :z, :x, :y, 0, 'anchor')
             ON DUPLICATE KEY UPDATE
                 plan = VALUES(plan), z = VALUES(z), x = VALUES(x), y = VALUES(y),
                 role = 'anchor'",
            [
                'p'    => $playerId,
                'c'    => (int) $row['coords_id'],
                'plan' => (string) $row['plan'],
                'z'    => (int) $row['z'],
                'x'    => (int) $row['x'],
                'y'    => (int) $row['y'],
            ]
        );

        return true;
    }

    /**
     * Pose l'emprise d'une entité depuis la découpe déclarée de son type.
     *
     * L'ancre reste où `syncAnchor()` l'a mise ; cette méthode ne s'occupe que
     * des AUTRES cases, celles que la découpe ajoute autour. Les deux se
     * partagent ainsi la table sans jamais se contredire.
     *
     * Idempotent : les cases qui ne figurent plus dans la découpe s'en vont,
     * les nouvelles arrivent, les inchangées restent. C'est ce qui permet de
     * reposer une emprise après qu'un animateur a corrigé la figure.
     *
     * # Le rôle d'une case sans opinion
     *
     * Une découpe ne dit un rôle que pour les morceaux qu'un humain a
     * marqués. Les autres prennent `part` : la case appartient à l'emprise et
     * ne prétend rien de plus, donc c'est le type qui tranche — exactement ce
     * que fait déjà l'ancre. Résoudre le rôle à l'écriture aurait figé une
     * réponse qui change quand `races.blocks_passage` change.
     *
     * @return int le nombre de cases posées autour de l'ancre
     */
    public function syncFootprint(int $entityId, ?EntityTypeFootprintService $footprints = null): int
    {
        $anchor = $this->conn->fetchAssociative(
            'SELECT p.race, p.coords_id, co.plan, co.z, co.x, co.y
               FROM players p
               JOIN coords co ON co.id = p.coords_id
              WHERE p.id = ? AND p.coords_id > 0',
            [$entityId]
        );

        if ($anchor === false) {
            $this->forgetSpread($entityId, []);

            return 0;
        }

        $footprint = ($footprints ?? new EntityTypeFootprintService($this->conn))
            ->catalogue()[(string) $anchor['race']] ?? null;

        if ($footprint === null || $footprint->isSingleCell()) {
            $this->forgetSpread($entityId, []);

            return 0;
        }

        /* L'ancre est le premier morceau de la figure — c'est la convention
         * des décalages, et `syncAnchor()` l'a posée à `players.coords_id`.
         * On demande donc la figure VUE DEPUIS lui : les autres morceaux
         * tombent alors d'eux-mêmes, sans arithmétique ici. */
        $anchorPiece = array_key_first($footprint->offsets());
        $keep = [(int) $anchor['coords_id']];
        $placed = 0;

        $around = $footprint->cellsAround($anchorPiece, (int) $anchor['x'], (int) $anchor['y']);

        foreach ($around as $piece => [$x, $y]) {
            /* L'ancre est déjà posée, et lui réécrire son rôle romprait
             * l'invariant « une ancre par entité ». */
            if ($piece === $anchorPiece) {
                continue;
            }

            $coordsId = (int) \Classes\View::get_coords_id((object) [
                'x' => $x, 'y' => $y, 'z' => (int) $anchor['z'], 'plan' => (string) $anchor['plan'],
            ]);

            $this->conn->executeStatement(
                "INSERT INTO entity_cells (player_id, coords_id, plan, z, x, y, piece, role)
                 VALUES (:p, :c, :plan, :z, :x, :y, :piece, :role)
                 ON DUPLICATE KEY UPDATE
                     plan = VALUES(plan), z = VALUES(z), x = VALUES(x), y = VALUES(y),
                     piece = VALUES(piece), role = VALUES(role)",
                [
                    'p'     => $entityId,
                    'c'     => $coordsId,
                    'plan'  => (string) $anchor['plan'],
                    'z'     => (int) $anchor['z'],
                    'x'     => $x,
                    'y'     => $y,
                    'piece' => (int) $piece,
                    'role'  => $footprint->roleOf((int) $piece, self::ROLE_PART),
                ]
            );

            $keep[] = $coordsId;
            $placed++;
        }

        $this->forgetSpread($entityId, $keep);

        return $placed;
    }

    /**
     * Repose l'emprise de tous les exemplaires posés d'un type.
     *
     * Corriger une figure dans la page d'administration ne servirait à rien si
     * les exemplaires déjà posés gardaient l'ancienne : c'est le geste qui
     * rend la correction visible.
     *
     * @return int le nombre d'exemplaires repris
     */
    public function reapplyForType(string $typeName): int
    {
        $footprints = new EntityTypeFootprintService($this->conn);
        $footprint = $footprints->catalogue()[$typeName] ?? null;

        /* Un type qui retombe à une seule case n'a rien à reposer : il a des
         * cases à RENDRE. Les rendre d'un seul ordre plutôt qu'une entité à la
         * fois — `mur_pierre_bleue` compte 99 exemplaires en local, et un
         * décor courant en aligne des centaines en production. */
        if ($footprint === null || $footprint->isSingleCell()) {
            return (int) $this->conn->executeStatement(
                "DELETE ec FROM entity_cells ec
                   JOIN players p ON p.id = ec.player_id
                  WHERE p.race = ? AND ec.role <> 'anchor'",
                [$typeName]
            );
        }

        $reapplied = 0;

        foreach ($this->conn->fetchFirstColumn(
            'SELECT id FROM players WHERE race = ? AND coords_id > 0',
            [$typeName]
        ) as $entityId) {
            $this->syncFootprint((int) $entityId, $footprints);
            $reapplied++;
        }

        return $reapplied;
    }

    /**
     * Retire les cases d'une emprise que la découpe ne réclame plus.
     *
     * L'ancre n'est jamais du lot : elle appartient à `syncAnchor()`, et la
     * retirer ici la ferait disparaître à chaque découpe rétrécie.
     *
     * @param list<int> $keep les cases à conserver
     */
    private function forgetSpread(int $entityId, array $keep): void
    {
        $sql = "DELETE FROM entity_cells WHERE player_id = ? AND role <> 'anchor'";
        $params = [$entityId];

        if ($keep !== []) {
            $sql .= ' AND coords_id NOT IN (' . implode(',', array_map('intval', $keep)) . ')';
        }

        $this->conn->executeStatement($sql, $params);
    }

    /**
     * Retire toute l'emprise d'une entité.
     *
     * La clé étrangère `ON DELETE CASCADE` s'en charge quand la ligne
     * `players` disparaît ; cette méthode sert aux cas où l'entité survit
     * mais quitte le monde (mise en réserve, dépose).
     */
    public function removeFor(int $playerId): int
    {
        return (int) $this->conn->executeStatement(
            'DELETE FROM entity_cells WHERE player_id = ?',
            [$playerId]
        );
    }

    /**
     * Les cases d'une entité, ancre comprise.
     *
     * @return list<array{coords_id: int, plan: string, z: int, x: int, y: int, piece: int, role: string}>
     */
    public function cellsOf(int $playerId): array
    {
        /** @var list<array{coords_id: int, plan: string, z: int, x: int, y: int, piece: int, role: string}> */
        return $this->conn->fetchAllAssociative(
            'SELECT coords_id, plan, z, x, y, piece, role
               FROM entity_cells WHERE player_id = ? ORDER BY piece, coords_id',
            [$playerId]
        );
    }

    /**
     * Ce qui occupe une case — plusieurs entités le peuvent.
     *
     * L'empilement sert aux animateurs et aux administrateurs, et la
     * superposition décor + déclencheur est l'usage NORMAL du monde : sur les
     * 1 746 téléporteurs de production, ce qui les signale au joueur est un
     * décor posé par-dessus (9 escaliers, 9 échelles, 14 portes des enfers).
     *
     * @return list<array{player_id: int, piece: int, role: string}>
     */
    public function occupantsOf(int $coordsId): array
    {
        /** @var list<array{player_id: int, piece: int, role: string}> */
        return $this->conn->fetchAllAssociative(
            'SELECT player_id, piece, role FROM entity_cells WHERE coords_id = ? ORDER BY player_id',
            [$coordsId]
        );
    }

    /**
     * Les entités dont l'ancre ne correspond plus à `players.coords_id`.
     *
     * Tant que rien ne lit l'emprise, une dérive ne casse rien — mais elle
     * ferait démarrer L4 d'une base fausse. C'est ce que la commande
     * `entity-cells` de la console interroge et répare.
     *
     * @return list<array{player_id: int, expected: int, actual: ?int}>
     */
    public function drift(): array
    {
        /** @var list<array{player_id: int, expected: int, actual: ?int}> */
        return $this->conn->fetchAllAssociative(
            "SELECT p.id AS player_id, p.coords_id AS expected, ec.coords_id AS actual
               FROM players p
               LEFT JOIN entity_cells ec ON ec.player_id = p.id AND ec.role = 'anchor'
              WHERE p.coords_id > 0
                AND (ec.coords_id IS NULL OR ec.coords_id <> p.coords_id)
              ORDER BY p.id"
        );
    }

    /**
     * Remet toutes les ancres dérivées d'aplomb.
     *
     * @return int nombre d'entités réparées
     */
    public function reconcile(): int
    {
        $repaired = 0;

        foreach ($this->drift() as $row) {
            if ($this->syncAnchor((int) $row['player_id'])) {
                $repaired++;
            }
        }

        return $repaired;
    }
}
