<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
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
 * s'exprime par l'entité posée au sol ; côté joueur, la colonne
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

    /**
     * Séquestrée par une offre de vente, ou par un échange en cours.
     *
     * Ces deux localisations n'ont demandé aucune migration de colonne :
     * les lectures de possession filtrent en LISTE BLANCHE sur
     * LOCATION_INVENTORY, si bien que toute valeur nouvelle met
     * automatiquement l'exemplaire hors d'atteinte de l'équipement, du
     * jet au sol, du dépôt et du comptage de get_n(). L'exemplaire
     * appartient toujours à son vendeur pendant l'entiercement — seul
     * son emplacement change, l'usure et le nom ne bougent pas de ligne.
     */
    public const LOCATION_MARKET = 'market';

    public const LOCATION_EXCHANGE = 'exchange';

    public static function isBroken(int $durability): bool
    {
        return $durability <= self::BROKEN_AT;
    }

    /**
     * The wear pair, rebuilt from the shared life.
     *
     * An exemplar no longer stores its durability: its maximum comes from its
     * type and its wear is a `players_bonus` deficit, like every other wound in
     * the game. These fragments produce the same two column names the readers
     * have always used, so what changed is where the numbers come from.
     *
     * Expects `item_instances i` and `items it` in the query, and adds the
     * deficit join itself.
     */
    public const WEAR_CURRENT = 'it.durability_max + COALESCE(wear.n, 0) AS durability';

    /** Use WEAR_CURRENT alone where the query already selects `it.*`. */
    public const WEAR_SELECT = self::WEAR_CURRENT . ', it.durability_max AS durability_max';

    public const WEAR_JOIN = "LEFT JOIN players_bonus wear
                                     ON wear.player_id = i.entity_id AND wear.name = 'pv'";

    /** `players.player_type` of an entity that IS an item exemplar. */
    public const ENTITY_TYPE = 'item';

    private const ENTITY_RANGE_START = 70000000;

    private const ENTITY_RANGE_END = 79999999;

    /**
     * Give a fresh exemplar its entity row: identity only, no location.
     *
     * Runs inside the caller's transaction and on the caller's connection, so
     * an exemplar is never visible without its entity. Nothing reads the row
     * yet — it is the anchor the location, and then the life, will move onto.
     */
    private static function attachEntity(
        $conn,
        int $instanceId,
        int $itemId,
        string $customName,
        ?int $holderId = null
    ): void {
        $catalogName = (string) $conn->fetchOne('SELECT name FROM items WHERE id = ?', [$itemId]);

        $entityId = (int) $conn->fetchOne(
            'SELECT COALESCE(MAX(id), ?) + 1 FROM players WHERE id BETWEEN ? AND ?',
            [self::ENTITY_RANGE_START - 1, self::ENTITY_RANGE_START, self::ENTITY_RANGE_END]
        );
        $displayId = (int) $conn->fetchOne(
            'SELECT COALESCE(MAX(display_id), 0) + 1 FROM players WHERE player_type = ?',
            [self::ENTITY_TYPE]
        );

        $conn->executeStatement(
            "INSERT INTO players
                (id, player_type, display_id, name, race, avatar, portrait,
                 coords_id, holder_id, slot, nextTurnTime, registerTime, text)
             VALUES (?, ?, ?, ?, ?, '', '', NULL, ?, '', 0, ?, '')",
            [
                $entityId,
                self::ENTITY_TYPE,
                $displayId,
                $customName !== '' ? $customName : ucfirst($catalogName),
                $catalogName,
                $holderId,
                time(),
            ]
        );

        $conn->executeStatement(
            'UPDATE item_instances SET entity_id = ? WHERE id = ?',
            [$entityId, $instanceId]
        );
    }

    /**
     * The link's two columns, derived from `slot` so callers keep their shape:
     * `equiped` is empty unless the slot is an equipment emplacement, and
     * `location` reads 'inventory' unless the exemplar sits elsewhere.
     */
    private static function linkColumnsFromSlot(string $entity = 'e'): string
    {
        $elsewhere = $entity . ".slot IN (" . self::heldElsewhereSlots() . ")";

        return "IF({$elsewhere}, '', {$entity}.slot) AS equiped,
                IF({$elsewhere}, {$entity}.slot, '" . self::LOCATION_INVENTORY . "') AS location";
    }

    /**
     * Slots that put an exemplar out of the carried inventory: the bank and the
     * two escrows. Quoted for inlining, so callers stay single statements.
     */
    public static function heldElsewhereSlots(): string
    {
        return "'" . implode("','", [
            self::LOCATION_BANK,
            self::LOCATION_MARKET,
            self::LOCATION_EXCHANGE,
        ]) . "'";
    }

    /** The slot a location name stands for; carried has no name of its own. */
    private static function slotFor(string $location): string
    {
        return $location === self::LOCATION_INVENTORY
            ? \App\Service\Map\EntityLocationService::SLOT_CARRIED
            : $location;
    }

    /** Move an exemplar between slots of the same holder. */
    private static function writeSlot($conn, int $instanceId, string $slot): void
    {
        $conn->executeStatement(
            'UPDATE players e JOIN item_instances i ON i.entity_id = e.id
                SET e.slot = ? WHERE i.id = ?',
            [$slot, $instanceId]
        );
    }

    /**
     * The entity of an exemplar, created if it has none.
     *
     * An exemplar reaching the floor on its own — spilled by a container that
     * broke — is the case that needs one minted late.
     *
     * @return int the exemplar's entity id
     */
    public static function ensureEntity($conn, int $instanceId): int
    {
        $entityId = $conn->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);

        if ($entityId !== false && $entityId !== null) {
            return (int) $entityId;
        }

        $row = $conn->fetchAssociative(
            'SELECT item_id, custom_name FROM item_instances WHERE id = ?',
            [$instanceId]
        );
        if ($row === false) {
            throw new \InvalidArgumentException("Exemplaire #{$instanceId} introuvable.");
        }

        self::attachEntity($conn, $instanceId, (int) $row['item_id'], (string) $row['custom_name']);

        return (int) $conn->fetchOne('SELECT entity_id FROM item_instances WHERE id = ?', [$instanceId]);
    }

    /**
     * Drop the entity of an exemplar that is going away.
     *
     * Call AFTER the `item_instances` row is gone: the foreign key is RESTRICT,
     * so an entity still pointed at refuses to be deleted.
     */
    private static function detachEntity($conn, ?int $entityId): void
    {
        if ($entityId === null) {
            return;
        }

        $conn->executeStatement(
            'DELETE FROM players WHERE id = ? AND player_type = ?',
            [$entityId, self::ENTITY_TYPE]
        );
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
                'SELECT n FROM players_items WHERE player_id = ? AND item_id = ? AND slot = "" FOR UPDATE',
                [$playerId, $itemId]
            );
            if ($n === false || (int) $n < 1) {
                throw new \RuntimeException(
                    "Promotion impossible : le joueur #{$playerId} n'a pas d'exemplaire de l'objet #{$itemId} en pile."
                );
            }

            $conn->executeStatement(
                'UPDATE players_items SET n = n - 1 WHERE player_id = ? AND item_id = ? AND slot = ""',
                [$playerId, $itemId]
            );
            $conn->executeStatement(
                'DELETE FROM players_items WHERE player_id = ? AND item_id = ? AND n <= 0 AND equiped = "" AND slot = ""',
                [$playerId, $itemId]
            );

            /* Born pristine: no deficit row at all, exactly as an unwounded
             * character has none. Its maximum comes from its type. */
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, created_at) VALUES (?, ?)',
                [$itemId, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            self::attachEntity($conn, $instanceId, $itemId, '', $playerId);

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
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, custom_name, creator_id, created_at) VALUES (?, ?, ?, ?)',
                [$itemId, $customName, $creatorId, time()]
            );
            $instanceId = (int) $conn->lastInsertId();

            self::attachEntity($conn, $instanceId, $itemId, $customName, $playerId);

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
                'SELECT i.id, i.item_id, ' . self::WEAR_SELECT . ', i.quality, i.custom_name,
                        i.params, i.destroyed, i.wear_pending, i.entity_id,
                        e.holder_id AS player_id, ' . self::linkColumnsFromSlot() . '
                 FROM item_instances i
                 JOIN items it ON it.id = i.item_id
                 JOIN players e ON e.id = i.entity_id
                 ' . self::WEAR_JOIN . '
                 WHERE i.id = ? AND e.holder_id IS NOT NULL FOR UPDATE',
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
            $conn->executeStatement('DELETE FROM item_instances WHERE id = ?', [$instanceId]);
            self::detachEntity($conn, isset($row['entity_id']) ? (int) $row['entity_id'] : null);

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
                 WHERE player_id = ? AND item_id = ? AND equiped = '' AND slot = ''",
                [$playerId, $itemId]
            );

            if ($stacked > 0) {
                $instanceId = $this->promote($playerId, $itemId);
            } else {
                $existing = $conn->fetchOne(
                    "SELECT i.id
                     FROM players e
                     JOIN item_instances i ON i.entity_id = e.id
                     WHERE e.holder_id = ? AND i.item_id = ? AND e.slot = '' AND i.destroyed = 0
                     ORDER BY i.id LIMIT 1",
                    [$playerId, $itemId]
                );

                // promote() sans pile lève l'exception « aucun exemplaire ».
                $instanceId = $existing !== false ? (int) $existing : $this->promote($playerId, $itemId);
            }
        }

        self::writeSlot($conn, $instanceId, $emplacement);

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
             FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             WHERE i.id = ? AND e.holder_id = ? AND i.item_id = ?
               AND e.slot = '' AND i.destroyed = 0",
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
        $conn = $this->entityManager->getConnection();
        self::writeSlot($conn, $instanceId, \App\Service\Map\EntityLocationService::SLOT_CARRIED);

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
            'SELECT i.id FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             WHERE e.holder_id = ? AND e.slot IN (' . implode(',', array_fill(0, count($emplacements), '?')) . ')',
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
        $equipedFilter = $equipedOnly ? "AND e.slot != ''" : '';

        return $this->entityManager->getConnection()->fetchAllAssociative(
            "SELECT it.*, i.item_id, i.id AS instance_id, " . self::WEAR_CURRENT . ", i.quality,
                    i.custom_name, i.params AS instance_params, i.creator_id, i.wear_pending,
                    e.slot AS equiped, 1 AS n
             FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             JOIN items it ON it.id = i.item_id
             " . self::WEAR_JOIN . "
             WHERE e.holder_id = ? AND i.destroyed = 0
               AND e.slot NOT IN (" . self::heldElsewhereSlots() . ") {$equipedFilter}
             ORDER BY e.slot DESC, i.id",
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
            "SELECT it.*, i.item_id, i.id AS instance_id, " . self::WEAR_CURRENT . ", i.quality,
                    i.custom_name, i.params AS instance_params, i.creator_id, i.wear_pending,
                    '' AS equiped, 1 AS n
             FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             JOIN items it ON it.id = i.item_id
             " . self::WEAR_JOIN . "
             WHERE e.holder_id = ? AND i.destroyed = 0
               AND e.slot = '" . self::LOCATION_BANK . "'
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
     * @throws \InvalidArgumentException when the exemplar is not in this
     *         player's vault, or the bag is at its line ceiling (an
     *         exemplar is always one more line)
     */
    public function withdrawFromBank(int $instanceId, int $playerId): void
    {
        if (!(new ContainerService())->hasRoomForALine($playerId)) {
            throw new \InvalidArgumentException('Votre sac est plein.');
        }

        $this->moveTo($instanceId, $playerId, self::LOCATION_BANK, self::LOCATION_INVENTORY);
    }

    /**
     * Mettre en vente : l'exemplaire quitte la banque pour l'offre. Il
     * reste la propriété du vendeur — c'est son emplacement qui change,
     * pas son propriétaire (players_items_instances.player_id porte une
     * clé étrangère vers players, il n'existe pas de « joueur marché »).
     *
     * @throws \InvalidArgumentException si l'exemplaire n'est pas en banque
     */
    public function escrowForMarket(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_BANK, self::LOCATION_MARKET);
    }

    /** Annulation d'une offre : l'exemplaire retourne au coffre. */
    public function releaseFromMarket(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_MARKET, self::LOCATION_BANK);
    }

    /** Mise en jeu dans un échange. */
    public function escrowForExchange(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_BANK, self::LOCATION_EXCHANGE);
    }

    /** Retrait d'un échange, ou annulation : retour au coffre. */
    public function releaseFromExchange(int $instanceId, int $playerId): void
    {
        $this->moveTo($instanceId, $playerId, self::LOCATION_EXCHANGE, self::LOCATION_BANK);
    }

    /**
     * Règlement : l'exemplaire séquestré change de PROPRIÉTAIRE et
     * retombe en banque chez l'acquéreur. C'est le seul geste du
     * cycle où player_id bouge.
     *
     * Atomique et conditionnel comme le reste : le WHERE porte le
     * vendeur ET la localisation attendue, et le nombre de lignes
     * affectées est vérifié — deux acheteurs simultanés sur la même
     * offre, un seul emporte l'exemplaire, l'autre lève. Sans cela on
     * livrerait deux fois un objet qui n'existe qu'en un exemplaire.
     *
     * @throws \InvalidArgumentException si l'exemplaire n'est plus là où
     *         on l'attendait, ou n'appartient plus au cédant
     */
    public function deliverEscrow(int $instanceId, int $fromPlayerId, int $toPlayerId, string $from): void
    {
        $conn = $this->entityManager->getConnection();

        $conn->transactional(function ($conn) use ($instanceId, $fromPlayerId, $toPlayerId, $from): void {
            $row = $conn->fetchAssociative(
                'SELECT ' . self::linkColumnsFromSlot() . ', i.destroyed
                 FROM players e
                 JOIN item_instances i ON i.entity_id = e.id
                 WHERE i.id = ? AND e.holder_id = ? FOR UPDATE',
                [$instanceId, $fromPlayerId]
            );

            if ($row === false || (int) $row['destroyed'] === 1) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} introuvable ou détruit.");
            }
            if ((string) $row['location'] !== $from) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} n'est plus séquestré.");
            }

            $affected = $conn->executeStatement(
                'UPDATE players e JOIN item_instances i ON i.entity_id = e.id
                    SET e.holder_id = ?, e.slot = ?
                  WHERE i.id = ? AND e.holder_id = ? AND e.slot = ?',
                [$toPlayerId, self::LOCATION_BANK, $instanceId, $fromPlayerId, self::slotFor($from)]
            );

            if ($affected === 0) {
                throw new \InvalidArgumentException("Exemplaire #{$instanceId} emporté entre-temps.");
            }
        });
    }

    /**
     * Paliers d'état qu'un acheteur peut exiger, du plus strict au plus
     * permissif : la clé est le pourcentage de durabilité MINIMAL, le
     * libellé décrit le PIRE état accepté.
     *
     * Trois niveaux à l'écran, un pourcentage en base — un quatrième
     * palier ne coûterait qu'une ligne ici. 50 % est la frontière de la
     * bande verte de stateLine() : ce que le joueur voit comme « en bon
     * état » est exactement ce qu'il obtient.
     *
     * @var array<int, string>
     */
    public const CONDITION_LEVELS = [
        100 => 'Neuf uniquement',
        50  => 'Bon état ou mieux',
        1   => 'Tout sauf brisé',
    ];

    /**
     * L'exemplaire satisfait-il le seuil exigé par une demande d'achat ?
     *
     * Une PILE (pas de durabilité) est intacte par construction : elle
     * satisfait tout seuil. Un objet BRISÉ n'en satisfait aucun — il ne
     * contribue plus ses caractéristiques, personne ne peut le vouloir,
     * et l'accepter serait une mauvaise surprise plutôt qu'un choix.
     */
    public static function meetsCondition(?int $durability, ?int $durabilityMax, int $minPct): bool
    {
        if ($minPct <= 0) {
            return true;
        }

        if ($durability === null || $durabilityMax === null || $durabilityMax <= 0) {
            return true;
        }

        if (self::isBroken($durability)) {
            return false;
        }

        return (int) round($durability / $durabilityMax * 100) >= $minPct;
    }

    /**
     * Libellé d'un exemplaire pour les journaux : son nom (personnalisé
     * s'il en a un) suivi de son état. « L'Éclat de Dorna (Durabilité
     * 7/20) » plutôt que « gladius » — un journal de vente qui ne dit
     * pas QUEL exemplaire est parti ne sert à rien.
     */
    public function describe(int $instanceId): string
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT i.custom_name, ' . self::WEAR_SELECT . ', it.name AS catalog_name
             FROM item_instances i JOIN items it ON it.id = i.item_id ' . self::WEAR_JOIN . '
             WHERE i.id = ?',
            [$instanceId]
        );

        if ($row === false) {
            return 'exemplaire #' . $instanceId;
        }

        $state = strip_tags(self::stateLine($row, withBreak: false));

        return self::label($row['custom_name'], (string) $row['catalog_name'])
            . ($state !== '' ? ' (' . $state . ')' : '');
    }

    /**
     * État d'un exemplaire, en une ligne prête à afficher.
     *
     * SOURCE UNIQUE de la règle : les seuils viennent d'ici (BROKEN_AT)
     * et les paliers de couleur aussi. Le marché, les échanges et
     * l'inventaire s'en servent tous — une deuxième formulation serait
     * un deuxième endroit à corriger le jour où l'usure changera.
     *
     * @param object|array<string, mixed> $row ligne portant durability
     *        et durability_max ; chaîne vide si ce n'est pas un exemplaire
     */
    public static function stateLine($row, bool $withBreak = true): string
    {
        $get = static function (string $key) use ($row) {
            if (is_array($row)) {
                return $row[$key] ?? null;
            }

            return $row->$key ?? null;
        };

        $d = $get('durability');
        $dMax = $get('durability_max');

        if ($d === null || $dMax === null || (int) $dMax <= 0) {
            return '';
        }

        $prefix = $withBreak ? '<br />' : '';

        if (self::isBroken((int) $d)) {
            return $prefix . '<font color="red"><b>Brisé</b></font>';
        }

        $pct = (int) round((int) $d / (int) $dMax * 100);
        $color = $pct < 20 ? 'red' : ($pct < 50 ? 'orange' : 'green');

        return $prefix . '<font color="' . $color . '">Durabilité ' . (int) $d . '/' . (int) $dMax . '</font>';
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
                'SELECT ' . self::linkColumnsFromSlot() . ', i.destroyed
                 FROM players e
                 JOIN item_instances i ON i.entity_id = e.id
                 WHERE i.id = ? AND e.holder_id = ? FOR UPDATE',
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
                'UPDATE players e JOIN item_instances i ON i.entity_id = e.id
                    SET e.slot = ?
                  WHERE i.id = ? AND e.holder_id = ? AND e.slot = ?',
                [self::slotFor($to), $instanceId, $playerId, self::slotFor($from)]
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
        $equipedFilter = $equipedOnly ? "AND e.slot != ''" : '';

        return (int) $this->entityManager->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             WHERE e.holder_id = ? AND i.item_id = ? AND i.destroyed = 0
               AND e.slot NOT IN (" . self::heldElsewhereSlots() . ") {$equipedFilter}",
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
                'SELECT ' . self::linkColumnsFromSlot() . ', i.destroyed
                 FROM players e
                 JOIN item_instances i ON i.entity_id = e.id
                 WHERE i.id = ? AND e.holder_id IS NOT NULL FOR UPDATE',
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

            (new \App\Service\Map\EntityLocationService($conn))
                ->dropOnCell(self::ensureEntity($conn, $instanceId), $coordsId);
        });
    }

    /**
     * Build path: a fresh exemplar born standing on a cell.
     *
     * The unit has already left the bag (`RequiresItem` consumed it at the
     * payment), so it never passes through an inventory.
     *
     * @return int the exemplar's entity id
     */
    public function installFromCatalogAt(
        int $itemId,
        int $coordsId,
        ?int $creatorId = null,
        ?int $ownerId = null
    ): int {
        $conn = $this->entityManager->getConnection();

        return $conn->transactional(function ($conn) use ($itemId, $coordsId, $creatorId, $ownerId): int {
            $conn->executeStatement(
                'INSERT INTO item_instances (item_id, creator_id, created_at) VALUES (?, ?, ?)',
                [$itemId, $creatorId, time()]
            );
            $entityId = self::ensureEntity($conn, (int) $conn->lastInsertId());

            (new \App\Service\Map\EntityLocationService($conn))->installOnCell($entityId, $coordsId);

            if ($ownerId !== null) {
                $conn->executeStatement('UPDATE players SET owner_id = ? WHERE id = ?', [$ownerId, $entityId]);
            }

            return $entityId;
        });
    }

    /**
     * Walk-on pickup: every ground instance of the tile joins the
     * walker's inventory. Returns display labels for the loot recap.
     *
     * @return string[]
     */
    public function collectAt(int $coordsId, int $playerId, ?int $onlyInstanceId = null): array
    {
        $conn = $this->entityManager->getConnection();

        $rows = $conn->fetchAllAssociative(
            'SELECT i.id AS instance_id, i.entity_id, i.custom_name, it.name AS catalog_name
               FROM players e
               JOIN item_instances i ON i.entity_id = e.id
               JOIN items it ON it.id = i.item_id
              WHERE e.coords_id = ? AND e.slot = ?'
            . ($onlyInstanceId !== null ? ' AND i.id = ' . (int) $onlyInstanceId : ''),
            [$coordsId, \App\Service\Map\EntityLocationService::SLOT_DROPPED]
        );

        $location = new \App\Service\Map\EntityLocationService($conn);
        $capacity = new ContainerService();
        $labels = [];
        foreach ($rows as $row) {
            // A container still holding something is not picked up: pocketing
            // it would take its contents along without anyone deciding to.
            if ($location->holdsAnything((int) $row['entity_id'])) {
                continue;
            }

            // A full bag takes nothing more: the rest stays on the ground —
            // and a line-by-line take says WHY it refused.
            if (!$capacity->hasRoomForALine($playerId)) {
                if ($onlyInstanceId !== null) {
                    throw new \RuntimeException('Votre sac est plein.');
                }
                break;
            }

            $taken = false;
            $conn->transactional(function ($conn) use ($row, $playerId, $coordsId, &$taken): void {
                // Deux marcheurs simultanés : seul celui dont l'UPDATE trouve
                // encore l'objet sur la case le ramasse — l'autre passe son
                // chemin au lieu de violer la PK du lien de possession. La
                // condition porte la course, comme le DELETE la portait.
                $affected = $conn->executeStatement(
                    'UPDATE players SET coords_id = NULL, holder_id = ?, slot = ?
                      WHERE id = ? AND coords_id = ? AND slot = ?',
                    [
                        $playerId,
                        \App\Service\Map\EntityLocationService::SLOT_CARRIED,
                        (int) $row['entity_id'],
                        $coordsId,
                        \App\Service\Map\EntityLocationService::SLOT_DROPPED,
                    ]
                );
                if ($affected === 0) {
                    return;
                }
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
            'SELECT i.*, ' . self::linkColumnsFromSlot() . ', it.name AS catalog_name
             FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             JOIN items it ON it.id = i.item_id
             WHERE e.holder_id = ?
             ORDER BY e.slot DESC, i.id',
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
            "SELECT i.id
             FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             WHERE e.holder_id = ? AND i.item_id = ? AND e.slot = '' AND i.destroyed = 0
             LIMIT 1",
            [$playerId, $itemId]
        );
        if ($free !== false) {
            return true;
        }

        return (int) ($conn->fetchOne(
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ? AND slot = ""',
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
            'SELECT n FROM players_items WHERE player_id = ? AND item_id = ? AND slot = ""',
            [$playerId, $itemId]
        ) ?: 0);

        $instances = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM players e
             JOIN item_instances i ON i.entity_id = e.id
             WHERE e.holder_id = ? AND i.item_id = ? AND i.destroyed = 0',
            [$playerId, $itemId]
        );

        return $stack + $instances;
    }
}
