<?php

namespace App\Service;

use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Items Phase 2 — the wear engine (docs/design-items-instances.md §3.4):
 * « le tour est l'unité d'usure ».
 *
 * Events during a turn only ARM (arm(): wear_pending = 1 on the
 * player's equipped instances whose catalog declares the trigger);
 * the decrement happens ONCE, at new-turn processing
 * (applyNewTurnWear(): durability −= wear_rate, flag cleared, recap
 * lines returned). Ten attacks in a turn wear the sword once.
 *
 * Thresholds (décision équipe): 0 = brisé — the item stays worn but
 * stops contributing caracs (get_caracs skips it); < 0 = détruit —
 * possible only through bigger immediate decrements (DamageObject),
 * never through per-turn wear, which floors at 0.
 */
class WearService extends BaseService
{
    private EntityManagerInterface $entityManager;

    public function __construct()
    {
        parent::__construct();
        $this->entityManager = EntityManagerFactory::getEntityManager();
    }

    /**
     * A trigger event happened for this player during the current turn:
     * arm every EQUIPPED instance whose catalog declares the trigger.
     * Cheap and idempotent within a turn (flag write).
     *
     * NOT routed through Classes\Db, so simulation must be guarded by
     * the CALLER (the executor's outcome hooks check isSimulated()).
     */
    public function arm(int $playerId, string $trigger): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE item_instances i
             JOIN players_items_instances l ON l.instance_id = i.id
             JOIN items it ON it.id = i.item_id
             SET i.wear_pending = 1
             WHERE l.player_id = ?
               AND l.equiped != ''
               AND i.destroyed = 0
               AND i.durability > 0
               AND it.wear_rate > 0
               AND FIND_IN_SET(?, it.wear_triggers)",
            [$playerId, $trigger]
        );
    }

    /**
     * New-turn pass: apply the pending wear of every armed instance the
     * player owns, floor at 0 (brisé), clear the flags.
     *
     * @return string[] recap lines for the new-turn screen / log
     */
    public function applyNewTurnWear(int $playerId): array
    {
        $conn = $this->entityManager->getConnection();

        $armed = $conn->fetchAllAssociative(
            "SELECT i.id, i.durability, i.custom_name, it.name AS catalog_name, it.wear_rate
             FROM item_instances i
             JOIN players_items_instances l ON l.instance_id = i.id
             JOIN items it ON it.id = i.item_id
             WHERE l.player_id = ? AND i.wear_pending = 1 AND i.destroyed = 0",
            [$playerId]
        );

        $recap = [];
        foreach ($armed as $row) {
            $before = (int) $row['durability'];
            $after = max(0, $before - (int) $row['wear_rate']);

            $conn->executeStatement(
                'UPDATE item_instances SET durability = ?, wear_pending = 0 WHERE id = ?',
                [$after, (int) $row['id']]
            );

            $label = ItemInstanceService::label($row['custom_name'], (string) $row['catalog_name']);

            if ($after === 0 && $before > 0) {
                $recap[] = $label . ' <span class="ra ra-shattered-sword"></span> s\'est <b>brisé</b> !';
            } else {
                $recap[] = $label . ' s\'use (−' . ($before - $after) . ').';
            }
        }

        return $recap;
    }
}
