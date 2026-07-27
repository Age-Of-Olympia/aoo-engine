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
 * L3 pose la table et la remplit à l'identique : une case par entité, celle
 * de `players.coords_id`, en rôle d'ancre. **Rien ne la lit encore.** Ce
 * service existe pour que la table ne pourrisse pas entre-temps — une entité
 * créée ou déplacée après la migration doit voir son ancre suivre, sinon L4
 * démarrerait d'une base fausse.
 *
 * Les emprises réelles (plusieurs cases, rôles distincts par case) viennent
 * avec L4, quand le décor deviendra des entités. La forme est déjà là :
 * `syncAnchor()` ne touche QUE l'ancre et laisse les autres cases en place.
 *
 * # Pourquoi l'ancre reste dans `players`
 *
 * `players.coords_id` est lue par 337 sites du dépôt. La supprimer d'un coup
 * était exclu ; elle reste donc la référence, et `entity_cells` la double.
 * Le jour où les lecteurs auront migré, c'est elle qui s'en ira.
 */
final class EntityCellService
{
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
