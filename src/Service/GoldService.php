<?php

namespace App\Service;

use App\Factory\EntityManagerFactory;
use App\Simulation\SimulationGuard;
use Doctrine\DBAL\Connection;

/**
 * The one writer that SPENDS gold.
 *
 * Never read the balance then subtract it: two requests spending at the
 * same instant both pass a stale check, the stack goes negative and the
 * purge of empty lines erases the debt with the line. The balance is
 * checked by the UPDATE itself and the affected-row count is the answer,
 * as {@see ProgressionService::spendPi()} does for Pi. The cost is
 * strictly positive, so a matched row is always a changed row.
 *
 * Gold is a stack, never an instance: the debit reaches the bag line
 * (`slot = ''`) and no worn copy.
 */
final class GoldService extends BaseService
{
    private const GOLD = 'or';

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        parent::__construct();
        $this->conn = $conn ?? EntityManagerFactory::getEntityManager()->getConnection();
    }

    /**
     * Debit gold, and answer whether it went through.
     *
     * A free thing is bought without a statement; a negative price is not
     * a purchase and is refused rather than credited.
     */
    public function spend(int $playerId, int $cost, bool $bank = false): bool
    {
        if ($cost < 0) {
            return false;
        }

        if ($cost === 0) {
            return true;
        }

        /* A preview pays nothing, and reports the spend as accepted so the
         * caller walks the same branch it would in play. */
        if (SimulationGuard::blocksWrite()) {
            return true;
        }

        $table = $bank ? 'players_items_bank' : 'players_items';

        /* The vault has no slots: only the bag holds worn copies apart. */
        $bagLine = $bank ? '' : ' AND ' . $table . '.slot = \'\'';

        return $this->conn->transactional(function (Connection $conn) use ($playerId, $cost, $table, $bagLine): bool {
            $paid = $conn->executeStatement(
                'UPDATE ' . $table . '
                INNER JOIN items ON items.id = ' . $table . '.item_id
                SET ' . $table . '.n = ' . $table . '.n - ?
                WHERE ' . $table . '.player_id = ?
                AND items.name = ?
                AND ' . $table . '.n >= ?' . $bagLine,
                [$cost, $playerId, self::GOLD, $cost]
            );

            if ($paid !== 1) {
                return false;
            }

            /* An emptied purse is no line at all, same as add_item(). */
            $conn->executeStatement(
                'DELETE FROM ' . $table . ' WHERE player_id = ? AND n <= 0' . $bagLine,
                [$playerId]
            );

            return true;
        });
    }
}
