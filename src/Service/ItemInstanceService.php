<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Classes\Item;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cycle de vie des instances (docs/design-items-instances.md §5c) — the lifecycle
 * of item INSTANCES under the lazy-promotion policy:
 *
 *   - promote(): a pristine unit leaves its stack and becomes an
 *     instance — called by the state events (equip, enchant, wear,
 *     map placement). Transactional: stack −1 + instance + link, or
 *     nothing.
 *   - create(): a brand-new instance born at craft (creator, date,
 *     custom name — the only moment a name can be set).
 *   - demote(): the ROLLBACK path — an instance whose state is still
 *     pristine returns to its stack. This is what makes the whole
 *     conversion reversible while nothing has diverged.
 *
 * Invariant owned here: an instance has exactly ONE location. Le sol
 * s'exprime par la table map_items_instances ; côté joueur, la colonne
 * players_items_instances.location distingue ce qu'il PORTE de ce qu'il
 * a rangé en BANQUE. Tout ce qui répond « le joueur a-t-il cet objet
 * sous la main » doit donc filtrer sur LOCATION_INVENTORY : sans ce
 * filtre, une arme déposée en banque resterait équipable, jetable et
 * vendable depuis n'importe où sur la carte.
 */
class ItemInstanceService extends BaseService
{
    /**
     * Seuils d'état d'une instance : brisée à 0 (réparable, ne
     * contribue plus ses caracs), détruite en dessous — LA règle,
     * partout où l'état est testé ou affiché.
     */
    public const BROKEN_AT = 0;

    /** Sous la main : équipable, jetable, comptée par get_n(). */
    public const LOCATION_INVENTORY = 'inventory';

    /** Rangée en banque : hors de portée de tout geste de jeu. */
    public const LOCATION_BANK = 'bank';

    public static function isBroken(int $durability): bool
    {
        return $durability <= self::BROKEN_AT;
    }

    /** La vie de départ d'une instance : items.durability_max (catalogue). */
    private static function catalogDurabilityMax($conn, int $itemId): int
    {
        $max = $conn->fetchOne('SELECT durability_max FROM items WHERE id = ?', [$itemId]);

        return $max !== false ? (int) $max : 100;
    }

    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * Promote one pristine unit of the player's stack into an instance.
     *
     * @return int the new instance id
     *
     * @throws \RuntimeException when the player has no unit in stack
     */
    public function promote(int $playerId, int $itemId): int
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($playerId, $itemId): int {
            // Lock the stack row and check availability atomically.
            $n = $conn->fetchOne(
                'SELECT n FROM players_items WHERE player_id = ? AND item_id = ? FOR UPDATE',
                [$playerId, $itemId]
            );
            if ($n === false || (int) $n < 1) {
                throw new \RuntimeException(
                    "Promotion impossible : le joueur #{$playerId} n'a pas d'exemplaire de l'objet #{$itemId} en pile."
                );
            }

            $conn->executeStatement(
                'UPDATE players_items SET n = n - 1 WHERE player_id = ? AND item_id = ?',
                [$playerId, $itemId]
            );
            $conn->executeStatement(
                'DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND n <= 0 AND equiped = ""',
                [$playerId, $itemId]
            );

            $durabilityMax = self::catalogDurabilityMax($conn, $itemId);
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, durability, durability_max, created_at) VALUES (?, ?, ?, ?)',
                [$itemId, $durabilityMax, $durabilityMax, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            $conn->executeStatement(
                'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                [$playerId, $instanceId]
            );

            return $instanceId;
        });
    }

    /**
     * Craft path: a brand-new instance, owned by $playerId. The ONLY
     * moment a custom name can be set (décision équipe 2026-07).
     */
    public function create(int $playerId, int $itemId, ?int $creatorId = null, string $customName = ''): int
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($playerId, $itemId, $creatorId, $customName): int {
            $durabilityMax = self::catalogDurabilityMax($conn, $itemId);
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, custom_name, creator_id, durability, durability_max, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                [$itemId, $customName, $creatorId, $durabilityMax, $durabilityMax, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            $conn->executeStatement(
                'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                [$playerId, $instanceId]
            );

            return $instanceId;
        });
    }

    /**
     * Rollback path: return a PRISTINE, unequipped instance to its
     * owner's stack. Refuses as soon as any state diverged (wear,
     * name, alterations, destroyed) — a diverged instance has
     * something to lose, a pristine one by definition does not.
     */
    public function demote(int $instanceId): bool
    {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($instanceId): bool {
            $row = $conn->fetchAssociative(
                'SELECT i.id, i.item_id, i.durability, i.durability_max, i.quality, i.custom_name,
                        i.params, i.destroyed, i.wear_pending, l.player_id, l.equiped, l.location
                 FROM item_instances i
                 JOIN players_items_instances l ON l.instance_id = i.id
                 WHERE i.id = ? FOR UPDATE',
                [$instanceId]
            );
            if ($row === false) {
                return false;
            }

            /* La démotion reverse l'exemplaire dans players_items, la
             * pile PORTÉE : appliquée à un exemplaire rangé en banque
             * elle le téléporterait dans l'inventaire. Un exemplaire en
             * banque reste une instance, même vierge. */
            if ((string) $row['location'] !== self::LOCATION_INVENTORY) {
                return false;
            }

            $pristine = (int) $row['durability'] === (int) $row['durability_max']
                && (int) $row['quality'] === 0
                && (string) $row['custom_name'] === ''
                && ($row['params'] === null || $row['params'] === '')
                && (int) $row['destroyed'] === 0
                && (int) $row['wear_pending'] === 0
                && (string) $row['equiped'] === '';
            if (!$pristine) {
                return false;
            }

            $conn->executeStatement(
                'INSERT INTO players_items (player_id, item_id, n) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [(int) $row['player_id'], (int) $row['item_id']]
            );
            $conn->executeStatement('DELETE FROM players_items_instances WHERE instance_id = ?', [$instanceId]);
            $conn->executeStatement('DELETE FROM item_instances WHERE id = ?', [$instanceId]);

            return true;
        });
    }

    /**
     * Equip a catalog item through the instance path: the caller's
     * chosen instance when given (a click on a SPECIFIC inventory
     * line), else one PRISTINE unit promoted from the stack, else the
     * player's oldest unequipped live instance of that item. Returns
     * the equipped instance id.
     *
     * La pile prime sur les instances existantes : le geste sans
     * instance précise (clic sur la ligne de pile, flux hérités) doit
     * équiper un exemplaire vierge, pas la plus vieille instance usée —
     * celle-ci ne s'équipe qu'en cliquant SA ligne. L'aller-retour
     * pile↔instance vierge (promote/demote) reste sans perte.
     *
     * @throws \RuntimeException when the player owns no unit at all, or
     *         when the requested instance is not equippable (absente,
     *         détruite, déjà portée) — jamais de repli silencieux sur
     *         une AUTRE instance que celle demandée
     */
    public function equipCatalogItem(int $playerId, int $itemId, string $emplacement, ?int $instanceId = null): int
    {
        $conn = $this->entityManager->getConnection();

        if ($instanceId !== null) {
            if (!$this->isInstanceEquippable($playerId, $itemId, $instanceId)) {
                throw new \RuntimeException("instance {$instanceId} non équipable pour le joueur {$playerId}");
            }
        } else {
            $stacked = (int) $conn->fetchOne(
                "SELECT COALESCE(SUM(n), 0) FROM players_items
                 WHERE player_id = ? AND item_id = ? AND equiped = ''",
                [$playerId, $itemId]
            );

            if ($stacked > 0) {
                $instanceId = $this->promote($playerId, $itemId);
            } else {
                $existing = $conn->fetchOne(
                    "SELECT l.instance_id
                     FROM players_items_instances l
                     JOIN item_instances i ON i.id = l.instance_id
                     WHERE l.player_id = ? AND i.item_id = ? AND l.equiped = '' AND i.destroyed = 0
                       AND l.location = '" . self::LOCATION_INVENTORY . "'
                     ORDER BY l.instance_id LIMIT 1",
                    [$playerId, $itemId]
                );

                // promote() sans pile lève l'exception « aucun exemplaire ».
                $instanceId = $existing !== false ? (int) $existing : $this->promote($playerId, $itemId);
            }
        }

        $conn->executeStatement(
            'UPDATE players_items_instances SET equiped = ? WHERE instance_id = ?',
            [$emplacement, $instanceId]
        );

        return $instanceId;
    }

    /**
     * La cible d'un équipement CHOISI est-elle valide : appartient au
     * joueur, est bien une unité de ce catalogue, non portée, non
     * détruite. Miroir instance-précise de hasEquippableUnit().
     */
    public function isInstanceEquippable(int $playerId, int $itemId, int $instanceId): bool
    {
        return (bool) $this->entityManager->getConnection()->fetchOne(
            "SELECT 1
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.instance_id = ? AND l.player_id = ? AND i.item_id = ?
               AND l.equiped = '' AND i.destroyed = 0
               AND l.location = '" . self::LOCATION_INVENTORY . "'",
            [$instanceId, $playerId, $itemId]
        );
    }

    /**
     * Unequip an instance; a still-pristine one silently returns to its
     * stack (the invisible round trip that keeps data lean), a diverged
     * one stays as an unequipped instance line.
     *
     * @return bool true quand l'instance est redevenue une unité de PILE
     *              (démotion) — l'appelant qui veut ensuite la déposer au
     *              sol doit alors passer par le chemin pile, pas dropAt().
     */
    public function unequipInstance(int $instanceId): bool
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE players_items_instances SET equiped = '' WHERE instance_id = ?",
            [$instanceId]
        );

        return $this->demote($instanceId);
    }

    /**
     * Clear the given emplacements for a player on the INSTANCE side —
     * the counterpart of Player::equip()'s legacy players_items clears
     * (target emplacement, deuxmains ↔ mains).
     *
     * @param string[] $emplacements
     */
    public function unequipEmplacements(int $playerId, array $emplacements): void
    {
        if ($emplacements === []) {
            return;
        }

        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT instance_id FROM players_items_instances
             WHERE player_id = ? AND equiped IN (' . implode(',', array_fill(0, count($emplacements), '?')) . ')',
            array_merge([$playerId], $emplacements)
        );

        foreach ($ids as $id) {
            $this->unequipInstance((int) $id);
        }
    }

    /**
     * Instance rows shaped for Item::get_item_list()'s dual-read
     * : catalog columns + n=1 + the instance meta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForInventory(int $playerId, bool $equipedOnly): array
    {
        $equipedFilter = $equipedOnly ? "AND l.equiped != ''" : '';

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT it.*, i.item_id, i.id AS instance_id, i.durability, i.durability_max, i.quality,
                    i.custom_name, i.params AS instance_params, i.creator_id, i.wear_pending,
                    l.equiped, 1 AS n
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ? AND i.destroyed = 0
               AND l.location = '" . self::LOCATION_INVENTORY . "' {$equipedFilter}
             ORDER BY l.equiped DESC, i.id",
            [$playerId]
        );
    }

    /**
     * Le pendant BANQUE de listForInventory() : mêmes colonnes, même
     * mise en forme « ligne de pile + méta d'instance », pour que
     * Ui::print_inventory affiche l'état réel (durabilité, nom
     * personnalisé) des exemplaires rangés — c'est tout l'objet de la
     * fonctionnalité. Jamais d'exemplaire équipé ici : l'invariant est
     * posé par storeInBank().
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForBank(int $playerId): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT it.*, i.item_id, i.id AS instance_id, i.durability, i.durability_max, i.quality,
                    i.custom_name, i.params AS instance_params, i.creator_id, i.wear_pending,
                    '' AS equiped, 1 AS n
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ? AND i.destroyed = 0
               AND l.location = '" . self::LOCATION_BANK . "'
             ORDER BY it.name, i.id",
            [$playerId]
        );
    }

    /**
     * Ranger un exemplaire individualisé en banque. Refuse un
     * exemplaire porté : le déposer laisserait un emplacement occupé
     * par un objet hors de portée.
     *
     * @throws \InvalidArgumentException quand l'exemplaire n'appartient
     *         pas au joueur, est détruit, est équipé, ou est déjà rangé
     */
    public function storeInBank(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_INVENTORY, self::LOCATION_BANK);
    }

    /**
     * Reprendre un exemplaire rangé : il retrouve l'inventaire avec son
     * usure et son identité, qui n'ont jamais bougé de ligne.
     *
     * @throws \InvalidArgumentException quand l'exemplaire n'est pas en
     *         banque chez ce joueur
     */
    public function withdrawFromBank(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_BANK, self::LOCATION_INVENTORY);
    }

    /**
     * Bascule de localisation, conditionnelle et atomique : l'UPDATE
     * porte la localisation ATTENDUE dans son WHERE, donc deux dépôts
     * concurrents du même exemplaire n'en valident qu'un — le second ne
     * touche aucune ligne et lève. Même garde que le ramassage au sol
     * (collectAt), pour la même raison.
     */
    private function moveTo(int $instanceId, int $playerId, string $from, string $to): void
    {
        $conn = $this->entityManager->getConnection();

        $conn->transactional(function ($conn) use ($instanceId, $playerId, $from, $to): void {
            $row = $conn->fetchAssociative(
                'SELECT l.equiped, l.location, i.destroyed
                 FROM players_items_instances l
                 JOIN item_instances i ON i.id = l.instance_id
                 WHERE l.instance_id = ? AND l.player_id = ? FOR UPDATE',
                [$instanceId, $playerId]
            );

            if ($row === false || (int) $row['destroyed'] === 1) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} non possédé ou détruit.");
            }
            if ((string) $row['equiped'] !== '') {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} encore équipé — déséquiper d'abord.");
            }
            if ((string) $row['location'] !== $from) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} n'est pas « {$from} ».");
            }

            $affected = $conn->executeStatement(
                'UPDATE players_items_instances SET location = ?
                 WHERE instance_id = ? AND player_id = ? AND location = ?',
                [$to, $instanceId, $playerId, $from]
            );

            if ($affected === 0) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} déplacé entre-temps.");
            }
        });
    }

    /**
     * Live instances a player owns of one catalog item (optionally only
     * equipped ones) — the instance half of Item::get_n()'s dual count.
     */
    public function countInstances(int $playerId, int $itemId, bool $equipedOnly = false): int
    {
        $equipedFilter = $equipedOnly ? "AND l.equiped != ''" : '';

        return (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND i.destroyed = 0
               AND l.location = '" . self::LOCATION_INVENTORY . "' {$equipedFilter}",
            [$playerId, $itemId]
        );
    }

    /**
     * Drop an owned, unequipped instance on the ground: it becomes part
     * of the tile's BOURSE (rendered like any loot, collected by walking
     * on it — retour de revue 2026-07-17). Identity travels with it.
     *
     * @throws \InvalidArgumentException when the instance is not owned,
     *         destroyed, or still equipped
     */
    public function dropAt(int $instanceId, int $coordsId): void
    {
        $conn = $this->entityManager->getConnection();

        $conn->transactional(function ($conn) use ($instanceId, $coordsId): void {
            $row = $conn->fetchAssociative(
                'SELECT l.equiped, l.location, i.destroyed
                 FROM players_items_instances l
                 JOIN item_instances i ON i.id = l.instance_id
                 WHERE l.instance_id = ? FOR UPDATE',
                [$instanceId]
            );
            if ($row === false || (int) $row['destroyed'] === 1) {
                throw new \InvalidArgumentException("Instance #{$instanceId} non possédée ou détruite.");
            }
            if ((string) $row['equiped'] !== '') {
                throw new \InvalidArgumentException("Instance #{$instanceId} encore équipée — déséquiper d'abord.");
            }
            /* On ne jette pas au sol ce qu'on a rangé à la banque : le
             * joueur n'est pas au même endroit que son coffre. */
            if ((string) $row['location'] !== self::LOCATION_INVENTORY) {
                throw new \InvalidArgumentException("Instance #{$instanceId} est en banque — la retirer d'abord.");
            }

            $conn->executeStatement('DELETE FROM players_items_instances WHERE instance_id = ?', [$instanceId]);
            $conn->executeStatement(
                'INSERT INTO map_items_instances (instance_id, coords_id) VALUES (?, ?)',
                [$instanceId, $coordsId]
            );
        });
    }

    /**
     * Walk-on pickup: every ground instance of the tile joins the
     * walker's inventory. Returns display labels for the loot recap.
     *
     * @return string[]
     */
    public function collectAt(int $coordsId, int $playerId): array
    {
        $conn = $this->entityManager->getConnection();

        $rows = $conn->fetchAllAssociative(
            'SELECT g.instance_id, i.custom_name, it.name AS catalog_name
             FROM map_items_instances g
             JOIN item_instances i ON i.id = g.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE g.coords_id = ?',
            [$coordsId]
        );

        $labels = [];
        foreach ($rows as $row) {
            $taken = false;
            $conn->transactional(function ($conn) use ($row, $playerId, &$taken): void {
                // Deux marcheurs simultanés : seul celui dont le DELETE
                // emporte la ligne sol ramasse — l'autre passe son chemin
                // au lieu de violer la PK du lien de possession.
                $affected = $conn->executeStatement(
                    'DELETE FROM map_items_instances WHERE instance_id = ?',
                    [(int) $row['instance_id']]
                );
                if ($affected === 0) {
                    return;
                }
                $conn->executeStatement(
                    'INSERT INTO players_items_instances (player_id, instance_id) VALUES (?, ?)',
                    [$playerId, (int) $row['instance_id']]
                );
                $taken = true;
            });
            if ($taken) {
                $labels[] = self::label($row['custom_name'], (string) $row['catalog_name']);
            }
        }

        return $labels;
    }

    /**
     * Libellé d'affichage d'une instance pour les journaux et récaps :
     * le nom personnalisé prime (échappé — il vient d'une saisie), sinon
     * le nom catalogue capitalisé. Source unique de la règle, partagée
     * avec WearService.
     */
    public static function label(?string $customName, string $catalogName): string
    {
        $customName = (string) $customName;

        return $customName !== ''
            ? '« ' . htmlspecialchars($customName, ENT_QUOTES, 'UTF-8') . ' »'
            : ucfirst($catalogName);
    }

    /**
     * All of a player's instances with their catalog name, worn first —
     * inventaire ET banque, avec leur localisation : c'est la vue
     * « tout ce que ce joueur possède », pas « ce qu'il a sous la main ».
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInstances(int $playerId): array
    {
        return $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT i.*, l.equiped, l.location, it.name AS catalog_name
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ?
             ORDER BY l.equiped DESC, i.id',
            [$playerId]
        );
    }

    /**
     * Total units a player owns of a catalog item, BOTH representations:
     * stack quantity + live (non-destroyed) instances. The future
     * dual-read shim for Item::get_n() — pinned by tests now so the
     * switch is a drop-in.
     */
    /**
     * Une unité ÉQUIPABLE existe-t-elle : pile non vide, ou instance
     * vivante non équipée ? Miroir exact des deux chemins de
     * equipCatalogItem() — la garde à passer AVANT toute mutation
     * d'emplacements (Player::equip).
     */
    public function hasEquippableUnit(int $playerId, int $itemId): bool
    {
        $conn = $this->entityManager->getConnection();

        $free = $conn->fetchOne(
            "SELECT l.instance_id
             FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND l.equiped = '' AND i.destroyed = 0
               AND l.location = '" . self::LOCATION_INVENTORY . "'
             LIMIT 1",
            [$playerId, $itemId]
        );
        if ($free !== false) {
            return true;
        }

        return (int) ($conn->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$playerId, $itemId]
        ) ?: 0) > 0;
    }

    /**
     * Tout ce que le joueur POSSÈDE de cet objet, banque comprise — à
     * ne pas confondre avec countInstances(), qui ne compte que ce
     * qu'il a sous la main et qui est le chiffre dont dépend get_n()
     * (donc les actions, les ventes, les jets au sol).
     */
    public function countOwned(int $playerId, int $itemId): int
    {
        $conn = $this->entityManager->getConnection();

        $stack = (int) ($conn->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ?',
            [$playerId, $itemId]
        ) ?: 0);

        $instances = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM players_items_instances l
             JOIN item_instances i ON i.id = l.instance_id
             WHERE l.player_id = ? AND i.item_id = ? AND i.destroyed = 0',
            [$playerId, $itemId]
        );

        return $stack + $instances;
    }
}
